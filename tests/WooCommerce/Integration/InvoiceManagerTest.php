<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\WooCommerce\Integration;

use DateTime;
use Siel\Acumulus\Api;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Tests\WooCommerce\Acumulus_WooCommerce_TestCase;
use Siel\Acumulus\Shop\InvoiceManager;

/**
 * InvoiceManagerTest tests the {@see \Siel\Acumulus\Shop\InvoiceManager} class.
 *
 * More specifically this test class tests the getInvoiceSourcesBy...Range methods of the
 * {@see \Siel\Acumulus\WooCommerce\Shop\InvoiceManager WooCommerce InvoiceManager} class.
 *
 */
class InvoiceManagerTest extends Acumulus_WooCommerce_TestCase
{
    private function getInvoiceManager(): InvoiceManager
    {
        return static::getAcumulusContainer()->getInvoiceManager();
    }

    public function InvoiceSourcesByIdRangeDataProvider(): array
    {
        return [
          [Source::Order, 0, 10, []],
          [Source::Order, 61, 61, [61]],
          [Source::Order, 60, 70, [61, 62, 67, 68, 69, 70]],
          [Source::Order, 92, 99, []],
          [Source::CreditNote, 0, 10, []],
          [Source::CreditNote, 72, 72, [72]],
          [Source::CreditNote, 70, 80, [72, 73]],
          [Source::CreditNote, 60, 70, []],
        ];
    }

    /**
     * Tests the
     * {@see \Siel\Acumulus\WooCommerce\Shop\InvoiceManager::getInvoiceSourcesByIdRange()}
     * method.
     *
     * @dataProvider InvoiceSourcesByIdRangeDataProvider
     */
    public function testGetInvoiceSourcesByIdRange(string $sourceType, int $from, int $to, array $expected): void
    {
        $sources = $this->getInvoiceManager()->getInvoiceSourcesByIdRange($sourceType, $from, $to);
        $this->assertSourcesEquals($sources, $expected);
    }

//    public function testGetInvoiceSourcesByReferenceRange(): void
//    {
//    }

    public function InvoiceSourcesByDateRangeDataProvider(): array
    {
        return [
            [Source::Order, '2021-01-01', '2021-12-31', []],
            [Source::Order, '2023-03-19', '2023-03-19', [61]],
            [Source::Order, '2023-03-01', '2023-05-29', [61, 67, 68]],
            [Source::Order, '2023-06-01', '2023-06-12', []],
            [Source::CreditNote, '2021-01-01', '2021-12-31', []],
            [Source::CreditNote, '2023-06-13', '2023-06-13', [72]],
            [Source::CreditNote, '2023-06-13', '2023-09-01', [72, 73]],
            [Source::CreditNote, '2023-05-01', '2023-06-12', []],
        ];
    }

    /**
     * Tests the
     * {@see \Siel\Acumulus\WooCommerce\Shop\InvoiceManager::getInvoiceSourcesByDateRange()}
     * method.
     *
     * @dataProvider InvoiceSourcesByDateRangeDataProvider
     */
    public function testGetInvoiceSourcesByDateRange(string $sourceType, string $from, string $to, array $expected): void
    {
        $from = DateTime::createFromFormat(Api::DateFormat_Iso, $from)->setTime(0, 0, 0);
        $to = DateTime::createFromFormat(Api::DateFormat_Iso, $to)->setTime(23, 59, 59);
        $sources = $this->getInvoiceManager()->getInvoiceSourcesByDateRange($sourceType, $from, $to);
        $this->assertSourcesEquals($sources, $expected);
    }

    /**
     * Asserts that a list of {@see \Siel\Acumulus\Invoice\Source}s equals a list
     * of ids by source id.
     *
     * @param Source[] $sources
     * @param int[] $expected
     */
    public function assertSourcesEquals(array $sources, array $expected): void
    {
        array_walk($sources, static function (Source &$value) {
            $value = $value->getId();
        });
        $this->assertEqualsCanonicalizing($expected, $sources);
    }
}
