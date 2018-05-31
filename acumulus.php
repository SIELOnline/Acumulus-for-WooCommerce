<?php
/*
 * Plugin Name: Acumulus
 * Description: Acumulus plugin for WooCommerce 2.4+
 * Author: Buro RaDer, http://www.burorader.com/
 * Copyright: SIEL BV, https://www.siel.nl/acumulus/
 * Version: 5.4.3
 * LICENCE: GPLv3
 * Requires at least: 4.2.3
 * Tested up to: 4.9
 * WC requires at least: 2.4
 * WC tested up to: 3.4
 * libAcumulus requires at least: 5.4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Result;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\BatchFormTranslations;
use Siel\Acumulus\Shop\ConfigFormTranslations;
use Siel\Acumulus\Shop\ModuleTranslations;

/**
 * Class Acumulus is the base plugin class.
 */
class Acumulus {

  /** @var Acumulus|null */
  private static $instance = NULL;

  /** @var string */
  private $file;

  /** @var \Siel\Acumulus\Helpers\Container */
  private $container = NULL;

  /**
   * Entry point for our plugin.
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
   * Constructor.
   */
  private function __construct() {
    $this->file = str_replace('\\', '/', __FILE__);
  }

  /**
   * Setup the environment for the plugin
   */
  public function bootstrap() {
    // Install/uninstall actions.
    register_activation_hook($this->file, array($this, 'activate'));
    register_deactivation_hook($this->file, array($this, 'deactivate'));
    register_uninstall_hook($this->file, array('Acumulus', 'uninstall'));

    // Actions.
    add_action('admin_menu', array($this, 'addMenuLinks'), 900);
    add_action('admin_post_acumulus_config', array($this, 'processConfigForm'));
    add_action('admin_post_acumulus_advanced', array($this, 'processAdvancedForm'));
    add_action('admin_post_acumulus_batch', array($this, 'processBatchForm'));
    add_action('woocommerce_order_status_changed', array($this, 'woocommerceOrderStatusChanged'), 10, 3);
    add_action('woocommerce_order_refunded', array($this, 'woocommerceOrderRefunded'), 10, 2);
    add_filter('acumulus_invoice_created', array($this, 'acumulusInvoiceCreated'), 10, 3);
    add_action('add_meta_boxes', array($this, 'addMetaBoxes'));
  }

  /**
   * Helper method for the ConfigStore object to get the version number from the
   * comment at the top of this file, as is the official location for WordPress
   * plugins.
   *
   * @return string
   *   The version number of this plugin.
   */
  public function getVersionNumber() {
    if (function_exists('get_plugin_data')) {
      $plugin_data = get_plugin_data($this->file);
      $version = $plugin_data['Version'];
    }
    else {
      $version = get_option('acumulus_version');
    }
    return $version;
  }

  /**
   * Helper method to translate strings.
   *
   * @param string $key
   *  The key to get a translation for.
   *
   * @return string
   *   The translation for the given key or the key itself if no translation
   *   could be found.
   */
  protected function t($key) {
    return $this->container->getTranslator()->get($key);
  }

  /**
   * Loads our library and creates a configuration object.
   */
  public function init() {
    if ($this->container === NULL) {
      // Load autoloader
      require_once(__DIR__ . '/lib/siel/acumulus/SielAcumulusAutoloader.php');
      SielAcumulusAutoloader::register();

      // Get language
      $languageCode = get_bloginfo('language');
      if (empty($languageCode)) {
        $languageCode = 'nl';
      }
      $languageCode = substr($languageCode, 0, 2);

      // Get WC version to set the shop namespace.
      /** @var \WooCommerce $woocommerce */
      global $woocommerce;
      $shopNamespace = version_compare($woocommerce->version, '3', '>=') ? 'WooCommerce' : 'WooCommerce\WooCommerce2';

      $this->container = new Container($shopNamespace, $languageCode);
      $this->container->getTranslator()->add(new ModuleTranslations());
    }
  }

  /**
   * Adds our pages to the admin menu.
   */
  public function addMenuLinks() {
    // Start with creating a config form, so we can use the translations.
    $this->init();
    $this->container->getTranslator()->add(new ConfigFormTranslations());
    add_submenu_page('options-general.php',
      $this->t('config_form_title'),
      $this->t('config_form_header'),
      'manage_options',
      'acumulus_config',
      array($this, 'processConfigForm')
    );
    add_submenu_page('options-general.php',
      $this->t('advanced_form_title'),
      $this->t('advanced_form_header'),
      'manage_options',
      'acumulus_advanced',
      array($this, 'processAdvancedForm')
    );

    // Start with creating the batch form, so we can use the translations.
    $this->container->getTranslator()->add(new BatchFormTranslations());
    add_submenu_page('woocommerce',
      $this->t('batch_form_title'),
      $this->t('batch_form_header'),
      'manage_woocommerce',
      'acumulus_batch',
      array($this, 'processBatchForm')
    );
  }

  /**
   * Implements the admin_post_acumulus_config action.
   *
   *  Processes and renders the basic config form.
   */
  public function processConfigForm() {
    $this->processForm('config', 'manage_options');
  }

  /**
   * Implements the admin_post_acumulus_advanced action.
   *
   *  Processes and renders the advanced config form.
   */
  public function processAdvancedForm() {
    $this->processForm('advanced', 'manage_options');
  }

  /**
   * Implements the admin_post_acumulus_batch action.
   *
   *  Processes and renders the batch form.
   */
  public function processBatchForm() {
    $this->processForm('batch', 'manage_woocommerce');
  }

  /**
   * Processes and renders the form of the given type.
   *
   * @param string $type
   *   The form type: config, advanced, or batch.
   * @param string $capability
   *   The required access right the user should have.
   */
  public function processForm($type, $capability ) {
    if (!current_user_can($capability)) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    $form = $this->getForm($type);

    // Trigger auto update before each form invocation.
    if (!$this->upgrade()) {
      $form->addErrorMessages(sprintf($this->t('update_failed'), $this->getVersionNumber()));
    }

    $doRemoveAction = false;
    if ($form->isSubmitted()) {
      check_admin_referer("acumulus_{$type}_nonce");
      if ($type === 'batch') {
        // WC 3.x: we use WC_Order::is_paid() to determine the payment status,
        // but the default states as returned by wc_get_is_paid_statuses() are
        // not as we define "is paid".
        add_action('woocommerce_order_is_paid_statuses', array($this, 'woocommerceOrderIsPaidStatuses'), 10, 2);
        // WC 2.x: we use WC_Order::needs_payment()
        add_action('woocommerce_valid_order_statuses_for_payment', array($this, 'woocommerceValidOrderStatusesForPayment'), 10, 2);
        $doRemoveAction = true;
      }
    }
    $form->process();
    if ($doRemoveAction) {
      remove_action('woocommerce_order_is_paid_statuses', array($this, 'woocommerceOrderIsPaidStatuses'), 10);
      remove_action('woocommerce_valid_order_statuses_for_payment', array($this, 'woocommerceValidOrderStatusesForPayment'), 10);
    }
    $this->renderForm($type, $capability);
  }

  /**
   * Renders the form of the given $type.
   *
   * @param string $type
   *   config, advanced, or batch.
   * @param string $capability
   *   The required access right the user should have.
   */
  protected function renderForm($type, $capability) {
    if (!current_user_can($capability)) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Add our own CSS.
    $pluginUrl = plugins_url('/acumulus');
    if ($type === 'batch') {
      wp_enqueue_script('jquery-ui-datepicker');
      wp_enqueue_script('acumulus.js', $pluginUrl . '/' . 'acumulus.js');
      wp_enqueue_style('jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css');
    }
    wp_enqueue_style('acumulus_css_admin', $pluginUrl . '/acumulus.css');

    $url = admin_url("admin.php?page=acumulus_{$type}");
    // Get our form.
    $form = $this->getForm($type);
    // Render the form first, so that any messages added during rendering can be
    // shown on top.
    $formOutput = $this->container->getFormRenderer()->render($form);

    // And kick off rendering the sections.
    $output = '';
    $output .= '<div class="wrap">';
    $output .= $this->showNotices($form);
    $output .= '<form method="post" action="' . $url . '">';
    $output .= "<input type=\"hidden\" name=\"action\" value=\"acumulus_{$type}\"/>";
    $output .= wp_nonce_field("acumulus_{$type}_nonce", '_wpnonce', true, false);
    $output .= $formOutput;
    $output .= get_submit_button($type === 'batch' ? $this->t('button_send') : '');
    $output .= '</form>';
    $output .= '</div>';
    echo $output;
  }

  /**
   * Action method that renders any notices coming from the form(s).
   *
   * @param \Siel\Acumulus\Helpers\Form $form
   *
   * @return string
   */
  public function showNotices($form) {
    $output = '';
    if (isset($form)) {
        foreach ($form->getErrorMessages() as $message) {
            $output .= $this->renderNotice($message, 'error');
        }
        foreach ($form->getWarningMessages() as $message) {
            $output .= $this->renderNotice($message, 'warning');
        }
        foreach ($form->getSuccessMessages() as $message) {
            $output .= $this->renderNotice($message, 'success');
        }
    }
    return $output;
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
  protected function renderNotice($message, $type) {
      return sprintf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $type, $message);
  }

  /**
   * Getter for the form object.
   *
   * @param string $type
   *
   * @return \Siel\Acumulus\Helpers\Form
   */
  protected function getForm($type) {
    $this->init();
    return $this->container->getForm($type);
  }

  /**
   * Filter function for the 'woocommerce_order_status_changed' action,
   *
   * This action gets called when the status of an order changes.
   *
   * @param int $orderId
   * param int $status
   * param int $newStatus
   */
  public function woocommerceOrderStatusChanged($orderId/*, $status, $newStatus*/) {
    $this->init();
    // WC 3.x: we use WC_Order::is_paid() to determine the payment status,
    // but the default states as returned by wc_get_is_paid_statuses() are
    // not as we define "is paid".
    add_action('woocommerce_order_is_paid_statuses', array($this, 'woocommerceOrderIsPaidStatuses'), 10, 2);
    // WC 2.x: we use WC_Order::needs_payment()
    add_action('woocommerce_valid_order_statuses_for_payment', array($this, 'woocommerceValidOrderStatusesForPayment'), 10, 2);
    $source = $this->container->getSource(Source::Order, $orderId);
    $this->container->getInvoiceManager()->sourceStatusChange($source);
    remove_action('woocommerce_valid_order_statuses_for_payment', array($this, 'woocommerceOrderIsPaidStatuses'), 10);
    remove_action('woocommerce_valid_order_statuses_for_payment', array($this, 'woocommerceValidOrderStatusesForPayment'), 10);
  }

  /**
   * Filter function that gets called when the status of an order changes.
   *
   * @param int $orderId
   * @param int $refundId
   */
  public function woocommerceOrderRefunded(/** @noinspection PhpUnusedParameterInspection */ $orderId, $refundId) {
    $this->init();
    $source = $this->container->getSource(Source::CreditNote, $refundId);
    $this->container->getInvoiceManager()->sourceStatusChange($source);
  }

  /**
   * Hook to correct the behaviour of WC_Order::is_paid().
   *
   * Note: this is used when running with WC 3.x.
   *
   * WooCommerce thinks that only orders in the processing or completed statuses
   * are to be seen as paid, whereas for Acumulus, refunded orders are also
   * paid. (if an order is refunded, a separate credit note invoice will be
   * created in Acumulus and thus the invoice for the original order remains
   * "paid".)
   *
   * @param array $statuses
   * param WC_Order $order
   *
   * @return array
   */
  public function woocommerceOrderIsPaidStatuses(array $statuses/*, WC_Order $order*/) {
      return array_merge($statuses, array('refunded'));
  }

  /**
   * Hook to correct the behaviour of WC_Order::needs_payment().
   *
   * Note: this is only used when running with WC 2.x.
   *
   * WooCommerce thinks that orders in the on-hold status are to be seen as
   * paid, whereas for Acumulus they are seen as due. (On-hold means waiting for
   * bank transfer to be booked on our account.)
   *
   * We also add the the cancelled and failed statuses, although for a cancelled
   * no invoice should be created. But if 1 gets created, mark it as due.
   *
   * @param array $statuses
   * param WC_Order $order
   *
   * @return array
   */
  public function woocommerceValidOrderStatusesForPayment(array $statuses/*, WC_Order $order*/) {
      return array_merge($statuses, array('on-hold', 'cancelled', 'failed'));
  }

  /**
   * Processes the filter triggered before an invoice will be sent to Acumulus.
   *
   * @param array|null $invoice
   *   The invoice in Acumulus format as will be sent to Acumulus or null if
   *   another filter already decided that the invoice should not be sent to
   *   Acumulus.
   * @param \Siel\Acumulus\Invoice\Source $invoiceSource
   *   Wrapper around the original WooCommerce order or refund for which the
   *   invoice has been created.
   * @param \Siel\Acumulus\Invoice\Result $localResult
   *   Any local error or warning messages that were created locally.
   *
   * @return array|null
   *   The changed invoice or null if you do not want the invoice to be sent
   *   to Acumulus.
   */
  public function acumulusInvoiceCreated($invoice, Source $invoiceSource, Result $localResult) {
    if ($invoice !== null) {
      $this->init();
      // Get WC version: only for WC 3+ do we support the other plugins.
      /** @var \WooCommerce $woocommerce */
      global $woocommerce;
      if (version_compare($woocommerce->version, '3', '>=')) {
        /** @var \Siel\Acumulus\WooCommerce\Invoice\CreatorPluginSupport $pluginSupport */
        $pluginSupport = $this->container->getInstance('CreatorPluginSupport', 'Invoice');
        $invoice = $pluginSupport->acumulusInvoiceCreated($invoice, $invoiceSource, $localResult);
      }
    }
    return $invoice;
  }

    /**
     * Action handler for the add_meta_boxes action.
     *
     * @param string $postType
     *
     *
     */
    public function addMetaBoxes($postType) {
      if ($postType === 'shop_order') {
        $this->init();
        // Load overview form translations.
        $this->getForm('shop_order');
        add_meta_box('acumulus_shop_order_info_box',
          $this->t('acumulus_invoice_title'),
          array($this, 'renderShopOrderInfoBox'),
          'shop_order',
          'side',
          'default');
      }
  }

  /**
   * Renders the content of the Acumulus info box.
   *
   * @param WP_Post|null $shopOrderPost
   *   The post for the current order.
   */
  public function renderShopOrderInfoBox($shopOrderPost = null) {
    $orderId = $shopOrderPost->ID;

    $this->init();
    $source = $this->container->getSource(Source::Order, $orderId);
    $type = 'shop_order';
//    $url = admin_url("admin.php?page=acumulus_{$type}");

    $pluginUrl = plugins_url('/acumulus');
    wp_enqueue_style('acumulus_css_admin', $pluginUrl . '/acumulus.css');

    // WC 3.x: we use WC_Order::is_paid() to determine the payment status,
    // but the default states as returned by wc_get_is_paid_statuses() are
    // not as we define "is paid".
    add_action('woocommerce_order_is_paid_statuses', array($this, 'woocommerceOrderIsPaidStatuses'), 10, 2);
    // WC 2.x: we use WC_Order::needs_payment()
    add_action('woocommerce_valid_order_statuses_for_payment', array($this, 'woocommerceValidOrderStatusesForPayment'), 10, 2);
    // Get our form.
    /** @var \Siel\Acumulus\WooCommerce\Shop\ShopOrderOverviewForm $form */
    $form = $this->getForm($type);
    $form->setSource($source);
    $formOutput = $this->container->getFormRenderer()
      ->setProperty('usePopupDescription', TRUE)
      ->setProperty('fieldsetContentWrapperClass', 'data')
      ->setProperty('detailsWrapperClass', '')
      ->setProperty('labelWrapperClass', 'label')
      ->setProperty('inputDescriptionWrapperClass', 'value')
      ->render($form);
    // And kick off rendering the sections.
    $output = '';
//    // No form for now, actions will be added later.
//    $output .= '<form id="acumulus-' . $type . '" class="acumulus-overview" method="post" action="' . $url . '">';
    $output .= '<div id="acumulus-' . $type . '" class="acumulus-overview">';
//    $output .= "<input type=\"hidden\" name=\"action\" value=\"acumulus_{$type}\"/>";
//    $output .= wp_nonce_field("acumulus_{$type}_nonce", '_wpnonce', true, false);
    $output .= $formOutput;
    $output .= '</div>';
//    $output .= '</form>';
    echo $output;
    remove_action('woocommerce_valid_order_statuses_for_payment', array($this, 'woocommerceOrderIsPaidStatuses'), 10);
    remove_action('woocommerce_valid_order_statuses_for_payment', array($this, 'woocommerceValidOrderStatusesForPayment'), 10);
  }

  /**
   * Forwards the call to an instance of the setup class.
   */
  public function activate() {
    $this->init();
    require_once('AcumulusSetup.php');
    $setup = new AcumulusSetup($this->container, $this->getVersionNumber());
    $setup->activate();
    $setup->upgrade();
  }

  /**
   * Forwards the call to an instance of the setup class.
   *
   * @return bool
   */
  public function upgrade() {
    $this->init();
    require_once('AcumulusSetup.php');
    $setup = new AcumulusSetup($this->container, $this->getVersionNumber());
    return $setup->upgrade();
  }

  /**
   * Forwards the call to an instance of the setup class.
   *
   * @return bool
   */
  public function deactivate() {
    $this->init();
    require_once('AcumulusSetup.php');
    $setup = new AcumulusSetup($this->container);
    return $setup->deactivate();
  }

  /**
   * Forwards the call to an instance of the setup class.
   *
   * @return bool
   */
  static public function uninstall() {
    $acumulus = static::create();
    $acumulus->init();
    require_once('AcumulusSetup.php');
    $setup = new AcumulusSetup($acumulus->container);
    return $setup->uninstall();
  }
}

// Entry point for WP: create and bootstrap our module.
Acumulus::create()->bootstrap();
