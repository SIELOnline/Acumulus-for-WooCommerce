<?php
/*
Plugin Name: Acumulus
Description: Acumulus koppeling voor WooCommerce 2.1+
Plugin URI: https://forum.acumulus.nl/index.php?board=17.0
Author: Acumulus
Version: 3.4.5
LICENCE: GPLv3
*/

use Siel\Acumulus\Common\WebAPI;
use Siel\Acumulus\WooCommerce\InvoiceAdd;

/*
 * Install/uninstall actions.
 */
register_activation_hook(__FILE__, array('Acumulus', 'activate'));
register_deactivation_hook(__FILE__, array('Acumulus', 'deactivate'));
register_uninstall_hook(__FILE__, array('Acumulus', 'uninstall'));

/*
 * Actions.
 */
add_action('plugins_loaded', array('Acumulus', 'create'));


class Acumulus {
  /** @var Acumulus|null */
  private static $instance = NULL;

  /** @var \Siel\Acumulus\WooCommerce\WooCommerceAcumulusConfig|null */
  private $acumulusConfig = NULL;

  /** @var \Siel\Acumulus\Common\WebAPI|null */
  private $webAPI = NULL;

  /** @var \Siel\Acumulus\WooCommerce\AcumulusConfigForm|null */
  private $form = NULL;

  /**
   * Entry point for WordPress.
   *
   * @return Acumulus
   */
  public static function create() {
    if (self::$instance === NULL) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Constructor: setup hooks
   */
  private function __construct() {
    $this->log('Acumulus: extension constructor');
    add_action('admin_init', array($this, 'adminInit'));
    add_action('admin_menu', array($this, 'addOptionsPage'));
    add_action('admin_enqueue_scripts', array($this, 'adminEnqueueScripts'));
    add_action('woocommerce_order_status_changed', array($this, 'woocommerceOrderStatusChanged'), 10, 3);
  }

  /**
   * return string
   *   The version number of this plugin.
   */
  public function getVersionNumber() {
    if (function_exists('get_plugin_data')) {
      $plugin_data = get_plugin_data( __FILE__ );
      $version = $plugin_data['Version'];
      update_option('woocommerce_acumulus_version', $version);
    }
    else {
      $version = get_option('woocommerce_acumulus_version');
    }
    return $version;
  }

  /**
   * Loads our library and creates and returns a configuration object.
   *
   * @return \Siel\Acumulus\WooCommerce\WooCommerceAcumulusConfig
   */
  public function init() {
    if ($this->acumulusConfig === null) {
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
      $this->acumulusConfig = new Siel\Acumulus\WooCommerce\WooCommerceAcumulusConfig($language);
    }
  }

  /**
   * Returns a configuration form object.
   *
   * @return \Siel\Acumulus\WooCommerce\AcumulusConfigForm
   */
  public function getForm() {
    $this->init();
    if ($this->form === null) {
      require_once(dirname(__FILE__) . '/Siel/Acumulus/Common/FormRenderer.php');
      require_once(dirname(__FILE__) . '/Siel/Acumulus/WooCommerce/AcumulusConfigForm.php');
      $this->form = new Siel\Acumulus\WooCommerce\AcumulusConfigForm($this->acumulusConfig);
    }
    return $this->form;
  }

  /**
   * Registers our settings.
   */
  public function adminInit() {
    register_setting('woocommerce_acumulus', 'woocommerce_acumulus', array($this->getForm(), 'validateForm'));
  }

  /**
   * Adds our configuration page to the menu.
   */
  public function addOptionsPage() {
    $this->init();
    add_options_page($this->acumulusConfig->t('module_name') . ' ' . $this->acumulusConfig->t('button_settings'),
      $this->acumulusConfig->t('module_name'),
      'manage_options',
      'woocommerce_acumulus',
      array($this, 'getOptionsForm'));
  }

  public function adminEnqueueScripts() {
    $screen = get_current_screen();
    if (is_object($screen) && $screen->id == 'settings_page_woocommerce_acumulus') {
      $pluginUrl = plugins_url('/woocommerce-acumulus');
      wp_enqueue_style('acumulus_css_admin', $pluginUrl . '/acumulus.css');
    }
  }

// Draw the menu page itself
  public function getOptionsForm() {
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $form = $this->getForm();
    $form->addFields();
    $form->getForm();
  }

  public function woocommerceOrderStatusChanged($orderId, $status, $newStatus) {
    $this->init();
    $this->log('Acumulus: woocommerceOrderStatusChanged(%d, %d, %d): trigger = %d', $orderId, $status, $newStatus, $this->acumulusConfig->get('triggerOrderStatus'));
    if ($newStatus == $this->acumulusConfig->get('triggerOrderStatus')) {
      $this->sendOrderToAcumulus(new WC_Order($orderId));
    }
  }

  /**
   * Sends the invoice for an order toAcumulus.
   *
   * We cannot know if an admin, a user or a batch triggered this hook.
   * So we assume the process to be not interactive and will send a mail
   * upon failure.
   *
   * @param WC_Order $order
   *   The order to send to Acumulus
   *
   * @return bool
   *   Success.
   */
  public function sendOrderToAcumulus(WC_Order $order) {
    $this->log('Acumulus: sendOrderToAcumulus(%d)', $order->id);
    $this->webAPI = new WebAPI($this->acumulusConfig);
    require_once(dirname(__FILE__) . '/Siel/Acumulus/WooCommerce/InvoiceAdd.php');
    $addInvoice = new InvoiceAdd($this->acumulusConfig);
    $invoice = $addInvoice->convertOrderToAcumulusInvoice($order);
    $invoice = apply_filters('acumulus_invoice_add', $invoice, $order);
    $result = $this->webAPI->invoiceAdd($invoice, $order->get_order_number());

    if (!empty($result['invoice'])) {
      $this->saveEntry($result['invoice'], $order);
    }

    $messages = $this->webAPI->resultToMessages($result);
    $this->log('Acumulus: sendOrderToAcumulus(): result = "%s"', $messages);
    if (!empty($messages)) {
      $this->sendMail($result, $messages, $order);
    }

    return !empty($result['invoice']['invoicenumber']);
  }

  /**
   * Save token and entryId in metadata of order.
   *
   * Note: we use separate meta data keys as to be able - in the future - to
   * query on these values in an efficient way using WP_query().
   *
   * @param array $acumulusInvoice
   * @param WC_Order $order
   */
  private function saveEntry(array $acumulusInvoice, WC_Order $order) {
    $now = current_time('timestamp', true);
    update_post_meta($order->id, '_acumulus_entry_id', (int) $acumulusInvoice['entryid']);
    update_post_meta($order->id, '_acumulus_token', $acumulusInvoice['token']);
    add_post_meta($order->id, '_acumulus_created', $now, true);
    update_post_meta($order->id, '_acumulus_updated', $now);
  }

  /**
   * Send a mail with the results.
   *
   * @param array $result
   * @param array $messages
   * @param WC_Order $order
   */
  private function sendMail(array $result, array $messages, WC_Order $order) {
    $credentials = $this->acumulusConfig->getCredentials();
    $toEmail = $credentials['emailonerror'];
    $fromEmail = get_bloginfo('admin_email');
    $fromName = get_bloginfo('name');
    $subject = $this->acumulusConfig->t('mail_subject');
//      $boundary = sha1(uniqid());
    $headers = array(
      "from: $fromName <$fromEmail>",
      'Content-Type: text/html; charset=UTF-8',
    );

    $replacements = array(
      '{order_id}' => $order->get_order_number(),
      '{invoice_id}' => isset($result['invoice']['invoicenumber']) ? $result['invoice']['invoicenumber'] : $this->acumulusConfig->t('message_no_invoice'),
      '{status}' => $result['status'],
      '{status_text}' => $this->webAPI->getStatusText($result['status']),
      '{status_1_text}' => $this->webAPI->getStatusText(1),
      '{status_2_text}' => $this->webAPI->getStatusText(2),
      '{status_3_text}' => $this->webAPI->getStatusText(3),
      '{messages}' => $this->webAPI->messagesToText($messages),
      '{messages_html}' => $this->webAPI->messagesToHtml($messages),
    );
//      $text = $this->acumulusConfig->t('mail_text');
//      $text = strtr($text, $replacements);
    $html = $this->acumulusConfig->t('mail_html');
    $html = strtr($html, $replacements);
//      $message = "--$boundary\r\n"
//        . "Content-Type: text/plain; charset=UTF-8\r\n"
//        . $text . "\r\n"
//        .  "--$boundary\r\n"
//        . "Content-Type: text/html; charset=UTF-8\r\n"
//        . $html . "\r\n";

    wp_mail($toEmail, $subject, $html, $headers);
  }

  public function log($message) {
    if (WP_DEBUG) {
      if (func_num_args() > 1) {
        $args = array_splice(func_get_args(), 0, 1);
        $message = vsprintf($message, $args);
      }
      error_log($message);
    }
  }

  /**
   * Forwards the call to the specific setup class.
   */
  public static function activate() {
    require_once(dirname(__FILE__) . '/AcumulusSetup.php');
    $setup = new AcumulusSetup();
    $setup->activate();
  }

  /**
   * Forwards the call to the specific setup class.
   */
  public static function deactivate() {
    require_once(dirname(__FILE__) . '/AcumulusSetup.php');
    $setup = new AcumulusSetup();
    $setup->deactivate();
  }

  /**
   * Forwards the call to the specific setup class.
   */
  public static function uninstall() {
    require_once(dirname(__FILE__) . '/AcumulusSetup.php');
    $setup = new AcumulusSetup();
    $setup->uninstall();
  }

}

