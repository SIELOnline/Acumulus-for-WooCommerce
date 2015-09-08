<?php
/*
Plugin Name: Acumulus
Description: Acumulus koppeling voor WooCommerce 2.3+
Plugin URI: https://forum.acumulus.nl/index.php?board=17.0
Author: Acumulus
Version: 4.0.0-alpha1
LICENCE: GPLv3
*/

use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Shop\Config;
use Siel\Acumulus\WooCommerce\Helpers\FormMapper;
use Siel\Acumulus\WooCommerce\Helpers\FormRenderer;
use Siel\Acumulus\WooCommerce\Helpers\Log;
use Siel\Acumulus\WooCommerce\Invoice\Source;
use Siel\Acumulus\WooCommerce\Shop\ConfigForm;
use Siel\Acumulus\WooCommerce\Shop\ConfigStore;

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

  /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
  protected $translator = null;

  /** @var \Siel\Acumulus\Shop\Config */
  protected $acumulusConfig;

  /** @var \Siel\Acumulus\Shop\ConfigForm */
  protected $form;

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
    add_action('admin_enqueue_scripts', array($this, 'adminEnqueueScripts'));
    add_action('woocommerce_order_status_changed', array($this, 'woocommerceOrderStatusChanged'), 10, 3);
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
   * Loads our library and creates a configuration object.
   */
  public function init() {
    if ($this->translator === null) {
      // Load autoloader
      require_once(dirname(__FILE__) . '/libraries/Siel/psr4.php');

      $languageCode = get_bloginfo('language');
      if (empty($languageCode)) {
        $languageCode = 'nl';
      }
      $languageCode = substr($languageCode, 0, 2);

      Log::createInstance();
      $this->translator = new Translator($languageCode);
      $this->acumulusConfig = new Config(new ConfigStore(), $this->translator);
    }
  }

  /**
   * Getter for the configuration form object.
   *
   * @return \Siel\Acumulus\Shop\ConfigForm
   */
  public function getForm() {
    $this->init();
    if ($this->form === null) {
      $this->form = new ConfigForm($this->translator, $this->acumulusConfig);
    }
    return $this->form;
  }

  /**
   * Registers our settings.
   */
  public function adminInit() {
    register_setting('woocommerce_acumulus', 'woocommerce_acumulus', array($this->getForm(), 'getSubmittedValues'));
  }

  /**
   * Adds our configuration page to the menu.
   */
  public function addOptionsPage() {
    $this->init();
    add_options_page($this->translator->get('module_name') . ' ' . $this->translator->get('button_settings'),
      $this->translator->get('module_name'),
      'manage_options',
      'woocommerce_acumulus',
      array($this, 'renderOptionsForm'));
  }

  /**
   * @deprecated ???
   */
  public function adminEnqueueScripts() {
    $screen = get_current_screen();
    if (is_object($screen) && $screen->id == 'settings_page_woocommerce_acumulus') {
//      $pluginUrl = plugins_url('/woocommerce-acumulus');
//      wp_enqueue_style('acumulus_css_admin', $pluginUrl . '/acumulus.css');
    }
  }

  public function renderOptionsForm() {
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Add our own CSS. @todo: still needed?
    $pluginUrl = plugins_url('/woocommerce-acumulus');
    wp_enqueue_style('acumulus_css_admin', $pluginUrl . '/acumulus.css');

    // Get our form.
    $form = $this->getForm();
    // Map our form to WordPress setting sections.
    $formMapper = new FormMapper();
    $formMapper->map($form);
    // Render the setting sections.
    $formRenderer = new FormRenderer();
    $formRenderer->render($form);
  }

  /**
   * Filter function that gets called when the status of an order changes.
   *
   * @param int $orderId
   * @param int $status
   * @param int $newStatus
   */
  public function woocommerceOrderStatusChanged($orderId, /** @noinspection PhpUnusedParameterInspection */ $status, $newStatus) {
    $this->init();
    $order = new WC_Order($orderId);
    $type = $order->order_type === 'refund' ? Source::CreditNote : Source::Order;
    $source = new Source($type, $order);
    // @todo: check how to handle refunds: upon creation? do they trigger this filter at all?
    $this->acumulusConfig->getManager()->sourceStatusChange($source, $newStatus);
  }

  /**
   * Forwards the call to an instance of the setup class.
   */
  public static function activate() {
    require_once(dirname(__FILE__) . '/AcumulusSetup.php');
    $setup = new AcumulusSetup();
    $setup->activate();
  }

  /**
   * Forwards the call to an instance of the setup class.
   */
  public static function deactivate() {
    require_once(dirname(__FILE__) . '/AcumulusSetup.php');
    $setup = new AcumulusSetup();
    $setup->deactivate();
  }

  /**
   * Forwards the call to an instance of the setup class.
   */
  public static function uninstall() {
    require_once(dirname(__FILE__) . '/AcumulusSetup.php');
    $setup = new AcumulusSetup();
    $setup->uninstall();
  }

}

