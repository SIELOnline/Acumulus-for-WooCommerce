<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\WooCommerce\Integration;

use Siel\Acumulus\Completors\InvoiceCompletor;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\SendInvoice;
use Siel\Acumulus\Tests\WooCommerce\Acumulus_WooCommerce_TestCase;
use Siel\Acumulus\Tests\WooCommerce\Data\TestData;

/**
 * SendInvoiceTest tests the process of creation and sending process.
 */
class SendInvoiceTest extends Acumulus_WooCommerce_TestCase
{
    public function InvoiceDataProvider(): array
    {
        return [
            [Source::Order, 61, [],]
        ];
    }

    /**
     * Tests the Creation process, i.e. collecting and completing an
     * {@see \Siel\Acumulus\Data\Invoice}.
     *
     * @dataProvider InvoiceDataProvider
     *
     * @throws \RuntimeException
     * @throws \JsonException
     */
    public function testCreateAndCompletInvoice(string $type, int $id, array $excludeCustomerFields): void
    {
        $manager = $this->getAcumulusContainer()->getCollectorManager();
        $invoiceSource = $this->getAcumulusContainer()->createSource($type, $id);
        $invoice = $manager->collectInvoice($invoiceSource);
        /** @var InvoiceCompletor $invoiceCompletor */
        $invoiceCompletor = $this->getAcumulusContainer()->getCompletor(DataType::Invoice);
        $result = $this->getAcumulusContainer()->createInvoiceAddResult('SendInvoiceTest::testCreateAndCompleteInvoice()');
        $invoiceCompletor->setSource($invoiceSource)->complete($invoice, $result);

        $testData = new TestData();
        $expected = $testData->get("$type$id");
        $result = $invoice->toArray();
        $this->assertCount(1, $result);
        $this->assertArrayHasKey(Fld::Customer, $result);
        $this->compareCustomerPart($expected[Fld::Customer], $result[Fld::Customer], $excludeCustomerFields);
    }

    private function compareCustomerPart(array $expected, array $customer, array $excludeCustomerFields): void
    {
        $excludeCustomerFields[] = Fld::Invoice;
        foreach ($expected as $field => $value) {
            if (!in_array($field, $excludeCustomerFields, true)) {
                $this->assertArrayHasKey($field, $customer);
                $this->assertEquals($value, $customer[$field]);
            }
        }
    }
}
