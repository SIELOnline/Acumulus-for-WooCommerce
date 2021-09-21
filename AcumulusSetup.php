<?php

use Siel\Acumulus\Helpers\Container;

class AcumulusSetup
{

    /** @var string */
    private $version;

    /** @var array */
    private $messages = [];

    /** @var \Siel\Acumulus\Helpers\Container */
    private $container;

    /**
     * AcumulusSetup constructor.
     *
     * @param \Siel\Acumulus\Helpers\Container $container
     * @param string $version
     */
    public function __construct(Container $container, $version = '')
    {
        $this->container = $container;
        $this->version = $version;
    }

    /**
     * Activates the plugin.
     *
     * Note that on installing a plugin (copying the files) nothing else happens.
     * Only on activating, a plugin can do its initial work.
     *
     * @return bool
     *   Success.
     */
    public function activate()
    {
        $result = false;
        // Check user access.
        if (current_user_can('activate_plugins')) {
            $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
            check_admin_referer("activate-plugin_{$plugin}");

            // Check plugin requirements.
            if ($this->checkRequirements()) {
                // Install.
                $model = $this->container->getAcumulusEntryManager();
                $result = $model->install();
                add_option('acumulus_version', $this->version);
            }

            // In 1 week time we will ask the user to rate this plugin.
            $this->container->getConfig()->save(['showRatePluginMessage' => time() + 7 * 24 * 60 * 60]);
        }

        return $result;
    }

    /**
     * Upgrades the plugin.
     *
     * @param string $dbVersion
     *
     * @return bool
     *   Success.
     */
    public function upgrade($dbVersion)
    {
        $result = true;

        // Only execute if we are really upgrading.
        if (empty($dbVersion)) {
            // Set it so we can compare it in the future.
            update_option('acumulus_version', $this->version);
        } else {
            $result = $this->container->getConfig()->upgrade($dbVersion);
            if (version_compare($dbVersion, '4.7.2', '<')) {
                $result = $this->upgrade472() && $result;
            }
            if (version_compare($dbVersion, '5.0.1', '<')) {
                $result = $this->upgrade501() && $result;
            }
            if (version_compare($dbVersion, '5.9.0', '<')) {
                $result = $this->upgrade590() && $result;
            }
            update_option('acumulus_version', $this->version);
        }

        return $result;
    }

    /**
     * Deactivates the plugin.
     *
     * @return bool
     *   Success.
     */
    public function deactivate()
    {
        if (!current_user_can('activate_plugins')) {
            return false;
        }
        $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
        check_admin_referer("deactivate-plugin_{$plugin}");

        // Deactivate.
        // None so far.
        return true;
    }

    /**
     * Uninstalls the plugin.
     *
     * @return bool
     *   Success.
     */
    public function uninstall()
    {
        if (!current_user_can('delete_plugins')) {
            return false;
        }

        // Uninstall.
        delete_option('acumulus');

        $model = $this->container->getAcumulusEntryManager();

        return $model->uninstall();
    }

    /**
     * Checks the requirements for this module (CURL, DOMXML, ...).
     *
     * @return bool
     *   Success.
     */
    public function checkRequirements()
    {
        $requirements = $this->container->getRequirements();
        $this->messages = $requirements->check();

        // Check that WooCommerce is active.
        if (!is_plugin_active('woocommerce/woocommerce.php')) {
            $this->messages[] = "The Acumulus component (version = {$this->version}) requires WooCommerce to be installed and enabled.";
        }

        if (count($this->messages) !== 0) {
            add_action('admin_notices', [$this, 'adminNotice']);
        }

        return count($this->messages) === 0;
    }

    /**
     * Action hook that adds administrator notices to the admin screen.
     */
    public function adminNotice()
    {
        $output = '';
        foreach ($this->messages as $message) {
            $output .= $this->renderNotice($message, 'error');
        }
        echo $output;
    }

    /**
     * Renders a notice.
     *
     * @param string $message
     * @param string $type
     *
     * @return string
     *   The rendered notice.
     */
    protected function renderNotice($message, $type)
    {
        return sprintf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $type, $message);
    }

    /**
     * 4.7.2 upgrade.
     *
     * - WC: Strip slashes and remove values that are not one of our keys.
     *
     * @return bool
     */
    protected function upgrade472()
    {
        $configurationValues = get_option('acumulus');
        // Not sure that this operation can be performed safely on just any value,
        // so I decided to not strip slashes, users can do this manually in the
        // settings forms.
        //    $configurationValues = stripslashes_deep($configurationValues);
        $keys = $this->container->getConfig()->getKeys();
        $defaults = $this->container->getConfig()->getDefaults();
        $result = [];
        foreach ($keys as $key) {
            if (isset($configurationValues[$key]) && $configurationValues[$key] != $defaults[$key]) {
                $result[$key] = $configurationValues[$key];
            }
        }

        return update_option('acumulus', $result);
    }

    /**
     * 5.0.1 upgrade.
     *
     * - Show message about explicitly removing old lib folder.
     */
    protected function upgrade501()
    {
        add_action('admin_notices', [$this, 'removeLibrariesFolder']);

        return true;
    }

    /**
     * 5.9.0 upgrade.
     *
     * - Move transient 'acumulus_rate_plugin' to a config value.
     */
    protected function upgrade590()
    {
        $value = get_transient('acumulus_rate_plugin');
        if (empty($value)) {
            // Apparently we are upgrading from a version that did not yet contain
            // the rate plugin message: start asking immediately.
            $value = time();
        } elseif ($value === 'done') {
            // 'done' is replaced by PHP_INT_MAX, meaning effectively: never again.
            $value = PHP_INT_MAX;
        }
        $this->container->getConfig()->save(['showRatePluginMessage' => $value]);
        delete_transient('acumulus_rate_plugin');

        return true;
    }

    public function removeLibrariesFolder()
    {
        $dir = strtr(__DIR__ . '/libraries', ['\\' => DIRECTORY_SEPARATOR, '/' => DIRECTORY_SEPARATOR]);
        $message = 'Version 5 of the Acumulus plugin renamed its libraries folder to lib. Check if the folder %s still exists and, if so, remove it manually';
        echo $this->renderNotice(sprintf($message, $dir), 'warning');
    }

}
