<?php
/*
Plugin Name: Acumulus
Description: Acumulus koppeling voor WooCommerce 2.4+
Plugin URI: https://wordpress.org/plugins/acumulus/
Author: SIEL Acumulus
Version: 4.5.0-beta2
LICENCE: GPLv3
*/

use Siel\Acumulus\Shop\Config;
use Siel\Acumulus\Shop\ModuleTranslations;
use Siel\Acumulus\WooCommerce\Helpers\FormMapper;
use Siel\Acumulus\WooCommerce\Invoice\Source;

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

/**
 * Class Acumulus
 */
class Acumulus {

  /** @var Acumulus|null */
  private static $instance = NULL;

  /** @var \Siel\Acumulus\Shop\Config */
  protected $acumulusConfig = NULL;

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
    add_action('admin_init', array($this, 'adminInit'));
    add_action('admin_menu', array($this, 'addOptionsPage'));
    add_action('admin_menu', array($this, 'addBatchForm'), 900);
    add_action('admin_post_acumulus_batch', array($this, 'processBatchForm'));
    add_action('woocommerce_order_status_changed', array($this, 'woocommerceOrderStatusChanged'), 10, 3);
    add_action('woocommerce_order_refunded', array($this, 'woocommerceOrderRefunded'), 10, 2);
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
      $plugin_data = get_plugin_data(__FILE__);
      $version = $plugin_data['Version'];
      $this->upgrade($version);
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
      // Load autoloader
      require_once(dirname(__FILE__) . '/libraries/Siel/psr4.php');

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
    register_setting('acumulus', 'acumulus', array($this, 'processConfigForm'));
  }

  /**
   * Adds our configuration page to the menu.
   */
  public function addOptionsPage() {
    // Create form now to get translations.
    $this->getForm('config');
    add_options_page($this->t('module_name') . ' ' . $this->t('button_settings'),
      $this->t('module_name'),
      'manage_options',
      'acumulus',
      array($this, 'renderOptionsForm'));
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
   * @see addOptionsPage()
   */
  public function renderOptionsForm() {
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Add our own CSS.
    $pluginUrl = plugins_url('/acumulus');
    wp_enqueue_style('acumulus_css_admin', $pluginUrl . '/acumulus.css');

    // Get our form.
    $form = $this->getForm('config');
    // Map our form to WordPress setting sections.
    $formMapper = new FormMapper();
    // And kick off rendering the sections.
    $formRenderer = $formMapper->map($form, 'acumulus');
    $output = '';
    $output .= '<div class="wrap">';
    /** @noinspection HtmlUnknownTarget */
    $output .= '<form method="post" action="options.php">';
    $formRenderer->render($form);
    ob_start();
    settings_fields('acumulus');
    do_settings_sections('acumulus');
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
  public function processConfigForm() {
    $form = $this->getForm('config');
    $form->process(FALSE);
    add_action('admin_notices', array($this, 'showNotices'));
    return $form->getFormValues();
  }

  /**
   * Renders the send batch form. either when called via the menu item this
   * plugin created or after processing the form.
   *
   * @see addBatchForm()
   */
  protected function renderBatchForm() {
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
    $output .= $this->showNotices($form);
    $output .= '<div class="wrap">';
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
    foreach ($form->getErrorMessages() as $message) {
      $output .= $this->renderNotice('error', $message);
    }
    foreach ($form->getSuccessMessages() as $message) {
      $output .= $this->renderNotice('updated', $message);
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
    $output = '';
    $output .= "<div class='$type notice'><p>";
    $output .= $message;
    $output .= '</p></div>';
    return $output;
  }

  /**
   * Getter for the configuration form object.
   *
   * @param string $type
   *
   * @return \Siel\Acumulus\Shop\ConfigForm
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
   * Forwards the call to an instance of the setup class.
   */
  public static function activate() {
    static::create()->init();
    require_once(dirname(__FILE__) . '/AcumulusSetup.php');
    $setup = new AcumulusSetup(static::$instance->acumulusConfig);
    $setup->activate();
  }

  /**
   * Forwards the call to an instance of the setup class.
   *
   * @param string $version
   */
  public function upgrade($version) {
    $dbVersion = get_option('acumulus_version', $version);
    if (empty($dbVersion) || version_compare($dbVersion, $version) === -1) {
      $this->init();
      require_once(dirname(__FILE__) . '/AcumulusSetup.php');
      $setup = new AcumulusSetup($this->acumulusConfig);
      $setup->upgrade($dbVersion, $version);
      update_option('acumulus_version', $version);
    }
  }

  /**
   * Forwards the call to an instance of the setup class.
   */
  public static function deactivate() {
    static::create()->init();
    require_once(dirname(__FILE__) . '/AcumulusSetup.php');
    $setup = new AcumulusSetup(static::$instance->acumulusConfig);
    $setup->deactivate();
  }

  /**
   * Forwards the call to an instance of the setup class.
   */
  public static function uninstall() {
    static::create()->init();
    require_once(dirname(__FILE__) . '/AcumulusSetup.php');
    $setup = new AcumulusSetup(static::$instance->acumulusConfig);
    $setup->uninstall();
  }

}
