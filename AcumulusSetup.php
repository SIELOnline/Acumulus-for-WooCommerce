<?php

declare(strict_types=1);

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Container;

use const Siel\Acumulus\Version;

/**
 * AcumulusSetup contains code to b executed on install, activate, deactivate,
 * and uninstall
 */
class AcumulusSetup
{
    /** @var array */
    private array $messages = [];

    /** @var \Siel\Acumulus\Helpers\Container */
    private Container $container;

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
    public function activate(): bool
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
            if (empty($this->container->getConfig()->get(Config::VersionKey))) {
                $values[Config::VersionKey] = Version;
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
    public function deactivate(): bool
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
    public function uninstall(): bool
    {
        if (!current_user_can('delete_plugins')) {
            return false;
        }

        // Uninstall.
        delete_option('acumulus');

        return $this->container->getAcumulusEntryManager()->uninstall();
    }

    /**
     * Checks the requirements for this module (cUrl, ...).
     *
     * @return bool
     *   Success.
     */
    public function checkRequirements(): bool
    {
        $this->messages = $this->container->getRequirements()->check();

        // Check that WooCommerce is active.
        if (!is_plugin_active('woocommerce/woocommerce.php')) {
            $this->messages['message_error_no_woocommerce'] = 'The Acumulus component requires WooCommerce to be installed and enabled.';
        }

        if (count($this->messages) > 0) {
            add_action('admin_notices', [$this, 'adminNotice']);
        }

        reset($this->messages);
        return count($this->messages) === 0 || (count($this->messages) === 1 && strpos(key($this->messages), 'warning') !== false);
    }

    /**
     * Action hook that adds administrator notices to the admin screen.
     */
    public function adminNotice(): void
    {
        $output = '';
        foreach ($this->messages as $key => $message) {
            $type = stripos($key, 'error') !== false ? 'error' : 'warning';
            $output .= $this->renderNotice($message, $type);
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
    protected function renderNotice(string $message, string $type): string
    {
        return sprintf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $type, $message);
    }
}
