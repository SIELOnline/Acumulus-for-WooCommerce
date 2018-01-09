<?php
/*
Plugin Name: Acumulus
Description: Acumulus plugin for WooCommerce 2.4+
Plugin URI: https://wordpress.org/plugins/acumulus/
Author: SIEL Acumulus
Version: 5.0.1
LICENCE: GPLv3
*/

use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\ModuleTranslations;

/**
 * Class Acumulus is the base plugin class.
 */
class Acumulus {

  /** @var Acumulus|null */
  private static $instance = NULL;

  /** @var string */
  private $file;

  /** @var \Siel\Acumulus\Helpers\ContainerInterface */
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
      $this->registerSielAutoloader();

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
    $this->getForm('config');
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
    $this->getForm('batch');
    add_submenu_page('woocommerce',
      $this->t('batch_form_title'),
      $this->t('batch_form_header'),
      'manage_woocommerce',
      'acumulus_batch',
      array($this, 'processBatchForm'));
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
      $form->addErrorMessage(sprintf($this->t('update_failed'), $this->getVersionNumber()));
    }

    $doRemoveAction = false;
    if ($form->isSubmitted()) {
      check_admin_referer("acumulus_{$type}_nonce");
      if ($type === 'batch') {
        add_action('woocommerce_valid_order_statuses_for_payment', array($this, 'woocommerceValidOrderStatusesForPayment'), 10, 2);
        $doRemoveAction = true;
      }
    }
    $form->process();
    if ($doRemoveAction) {
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
    // And kick off rendering the sections.
    $formRenderer = $this->container->getFormRenderer();
    /** @var \Siel\Acumulus\WooCommerce\Helpers\FormMapper $formMapper */
    $formMapper = $this->container->getFormMapper();
    $formMapper->setPage("acumulus_{$type}")->setCallback(array($formRenderer, 'field'))->map($form);
    $output = '';
    $output .= '<div class="wrap">';
    $output .= $this->showNotices($form);
    $output .= '<form method="post" action="' . $url . '">';
    $output .= "<input type=\"hidden\" name=\"action\" value=\"acumulus_{$type}\"/>";
    $output .= wp_nonce_field("acumulus_{$type}_nonce", '_wpnonce', true, false);
    $formRenderer->render($form);
    ob_start();
    do_settings_sections("acumulus_{$type}");
    $output .= ob_get_clean();
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
            $output .= $this->renderNotice($message, 'updated');
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
      return sprintf('<div class="notice notice-%s is-dismissble"><p>%s</p></div>', $type, $message);
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
    add_action('woocommerce_valid_order_statuses_for_payment', array($this, 'woocommerceValidOrderStatusesForPayment'), 10, 2);
    $source = $this->container->getSource(Source::Order, $orderId);
    $this->container->getManager()->sourceStatusChange($source);
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
    $this->container->getManager()->sourceStatusChange($source);
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

  /**
   * Registers an autoloader for the Siel\Acumulus namespace library.
   *
   * As not all web shops support auto-loading based on namespaces or have
   * other glitches, eg. expecting lower cased file names, we define our own
   * autoloader. If the module cannot use the autoloader of the web shop, this
   * function should be loaded during bootstrapping of the module.
   *
   * Thanks to https://gist.github.com/mageekguy/8300961
   */
  private function registerSielAutoloader() {
    $dir = __DIR__ . '/lib/siel/acumulus/src/';
    $ourNamespace = 'Siel\\Acumulus\\';
    $ourNamespaceLen = strlen($ourNamespace);
    $autoloadFunction = function ($class) use ($ourNamespace, $ourNamespaceLen, $dir) {
      if (strncmp($class, $ourNamespace, $ourNamespaceLen) === 0) {
        $fileName = $dir . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, $ourNamespaceLen)) . '.php';
        if (is_readable($fileName)) {
          /** @noinspection PhpIncludeInspection */
          include($fileName);
        }
      }
    };
    // Prepend this autoloader: it will not throw, nor warn, while the shop
    // specific autoloader might do so.
    spl_autoload_register($autoloadFunction, true, true);
  }
}

// Entry point for WP: create and bootstrap our module.
Acumulus::create()->bootstrap();
