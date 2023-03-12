<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\WooCommerce\Integration;

use Acumulus;
use Siel\Acumulus\Invoice\Source;
use WP_UnitTestCase;

/**
 * Tests collecting data in WooCommerce
 *
 * Things that should get tested:
 * - Defaults from {@see \Siel\Acumulus\WooCommerce\Config\ShopCapabilities::getDefaultShopMappings()}
 */
class CollectorTest extends WP_UnitTestCase
{
    public function customerProvider(): array
    {
        return [
            [
                Source::Order, 462,
                [
                    'contactId' => null,
                    'type' => null,
                    'vatTypeId' => null,
                    'contactYourId' => 9,
                    'contactStatus' => null,
                    'website' => null,
                    'vatNumber' => null,
                    'telephone' => '0303132334',
                    'telephone2' => null,
                    'fax' => null,
                    'email' => 'erwin@reve-provencal.eu',
                    'overwriteIfExists' => null,
                    'bankAccountNumber' => null,
                    'mark' => null,
                    'disableDuplicates' => null,
                ],
            ],
        ];
    }

    /**
     * @dataProvider customerProvider
     */
    public function testCollectCustomer(string $type, int $id, array $values): void
    {
        $container = Acumulus::create()->getAcumulusContainer();
        $collector = $container->getCollectorManager();
        $source = $container->createSource(Source::Order, 462);
        $collector->addPropertySource('source', $source);
        $customer = $collector->collectCustomer();
        foreach ($values as $key => $value) {
            /** @noinspection PhpVariableVariableInspection */
            $this->assertSame($value, $customer->$key, $key);
        }
    }
}
