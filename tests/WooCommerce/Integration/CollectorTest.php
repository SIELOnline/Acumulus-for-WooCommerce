<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\WooCommerce\Integration;

use Acumulus;
use Siel\Acumulus\Invoice\Source;
use WC_Unit_Test_Case;

/**
 * Tests collecting data in WooCommerce
 *
 * Things that should get tested:
 * - Defaults from {@see \Siel\Acumulus\WooCommerce\Config\ShopCapabilities::getDefaultShopMappings()}
 */
class CollectorTest extends WC_Unit_Test_Case
{
    /**
     * @return false|\WP_User
     */
    private function createCustomer()
    {
        $id = wc_create_new_customer('erwin@example.com', 'erwin', 'password', [
            'first_name' => 'Erwin',
            'last_name' => 'Derksen',
        ]);
        return get_user_by('id', $id);
    }

    public function customerProvider(): array
    {
        return [
            [
                Source::Order,
                1,
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
                    'email' => 'erwin@example.com',
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
        $customer = $this->createCustomer();
        $source = $container->createSource(Source::Order, 462);
        $customer = $collector->collectCustomer($source);
        foreach ($values as $key => $value) {
            /** @noinspection PhpVariableVariableInspection */
            $this->assertSame($value, $customer->$key, $key);
        }
    }
}
