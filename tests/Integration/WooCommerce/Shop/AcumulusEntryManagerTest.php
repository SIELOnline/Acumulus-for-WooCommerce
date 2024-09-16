<?php
/**
 * @noinspection PhpUnhandledExceptionInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\WooCommerce\Shop;

use DateTimeImmutable;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\AcumulusEntry;
use Siel\Acumulus\Shop\AcumulusEntryManager;
use Siel\Acumulus\Tests\WooCommerce\Acumulus_WooCommerce_TestCase;

/**
 * AcumulusEntryTest tests the CRUD actions on the acumulus entries storage in WordPress.
 */
class AcumulusEntryManagerTest extends Acumulus_WooCommerce_TestCase
{
    private const testSourceType = Source::CreditNote;
    private const testSourceId = 72;
    private const testConceptId = 2; // Acumulus concept ids are auto incrementing and will never equal this anymore.
    private const testEntryId = 1; // Acumulus entry ids are auto incrementing and will never equal this anymore.
    private const testToken = 'TESTTOKEN0123456789TESTTOKENtest';

    private function getAcumulusEntryManager(): AcumulusEntryManager
    {
        return static::getAcumulusContainer()->getAcumulusEntryManager();
    }

    /**
     * This method is run to clean up the Entry record for the test source used in these
     * tests.
     */
    public function testDeleteForTestSource(): Source
    {
        $acumulusEntryManager = $this->getAcumulusEntryManager();
        $source = static::getAcumulusContainer()->createSource(static::testSourceType, static::testSourceId);
        $entry = $acumulusEntryManager->getByInvoiceSource($source);
        self::assertTrue($entry === null || $acumulusEntryManager->delete($entry));
        static::commit_transaction();
        return $source;
    }

    /**
     * Tests creating an acumulus entry and the getByInvoiceSource() method.
     *
     * @depends testDeleteForTestSource
     */
    public function testCreate(Source $source): Source
    {
        $acumulusEntryManager = $this->getAcumulusEntryManager();
        $now = current_datetime();
        self::assertTrue($acumulusEntryManager->save($source, static::testConceptId, null));
        static::commit_transaction();

        $entry = $acumulusEntryManager->getByInvoiceSource($source);
        self::assertInstanceOf(AcumulusEntry::class, $entry);
        self::assertSame(static::testSourceType, $entry->getSourceType());
        self::assertSame(static::testSourceId, $entry->getSourceId());
        self::assertSame(static::testConceptId, $entry->getConceptId());
        self::assertNull($entry->getEntryId());
        self::assertNull($entry->getToken());
        // Checks that the timezone is correct, 25s is a large interval but is for when we are debugging.
        self::assertEqualsWithDelta(0, $this->getDiffInSeconds($entry->getCreated(), $now), 25);
        $diff = $this->getDiffInSeconds($entry->getCreated(), $entry->getUpdated());
        self::assertSame(0, $diff);

        return $source;
    }

    /**
     * Tests updating an acumulus entry and the getByEntryId() method.
     *
     * @depends testCreate
     */
    public function testUpdate(Source $source): Source
    {
        $acumulusEntryManager = $this->getAcumulusEntryManager();
        $entry = $acumulusEntryManager->getByInvoiceSource($source);
        $created = $entry->getCreated();
        $updated = $entry->getUpdated();
        $now = new DateTimeImmutable();
        sleep(1);
        self::assertTrue($acumulusEntryManager->save($source, static::testEntryId, static::testToken));
        static::commit_transaction();

        $entry = $acumulusEntryManager->getByEntryId(static::testEntryId);
        self::assertInstanceOf(AcumulusEntry::class, $entry);
        self::assertSame(static::testSourceType, $entry->getSourceType());
        self::assertSame(static::testSourceId, $entry->getSourceId());
        self::assertNull($entry->getConceptId());
        self::assertSame($entry->getEntryId(), static::testEntryId);
        self::assertSame($entry->getToken(), static::testToken);
        $diff = $this->getDiffInSeconds($entry->getCreated(), $created);
        self::assertSame(0, $diff);
        $diff = $this->getDiffInSeconds($entry->getUpdated(), $updated);
        self::assertNotSame(0, $diff);
        // Checks that the timezone is correct
        $diff = $this->getDiffInSeconds($entry->getUpdated(), $now);
        self::assertEqualsWithDelta(0, $diff, 25);

        return $source;
    }

    /**
     * Tests deleting an acumulus entry.
     *
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $acumulusEntryManager = $this->getAcumulusEntryManager();
        $entry = $acumulusEntryManager->getByEntryId(static::testEntryId);
        self::assertTrue($acumulusEntryManager->delete($entry));
        static::commit_transaction();
        self::assertNull($acumulusEntryManager->getByEntryId(static::testEntryId));
    }
}
