<?php
/*
 * Plugin Name: Acumulus
 * Description: Acumulus plugin for WooCommerce
 * Author: Buro RaDer, https://burorader.com/
 * Copyright: SIEL BV, https://www.siel.nl/acumulus/
 * Version: 8.1.1
 * LICENCE: GPLv3
 * Requires at least: 5.9
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.4
 * libAcumulus requires at least: 8.1.1
 * Requires PHP: 7.4
 */

/**
 * @noinspection PhpMissingStrictTypesDeclarationInspection  accept strings as int
 * @noinspection AutoloadingIssuesInspection
 */

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Siel\Acumulus\ApiClient\AcumulusResult;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\ActivateSupportFormTranslations;
use Siel\Acumulus\Shop\BatchFormTranslations;
use Siel\Acumulus\Shop\ConfigFormTranslations;
use Siel\Acumulus\Shop\RegisterFormTranslations;

/**
 * Class Acumulus is the base plugin class.
 *
 * WooCommerce HPOS compatibility notes:
 * - new order list page: wp-admin/admin.php?page=wc-orders
 * - new order detail page: wp-admin/admin.php?page=wc-orders&action=edit&id={post_id}
 * - legacy order as a post order list page: wp-admin/edit.php?post_type=shop_order
 * - legacy order as a post order detail page: wp-admin/post.php?post={post_id}&action=edit
 *
 * @noinspection EfferentObjectCouplingInspection
 */
class Acumulus
{
    // Singleton pattern.
    private static Acumulus $instance;

    /**
     * Entry point for our plugin.
     */
    public static function create(): Acumulus
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    // End of singleton pattern.

    private string $file;
    private Container $acumulusContainer;

    /**
     * Private constructor, create via {@see create()}
     */
    private function __construct()
    {
        $this->file = str_replace('\\', '/', __FILE__);
    }

    /**
     * Helper method for the ConfigStore object to get the version number from the
     * comment at the top of this file, as is the official location for WordPress
     * plugins.
     *
     * @return string
     *   The version number of this plugin.
     */
    public function getVersionNumber(): string
    {
        return get_plugins()['acumulus/acumulus.php']['Version'];
    }

    /**
     * Set up the hooks for this plugin.
     */
    public function registerHooks(): void
    {
        // activate/deactivate actions.
        register_activation_hook($this->file, [$this, 'activate']);
        register_deactivation_hook($this->file, [$this, 'deactivate']);

        // Actions:
        // - Add our forms to the admin menu.
        add_action('admin_menu', [$this, 'addMenuLinks'], 900);
        // - Admin notices , meta boxes, and ajax requests from them.
        add_action('admin_notices', [$this, 'showAdminNotices']);
        add_action('add_meta_boxes', [$this, 'addShopOrderMetaBox'], 10, 2);
        add_action('wp_ajax_acumulus_ajax_action', [$this, 'handleAjaxRequest']);
        add_filter( 'woocommerce_admin_order_actions', [$this, 'adminOrderActions'], 100, 2 );
        // - To process our own forms.
        add_action('admin_post_acumulus_settings', [$this, 'processSettingsForm']);
        add_action('admin_post_acumulus_mappings', [$this, 'processMappingsForm']);
        add_action('admin_post_acumulus_config', [$this, 'processConfigForm']);
        add_action('admin_post_acumulus_advanced', [$this, 'processAdvancedForm']);
        add_action('admin_post_acumulus_batch', [$this, 'processBatchForm']);
        add_action('admin_post_acumulus_register', [$this, 'processRegisterForm']);
        // - WooCommerce order/refund events.
        add_action('woocommerce_new_order', [$this, 'woocommerceOrderChanged'], 10, 2);
        add_action('woocommerce_order_status_changed', [$this, 'woocommerceOrderChanged'], 10, 4);
        add_action('woocommerce_order_refunded', [$this, 'woocommerceOrderRefunded'], 10, 2);
        // - Our own invoice related events.
        add_filter('acumulus_invoice_created', [$this, 'acumulusInvoiceCreated'], 10, 3);

        // @todo: WooCommerce HPOS compatibility.
        //   Declare incompatibility, change to true to test or once we are compatible.
        add_action('before_woocommerce_init', static function () {
            if (class_exists(FeaturesUtil::class)) {
                FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });

    }

    /**
     * Returns whether the current page being rendered is the dashboard.
     *
     * @return bool
     */
    private function isDashboard(): bool
    {
        $screen = get_current_screen();
        return $screen && $screen->id === 'dashboard';
    }

    /**
     * Returns whether the current page being rendered is one of our own pages.
     *
     * @return bool
     */
    private function isOwnPage(): bool
    {
        $screenIds = [
            'settings_page_acumulus_settings',
            'settings_page_acumulus_mappings',
            'settings_page_acumulus_config',
            'settings_page_acumulus_advanced',
            'woocommerce_page_acumulus_batch',
        ];
        $screen = get_current_screen();
        return $screen && in_array($screen->id, $screenIds);
    }

    /**
     * Returns whether HPOS is used or the legacy order as post storage.
     */
    public function useHpos(): bool
    {
        return class_exists(CustomOrdersTableController::class)
            && wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled();
    }

    /**
     * Returns the page that shows the list of orders.
     */
    public function getOrderListPage(): string
    {
        return $this->useHpos() ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order';
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
    private function t(string $key): string
    {
        return $this->getAcumulusContainer()->getTranslator()->get($key);
    }

    /**
     * Loads our library and creates a configuration object.
     */
    private function init(): void
    {
        if (!isset($this->acumulusContainer)) {
            // Get access to our library.
            require_once __DIR__ . '/vendor/autoload.php';

            // Get language.
            $languageCode = get_bloginfo('language');
            if (empty($languageCode)) {
                $languageCode = 'nl';
            }
            $languageCode = substr($languageCode, 0, 2);

            $shopNamespace = 'WooCommerce';
            $this->acumulusContainer = new Container($shopNamespace, $languageCode);

            // Start with a high log level, will be corrected when the config is
            // loaded.
            $this->getAcumulusContainer()->getLog()->setLogLevel(Severity::Log);

            // Check for any updates to perform.
            $this->upgrade();
        }
    }

    /**
     * Returns an Acumulus container.
     *
     * Perhaps, more importantly - especially for 3rd parties that wants to use
     * features from libAcumulus - the 1st time it is called, it ensures that
     * libAcumulus autoloading is defined and that a Container with the correct
     * settings is created.
     *
     * So, for 3rd parties, this is the correct way to access the libAcumulus:
     * <code>Acumulus::create()->getAcumulusContainer()</code>
     *
     * @return \Siel\Acumulus\Helpers\Container
     */
    public function getAcumulusContainer(): Container
    {
        $this->init();
        return $this->acumulusContainer;
    }

    /**
     * Adds our pages to the admin menu.
     *
     * We load the translations for each form to be able to show the translated
     * form titles and headers.
     */
    public function addMenuLinks(): void
    {
        // Initialise translations.
        $this->getAcumulusContainer()->getTranslator()->add(new ConfigFormTranslations());

        if ($this->getAcumulusContainer()->getShopCapabilities()->usesNewCode()) {
            add_submenu_page(
                'options-general.php',
                $this->t('settings_form_title'),
                $this->t('settings_form_header'),
                'manage_options',
                'acumulus_settings',
                [$this, 'processSettingsForm']
            );
            add_submenu_page(
                'options-general.php',
                $this->t('mappings_form_title'),
                $this->t('mappings_form_header'),
                'manage_options',
                'acumulus_mappings',
                [$this, 'processMappingsForm']
            );
        } else {
            add_submenu_page(
                'options-general.php',
                $this->t('config_form_title'),
                $this->t('config_form_header'),
                'manage_options',
                'acumulus_config',
                [$this, 'processConfigForm']
            );
            add_submenu_page(
                'options-general.php',
                $this->t('advanced_form_title'),
                $this->t('advanced_form_header'),
                'manage_options',
                'acumulus_advanced',
                [$this, 'processAdvancedForm']
            );
        }
        $this->getAcumulusContainer()->getTranslator()->add(new ActivateSupportFormTranslations());
        add_submenu_page('options-general.php',
            $this->t('activate_form_title'),
            $this->t('activate_form_header'),
            'manage_options',
            'acumulus_activate',
            [$this, 'processActivateForm']
        );
        $this->getAcumulusContainer()->getTranslator()->add(new RegisterFormTranslations());
        // Do not show the registration form in the menu by making it a child of
        // our config form.
        add_submenu_page('acumulus_activate',
            $this->t('register_form_title'),
            $this->t('register_form_header'),
            'manage_options',
            'acumulus_register',
            [$this, 'processRegisterForm']
        );
        $this->getAcumulusContainer()->getTranslator()->add(new BatchFormTranslations());
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
     *
     * @throws \Throwable
     */
    public function showAdminNotices(): void
    {
        // These notices should only show on the main dashboard and our own screens.
        if ($this->isDashboard() || $this->isOwnPage()) {
            // Notice about the new version.
            if (time() >= $this->getAcumulusContainer()->getConfig()->getPluginV8Message()) {
                echo $this->processMessageForm();
            }
            // Notice about rating our plugin.
            if (time() >= $this->getAcumulusContainer()->getConfig()->getShowRatePluginMessage()) {
                echo $this->processRatePluginForm();
            }
        }
    }

    /**
     * Handles ajax requests for this plugin.
     *
     * Uses of ajax:
     * - Invoice status overview.
     * - Rate plugin message.
     * - plugin new version message.
     * - Mail invoice/packing slip from order list. This use is not a real ajax
     *   request but uses admin-ajax.php as entry point like WooCommerce does:
     *   no need to define a proper admin page and finish with a redirect back to
     *   the order list.
     *
     * @throws \Throwable
     */
    public function handleAjaxRequest(): void
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
                case 'acumulus-message':
                    $content = $this->processMessageForm();
                    break;
                default:
                    $content = $this->renderNotice('Area parameter of ajax request unknown to Acumulus.', 'error');
            }
        } elseif (isset($_REQUEST['acumulus_action'])) {
            // @nth: should we have some visual feedback here, in case of both
            //   error and success. Given the redirect at the end, this needs
            //   transient flags or messages. See e.g:
            // - https://wordpress.stackexchange.com/questions/261167/getting-admin-notices-to-appear-after-page-refresh
            // - https://github.com/wpscholar/wp-transient-admin-notices
            switch ($_REQUEST['acumulus_action']) {
                case 'acumulus-document-invoice-mail':
                case 'acumulus-document-packing-slip-mail':
                    $result = $this->mailPdf($_REQUEST['acumulus_action'], $_REQUEST['order_id']);
                    $content = !$result->hasError() ? '✓' : '❌';
                    break;
                default:
                    $content = $this->renderNotice('Acumulus_action parameter of ajax request unknown to Acumulus.', 'error');
            }
            // @todo: WooCommerce HPOS compatibility.
            wp_safe_redirect(wp_get_referer() ?: admin_url($this->getOrderListPage()));
            exit;
        } else {
            $content = $this->renderNotice('No recognised Acumulus ajax request.', 'error');
        }
        wp_send_json(['content' => $content]);
    }

    /**
     * Mails an Acumulus invoice or packing slip pdf.
     *
     * @param string $acumulusAction
     *   Either 'acumulus-invoice' or 'acumulus-packing-slip' (already checked
     *   for).
     * @param int $order_id
     *   The order id. The parameter type conversion will throw an error if
     *   someone played with the order_id parameter.
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     */
    public function mailPdf(string $acumulusAction, int $order_id): AcumulusResult
    {
        $source = $this->getAcumulusContainer()->createSource(Source::Order, $order_id);
        return $acumulusAction === 'acumulus-document-invoice-mail'
            ? $this->getAcumulusContainer()->getInvoiceManager()->emailInvoiceAsPdf($source)
            : $this->getAcumulusContainer()->getInvoiceManager()->emailPackingSlipAsPdf($source);
    }

    /**
     * Action handler for the add_meta_boxes_shop_order action.
     *
     * @param string $screen_id
     *   The id of the screen to add meta boxes to: we want to add our meta box to:
     *   - 'shop_order': the legacy edit order as a post screen.
     *   - 'woocommerce_page_wc-orders': the HPOS edit order screen.
     * @noinspection PhpDocSignatureInspection  object is base type of these possibilities.
     * @param \WP_Post|\WC_Order $shopOrderOrPost
     *   This will be a:
     *   - WP_POST: on the legacy screen.
     *   - WC_Order: on the HPOS screen.
     *
     * @throws \Throwable
     */
    public function addShopOrderMetaBox(string $screen_id, object $shopOrderOrPost): void
    {
        if (in_array($screen_id, ['woocommerce_page_wc-orders', 'shop_order'])) {
            $orderId = ($shopOrderOrPost instanceof WP_Post) ? $shopOrderOrPost->ID : $shopOrderOrPost->get_id();
            $invoiceStatusSettings = $this->getAcumulusContainer()->getConfig()->getInvoiceStatusSettings();
            if ($invoiceStatusSettings['showInvoiceStatus']) {
                // Create form to load form translations and set its Source.
                /** @var \Siel\Acumulus\Shop\InvoiceStatusForm $form */
                try {
                    $form = $this->getForm('invoice');
                    $source = $this->getAcumulusContainer()->createSource(Source::Order, $orderId);
                    $form->setSource($source);
                    add_meta_box(
                        'acumulus-invoice-status-overview',
                        $this->t('invoice_form_title'),
                        [$this, 'outputInvoiceStatusInfoBox'],
                        $screen_id,
                        'side'
                    );
                } catch (Throwable $e) {
                    // We do not show the meta box, not even a message (though we
                    // could add an action for admin_notices), the mail should
                    // suffice to inform the user.
                    try {
                        $crashReporter = $this->getAcumulusContainer()->getCrashReporter();
                        $crashReporter->logAndMail($e);
                    } catch (Throwable $inner) {
                        // We do not know if we have informed the user per mail or
                        // screen, so assume not, and rethrow the original exception.
                        throw $e;
                    }
                }
            }
        }
    }

    /**
     * Callback that renders the contents of the Acumulus invoice info box.
     *
     * param WP_Post $shopOrderPost
     *   The post for the current order.
     *
     * @throws \Throwable
     */
    public function outputInvoiceStatusInfoBox(/*WP_Post $shopOrderPost*/): void
    {
        echo $this->processInvoiceStatusForm();
    }

    /**
     * Callback that renders tha Acumulus actions on the order list page.
     *
     * @param array $actions
     * @param \WC_Order $order
     *
     * @return array
     *   The $actions array with the (enabled) Acumulus actions added.
     */
    public function adminOrderActions(array $actions, WC_Order $order): array
    {
        $documentsSettings = $this->getAcumulusContainer()->getConfig()->getDocumentsSettings();
        if ($documentsSettings['showInvoiceList']
            || $documentsSettings['mailInvoiceList']
            || $documentsSettings['showPackingSlipList']
            || $documentsSettings['mailPackingSlipList']
        )
        {
            $source = $this->getAcumulusContainer()->createSource(Source::Order, $order);
            $acumulusEntry = $this->getAcumulusContainer()->getAcumulusEntryManager()->getByInvoiceSource($source);
            if ($acumulusEntry !== null) {
                $token = $acumulusEntry->getToken();
                // If sent as concept, token will be null.
                if ($token !== null) {
                    $subActions = [];
                    $ajaxAction = 'acumulus_ajax_action';
                    $document = ucfirst($this->t('document_invoice'));
                    if ($documentsSettings['showInvoiceList']) {
                        $action = 'acumulus-document-invoice-show';
                        $uri = $this->getAcumulusContainer()->getAcumulusApiClient()->getInvoicePdfUri($token);
                        $subActions[$action] = [
                            'url' => $uri,
                            'action' => $action,
                            'name' => sprintf($this->t('document_show'), $document),
                            'title' => sprintf($this->t('document_show_title'), $document),
                        ];
                    }
                    if ($documentsSettings['mailInvoiceList']) {
                        $action = 'acumulus-document-invoice-mail';
                        $uri = sprintf('admin-ajax.php?action=%s&acumulus_action=%s&order_id=%d', $ajaxAction, $action, $order->get_id());
                        $subActions[$action] = [
                            'url' => wp_nonce_url(admin_url($uri), $ajaxAction, 'acumulus_nonce'),
                            'action' => $action,
                            'name' => sprintf($this->t('document_mail'), $document),
                            'title' => sprintf($this->t('document_mail_title'), $document),
                        ];
                    }

                    $document = ucfirst($this->t('document_packing_slip'));
                    if ($documentsSettings['showPackingSlipList']) {
                        $action = 'acumulus-document-packing-slip-show';
                        $uri = $this->getAcumulusContainer()->getAcumulusApiClient()->getPackingSlipPdfUri($token);
                        $subActions[$action] = [
                            'url' => $uri,
                            'action' => $action,
                            'name' => sprintf($this->t('document_show'), $document),
                            'title' => sprintf($this->t('document_show_title'), $document),
                        ];
                    }
                    if ($documentsSettings['mailPackingSlipList']) {
                        $action = 'acumulus-document-packing-slip-mail';
                        $uri = sprintf('admin-ajax.php?action=%s&acumulus_action=%s&order_id=%d', $ajaxAction, $action, $order->get_id());
                        $subActions[$action] = [
                            'url' => wp_nonce_url(admin_url($uri), $ajaxAction, 'acumulus_nonce'),
                            'action' => $action,
                            'name' => sprintf($this->t('document_mail'), $document),
                            'title' => sprintf($this->t('document_mail_title'), $document),
                        ];
                    }

                    // Add the actions and our own css.
                    $actions += $subActions;
                    $pluginUrl = plugins_url('/acumulus');
                    wp_enqueue_style('acumulus_css_admin', $pluginUrl . '/acumulus.css');
                }
            }
        }
        return $actions;
    }

    /**
     * Getter for the form object.
     *
     * @param string $type
     *
     * @return \Siel\Acumulus\Helpers\Form
     */
    private function getForm(string $type): Form
    {
        return $this->getAcumulusContainer()->getForm($type);
    }

    /**
     * Implements the admin_post_acumulus_register action.
     *
     * Processes and renders the batch form.
     *
     * @throws \Throwable
     */
    public function processRegisterForm(): void
    {
        $this->checkCapability('manage_options');
        $this->checkCapability('manage_woocommerce');
        echo $this->processForm('register');
    }

    /**
     * Implements the admin_post_acumulus_config action.
     *
     * Processes and renders the basic config form.
     *
     * @throws \Throwable
     */
    public function processConfigForm(): void
    {
        $this->checkCapability('manage_options');
        $this->checkCapability('manage_woocommerce');
        echo $this->processForm('config');
    }

    /**
     * Implements the admin_post_acumulus_advanced action.
     *
     * Processes and renders the advanced config form.
     *
     * @throws \Throwable
     */
    public function processAdvancedForm(): void
    {
        $this->checkCapability('manage_options');
        $this->checkCapability('manage_woocommerce');
        echo $this->processForm('advanced');
    }

    /**
     * Implements the admin_post_acumulus_settings action.
     *
     * Processes and renders the settings form.
     *
     * @throws \Throwable
     */
    public function processSettingsForm(): void
    {
        $this->checkCapability('manage_options');
        $this->checkCapability('manage_woocommerce');
        echo $this->processForm('settings');
    }

    /**
     * Implements the admin_post_acumulus_mappings action.
     *
     * Processes and renders the mappings form.
     *
     * @throws \Throwable
     */
    public function processMappingsForm(): void
    {
        $this->checkCapability('manage_options');
        $this->checkCapability('manage_woocommerce');
        echo $this->processForm('mappings');
    }

    /**
     * Implements the admin_post_acumulus_activate action.
     *
     * Processes and renders the "activate pro-support" form.
     *
     * @throws \Throwable
     */
    public function processActivateForm(): void
    {
        $this->checkCapability('manage_options');
        $this->checkCapability('manage_woocommerce');
        echo $this->processForm('activate');
    }

    /**
     * Implements the admin_post_acumulus_batch action.
     *
     * Processes and renders the batch form.
     *
     * @throws \Throwable
     */
    public function processBatchForm(): void
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
     *
     * @throws \Throwable
     */
    public function processInvoiceStatusForm(): string
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
     *
     * @throws \Throwable
     */
    public function processRatePluginForm(): string
    {
        return $this->processForm('rate');
    }

    /**
     * Processes and renders the Acumulus message form.
     *
     * Called via:
     * - Render admin notice.
     *
     * @return string
     *   The rendered form (embedded in any necessary html).
     *
     * @throws \Throwable
     */
    public function processMessageForm(): string
    {
        return $this->processForm('message');
    }

    /**
     * Processes and renders the form of the given type.
     *
     * @param string $type
     *   The form type: config, advanced, or batch.
     *
     * @return string
     *   the form html to output.
     *
     * @throws \Throwable
     */
    public function processForm(string $type): string
    {
        $form = $this->getForm($type);
        try {
            $this->preProcessForm($form);
            $form->process();
            $this->preRenderForm($form);
            // Render the form first before wrapping it in its final format, so that any
            // messages added during rendering can be shown on top.
            $formOutput = $this->getAcumulusContainer()->getFormRenderer()->render($form);
        } catch (Throwable $e) {
            // We handle our "own" exceptions but only when we can process them
            // as we want, i.e. show it as an error at the beginning of the
            // form. That's why we start catching only after we have a form, and
            // stop catching just before postRenderForm().
            try {
                $crashReporter = $this->getAcumulusContainer()->getCrashReporter();
                $message = $crashReporter->logAndMail($e);
                $form->createAndAddMessage($message, Severity::Exception);
            } catch (Throwable $inner) {
                // We do not know if we have informed the user per mail or
                // screen, so assume not, and rethrow the original exception.
                throw $e;
            }
        }
        $output = $this->postRenderForm($form, $formOutput ?? '');
        $this->postProcessForm($form);
        return $output;
    }

    /**
     * Performs form type specific actions prior to processing a form.
     *
     * @param \Siel\Acumulus\Helpers\Form $form
     *   The form that is going to be processed.
     */
    private function preProcessForm(Form $form): void
    {
        $type = $form->getType();

        // Check nonce.
        if (!wp_doing_ajax() && $form->isSubmitted()) {
            check_admin_referer("acumulus_{$type}_nonce");
        }

        // Form processing may depend on determining the payment status, but the
        // default states as returned by wc_get_is_paid_statuses() are not how
        // we would define "is paid".
        if (in_array($type, ['batch', 'invoice'])) {
            // We use WC_Order::is_paid()
            add_action('woocommerce_order_is_paid_statuses', [$this, 'woocommerceOrderIsPaidStatuses'], 10, 2);
        }
    }

    /**
     * Performs form type specific actions after a form has been processed.
     *
     * @param \Siel\Acumulus\Helpers\Form $form
     *   The form that has been processed.
     */
    private function postProcessForm(Form $form): void
    {
        $type = $form->getType();

        // Remove our actions that redefine "is paid".
        if (in_array($type, ['batch', 'invoice'])) {
            remove_action('woocommerce_order_is_paid_statuses', [$this, 'woocommerceOrderIsPaidStatuses']);
        }
    }

    /**
     * Performs form type specific actions prior to rendering a form
     *
     * @param \Siel\Acumulus\Helpers\Form $form
     *   The form that is going to be rendered.
     */
    private function preRenderForm(Form $form): void
    {
        // Get a new FormRenderer as the rate plugin message may be shown inside our
        // pages and that one has different settings.
        $this->getAcumulusContainer()->getFormRenderer(true);

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
                $this->getAcumulusContainer()->getFormRenderer()
                    ->setProperty('usePopupDescription', true)
                    ->setProperty('fieldsetContentWrapperClass', 'data')
                    ->setProperty('detailsWrapperClass', '')
                    ->setProperty('labelWrapperClass', 'label')
                    ->setProperty('inputDescriptionWrapperClass', 'value')
                    ->setProperty('markupWrapperTag', '');
                break;
            case 'rate':
            case 'message':
                // Add some js.
                wp_enqueue_script('acumulus-ajax.js', $pluginUrl . '/' . 'acumulus-ajax.js');

                // The rte plugin message is not rendered as other forms, therefore
                // we change some properties of the form renderer.
                $this->getAcumulusContainer()->getFormRenderer()
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
    private function postRenderForm(Form $form, string $formOutput): string
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
            case 'settings':
            case 'mappings':
            case 'activate':
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
            case 'message':
                $extraAttributes = [
                    'class' => 'acumulus acumulus-area',
                    'data-acumulus-wait' => $wait,
                    'data-acumulus-nonce' => $nonce,
                ];
                if ($this->isOwnPage()) {
                    $extraAttributes['class'] .= ' inline';
                }
                $noticeType = $type === 'rate' ? 'success' : 'info';
                $output .= $this->renderNotice($formOutput, $noticeType, $id, $extraAttributes, true);
                break;
        }

        return $output;
    }

    /**
     * Action method that renders any notices coming from the form(s).
     *
     * @param \Siel\Acumulus\Helpers\Form|null $form
     *
     * @return string
     */
    private function showNotices(?Form $form): string
    {
        $output = '';
        if (isset($form)) {
            foreach ($form->getMessages() as $message) {
                $output .= $this->renderNotice(
                    $message->format(Message::Format_PlainWithSeverity),
                    $this->SeverityToNoticeClass($message->getSeverity()),
                    $message->getField()
                );
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
    private function SeverityToNoticeClass(int $severity): string
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
    private function renderNotice(string $message, string $type, string $id = '', array $extraAttributes = [], bool $isHtml = false):
    string
    {
        $for = '';
        if (!empty($id) && func_num_args() === 3) {
            // Form field message (because: 3 arguments (I know: this sucks)):
            //   make it a clickable label.
            $for = $id;
            $id = '';
        }

        if (!empty($id)) {
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
    private function checkCapability(string $capability): void
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
     *   param 2: WC[_Abstract]_Order $order
     * - For 'woocommerce_order_status_changed'
     *   param 2: int $fromStatus
     *   param 3: int $toStatus
     *   param 4: WC[_Abstract]_Order $order
     *
     * @throws \Throwable
     */
    public function woocommerceOrderChanged(int $orderId): void
    {
        $this->init();
        /** @var WC_Order|null $order */
        $order = null;
        if (func_num_args() === 2) {
            $order = func_get_arg(1);
        } elseif (func_num_args() === 4) {
            $order = func_get_arg(3);
        }
        // We use WC_Order::is_paid() to determine the payment status, but the
        // default states as returned by wc_get_is_paid_statuses() are not as we
        // define "is paid".
        add_action('woocommerce_order_is_paid_statuses', [$this, 'woocommerceOrderIsPaidStatuses'], 10, 2);
        $this->sourceStatusChange(Source::Order, $order instanceof WC_Order ? $order : $orderId);
        remove_action('woocommerce_order_is_paid_statuses', [$this, 'woocommerceOrderIsPaidStatuses']);
    }

    /**
     * Filter function that gets called when the status of an order changes.
     *
     * @param int $orderId
     * @param int $refundId
     *
     * @throws \Throwable
     */
    public function woocommerceOrderRefunded(/** @noinspection PhpUnusedParameterInspection */ int $orderId, int $refundId): void
    {
        $this->init();
        $this->sourceStatusChange(Source::CreditNote, $refundId);
    }

    /**
     * @param string $invoiceSourceType
     *   The type of the invoice source to create.
     * @param int|object|array $invoiceSourceOrId
     *   The invoice source itself or its id to create a
     *   \Siel\Acumulus\Invoice\Source instance for.
     *
     * @return void
     *
     * @throws \Throwable
     */
    private function sourceStatusChange(string $invoiceSourceType, $invoiceSourceOrId): void
    {
        try {
            $source = $this->getAcumulusContainer()->createSource($invoiceSourceType, $invoiceSourceOrId);
            $this->getAcumulusContainer()->getInvoiceManager()->sourceStatusChange($source);
        } catch (Throwable $e) {
            try {
                $crashReporter = $this->getAcumulusContainer()->getCrashReporter();
                // We do not know if we are on the admin side, so we should not
                // try to display the message returned by logAndMail().
                $crashReporter->logAndMail($e);
            } catch (Throwable $inner) {
                // We do not know if we have informed the user per mail or
                // screen, so assume not, and rethrow the original exception.
                throw $e;
            }
        }
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
    public function woocommerceOrderIsPaidStatuses(array $statuses/*, WC_Order $order*/): array
    {
        return array_merge($statuses, ['refunded']);
    }

    /**
     * Processes the filter triggered before an invoice will be sent to Acumulus.
     *
     * @param \Siel\Acumulus\Data\Invoice|array|null $invoice
     *   The invoice in Acumulus format as will be sent to Acumulus or null if
     *   another filter already decided that the invoice should not be sent to
     *   Acumulus.
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   Wrapper around the original WooCommerce order or refund for which the
     *   invoice has been created.
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $localResult
     *   Any local error or warning messages that were created locally.
     *
     * @return array|null
     *   The changed invoice or null if you do not want the invoice to be sent
     *   to Acumulus.
     */
    public function acumulusInvoiceCreated($invoice, Source $invoiceSource, InvoiceAddResult $localResult): ?array
    {
        if ($invoice !== null) {
            if ($this->getAcumulusContainer()->getShopCapabilities()->usesNewCode()) {
                /**
                 * @var \Siel\Acumulus\WooCommerce\Invoice\CreatorPluginSupport $pluginSupport
                 */
                $pluginSupport = $this->getAcumulusContainer()->getInstance('CreatorPluginSupport', 'Invoice');
            } else {
                /**
                 * @var \Siel\Acumulus\WooCommerce\Completors\Legacy\CreatorPluginSupport $pluginSupport
                 */
                $pluginSupport = $this->getAcumulusContainer()->getInstance('CreatorPluginSupport', 'Completors\Legacy');
            }
            $invoice = $pluginSupport->acumulusInvoiceCreated($invoice, $invoiceSource, $localResult);
        }

        return $invoice;
    }

    /**
     * Forwards the call to an instance of the setup class.
     */
    public function activate(): void
    {
        register_uninstall_hook($this->file, ['Acumulus', 'uninstall']);
        require_once __DIR__ . '/AcumulusSetup.php';
        $setup = new AcumulusSetup($this->getAcumulusContainer());
        $setup->activate();
    }

    /**
     * Checks if we may have to upgrade.
     *
     * This will convert the separate 'acumulus_version' option, if still
     * existing, to the 7.0+ 'VersionKey' config value.
     *
     * WP specific updates (to metadata definitions) should also be placed here.
     *
     * @return bool
     *   Success.
     */
    public function upgrade(): bool
    {
        $result = true;

        $dbVersion = get_option('acumulus_version');
        if (!empty($dbVersion) && empty($this->getAcumulusContainer()->getConfig()->get(Config::VersionKey))) {
            $result = $this->getAcumulusContainer()->getConfig()->save([Config::VersionKey => $dbVersion]);
            delete_option('acumulus_version');
        }

        return $result;
    }

    /**
     * Forwards the call to an instance of the setup class.
     *
     * @return bool
     */
    public function deactivate(): bool
    {
        require_once __DIR__ . '/AcumulusSetup.php';
        return (new AcumulusSetup($this->getAcumulusContainer()))->deactivate();
    }

    /**
     * Forwards the call to an instance of the setup class.
     *
     * @return bool
     */
    public static function uninstall(): bool
    {
        $acumulus = static::create();
        require_once __DIR__ . '/AcumulusSetup.php';
        return (new AcumulusSetup($acumulus->getAcumulusContainer()))->uninstall();
    }
}

// Entry point for WP: create and bootstrap our module.
Acumulus::create()->registerHooks();
