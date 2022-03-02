<?php
/**
 * @noinspection PhpMissingParamTypeInspection
 * @noinspection PhpMissingReturnTypeInspection
 * @noinspection PhpMissingFieldTypeInspection
 * @noinspection PhpMissingVisibilityInspection
 */

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Container;

use const Siel\Acumulus\Version as Version;

class AcumulusSetup
{
    /** @var array */
    private $messages = [];

    /** @var \Siel\Acumulus\Helpers\Container */
    private $container;

    /**
     * AcumulusSetup constructor.
     *
     * @param \Siel\Acumulus\Helpers\Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
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
            $plugin = $_REQUEST['plugin'] ?? '';
            check_admin_referer("activate-plugin_$plugin");

            // Check plugin requirements.
            if ($this->checkRequirements()) {
                // Install.
                $model = $this->container->getAcumulusEntryManager();
                $result = $model->install();
            }

            $values = [];
            // Set initial config version.
            if (empty($this->container->getConfig()->get(Config::configVersion))) {
                $values[Config::configVersion] = Version;
            }
            // In 1 week time we will ask the user to rate this plugin.
            $values['showRatePluginMessage'] = time() + 7 * 24 * 60 * 60;
            $this->container->getConfig()->save($values);
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
        $plugin = $_REQUEST['plugin'] ?? '';
        check_admin_referer("deactivate-plugin_$plugin");

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
            $this->messages[] = "The Acumulus component requires WooCommerce to be installed and enabled.";
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
}
