<?php
use Siel\Acumulus\Helpers\Requirements;
use Siel\Acumulus\WooCommerce\Shop\AcumulusEntryModel;

defined('ABSPATH') OR exit;

class AcumulusSetup {
  private $messages = array();

  public function activate() {
    if (!current_user_can('activate_plugins')) {
      return FALSE;
    }
    $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
    check_admin_referer("activate-plugin_{$plugin}");

    // Setup.
    if ($this->checkRequirements()) {
      // Install
      $model = new AcumulusEntryModel();
      return $model->install();
    }

    return FALSE;
  }

  public function deactivate() {
    if (!current_user_can('activate_plugins')) {
      return;
    }
    $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
    check_admin_referer("deactivate-plugin_{$plugin}");

    // Deactivate.
    // None so far.
  }

  public function uninstall() {
    if (!current_user_can('activate_plugins')) {
      return false;
    }
    check_admin_referer('bulk-plugins');

    // Uninstall.
    delete_option('woocommerce_acumulus');

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
