<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\WooCommerce\Collectors;

use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Tests\WooCommerce\Acumulus_WooCommerce_TestCase;

use function is_array;

/**
 * Tests collecting data in WooCommerce
 *
 * Things that should get tested:
 * - Defaults from {@see \Siel\Acumulus\WooCommerce\Config\ShopCapabilities::getDefaultPropertyMappings()}
 *   - Customer
 *   - Invoice address
 *   - Shipping address
 *   - @todo email invoice as pdf
 *   - @todo email packing slip as pdf
 *   - @todo invoice + lines
 */
class CollectorTest extends Acumulus_WooCommerce_TestCase
{
    public function collectCustomerProvider(): array
    {
        return [
            [
                Source::Order,
                61,
                [
                    'contactId' => null,
                    'type' => null,
                    'vatTypeId' => null,
                    'contactYourId' => '2',
                    'contactStatus' => null,
                    'salutation' => null,
                    'website' => null,
                    'vatNumber' => null,
                    'telephone' => '0123456789',
                    'telephone2' => null,
                    'fax' => null,
                    'email' => 'nederland@example.com',
                    'overwriteIfExists' => null,
                    'bankAccountNumber' => null,
                    'mark' => null,
                    'disableDuplicates' => null,
                    'invoiceAddress' => [
                        'companyName1' => null,
                        'companyName2' => null,
                        'fullName' => 'Account Holland',
                        'address1' => 'straat 1',
                        'address2' => null,
                        'postalCode' => '7777 AB',
                        'city' => 'Nijmegen',
                        'country' => null,
                        'countryCode' => 'NL',
                        'countryAutoName' => null,
                        'countryAutoNameLang' => null,
                    ],
                    'shippingAddress' => [
                        'companyName1' => null,
                        'companyName2' => null,
                        'fullName' => 'Account2 Holland2',
                        'address1' => 'straat 2',
                        'address2' => null,
                        'postalCode' => '8888 AB',
                        'city' => 'Arnhem',
                        'country' => null,
                        'countryCode' => 'NL',
                        'countryAutoName' => null,
                        'countryAutoNameLang' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider collectCustomerProvider
     */
    public function testCollectCustomer(string $type, int $id, array $values): void
    {
        $container = $this->getContainer();
        $source = $container->createSource($type, $id);
        $collectorManager = $container->getCollectorManager();
        $collectorManager->getPropertySources()->add('source', $source);
        $customer = $collectorManager->collectCustomer();
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $address = $key === 'invoiceAddress' ? $customer->getInvoiceAddress() : $customer->getShippingAddress();
                foreach ($value as $key2 => $value2) {
                    /** @noinspection PhpVariableVariableInspection */
                    $this->assertSame($value2, $address->$key2, $key2);
                }
            } else {
                /** @noinspection PhpVariableVariableInspection */
                $this->assertSame($value, $customer->$key, $key);
            }
        }
    }
}
