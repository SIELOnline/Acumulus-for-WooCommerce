<?php
/** @noinspection PhpUnused */
/*
 * Plugin Name: Acumulus
 * Description: Acumulus plugin for WooCommerce
 * Author: Buro RaDer, https://burorader.com/
 * Copyright: SIEL BV, https://www.siel.nl/acumulus/
 * Version: 6.3.8
 * LICENCE: GPLv3
 * Requires at least: 5.0
 * Tested up to: 5.9
 * WC requires at least: 5.0
 * WC tested up to: 6.1
 * libAcumulus requires at least: 6.3.8
 */

if (!defined('ABSPATH')) {
    exit;
}

use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Invoice\Result;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\BatchFormTranslations;
use Siel\Acumulus\Shop\ConfigFormTranslations;
use Siel\Acumulus\Shop\RegisterFormTranslations;

/**
 * Class Acumulus is the base plugin class.
 *
 * @noinspection PhpIllegalPsrClassPathInspection
 */
class Acumulus
{

    /** @var Acumulus */
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
    public static function create()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        $this->file = str_replace('\\', '/', __FILE__);
    }

    /**
     * Setup the environment for the plugin
     */
    public function bootstrap()
    {
        // Install/uninstall actions.
        register_activation_hook($this->file, [$this, 'activate']);
        register_deactivation_hook($this->file, [$this, 'deactivate']);
        register_uninstall_hook($this->file, ['Acumulus', 'uninstall']);

        // Actions:
        // - Add our forms to the admin menu.
        add_action('admin_menu', [$this, 'addMenuLinks'], 900);
        // - Admin notices , meta boxes, and ajax requests from them.
        add_action('admin_notices', [$this, 'showAdminNotices']);
        add_action('add_meta_boxes_shop_order', [$this, 'addShopOrderMetaBox']);
        add_action('wp_ajax_acumulus_ajax_action', [$this, 'handleAjaxRequest']);
        // - To process our own forms.
        add_action('admin_post_acumulus_config', [$this, 'processConfigForm']);
        add_action('admin_post_acumulus_advanced', [$this, 'processAdvancedForm']);
        add_action('admin_post_acumulus_batch', [$this, 'processBatchForm']);
        add_action('admin_post_acumulus_register', [$this, 'processRegisterForm']);
        // - WooCommerce order/refund events.
        add_action('woocommerce_new_order', [$this, 'woocommerceOrderChanged'], 10, 2);
        // This could be an alternative for 'woocommerce_order_status_changed'
        //add_action('woocommerce_update_order', [$this, 'woocommerceOrderStatusChanged'], 10, 2);
        add_action('woocommerce_order_status_changed', [$this, 'woocommerceOrderChanged'], 10, 4);
        add_action('woocommerce_order_refunded', [$this, 'woocommerceOrderRefunded'], 10, 2);
        // - Our own invoice related events.
        add_filter('acumulus_invoice_created', [$this, 'acumulusInvoiceCreated'], 10, 3);
    }

    /**
     * Helper method for the ConfigStore object to get the version number from the
     * comment at the top of this file, as is the official location for WordPress
     * plugins.
     *
     * @return string
     *   The version number of this plugin.
     */
    public function getVersionNumber()
    {
        if (function_exists('get_plugins')) {
            $plugin_data = get_plugins();
            $version = $plugin_data['acumulus/acumulus.php']['Version'];
        } else {
            $version = get_option('acumulus_version');
        }
        return $version;
    }

    /**
     * Returns the WooCommerce version.
     *
     * @return string
     *   The WooCommerce version.
     */
    private function getWooCommerceVersion()
    {
        global $woocommerce;

        return $woocommerce->version;
    }

    /**
     * Returns whether the WooCommerce version is 3 or higher.
     *
     * @return bool
     *   Whether the WooCommerce version is 3 or higher
     */
    private function isWooCommerce3plus()
    {
        return version_compare($this->getWooCommerceVersion(), '3', '>=');
    }

    /**
     * Returns whether the current page being rendered is the dashboard.
     *
     * @return bool
     */
    private function isDashboard()
    {
        $screen = get_current_screen();
        return $screen && $screen->id === 'dashboard';
    }

    /**
     * Returns whether the current page being rendered is one of our own pages.
     *
     * @return bool
     */
    private function isOwnPage()
    {
        $screenIds = ['settings_page_acumulus_config', 'settings_page_acumulus_advanced', 'woocommerce_page_acumulus_batch'];
        $screen = get_current_screen();
        return $screen && in_array($screen->id, $screenIds);
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
    private function t($key)
    {
        return $this->container->getTranslator()->get($key);
    }

    /**
     * Loads our library and creates a configuration object.
     */
    public function init()
    {
        if ($this->container === null) {
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
            if ($this->isWooCommerce3plus()) {
                $shopNamespace = 'WooCommerce';
            } else {
                $shopNamespace = 'WooCommerce\WooCommerce2';
            }
            $this->container = new Container($shopNamespace, $languageCode);

            // Start with a high log level, will be corrected when the config is
            // loaded.
            $this->container->getLog()->setLogLevel(Severity::Log);

            // Check for any updates to perform.
            $this->upgrade();

        }
    }

    /**
     * Adds our pages to the admin menu.
     */
    public function addMenuLinks()
    {
        $this->init();
        // Add the (advanced) config form translations.
        $this->container->getTranslator()->add(new ConfigFormTranslations());
        add_submenu_page('options-general.php',
            $this->t('config_form_title'),
            $this->t('config_form_header'),
            'manage_options',
            'acumulus_config',
            [$this, 'processConfigForm']
        );
        add_submenu_page('options-general.php',
            $this->t('advanced_form_title'),
            $this->t('advanced_form_header'),
            'manage_options',
            'acumulus_advanced',
            [$this, 'processAdvancedForm']
        );
        // Add the register form translations.
        $this->container->getTranslator()->add(new RegisterFormTranslations());
        add_submenu_page('acumulus_config',
            $this->t('register_form_title'),
            $this->t('register_form_header'),
            'manage_options',
            'acumulus_register',
            [$this, 'processRegisterForm']
        );

        // Add the batch form translations.
        $this->container->getTranslator()->add(new BatchFormTranslations());
        add_submenu_page('woocommerce',
            $this->t('batch_form_title'),
            $this->t('batch_form_header'),
            'manage_woocommerce',
            'acumulus_batch',
            [$this, 'processBatchForm']
        );
    }

    /**
     * Shows admin notices.
     *
     * Due to the order of execution and the habit of redirecting at the end of
     * an action, just adding a notice may not work. Therefore, we work with
     * transients.
     */
    public function showAdminNotices()
    {
        // These notices should only show on the main dashboard and our own screens.
        if ($this->isDashboard() || $this->isOwnPage()) {
            // Notice about rating our plugin.
            if (time() >= $this->container->getConfig()->getShowRatePluginMessage()) {
                echo $this->processRatePluginForm();
            }

            // Notice about ending support for WooCommerce 2.
            if ($this->isWooCommerce3plus()) {
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
        check_ajax_referer('acumulus_ajax_action', 'acumulus_nonce');

        $this->init();
        // Check where the ajax call came from.
        if (isset($_POST['area'])) {
            switch ($_POST['area']) {
                case 'acumulus-invoice':
                    $content = $this->processInvoiceStatusForm();
                    break;
                case 'acumulus-rate':
                    $content = $this->processRatePluginForm();
                    break;
                default:
                    $content = $this->renderNotice('Area parameter of ajax request unknown to Acumulus.', 'error');
            }
        } else {
            $content = $this->renderNotice('No area parameter in ajax request for Acumulus.', 'error');
        }
        wp_send_json(['content' => $content]);
    }

    /**
     * Action handler for the add_meta_boxes_shop_order action.
     *
     * @param WP_Post $shopOrderPost
     */
    public function addShopOrderMetaBox(WP_Post $shopOrderPost)
    {
        $this->init();
        $invoiceStatusSettings = $this->container->getConfig()->getInvoiceStatusSettings();
        if ($invoiceStatusSettings['showInvoiceStatus']) {
            // Create form to already load form translations and to set the Source.
            /** @var \Siel\Acumulus\Shop\InvoiceStatusForm $form */
            $form = $this->getForm('invoice');
            $orderId = $shopOrderPost->ID;
            $source = $this->container->getSource(Source::Order, $orderId);
            $form->setSource($source);
            add_meta_box('acumulus-invoice-status-overview',
                $this->t('invoice_form_title'),
                [$this, 'outputInvoiceStatusInfoBox'],
                'shop_order',
                'side',
                'default');
        }
    }

    /**
     * Callback that renders the contents of the Acumulus invoice info box.
     *
     * param WP_Post $shopOrderPost
     *   The post for the current order.
     */
    public function outputInvoiceStatusInfoBox(/*WP_Post $shopOrderPost*/)
    {
        echo $this->processInvoiceStatusForm();
    }

    /**
     * Getter for the form object.
     *
     * @param string $type
     *
     * @return \Siel\Acumulus\Helpers\Form
     */
    private function getForm($type)
    {
        $this->init();

        return $this->container->getForm($type);
    }

    /**
     * Implements the admin_post_acumulus_register action.
     *
     * Processes and renders the batch form.
     */
    public function processRegisterForm()
    {
        $this->checkCapability('manage_options');
        $this->checkCapability('manage_woocommerce');
        echo $this->processForm('register');
    }

    /**
     * Implements the admin_post_acumulus_config action.
     *
     * Processes and renders the basic config form.
     */
    public function processConfigForm()
    {
        $this->checkCapability('manage_options');
        $this->checkCapability('manage_woocommerce');
        echo $this->processForm('config');
    }

    /**
     * Implements the admin_post_acumulus_advanced action.
     *
     * Processes and renders the advanced config form.
     */
    public function processAdvancedForm()
    {
        $this->checkCapability('manage_options');
        $this->checkCapability('manage_woocommerce');
        echo $this->processForm('advanced');
    }

    /**
     * Implements the admin_post_acumulus_batch action.
     *
     * Processes and renders the batch form.
     */
    public function processBatchForm()
    {
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
    public function processInvoiceStatusForm()
    {
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
    public function processRatePluginForm()
    {
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
    public function processForm($type)
    {
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
    private function renderForm(Form $form)
    {
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
    private function preProcessForm(Form $form)
    {
        $type = $form->getType();

        // Check nonce.
        if (!wp_doing_ajax() && $form->isSubmitted()) {
            check_admin_referer("acumulus_{$type}_nonce");
        }

        // Form processing may depend on determining the payment status, but the
        // default states as returned by wc_get_is_paid_statuses() are not how we
        // would define "is paid".
        if (in_array($type, ['batch', 'invoice'])) {
            // WC 3.x: we use WC_Order::is_paid()
            add_action('woocommerce_order_is_paid_statuses', [$this, 'woocommerceOrderIsPaidStatuses'], 10, 2);
            // WC 2.x: we use WC_Order::needs_payment()
            add_action('woocommerce_valid_order_statuses_for_payment', [$this, 'woocommerceValidOrderStatusesForPayment'], 10, 2);
        }
    }

    /**
     * Performs form type specific actions after a form has been processed.
     *
     * @param \Siel\Acumulus\Helpers\Form $form
     *   The form that has been processed.
     */
    private function postProcessForm(Form $form)
    {
        $type = $form->getType();

        // Remove our actions that redefine "is paid".
        if (in_array($type, ['batch', 'invoice'])) {
            remove_action('woocommerce_order_is_paid_statuses', [$this, 'woocommerceOrderIsPaidStatuses']);
            remove_action('woocommerce_valid_order_statuses_for_payment', [$this, 'woocommerceValidOrderStatusesForPayment']);
        }
    }

    /**
     * Performs form type specific actions prior to rendering a form
     *
     * @param \Siel\Acumulus\Helpers\Form $form
     *   The form that is going to be rendered.
     */
    private function preRenderForm(Form $form)
    {
        // Get a new FormRenderer as the rate plugin message may be shown inside our
        // pages and that one has different settings.
        $this->container->getFormRenderer(true);

        // Add our own js.
        $type = $form->getType();
        $pluginUrl = plugins_url('/acumulus');
        switch ($type) {
            case 'invoice':
                // Add some js.
                wp_enqueue_script('jquery-ui-datepicker');
                wp_enqueue_script('acumulus-ajax.js', $pluginUrl . '/' . 'acumulus-ajax.js');

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
                wp_enqueue_script('acumulus-ajax.js', $pluginUrl . '/' . 'acumulus-ajax.js');
                wp_localize_script('acumulus-ajax.js', 'acumulus_data',
                    [
                        'ajax_nonce' => wp_create_nonce('acumulus_ajax_action'),
                        'wait' => $this->t('wait'),
                    ]
                );

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
    private function postRenderForm(Form $form, $formOutput)
    {
        $output = '';
        $type = $form->getType();
        $id = "acumulus-$type";
        $wait = $this->t('wait');
        $nonce = wp_create_nonce('acumulus_ajax_action');
        $url = admin_url("admin.php?page=acumulus_$type");
        $output .= $this->showNotices($form);
        switch ($type) {
            case 'register':
            case 'config':
            case 'advanced':
            case 'batch':
            case 'invoice':
                $wrap = $form->isFullPage();
                if ($wrap) {
                    $output .= '<div class="wrap"><form id="' . $id . '" method="post" action="' . $url . '">';
                    $output .= wp_nonce_field("acumulus_{$type}_nonce", '_wpnonce', true, false);
                } else {
                    $output .= "<div id='$id' class='acumulus-area' data-acumulus-wait='$wait' data-acumulus-nonce='$nonce'>";
                }
                $output .= $formOutput;
                if ($wrap) {
                    $output .= get_submit_button(!in_array($type, ['config', 'advanced']) ? $this->t("button_submit_$type") : '');
                    $output .= '</form></div>';
                } else {
                    $output .= '</div>';
                }
                break;
            case 'rate':
                $extraAttributes = [
                    'class' => 'acumulus-area',
                    'data-acumulus-wait' => $wait,
                    'data-acumulus-nonce' => $nonce,
                ];
                if ($this->isOwnPage()) {
                    $extraAttributes['class'] .= ' inline';
                }
                $output .= $this->renderNotice($formOutput, 'success', $id, $extraAttributes, true);
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
    public function showNotices($form)
    {
        $output = '';
        if (isset($form)) {
            foreach ($form->getMessages() as $message) {
                $output .= $this->renderNotice($message->format(Message::Format_PlainWithSeverity), $this->SeverityToNoticeClass($message->getSeverity()),
                    $message->getField());
            }
        }

        return $output;
    }

    /**
     * Converts a Severity constant into a WP notice class.
     *
     * @param int $severity
     *
     * @return string
     *
     */
    private function SeverityToNoticeClass($severity)
    {
        switch ($severity) {
            case Severity::Success:
                $class = 'success';
                break;
            case Severity::Info:
            case Severity::Notice:
                $class = 'info';
                break;
            case Severity::Warning:
                $class = 'warning';
                break;
            case Severity::Error:
            case Severity::Exception:
                $class = 'error';
                break;
            default:
                $class = '';
                break;
        }

        return $class;
    }

    /**
     * Renders a notice.
     *
     * @param string $message
     * @param string $type
     *   The type of notice, used to construct css classes to distinguish the
     *   different types of messages. error, warning, info, etc.
     * @param string $id
     *   An optional id to use for the outer tag OR the name (id) of the field the
     *   form error message is meant for.
     * @param array $extraAttributes
     *   Optional attributes, including additional css classes, to add to the
     *   surrounding div.
     * @param bool $isHtml
     *   Indicates whether $message is html or plain text. plain text will be
     *   embedded in a <p>.
     *
     * @return string
     *   The rendered notice.
     */
    private function renderNotice($message, $type, $id = '', $extraAttributes = [], $isHtml = false)
    {
        $for = '';
        if ($id !== '' && func_num_args() === 3) {
            // Form field message (because: 3 arguments (I know: this sucks)):
            //   make it a clickable label.
            $for = $id;
            $id = '';
        }

        if ($id !== '') {
            $id = ' id="' . $id . '"';
        }

        $class = '';
        if (!empty($extraAttributes['class'])) {
            $class = ' ' . $extraAttributes['class'];
            unset($extraAttributes['class']);
        }

        $extraAttributesString = '';
        foreach ($extraAttributes as $attribute => $value) {
            $extraAttributesString .= " $attribute='$value'";
        }

        $result = "<div$id class='notice notice-$type is-dismissible$class'$extraAttributesString>";
        if (!$isHtml) {
            $result .= '<p>';
        }
        if ($for) {
            $result .= "<label for='$for'>";
        }
        $result .= $message;
        if ($for) {
            $result .= '</label>';
        }
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
    private function checkCapability($capability)
    {
        if (!empty($capability) && !current_user_can($capability)) {
            /** @noinspection ForgottenDebugOutputInspection */
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
    }

    /**
     * Action function for the 'woocommerce_new_order' and
     * 'woocommerce_order_status_changed' actions.
     *
     * This action gets called when an order is created resp. when the status of
     * an order changes.
     *
     * @param int $orderId
     * - For 'woocommerce_new_order'
     *   param WC_Order $Order
     * - For 'woocommerce_order_status_changed'
     *   param int $fromStatus
     *   param int $toStatus
     *   param WC_Order $Order
     * - For WC2 'woocommerce_order_status_changed'
     *   param int $status
     *   param int $newStatus
     */
    public function woocommerceOrderChanged($orderId)
    {
        $this->init();

        /** @var WC_Order|null $order */
        $order = null;
        if (func_num_args() === 2) {
            $order = func_get_arg(1);
        } elseif (func_num_args() === 4) {
            $order = func_get_arg(3);
        }
        // WC 3.x: we use WC_Order::is_paid() to determine the payment status,
        // but the default states as returned by wc_get_is_paid_statuses() are
        // not as we define "is paid".
        add_action('woocommerce_order_is_paid_statuses', [$this, 'woocommerceOrderIsPaidStatuses'], 10, 2);
        $source = $this->container->getSource(Source::Order, $order instanceof WC_Order ? $order : $orderId);
        $this->container->getInvoiceManager()->sourceStatusChange($source);
        remove_action('woocommerce_order_is_paid_statuses', [$this, 'woocommerceOrderIsPaidStatuses']);
        remove_action('woocommerce_valid_order_statuses_for_payment', [$this, 'woocommerceValidOrderStatusesForPayment']);
    }

    /**
     * Filter function that gets called when the status of an order changes.
     *
     * @param int $orderId
     * @param int $refundId
     */
    public function woocommerceOrderRefunded(/** @noinspection PhpUnusedParameterInspection */ $orderId, $refundId)
    {
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
    public function woocommerceOrderIsPaidStatuses(array $statuses/*, WC_Order $order*/)
    {
        return array_merge($statuses, ['refunded']);
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
    public function woocommerceValidOrderStatusesForPayment(array $statuses/*, WC_Order $order*/)
    {
        return array_merge($statuses, ['on-hold', 'cancelled', 'failed']);
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
    public function acumulusInvoiceCreated($invoice, Source $invoiceSource, Result $localResult)
    {
        if ($invoice !== null) {
            $this->init();
            // Get WC version: only for WC 3+ do we support the other plugins.
            if ($this->isWooCommerce3plus()) {
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
    public function activate()
    {
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
    public function upgrade()
    {
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
    public function deactivate()
    {
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
    public static function uninstall()
    {
        $acumulus = static::create();
        $acumulus->init();
        require_once 'AcumulusSetup.php';
        $setup = new AcumulusSetup($acumulus->container);

        return $setup->uninstall();
    }
}

// Entry point for WP: create and bootstrap our module.
Acumulus::create()->bootstrap();
