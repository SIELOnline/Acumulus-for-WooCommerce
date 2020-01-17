<?php /** @noinspection PhpUnused */
/*
 * Plugin Name: Acumulus
 * Description: Acumulus plugin for WooCommerce
 * Author: Buro RaDer, https://burorader.com/
 * Copyright: SIEL BV, https://www.siel.nl/acumulus/
 * Version: 5.8.1
 * LICENCE: GPLv3
 * Requires at least: 4.2.3
 * Tested up to: 5.3
 * WC requires at least: 2.4
 * WC tested up to: 3.8
 * libAcumulus requires at least: 5.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Form;
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
  private static $instance;

  /** @var string */
  private $file;

  /** @var \Siel\Acumulus\Helpers\Container */
  private $container;

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

    // Actions:
    // - Add our forms to the admin menu.
    add_action('admin_menu', array($this, 'addMenuLinks'), 900);
    // - Admin notices , meta boxes, and ajax requests from them.
    add_action('admin_notices', array($this, 'showAdminNotices'));
    add_action('add_meta_boxes_shop_order', array($this, 'addShopOrderMetaBox'));
    add_action('wp_ajax_acumulus_ajax_action', array($this, 'handleAjaxRequest'));
    // - To process our own forms.
    add_action('admin_post_acumulus_config', array($this, 'processConfigForm'));
    add_action('admin_post_acumulus_advanced', array($this, 'processAdvancedForm'));
    add_action('admin_post_acumulus_batch', array($this, 'processBatchForm'));
    // - WooCommerce order/refund events.
    add_action('plugins_loaded', array($this, 'pluginsLoaded'));
    add_action('woocommerce_order_refunded', array($this, 'woocommerceOrderRefunded'), 10, 2);
    // - Our own invoice related events.
    add_filter('acumulus_invoice_created', array($this, 'acumulusInvoiceCreated'), 10, 3);
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
    } else {
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
      require_once __DIR__ . '/lib/siel/acumulus/SielAcumulusAutoloader.php';
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
      if (version_compare($woocommerce->version, '3', '>=')) {
        $shopNamespace = 'WooCommerce';
      } else {
        $shopNamespace = 'WooCommerce\WooCommerce2';
      }
      $this->container = new Container($shopNamespace, $languageCode);
      $this->container->getTranslator()->add(new ModuleTranslations());

      // Check for any updates to perform.
      $this->upgrade();
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
   * Shows admin notices.
   *
   * Due to the order of execution and the habit of redirecting at the end of
   * an action, just adding a notice may not work. Therefore we work with
   * transients.
   */
  public function showAdminNotices() {
    $screen = get_current_screen();
    // These notices should only show on the main dashboard. (@todo: and the acumulus screens)
    if ($screen && $screen->id === 'dashboard') {
      // Check the transients to see if we should display notices.

      // Notice about rating our plugin.
      $value = get_transient('acumulus_rate_plugin');
      if (empty($value) || ctype_digit($value) && time() >= $value) {
        echo $this->processRatePluginForm();
      }

      // Notice about ending support for WooCommerce 2.
      /** @var \WooCommerce $woocommerce */
      global $woocommerce;
      if (version_compare($woocommerce->version, '3', '>=')) {
        // WooCommerce has been upgraded to a version >= 3. We do no longer need
        // this transient.
        delete_transient('acumulus_stop_support_woocommerce2');
      } else {
        $value = get_transient('acumulus_stop_support_woocommerce2');
        if (empty($value) || time() >= $value + 2 * 24 * 60 * 60) {
          echo '<div class="notice notice-warning is-dismissible"><p>' . $this->t('wc2_end_support') . '</p></div>';
          // Store the next time we are displaying it: in 2 days.
          set_transient('acumulus_stop_support_woocommerce2', time() + 2 * 24 * 60 * 60);
        }
      }
    }
  }

  /**
   * Handles ajax requests for this plugin.
   *
   * The invoice status overview and the rate plugin form use ajax requests.
   */
  public function handleAjaxRequest()
  {
    check_ajax_referer('acumulus_ajax_action', 'security');

    $this->init();
    // Check where the ajax call came from.
    if (isset($_POST['form'])) {
      switch ($_POST['form']) {
        case 'invoice':
          $content = $this->handleInvoiceStatusOverviewFormRequest();
          break;
        case 'rate':
          $content = $this->handleRatePluginFormRequest();
          break;
        default:
          $content = $this->renderNotice('Form parameter of ajax request unknown to Acumulus.', 'error');
      }
    } else {
      $content = $this->renderNotice('No form parameter in ajax request for Acumulus.', 'error');
    }
    wp_send_json(array(
      'id' => 'acumulus-rate-plugin',
      'content' => $content,
    ));
  }

  /**
   * Handles an ajax request from the metabox on the view/edit order screen that
   * shows the status in acumulus of the invoice for the current order.
   *
   * @return string
   *   The rendered form (embedded in any necessary html).
   */
  private function handleRatePluginFormRequest() {
    $content = $this->processRatePluginForm();
    // Processing the form may result in that the form should be removed.
    $value = get_transient('acumulus_rate_plugin');
    if (ctype_digit($value) || time() < $value) {
      $content = '';
    } elseif ($value === 'done') {
      $content = $this->renderNotice($this->t('done_thanks'), 'success');
    }
    return $content;
  }

  /**
   * Handles an ajax request from the meta box on the view/edit order screen
   * that shows the status in acumulus of the invoice for the current order.
   *
   * @return string
   *   The rendered form (embedded in any necessary html).
   */
  private function handleInvoiceStatusOverviewFormRequest() {
    /** @var \Siel\Acumulus\WooCommerce\Shop\InvoiceStatusOverviewForm $form */
    $form = $this->getForm('invoice');

    $parentType = $_POST['parent_type'] === Source::Order ? Source::Order : Source::CreditNote;
    $parentId = (int) $_POST['parent_source'];
    $shopOrderPost = get_post($parentId);
    if ($shopOrderPost instanceof WP_Post && get_post_type($shopOrderPost) === 'shop_order') {
      $parentSource = $this->container->getSource($parentType, $parentId);
      $form->setSource($parentSource);
      $content = $this->processInvoiceStatusOverviewForm();
    } else {
      $content = sprintf($this->t('unknown_source'), strtolower($parentType), $parentId);
    }
    return $content;
  }

  /**
   * Action handler for the add_meta_boxes_shop_order action.
   *
   * @param WP_Post $shopOrderPost
   */
  public function addShopOrderMetaBox(WP_Post $shopOrderPost) {
    $this->init();
    // Already load form translations and set Source.
    /** @var \Siel\Acumulus\WooCommerce\Shop\InvoiceStatusOverviewForm $form */
    $form = $this->getForm('invoice');
    $orderId = $shopOrderPost->ID;
    $source = $this->container->getSource(Source::Order, $orderId);
    $form->setSource($source);
    add_meta_box('acumulus_invoice_status_overview_info_box',
      $this->t('acumulus_invoice_title'),
      array($this, 'outputInvoiceStatusOverviewInfoBox'),
      'shop_order',
      'side',
      'default');
  }

  /**
   * Callback that renders the contents of the Acumulus invoice info box.
   *
   * param WP_Post $shopOrderPost
   *   The post for the current order.
   */
  public function outputInvoiceStatusOverviewInfoBox(/*WP_Post $shopOrderPost*/) {
    echo $this->processInvoiceStatusOverviewForm();
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
   * Implements the admin_post_acumulus_config action.
   *
   * Processes and renders the basic config form.
   */
  public function processConfigForm() {
    $this->checkCapability('manage_options');
    $this->checkCapability('manage_woocommerce');
    echo $this->processForm('config');
  }

  /**
   * Implements the admin_post_acumulus_advanced action.
   *
   * Processes and renders the advanced config form.
   */
  public function processAdvancedForm() {
    $this->checkCapability('manage_options');
    $this->checkCapability('manage_woocommerce');
    echo $this->processForm('advanced');
  }

  /**
   * Implements the admin_post_acumulus_batch action.
   *
   * Processes and renders the batch form.
   */
  public function processBatchForm() {
    $this->checkCapability('manage_woocommerce');
    echo $this->processForm('batch');
  }

  /**
   * Processes and renders the Acumulus invoice status overview form.
   *
   * Either called via:
   * - Callback that renders the contents of the Acumulus invoice info box.
   * - Ajax request handler.
   *
   * @return string
   *   The rendered form (embedded in any necessary html).
   */
  public function processInvoiceStatusOverviewForm() {
    return $this->processForm('invoice');
  }

  /**
   * Processes and renders the Rate Acumulus plugin form.
   *
   * Either called via:
   * - Render admin notice.
   * - Ajax request handler.
   *
   * @return string
   *   The rendered form (embedded in any necessary html).
   */
  public function processRatePluginForm() {
    return $this->processForm('rate');
  }

  /**
   * Processes and renders the form of the given type.
   *
   * @param string $type
   *   The form type: config, advanced, or batch.
   *
   * @return string
   *   the form html to output.
   */
  public function processForm($type) {
    $form = $this->getForm($type);
    $this->preProcessForm($form);
    $form->process();
    $output = $this->renderForm($form);
    $this->postProcessForm($form);
    return $output;
  }

  /**
   * Renders the form of the given $type.
   *
   * @param $form
   *   config, advanced, or batch.
   *
   * @return string
   *   the form html to output.
   */
  protected function renderForm(Form $form) {
    $this->preRenderForm($form);

    // Render the form first before wrapping it in its final format, so that any
    // messages added during rendering can be shown on top.
    $formOutput = $this->container->getFormRenderer()->render($form);
    return $this->postRenderForm($form, $formOutput);
  }

  /**
   * Performs form type specific actions prior to processing a form.
   *
   * @param \Siel\Acumulus\Helpers\Form $form
   *   The form that is going to be processed.
   */
  protected function preProcessForm(Form $form) {
    $type = $form->getType();

    // Check nonce.
    if (!is_ajax() && $form->isSubmitted()) {
        check_admin_referer("acumulus_{$type}_nonce");
    }

    // Form processing may depend on determining the payment status, but the
    // default states as returned by wc_get_is_paid_statuses() are not how we
    // would define "is paid".
    if (in_array($type, array('batch', 'invoice'))) {
        // WC 3.x: we use WC_Order::is_paid()
        add_action('woocommerce_order_is_paid_statuses', array($this, 'woocommerceOrderIsPaidStatuses'), 10, 2);
        // WC 2.x: we use WC_Order::needs_payment()
        add_action('woocommerce_valid_order_statuses_for_payment', array($this, 'woocommerceValidOrderStatusesForPayment'), 10, 2);
    }
  }

  /**
   * Performs form type specific actions after a form has been processed.
   *
   * @param \Siel\Acumulus\Helpers\Form $form
   *   The form that has been processed.
   */
  protected function postProcessForm(Form $form) {
    $type = $form->getType();

    // Remove our actions that redefine "is paid".
    if (in_array($type, array('batch', 'invoice'))) {
      remove_action('woocommerce_order_is_paid_statuses', array($this, 'woocommerceOrderIsPaidStatuses'));
      remove_action('woocommerce_valid_order_statuses_for_payment', array($this, 'woocommerceValidOrderStatusesForPayment'));
    }
  }

  /**
   * Performs form type specific actions prior to rendering a form
   *
   * @param \Siel\Acumulus\Helpers\Form $form
   *   The form that is going to be rendered.
   */
  protected function preRenderForm(Form $form) {
    // Add our own js.
    $type = $form->getType();
    $pluginUrl = plugins_url('/acumulus');
    switch ($type) {
      case 'batch':
        // Add some js.
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('acumulus.js', $pluginUrl . '/' . 'acumulus.js');
        wp_enqueue_style('jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css');
        break;
      case 'invoice':
        // Add some js.
        wp_enqueue_script('acumulus.js', $pluginUrl . '/' . 'acumulus.js');
        wp_enqueue_script('acumulus-ajax.js', $pluginUrl . '/' . 'acumulus-ajax.js');
        wp_localize_script('acumulus-ajax.js', 'acumulus_data',
          array('ajax_nonce' => wp_create_nonce('acumulus_ajax_action')));

        // The invoice status overview is not rendered as other forms, therefore
        // we change some properties of the form renderer.
        $this->container->getFormRenderer()
          ->setProperty('usePopupDescription', true)
          ->setProperty('fieldsetContentWrapperClass', 'data')
          ->setProperty('detailsWrapperClass', '')
          ->setProperty('labelWrapperClass', 'label')
          ->setProperty('inputDescriptionWrapperClass', 'value')
          ->setProperty('markupWrapperTag', '');
        break;
      case 'rate':
        // Add some js.
        wp_enqueue_script('acumulus.js', $pluginUrl . '/' . 'acumulus.js');
        wp_enqueue_script('acumulus-ajax.js', $pluginUrl . '/' . 'acumulus-ajax.js');
        wp_localize_script('acumulus-ajax.js', 'acumulus_data',
          array('ajax_nonce' => wp_create_nonce('acumulus_ajax_action')));

        // The invoice status overview is not rendered as other forms, therefore
        // we change some properties of the form renderer.
        $this->container->getFormRenderer()
          ->setProperty('fieldsetContentWrapperTag', 'div')
          ->setProperty('fieldsetContentWrapperClass', '')
          ->setProperty('elementWrapperTag', '')
          ->setProperty('inputDescriptionWrapperTag', '')
          ->setProperty('renderEmptyLabel', false)
          ->setProperty('markupWrapperTag', '');
        break;
    }
    // Add our own css.
    wp_enqueue_style('acumulus_css_admin', $pluginUrl . '/acumulus.css');
  }

  /**
   * Performs form type specific actions after a form has been rendered.
   *
   * @param \Siel\Acumulus\Helpers\Form $form
   *   The form that has been rendered.
   * @param string $formOutput
   *   The html of the rendered form.
   *
   * @return string
   *   The rendered form with any wrapping around it.
   */
  protected function postRenderForm(Form $form, $formOutput) {
    $output = '';
    $type = $form->getType();
    switch ($type) {
      case 'config':
      case 'advanced':
      case 'batch':
        $url = admin_url("admin.php?page=acumulus_{$type}");
        $output .= '<div class="wrap">';
        $output .= $this->showNotices($form);
        $output .= '<form method="post" action="' . $url . '">';
        $output .= "<input type=\"hidden\" name=\"action\" value=\"acumulus_{$type}\"/>";
        $output .= wp_nonce_field("acumulus_{$type}_nonce", '_wpnonce', true, false);
        $output .= $formOutput;
        $output .= get_submit_button($type === 'batch' ? $this->t('button_send') : '');
        $output .= '</form>';
        $output .= '</div>';
        break;
      case 'invoice':
        $output .= '<div id="acumulus-invoice-status-overview" class="acumulus-invoice-status-overview">';
        $output .= $formOutput;
        $output .= $this->showNotices($form);
        $output .= '</div>';
        break;
      case 'rate':
        $output .= $this->showNotices($form);
        $output .= $this->renderNotice($formOutput, 'success', 'acumulus-rate-plugin', true);
        break;
    }
    return $output;
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
     *   The type of notice, used to construct css classes to distinguish the
     *   different types of messages. error, warning, info, etc.
     * @param string $id
     *   An optional id to use for the outer tag.
     * @param bool $isHtml
     *   Indicates whether $message is html or plain text. plain text will be
     *   embedded in a <p>.
     *
     * @return string
     *   The rendered notice.
     */
  protected function renderNotice($message, $type, $id = '', $isHtml = false) {
      if (!empty($id)) {
          $id = ' id="' . $id . '"';
      }
      $result = "<div$id class=\"notice notice-$type is-dismissible\">";
      if (!$isHtml) {
        $result .= '<p>';
      }
      $result .= $message;
      if (!$isHtml) {
        $result .= '</p>';
      }
      $result .= '</div>';
      return $result;
  }

  /**
   * Checks access to the current form/page.
   *
   * @param string $capability
   *   The access right to check for.
   */
  protected function checkCapability($capability)
  {
    if (!empty($capability) && !current_user_can($capability)) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }
  }

  /**
   * Defines an order status changed action for each status.
   */
  public function pluginsLoaded() {
    $this->init();
    // Get WC version to set the shop namespace.
    /** @var \WooCommerce $woocommerce */
    global $woocommerce;
    if (version_compare($woocommerce->version, '3', '>=')) {
      $statuses = $this->container->getShopCapabilities()->getShopOrderStatuses();
      foreach ($statuses as $status => $label) {
        add_action('woocommerce_order_status_' . $status, array($this, 'woocommerceOrderStatusChanged'), 10, 1);
      }
    } else {
      add_action('woocommerce_order_status_changed', array($this, 'woocommerceOrderStatusChanged'), 10, 1);
    }
  }

  /**
   * Filter function for the 'woocommerce_order_status_changed' action,
   *
   * This action gets called when the status of an order changes.
   *
   * @param int $orderId
   * - For WC3+ on 'woocommerce_order_status_...'
   *   param WC_Order $order
   * - For WC3+ on 'woocommerce_order_status_changed'
   *   param int $status
   *   param int $newStatus
   *   param WC_Order $Order
   * - For WC2 on 'woocommerce_order_status_changed'
   *   param int $status
   *   param int $newStatus
   */
  public function woocommerceOrderStatusChanged($orderId) {
    $this->init();
    // WC 3.x: we use WC_Order::is_paid() to determine the payment status,
    // but the default states as returned by wc_get_is_paid_statuses() are
    // not as we define "is paid".
    add_action('woocommerce_order_is_paid_statuses', array($this, 'woocommerceOrderIsPaidStatuses'), 10, 2);
    // WC 2.x: we use WC_Order::needs_payment()
    add_action('woocommerce_valid_order_statuses_for_payment', array($this, 'woocommerceValidOrderStatusesForPayment'), 10, 2);
    $source = $this->container->getSource(Source::Order, $orderId);
    $this->container->getInvoiceManager()->sourceStatusChange($source);
    remove_action('woocommerce_order_is_paid_statuses', array($this, 'woocommerceOrderIsPaidStatuses'));
    remove_action('woocommerce_valid_order_statuses_for_payment', array($this, 'woocommerceValidOrderStatusesForPayment'));
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
   * Forwards the call to an instance of the setup class.
   */
  public function activate() {
    $this->init();
    require_once 'AcumulusSetup.php';
    $setup = new AcumulusSetup($this->container, $this->getVersionNumber());
    $setup->activate();
  }

  /**
   * Forwards the call to an instance of the setup class.
   *
   * To keep the lazy loading we do check the version number here.
   *
   * @return bool
   */
  public function upgrade() {
    $dbVersion = get_option('acumulus_version');
    if (empty($dbVersion) || version_compare($dbVersion, $this->getVersionNumber(), '<')) {
      require_once 'AcumulusSetup.php';
      $setup = new AcumulusSetup($this->container, $this->getVersionNumber());
      return $setup->upgrade($dbVersion);
    }
    return true;
  }

  /**
   * Forwards the call to an instance of the setup class.
   *
   * @return bool
   */
  public function deactivate() {
    $this->init();
    require_once 'AcumulusSetup.php';
    $setup = new AcumulusSetup($this->container);
    return $setup->deactivate();
  }

  /**
   * Forwards the call to an instance of the setup class.
   *
   * @return bool
   */
  public static function uninstall() {
    $acumulus = static::create();
    $acumulus->init();
    require_once 'AcumulusSetup.php';
    $setup = new AcumulusSetup($acumulus->container);
    return $setup->uninstall();
  }
}

// Entry point for WP: create and bootstrap our module.
Acumulus::create()->bootstrap();
