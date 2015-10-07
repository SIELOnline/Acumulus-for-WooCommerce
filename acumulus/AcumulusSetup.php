<?php
use Siel\Acumulus\Helpers\Requirements;
use Siel\Acumulus\WooCommerce\Shop\AcumulusEntryModel;

defined('ABSPATH') OR exit;

class AcumulusSetup {

  private $messages = array();

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
    if (!current_user_can('activate_plugins')) {
      return FALSE;
    }
    $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
    check_admin_referer("activate-plugin_{$plugin}");

    // Setup.
    if ($this->checkRequirements()) {
      // Install.
      $model = new AcumulusEntryModel();
      return $model->install();
    }

    return FALSE;
  }

  /**
   * Upgrades the plugin.
   *
   *
   * @param string $oldVersion
   * @param string $newVersion
   *
   * @return bool
   *   Success.
   */
  public function upgrade($oldVersion, $newVersion) {
    // Upgrade data, settings, etc: nothing for now.
    return TRUE;
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
