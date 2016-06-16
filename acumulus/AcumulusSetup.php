<?php
use Siel\Acumulus\Helpers\Requirements;
use Siel\Acumulus\Shop\Config;
use Siel\Acumulus\WooCommerce\Shop\AcumulusEntryModel;

defined('ABSPATH') OR exit;

class AcumulusSetup {

  /** @var string */
  private $version;

  /** @var array */
  private $messages = array();

  /** @var \Siel\Acumulus\Shop\Config */
  private $acumulusConfig;

  /**
   * AcumulusSetup constructor.
   *
   * @param \Siel\Acumulus\Shop\Config $acumulusConfig
   * @param string $version
   */
  public function __construct(Config $acumulusConfig, $version = '') {
    $this->acumulusConfig = $acumulusConfig;
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
  public function activate() {
    $result = FALSE;
    if (current_user_can('activate_plugins')) {
      $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
      check_admin_referer("activate-plugin_{$plugin}");

      // Install.
      if ($this->checkRequirements()) {
        $model = new AcumulusEntryModel();
        $result = $model->install();
        add_option('acumulus_version', $this->version);
      }
    }
    return $result;
  }

  /**
   * Upgrades the plugin.
   *
   * @return bool
   *   Success.
   */
  public function upgrade() {
    $result = true;
    // Only execute if we are really upgrading.
    $dbVersion = get_option('acumulus_version');
    if (!empty($dbVersion) && version_compare($dbVersion, $this->version, '<')) {
      $result = $this->acumulusConfig->upgrade($dbVersion);
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
  public function deactivate() {
    if (!current_user_can('activate_plugins')) {
      return FALSE;
    }
    $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
    check_admin_referer("deactivate-plugin_{$plugin}");

    // Deactivate.
    // None so far.
    return TRUE;
  }

  /**
   * Uninstalls the plugin.
   *
   * @return bool
   *   Success.
   */
  public function uninstall() {
    if (!current_user_can('activate_plugins')) {
      return FALSE;
    }
    check_admin_referer('bulk-plugins');

    // Uninstall.
    delete_option('acumulus');

    $model = new AcumulusEntryModel();
    return $model->uninstall();
  }

  /**
   * Checks the requirements for this module (CURL, DOMXML, ...).
   *
   * @return bool
   *   Success.
   */
  public function checkRequirements() {
    $requirements = new Requirements();
    $this->messages = $requirements->check();

    if (!empty($this->messages)) {
      add_action('admin_notices', array($this, 'adminNotice'));
    }

    return empty($this->messages);
  }

  /**
   * Action hook that adds administrator notices to the admin screen.
   */
  public function adminNotice() {
    foreach ($this->messages as $message) {
      echo '<div class="error">' . $message . '</div>';
    }
  }
}
