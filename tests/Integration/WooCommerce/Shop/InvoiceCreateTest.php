<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\WooCommerce\Shop;

use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Tests\WooCommerce\Acumulus_WooCommerce_TestCase;

/**
 * InvoiceCreateTest tests the process of creating an {@see Invoice}.
 *
 * @todo: add a test with fee lines.
 * @todo: add a test with margin scheme.
 */
class InvoiceCreateTest extends Acumulus_WooCommerce_TestCase
{
    protected static bool $emailAsPdf;
    protected static string $taxBasedOn;

    /**
     * @before
     */
    public function beforeGetConfig(): void
    {
        self::$emailAsPdf = self::getContainer()->getConfig()->get('emailAsPdf');
        self::$taxBasedOn = get_option('woocommerce_tax_based_on');
        self::getContainer()->getConfig()->set('emailAsPdf', true);
        update_option('woocommerce_tax_based_on', 'billing');
    }

    /**
     * @after
     */
    public function afterResetConfig(): void
    {
        self::getContainer()->getConfig()->set('emailAsPdf', self::$emailAsPdf ?? true);
        update_option('woocommerce_tax_based_on', self::$taxBasedOn ?? 'billing');
    }

    public function InvoiceDataProviderWithoutEmailAsPdf(): array
    {
        return [
            '2 (fixed amount and percentage) coupons' => [Source::Order, 68,],
            'reversed vat, different shipping country, variants' => [Source::Order, 69,],
            'EU VAT, variants, percentage coupon' => [Source::Order, 70,],
            'EU VAT Belgium (same vat rate as NL), shipping to NL' => [Source::Order, 71,],
        ];
    }

    /**
     * Tests the Creation process, i.e. collecting and completing an
     * {@see \Siel\Acumulus\Data\Invoice}.
     *
     * @dataProvider InvoiceDataProviderWithoutEmailAsPdf
     * @throws \JsonException
     */
    public function testCreateWithoutEmailAsPdf(string $type, int $id, array $excludeFields = []): void
    {
        self::getContainer()->getConfig()->set('emailAsPdf', false);
        $this->_testCreate($type, $id, $excludeFields);
    }

    public function InvoiceDataProviderWithEmailAsPdf(): array
    {
        return [
            'NL consument' => [Source::Order, 61,],
            'NL company' => [Source::Order, 62,],
            '1 fixed amount coupon' => [Source::Order, 67,],
            'NL Refund' => [Source::CreditNote, 73,],
        ];
    }

    /**
     * Tests the Creation process, i.e. collecting and completing an
     * {@see \Siel\Acumulus\Data\Invoice}.
     *
     * @dataProvider InvoiceDataProviderWithEmailAsPdf
     * @throws \JsonException
     */
    public function testCreateWithEmailAsPdf(string $type, int $id, array $excludeFields = []): void
    {
        self::getContainer()->getConfig()->set('emailAsPdf', true);
        $this->_testCreate($type, $id, $excludeFields);
    }

    public function InvoiceDataProviderVatBasedOnShippingAddress(): array
    {
        return [
            'invoice to FR, shipping to NL, vat based on shipping' => [Source::Order, 77,],
        ];
    }

    /**
     * Tests the Creation process with tax based on shipping address, i.e. collecting and
     * completing an {@see \Siel\Acumulus\Data\Invoice}.
     *
     * @dataProvider InvoiceDataProviderVatBasedOnShippingAddress
     */
    public function testCreateVatBasedOnShippingAddress(string $type, int $id, array $excludeFields = []): void
    {
        self::getContainer()->getConfig()->set('emailAsPdf', false);
        update_option('woocommerce_tax_based_on', 'shipping');
        $this->_testCreate($type, $id, $excludeFields);
    }
}
