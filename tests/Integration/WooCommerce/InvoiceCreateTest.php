<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\WooCommerce;

use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Tests\WooCommerce\Acumulus_WooCommerce_TestCase;

/**
 * InvoiceCreateTest tests the process of creating an {@see Invoice}.
 */
class InvoiceCreateTest extends Acumulus_WooCommerce_TestCase
{
    protected static bool $emailAsPdf;
    protected static string $taxBasedOn;
    protected string $dataPath;

    public function __construct(?string $name = null, array $data = [], int|string $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->dataPath = __DIR__ . '/Data';
    }

    /**
     * @before
     * @noinspection PhpUnhandledExceptionInspection
     */
    public function beforeGetConfig(): void
    {
        self::$emailAsPdf = self::getAcumulusContainer()->getConfig()->get('emailAsPdf');
        self::$taxBasedOn = get_option('woocommerce_tax_based_on');
        self::getAcumulusContainer()->getConfig()->set('emailAsPdf', true);
        update_option('woocommerce_tax_based_on', 'billing');
    }

    /**
     * @after
     */
    public function afterResetConfig(): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        self::getAcumulusContainer()->getConfig()->set('emailAsPdf', self::$emailAsPdf ?? true);
        update_option('woocommerce_tax_based_on', self::$taxBasedOn ?? 'billing');
    }

    public function InvoiceDataProviderWithoutEmailAsPdf(): array
    {
        return [
            '2 (fixed amount and percentage) coupons' => [$this->dataPath, Source::Order, 68,],
            'reversed vat, different shipping country, variants' => [$this->dataPath, Source::Order, 69,],
            'EU VAT, variants, percentage coupon' => [$this->dataPath, Source::Order, 70,],
            'EU VAT Belgium (same vat rate as NL), shipping to NL' => [$this->dataPath, Source::Order, 71,],
        ];
    }

    /**
     * Tests the Creation process, i.e. collecting and completing an
     * {@see \Siel\Acumulus\Data\Invoice}.
     *
     * @dataProvider InvoiceDataProviderWithoutEmailAsPdf
     * @throws \JsonException
     */
    public function testCreateWithoutEmailAsPdf(string $dataPath, string $type, int $id, array $excludeFields = []): void
    {
        self::getAcumulusContainer()->getConfig()->set('emailAsPdf', false);
        $this->_testCreate($dataPath, $type, $id, $excludeFields);
    }

    public function InvoiceDataProviderWithEmailAsPdf(): array
    {
        return [
            'NL consument' => [$this->dataPath, Source::Order, 61,],
            'NL company' => [$this->dataPath, Source::Order, 62,],
            '1 fixed amount coupon' => [$this->dataPath, Source::Order, 67,],
            'NL Refund' => [$this->dataPath, Source::CreditNote, 73,],
        ];
    }

    /**
     * Tests the Creation process, i.e. collecting and completing an
     * {@see \Siel\Acumulus\Data\Invoice}.
     *
     * @dataProvider InvoiceDataProviderWithEmailAsPdf
     * @throws \JsonException
     */
    public function testCreateWithEmailAsPdf(string $dataPath, string $type, int $id, array $excludeFields = []): void
    {
        self::getAcumulusContainer()->getConfig()->set('emailAsPdf', true);
        $this->_testCreate($dataPath, $type, $id, $excludeFields);
    }

    public function InvoiceDataProviderVatBasedOnShippingAddress(): array
    {
        return [
            'invoice to FR, shipping to NL, vat based on shipping' => [$this->dataPath, Source::Order, 77,],
        ];
    }

    /**
     * Tests the Creation process with tax based on shipping address, i.e. collecting and
     * completing an {@see \Siel\Acumulus\Data\Invoice}.
     *
     * @dataProvider InvoiceDataProviderVatBasedOnShippingAddress
     * @throws \JsonException
     */
    public function testCreateVatBasedOnShippingAddress(string $dataPath, string $type, int $id, array $excludeFields = []): void
    {
        self::getAcumulusContainer()->getConfig()->set('emailAsPdf', false);
        update_option('woocommerce_tax_based_on', 'shipping');
        $v = get_option('woocommerce_tax_based_on');
        $this->_testCreate($dataPath, $type, $id, $excludeFields);
    }
}
