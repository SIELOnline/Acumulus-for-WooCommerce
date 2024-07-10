<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\WooCommerce\Integration;

use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Tests\WooCommerce\Acumulus_WooCommerce_TestCase;

use function dirname;

/**
 * InvoiceCreateTest tests the process of creating an {@see Invoice}.
 */
class InvoiceCreateTest extends Acumulus_WooCommerce_TestCase
{
    public function InvoiceDataProviderWithoutEmailAsPdf(): array
    {
        $dataPath = dirname(__FILE__, 2) . '/Data';
        return [
            '2 (fixed amount and percentage) coupons' => [$dataPath, Source::Order, 68,],
            'reversed vat, different shipping country, variants' => [$dataPath, Source::Order, 69,],
            'EU VAT, variants, percentage coupon' => [$dataPath, Source::Order, 70,],
            'EU VAT Belgium (same vat rate as NL), shipping to NL' => [$dataPath, Source::Order, 71,],
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
        $emailAsPdf = self::getAcumulusContainer()->getConfig()->get('emailAsPdf');
        self::getAcumulusContainer()->getConfig()->set('emailAsPdf', false);
        $this->_testCreate($dataPath, $type, $id, $excludeFields);
        self::getAcumulusContainer()->getConfig()->set('emailAsPdf', $emailAsPdf);
    }

    public function InvoiceDataProviderWithEmailAsPdf(): array
    {
        $dataPath = dirname(__FILE__, 2) . '/Data';
        return [
            'NL consument' => [$dataPath, Source::Order, 61,],
            'NL company' => [$dataPath, Source::Order, 62,],
            '1 fixed amount coupon' => [$dataPath, Source::Order, 67,],
            'NL Refund' => [$dataPath, Source::CreditNote, 73,],
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
        $emailAsPdf = self::getAcumulusContainer()->getConfig()->get('emailAsPdf');
        self::getAcumulusContainer()->getConfig()->set('emailAsPdf', true);
        $this->_testCreate($dataPath, $type, $id, $excludeFields);
        self::getAcumulusContainer()->getConfig()->set('emailAsPdf', $emailAsPdf);
    }

    public function InvoiceDataProviderVatBasedOnShippingAddress(): array
    {
        $dataPath = dirname(__FILE__, 2) . '/Data';
        return [
            'invoice to FR, shipping to NL, vat based on shipping' => [$dataPath, Source::Order, 77,],
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
        $taxBasedOn = get_option('woocommerce_tax_based_on');
        update_option('woocommerce_tax_based_on', 'shipping');
        $this->_testCreate($dataPath, $type, $id, $excludeFields);
        update_option('woocommerce_tax_based_on', $taxBasedOn);
    }
}
