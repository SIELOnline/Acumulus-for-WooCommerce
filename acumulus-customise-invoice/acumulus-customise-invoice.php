<?php
/*
Plugin Name: Acumulus Customise Invoice
Description: Plugin to customise Acumulus invoices before sending them
Author: Buro RaDer, http://www.burorader.com/
Copyright: SIEL BV, https://www.siel.nl/acumulus/
Version: 5.0.1
LICENCE: GPLv3
*/

use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Creator;
use Siel\Acumulus\Invoice\Result;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * The AcumulusCustomiseInvoice plugin class contains plumbing and example code
 * to react to filters and actions triggered by the Acumulus plugin. These
 * filters and actions allow you to:
 * - Prevent sending an invoice to Acumulus.
 * - Customise the invoice before it is sent to Acumulus.
 * - Process the results of sending the invoice to Acumulus.
 *
 * Usage of this plugin:
 * You can use and modify this example plugin as you like:
 * - only register the filters/actions you are going to use.
 * - add your own filter/action handling in those handler methods.
 *
 * Or, if you already have a plugin with custom code, you can add this code
 * over there:
 * - any filter and action handling code and its add_action() statements: only
 *   copy the filters/actions you are going to use.
 * - any activate, deactivate and uninstall code and its accompanying
 *   register_***_hooks: only copy those hooks that you need.
 *
 * Documentation for the filters and actions:
 * The filters defined by the Acumulus plugin:
 * 1) acumulus_invoice_created
 * 2) acumulus_invoice_send_before
 * The action defined by the Acumulus plugin:
 * 3) acumulus_invoice_send_after
 *
 * ad 1)
 * This filter is triggered after the raw invoice has been created but before
 * it is "completed". The raw invoice contains all data from the original order
 * or refund needed to create an invoice in the Acumulus format. The raw
 * invoice needs to be completed before it can be sent. Completing includes:
 * - Determining vat rates for those lines that do not yet have one (mostly
 *   discount lines or other special lines like processing or payment costs).
 * - Correcting vat rates if they were based on dividing a vat amount (in
 *   cents) by a price (in cents).
 * - Splitting discount lines over multiple vat rates.
 * - Making prices ex vat more precise to prevent invoice amount differences.
 * - Converting non Euro currencies (future feature).
 * - Flattening composed products or products with options.
 *
 * So with this filter you can make changes to the raw invoice based on your
 * specific situation. By returning null, you can prevent having the invoice
 * been sent to Acumulus. Normally you should prefer the 2nd filter, where you
 * can assume that the invoice has been flattened and all fields are filled in
 * and have valid values.
 *
 * However, in some specific cases this filter may be needed, e.g. setting or
 * correcting tax rates before the completor strategies are executed.
 *
 * ad 2)
 * This filter is triggered just before the invoice will be sent to Acumulus.
 * You can make changes to the invoice or add warnings or errors to the Result
 * object.
 *
 * Typical use cases are:
 * - Template, account number, or cost center selection based on order
 *   specifics, e.g. in a multi-shop environment.
 * - Adding descriptive info to the invoice or invoice lines based on custom
 *   order meta data or data from not supported modules.
 * - Correcting payment info based on specific knowledge of your situation or
 *   on payment modules not supported by this module.
 *
 * ad 3)
 * This action is triggered after the invoice has been sent to Acumulus. The
 * Result object will tell you if there was an exception or if errors or
 * warnings were returned by the Acumulus API. On success, the entry id and
 * token for the newly created invoice in Acumulus are available, so you can
 * e.g. retrieve the pdf of the Acumulus invoice.
 *
 * External Resources:
 * - https://apidoc.sielsystems.nl/content/invoice-add.
 * - https://apidoc.sielsystems.nl/content/warning-error-and-status-response-section-most-api-calls
 */
class AcumulusCustomiseInvoice {

	/** @var Acumulus|null */
	private static $instance = null;

	/** @var string */
	private $file;

	/**
   * Do not call directly, use the getter getAcumulusContainer().
   *
   * @var \Siel\Acumulus\Helpers\ContainerInterface
   */
	private $container = null;

	/**
	 * Entry point for our plugin.
	 *
	 * @return \AcumulusCustomiseInvoice
	 */
	public static function create() {
		if (self::$instance === null) {
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
	 * Setup the environment for the plugin.
	 */
	public function bootstrap() {
		// Install/uninstall actions.
		register_activation_hook($this->file, array($this, 'activate'));
		register_deactivation_hook($this->file, array($this, 'deactivate'));
		register_uninstall_hook($this->file, array('Acumulus', 'uninstall'));

		// Actions.
		add_filter('acumulus_invoice_created', array($this, 'acumulusInvoiceCreated'), 10, 3);
		add_filter('acumulus_invoice_send_before', array($this, 'acumulusInvoiceSendBefore'), 10, 3);
		add_action('acumulus_invoice_send_after', array($this, 'acumulusInvoiceSendAfter'), 10, 3);
	}

	/**
	 * Loads the Acumulus library and creates a configuration object so this
	 * custom plugin has access to the Acumulus classes, configuration and
	 * constants.
   *
   * Do not call directly, use getAcumulusContainer().
	 */
	private function init() {
		if ($this->container === null) {
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
		}
	}

  /**
   * @return \Siel\Acumulus\Helpers\ContainerInterface
   */
  private function getAcumulusContainer() {
    $this->init();
    return $this->container;
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
		// Here you can make changes to the raw invoice based on your specific
		// situation, e.g. setting or correcting tax rates before the completor
		// strategies execute.

		// NOTE: the example below is now an option in the advanced settings:
		// Prevent sending 0-amount invoices (free products).
		if (empty($invoice) || $invoice['customer']['invoice'][Meta::InvoiceAmountInc] == 0) {
			return null;
		} else {
			// Change invoice here.
            foreach ($invoice['customer']['invoice']['line'] as &$line) {
                if ($line[Tag::Quantity] < 1 && $line[Tag::Quantity] > 0) {
                    // Get precision info.
                    $precisionEx = !empty($line[Meta::PrecisionUnitPrice]) ? $line[Meta::PrecisionUnitPrice] : (wc_prices_include_tax() ? 0.02 : 0.01);
                    $line[Meta::VatAmount] /= $line[Tag::Quantity];
                    $line += Creator::getVatRangeTags($line[Meta::VatAmount], $line[Tag::UnitPrice], 0.01, $precisionEx);
                }
            }

            return $invoice;
		}
	}

	/**
	 * Processes the filter triggered before an invoice will be sent to Acumulus.
	 *
	 * @param array|null $invoice
	 *   The invoice in Acumulus format as will be sent to Acumulus or null if
	 *   another filter already decided that the invoice should not be sent to
	 *   Acumulus.
	 * @param \Siel\Acumulus\Invoice\Source $invoiceSource
	 *   Wrapper around the original WooCommerce order or refund for which te
	 *   invoice has been created.
	 * @param \Siel\Acumulus\Invoice\Result $localResult
	 *   Any local error or warning messages that were created locally.
	 *
	 * @return array
	 *   The changed invoice or null if you do not want the invoice to be sent
	 *   to Acumulus.
	 */
	public function acumulusInvoiceSendBefore($invoice, Source $invoiceSource, Result $localResult) {
		// Here you can make changes to the raw invoice based on your specific
		// situation, e.g. setting or correcting tax rates before the completor
		// strategies execute.
		// Here you can make changes to the invoice based on your specific
		// situation, e.g. setting the payment status to its correct value:
		if (!empty($invoice)) {
			$invoice['customer']['invoice']['paymentstatus'] = $this->isOrderPaid($invoiceSource) ? Api::PaymentStatus_Paid : Api::PaymentStatus_Due;
		}
		return $invoice;
	}

	/**
	 * Processes the action triggered after an invoice has been sent to Acumulus.
	 *
	 * You can add warnings and errors to the result and they will be mailed.
	 *
	 * @param array $invoice
	 *   The invoice in Acumulus format as has been sent to Acumulus.
	 * @param \Siel\Acumulus\Invoice\Source $invoiceSource
	 *   Wrapper around the original WooCommerce order or refund for which te
	 *   invoice has been sent.
	 * @param \Siel\Acumulus\Invoice\Result $result
     *   The result as sent back by Acumulus + any local messages and warnings.
	 */
	public function acumulusinvoiceSendAfter(array $invoice, Source $invoiceSource, Result $result)	{
		if ($result->getException()) {
			// Serious error:
			if ($result->isSent()) {
				// During sending.
			}
			else {
				// Before sending.
			}
		}
		elseif ($result->hasError()) {
			// Invoice was sent to Acumulus but not created due to errors in the
			// invoice.
		}
		else {
			// Sent successfully, invoice has been created in Acumulus:
			if ($result->getWarnings()) {
				// With warnings.
			}
			else {
				// Without warnings.
			}
		}
	}

	/**
	 * Returns if the order has been paid or not.
	 *
	 * WooCommerce does not store any payment data, so determining the payment
	 * status is not really possible other then using order states. Therefore
	 * this is a valid example of a change you may want to make to the invoice
	 * before it is being send.
	 *
	 * Please fill in your own logic here in this method.
	 *
	 * @param \Siel\Acumulus\Invoice\Source $invoiceSource
	 *   Wrapper around the original WooCommerce order or refund for which the
	 *   invoice has been created.
	 *
	 * @return bool
	 *   True if the order has been paid, false otherwise.
	 */
	private function isOrderPaid(Source $invoiceSource)	{
		/** @var \WC_Abstract_Order $order */
		$order = $invoiceSource->getSource();
    //$this->getAcumulusContainer()->getLog()->info('ControllerExtensionModuleCustomiseAcumulusInvoice::isOrderPaid(): invoiceSource = ' . var_export($order, true));
		return true;
	}

	/**
	 * Add any activate code as needed.
	 *
	 * @return bool
	 */
	public function activate() {
		if (!current_user_can('activate_plugins')) {
			return false;
		}

		return true;
	}

	/**
	 * Add any deactivsate code as needed.
	 *
	 * @return bool
	 */
	public function deactivate() {
		if (!current_user_can('activate_plugins')) {
			return false;
		}

		return true;
	}

	/**
	 * Add any uninstall code as needed.
	 *
	 * @return bool
	 */
	static public function uninstall() {
		if (!current_user_can('delete_plugins')) {
			return false;
		}

		return true;
	}

}

// Entry point for WP: create and bootstrap our module.
AcumulusCustomiseInvoice::create()->bootstrap();
