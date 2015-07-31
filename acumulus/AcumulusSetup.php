<?php
use Siel\Acumulus\Common\WebAPI;

defined('ABSPATH') OR exit;

class AcumulusSetup {
  private $messages = array();

  public function activate() {
    if (!current_user_can('activate_plugins')) {
      return;
    }
    $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
    check_admin_referer("activate-plugin_{$plugin}");

    // Setup.
    $this->checkRequirements();
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
      return;
    }
    check_admin_referer('bulk-plugins');

    // Uninstall.
    delete_option('woocommerce_acumulus');
  }

  public function checkRequirements() {
    require_once(dirname(__FILE__) . '/Siel/Acumulus/Common/TranslatorInterface.php');
    require_once(dirname(__FILE__) . '/Siel/Acumulus/Common/BaseTranslator.php');
    require_once(dirname(__FILE__) . '/Siel/Acumulus/Common/ConfigInterface.php');
    require_once(dirname(__FILE__) . '/Siel/Acumulus/Common/BaseConfig.php');
    require_once(dirname(__FILE__) . '/Siel/Acumulus/Common/WebAPICommunication.php');
    require_once(dirname(__FILE__) . '/Siel/Acumulus/Common/WebAPI.php');
    require_once(dirname(__FILE__) . '/Siel/Acumulus/WooCommerce/WooCommerceAcumulusConfig.php');

    $language = get_bloginfo('language');
    if (empty($language)) {
      $language = 'nl';
    }
    $language = substr($language, 0, 2);
    $acumulusConfig = new Siel\Acumulus\WooCommerce\WooCommerceAcumulusConfig($language);

    // Requirements checking. Not sure if this is the right place.
    $webAPI = new WebAPI($acumulusConfig);

    $messages = $webAPI->checkRequirements();
    if (!empty($messages)) {
        foreach ($messages as $error) {
          $this->messages[] = $acumulusConfig->t($error['message']);
        }
        add_action('admin_notices', array($this, 'adminNotice'));
    }
  }

  public function adminNotice() {
    foreach ($this->messages as $message) {
      echo '<div class="error">' . $message . '</div>';
    }
  }
}
