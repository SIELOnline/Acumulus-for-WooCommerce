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

/**
 * SendInvoiceTest tests the process of creation and sending process.
 */
class SendInvoiceTest extends Acumulus_WooCommerce_TestCase
{
    /**
     * Tests the Creation process, i.e. collecting and completing an
     * {@see \Siel\Acumulus\Data\Invoice}.
     */
    public function testCreateInvoice(): void
    {
        $manager = $this->getAcumulusContainer()->getCollectorManager();
        $invoiceSource = $this->getAcumulusContainer()->createSource(Source::Order, 61);
        $invoice = $manager->collectInvoice($invoiceSource);
        /** @var InvoiceCompletor $invoiceCompletor */
        $invoiceCompletor = $this->getAcumulusContainer()->getCompletor(DataType::Invoice);
        $result = $this->getAcumulusContainer()->createInvoiceAddResult('SendInvoiceTest::testCreateInvoice()');
        $invoiceCompletor->setSource($invoiceSource)->complete($invoice, $result);

        $result = $invoice->toArray();

        $this->assertCount(1, $result);
        $this->assertArrayHasKey(Fld::Customer, $result);
        $customer = $result[Fld::Customer];
        $this->assertArrayHasKey(Fld::Email, $customer);
        $this->assertArrayHasKey(Fld::FullName, $customer);
        $this->assertArrayHasKey(Fld::AltFullName, $customer);
        $this->assertArrayHasKey(Fld::Invoice, $customer);
        $invoice = $customer[Fld::Invoice];
        $this->assertArrayHasKey(Fld::Concept, $invoice);
        $this->assertIsArray($invoice[Fld::Line]);
    }
}
