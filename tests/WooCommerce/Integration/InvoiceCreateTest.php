<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\WooCommerce\Integration;

use Siel\Acumulus\Fld;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Invoice\Translations;
use Siel\Acumulus\Tests\WooCommerce\Acumulus_WooCommerce_TestCase;
use Siel\Acumulus\Tests\WooCommerce\Data\TestData;

use function in_array;

/**
 * InvoiceCreateTest tests the process of creating an {@see Invoice}.
 */
class InvoiceCreateTest extends Acumulus_WooCommerce_TestCase
{
    /**
     * @beforeClass
     *   Adds translations that are not added by default when the Translator is created.
     */
    public static function addTranslations(): void
    {
        self::getAcumulusContainer()->getTranslator()->add(new Translations());
    }

    public function InvoiceDataProvider(): array
    {
        return [
            'NL consument' => [Source::Order, 61,],
            'NL company' => [Source::Order, 62,],
            '1 fixed amount coupon' => [Source::Order, 67,],
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
     * @dataProvider InvoiceDataProvider
     * @throws \JsonException
     */
    public function testCreate(string $type, int $id, array $excludeFields = []): void
    {
        $invoiceSource = $this->getAcumulusContainer()->createSource($type, $id);
        $invoiceAddResult = $this->getAcumulusContainer()->createInvoiceAddResult('SendInvoiceTest::testCreateAndCompleteInvoice()');
        $invoice = $this->getAcumulusContainer()->getInvoiceCreate()->create($invoiceSource, $invoiceAddResult);
        $result = $invoice->toArray();
        $testData = new TestData();
        // Get order from Order{id}.json.
        $expected = $testData->get($type, $id);
        if ($expected !== null) {
            // Save order to Order{id}.latest.json, so we can compare differences ourselves.
            $testData->save($type, $id, false, $result);
            $this->assertCount(1, $result);
            $this->assertArrayHasKey(Fld::Customer, $result);
            $this->compareAcumulusObject($expected[Fld::Customer], $result[Fld::Customer], $excludeFields);
        } else {
            // File does not yet exist: first time for a new test order: save order to Order{id}.json.
            // Will raise a warning that no asserts have been executed.
            $testData->save($type, $id, true, $result);
        }
    }

    private function compareAcumulusObject(array $expected, array $object, array $excludeFields): void
    {
        foreach ($expected as $field => $value) {
            if (!in_array($field, $excludeFields, true)) {
                $this->assertArrayHasKey($field, $object);
                switch ($field) {
                    case 'invoice':
                    case 'emailAsPdf':
                        $this->compareAcumulusObject($value, $object[$field], $excludeFields);
                        break;
                    case 'lines':
                        foreach ($value as $index => $line) {
                            $this->compareAcumulusObject($line, $object[$field][$index], $excludeFields);
                        }
                        break;
                    default:
                        $this->assertEquals($value, $object[$field]);
                        break;
                }
            }
        }
    }
}
