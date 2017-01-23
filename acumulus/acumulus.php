<?php
/*
Plugin Name: Acumulus
Description: Acumulus plugin for WooCommerce 2.4+
Plugin URI: https://wordpress.org/plugins/acumulus/
Author: SIEL Acumulus
Version: 4.6.7
LICENCE: GPLv3
*/

use Siel\Acumulus\Shop\Config;
use Siel\Acumulus\Shop\ConfigForm;
use Siel\Acumulus\Shop\ModuleTranslations;
use Siel\Acumulus\WooCommerce\Helpers\FormMapper;
use Siel\Acumulus\WooCommerce\Invoice\Source;

/**
 * Class Acumulus is the base plugin class.
 *
 * @todo: warnings or errors are not displayed on config screen: WP executes a redirect in between.
 */
class Acumulus {

  /** @var Acumulus|null */
  private static $instance = NULL;

  /** @var string */
  private $file;

  /** @var \Siel\Acumulus\Shop\Config */
  private $acumulusConfig = NULL;

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
    add_action('admin_init', array($this, 'adminInit'));
    add_action('admin_menu', array($this, 'addOptionsPages'));
    add_action('admin_menu', array($this, 'addBatchForm'), 900);
    add_action('admin_post_acumulus_advanced', array($this, 'processAdvancedConfigForm'));
    add_action('admin_post_acumulus_batch', array($this, 'processBatchForm'));
    add_action('woocommerce_order_status_changed', array($this, 'woocommerceOrderStatusChanged'), 10, 3);
    add_action('woocommerce_order_refunded', array($this, 'woocommerceOrderRefunded'), 10, 2);
    add_action('woocommerce_valid_order_statuses_for_payment', array($this, 'woocommerceValidOrderStatusesForPayment'), 10, 2);
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
    return $this->acumulusConfig->getTranslator()->get($key);
  }

  /**
   * Loads our library and creates a configuration object.
   */
  public function init() {
    if ($this->acumulusConfig === NULL) {
      // Load autoloader.
      require_once('libraries/Siel/psr4.php');

      $languageCode = get_bloginfo('language');
      if (empty($languageCode)) {
        $languageCode = 'nl';
      }
      $languageCode = substr($languageCode, 0, 2);
      $this->acumulusConfig = new Config('WooCommerce', $languageCode);
      $this->acumulusConfig->getTranslator()->add(new ModuleTranslations());
    }
  }

  /**
   * Registers our settings and its sanitation callback.
   */
  public function adminInit() {
    register_setting('acumulus', 'acumulus', array($this, 'processSettingsForm'));
    register_setting('acumulus', 'acumulus_advanced', array($this, 'processSettingsForm'));
  }

  /**
   * Adds our configuration page to the menu.
   */
  public function addOptionsPages() {
    // Create form now to get translations.
    $this->getForm('config');
    add_options_page($this->t('config_form_title'),
      $this->t('module_name'),
      'manage_options',
      'acumulus',
      array($this, 'renderOptionsForm'));
    add_options_page($this->t('advanced_form_title'),
      $this->t('advanced_form_header'),
      'manage_options',
      'acumulus_advanced',
      array($this, 'renderAdvancedConfigForm'));
  }

  /**
   * Adds our configuration page to the menu.
   */
  public function addBatchForm() {
    // Create form now to get translations.
    $this->getForm('batch');
    add_submenu_page('woocommerce',
      $this->t('batch_form_title'),
      $this->t('module_name'),
      'manage_woocommerce',
      'acumulus_batch',
      array($this, 'processBatchForm'));
  }

  /**
   * Renders the configuration form.
   *
   * @see addOptionsPages()
   *
   * @param string $type
   */
  public function renderConfigForm($type) {
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Add our own CSS.
    $pluginUrl = plugins_url('/acumulus');
    wp_enqueue_style('acumulus_css_admin', $pluginUrl . '/acumulus.css');

    // Get our form.
    $form = $this->getForm($type);
    $option_group = $form instanceof ConfigForm ? 'acumulus' : 'acumulus_advanced';
    // Map our form to WordPress setting sections.
    $formMapper = new FormMapper();
    // And kick off rendering the sections.
    $formRenderer = $formMapper->map($form, $option_group);
    $output = '';
    $output .= '<div class="wrap">';
    $output .= $this->showNotices($form);
    /** @noinspection HtmlUnknownTarget */
    $output .= '<form method="post" action="options.php">';
    $formRenderer->render($form);
    ob_start();
    settings_fields('acumulus');
    do_settings_sections($option_group);
    $output .= ob_get_clean();
    $output .= get_submit_button();
    $output .= '</form>';
    $output .= '</div>';
    echo $output;
  }

  /**
   * Validates and sanitizes the submitted form values.
   *
   * This is the registered settings and sanitation callback.
   *
   * @return array
   *   The sanitized form values.
   *
   * @see adminInit()
   */
  public function renderOptionsForm() {
      return $this->renderConfigForm('config');
  }

  /**
   * Validates and sanitizes the submitted form values.
   *
   * This is the registered settings and sanitation callback.
   *
   * @return array
   *   The sanitized form values.
   *
   * @see adminInit()
   */
  public function renderAdvancedConfigForm() {
      return $this->renderConfigForm('advanced');
  }

  /**
   * Validates and sanitizes the submitted form values.
   *
   * This is the registered settings and sanitation callback.
   *
   * @param string $type
   *   The form type.
   *
   * @return array
   *   The sanitized form values.
   *
   * @see adminInit()
   */
  protected function processConfigForm($type) {
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    $form = $this->getForm($type);
    $form->process(FALSE);
    return $form->getFormValues();
  }

  /**
   * Validates and sanitizes the submitted form values.
   *
   * This is the registered settings and sanitation callback.
   *
   * @return array
   *   The sanitized form values.
   *
   * @see adminInit()
   */
  public function processSettingsForm() {
    $type = 'config';
    @parse_str(parse_url($_POST['_wp_http_referer'], PHP_URL_QUERY));
    /** @var string $page */
    if ($page === 'acumulus_advanced') {
        $type = 'advanced';
    }
    return $this->processConfigForm($type);
  }

  /**
   * Renders the send batch form. either when called via the menu item this
   * plugin created or after processing the form.
   *
   * @see addBatchForm()
   */
  protected function renderBatchForm() {
    if (!current_user_can('manage_woocommerce')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Add our own CSS.
    $pluginUrl = plugins_url('/acumulus');
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_script('acumulus.js', $pluginUrl . '/' . 'acumulus.js');
    //wp_enqueue_style('jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/smoothness/jquery-ui.css');
    wp_enqueue_style('jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css');
    wp_enqueue_style('acumulus_css_admin', $pluginUrl . '/acumulus.css');

    $url = admin_url('admin.php?page=acumulus_batch');
    // Get our form.
    $form = $this->getForm('batch');
    $formMapper = new FormMapper();
    // And kick off rendering the sections.
    $formRenderer = $formMapper->map($form, 'acumulus_batch');
    $output = '';
    $output .= '<div class="wrap">';
      $output .= $this->showNotices($form);
    /** @noinspection HtmlUnknownTarget */
    $output .= '<form method="post" action="' . $url . '">';
    $output .= '<input type="hidden" name="action" value="acumulus_batch"/>';
    $output .= wp_nonce_field('acumulus_batch_nonce', '_wpnonce', true, false);
    $formRenderer->render($form);
    ob_start();
    do_settings_sections('acumulus_batch');
    $output .= ob_get_clean();
    $output .= get_submit_button($this->t('button_send'));
    $output .= '</form>';
    $output .= '</div>';
    echo $output;
  }

  /**
   * Implements the admin_post_acumulus_batch action.
   */
  public function processBatchForm() {
    if (!current_user_can('manage_woocommerce')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    $form = $this->getForm('batch');
    if ($form->isSubmitted()) {
      check_admin_referer('acumulus_batch_nonce');
    }
    $form->process();
    $this->renderBatchForm();
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
            $output .= $this->renderNotice('error', $message);
        }
        foreach ($form->getWarningMessages() as $message) {
            $output .= $this->renderNotice('warning', $message);
        }
        foreach ($form->getSuccessMessages() as $message) {
            $output .= $this->renderNotice('updated', $message);
        }
    }
    return $output;
  }

  /**
   * Renders a notice.
   *
   * @param string $type
   * @param string $message
   *
   * @return string
   *   The rendered notice.
   */
  public function renderNotice($type, $message) {
      return "<div class='notice notice-$type'><p>$message</p></div>";
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
    return $this->acumulusConfig->getForm($type);
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
    $source = new Source(Source::Order, $orderId);
    $this->acumulusConfig->getManager()->sourceStatusChange($source);
  }

  /**
   * Filter function that gets called when the status of an order changes.
   *
   * @param int $orderId
   * @param int $refundId
   */
  public function woocommerceOrderRefunded(/** @noinspection PhpUnusedParameterInspection */ $orderId, $refundId) {
    $this->init();
    $source = new Source(Source::CreditNote, $refundId);
    $this->acumulusConfig->getManager()->sourceStatusChange($source);
  }

  /**
   * Hook to correct the behavior of WC_Abstract_Order::needs_payment().
   *
   * WooCommerce thinks that orders in the on-hold state are to be seen as
   * paid, whereas for Acumulus they are seen as due.
   *
   * @param array $statuses
   * param WC_Abstract_Order $order
   *
   * @return array
   */
  public function woocommerceValidOrderStatusesForPayment(array $statuses/*, WC_Abstract_Order $order*/) {
      return array_merge($statuses, array('on-hold'));
  }

  /**
   * Forwards the call to an instance of the setup class.
   */
  public function activate() {
    $this->init();
    require_once('AcumulusSetup.php');
    $setup = new AcumulusSetup($this->acumulusConfig, $this->getVersionNumber());
    $setup->activate();
    $setup->upgrade();
  }

  /**
   * Forwards the call to an instance of the setup class.
   */
  public function deactivate() {
    $this->init();
    require_once('AcumulusSetup.php');
    $setup = new AcumulusSetup($this->acumulusConfig);
    $setup->deactivate();
  }

  /**
   * Forwards the call to an instance of the setup class.
   */
  static public function uninstall() {
    $acumulus = static::create();
    $acumulus->init();
    require_once('AcumulusSetup.php');
    $setup = new AcumulusSetup($acumulus->acumulusConfig);
    $setup->uninstall();
  }

}

// Entry point for WP: create and bootstrap our module.
Acumulus::create()->bootstrap();
