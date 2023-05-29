<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\WooCommerce\Integration;

use RuntimeException;
use Siel\Acumulus\Completors\InvoiceCompletor;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Invoice\Translations;
use Siel\Acumulus\Tests\WooCommerce\Acumulus_WooCommerce_TestCase;
use Siel\Acumulus\Tests\WooCommerce\Data\TestData;

use function in_array;

/**
 * SendInvoiceTest tests the process of creation and sending process.
 */
class SendInvoiceTest extends Acumulus_WooCommerce_TestCase
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
            'NL consument' => [Source::Order, 61, [],],
            'NL bedrijf' => [Source::Order, 62,],
            '1 kortingsbon' => [Source::Order, 67,],
            '2 kortingsbonnen' => [Source::Order, 68,],
        ];
    }

    /**
     * Tests the Creation process, i.e. collecting and completing an
     * {@see \Siel\Acumulus\Data\Invoice}.
     *
     * @dataProvider InvoiceDataProvider
     * @throws \JsonException
     */
    public function testCreateAndCompleteInvoice(string $type, int $id, array $excludeFields = []): void
    {
        $manager = $this->getAcumulusContainer()->getCollectorManager();
        $invoiceSource = $this->getAcumulusContainer()->createSource($type, $id);
        $invoice = $manager->collectInvoice($invoiceSource);
        /** @var InvoiceCompletor $invoiceCompletor */
        $invoiceCompletor = $this->getAcumulusContainer()->getCompletor(DataType::Invoice);
        $result = $this->getAcumulusContainer()->createInvoiceAddResult('SendInvoiceTest::testCreateAndCompleteInvoice()');
        $invoiceCompletor->setSource($invoiceSource)->complete($invoice, $result);
        $result = $invoice->toArray();
        $testData = new TestData();

        try {
            $expected = $testData->get($type, $id);
            $this->assertCount(1, $result);
            $this->assertArrayHasKey(Fld::Customer, $result);
            $this->compareAcumulusObject($expected[Fld::Customer], $result[Fld::Customer], $excludeFields);
            $testData->save($type, $id, false, $result);
        } catch (RuntimeException $e) {
            // First time for a new test order: save to the data folder.
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
