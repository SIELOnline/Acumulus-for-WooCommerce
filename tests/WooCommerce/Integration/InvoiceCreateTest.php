<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\WooCommerce\Integration;

use Siel\Acumulus\Fld;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Tests\WooCommerce\Acumulus_WooCommerce_TestCase;

use function dirname;

/**
 * InvoiceCreateTest tests the process of creating an {@see Invoice}.
 */
class InvoiceCreateTest extends Acumulus_WooCommerce_TestCase
{
    public function InvoiceDataProvider(): array
    {
        $dataPath = dirname(__FILE__, 2) . '/Data';
        return [
            'NL consument' => [$dataPath, Source::Order, 61,],
            'NL company' => [$dataPath, Source::Order, 62,],
            '1 fixed amount coupon' => [$dataPath, Source::Order, 67,],
            '2 (fixed amount and percentage) coupons' => [$dataPath, Source::Order, 68,],
            'reversed vat, different shipping country, variants' => [$dataPath, Source::Order, 69,],
            'EU VAT, variants, percentage coupon' => [$dataPath, Source::Order, 70,],
            'EU VAT Belgium (same vat rate as NL), shipping to NL' => [$dataPath, Source::Order, 71,],
            'NL Refund' => [$dataPath, Source::CreditNote, 73,],
        ];
    }

    /**
     * Tests the Creation process, i.e. collecting and completing an
     * {@see \Siel\Acumulus\Data\Invoice}.
     *
     * @dataProvider InvoiceDataProvider
     * @throws \JsonException
     */
    public function testCreate(string $dataPath, string $type, int $id, array $excludeFields = []): void
    {
        $this->_testCreate($dataPath, $type, $id, $excludeFields);
    }
}
