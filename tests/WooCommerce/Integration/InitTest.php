<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\WooCommerce\Integration;

use Acumulus;
use WP_UnitTestCase;

/**
 * Tests collecting data in WooCommerce
 *
 * Things that should get tested:
 * - Defaults from {@see \Siel\Acumulus\WooCommerce\Config\ShopCapabilities::getDefaultShopMappings()}
 */
class InitTest extends WP_UnitTestCase
{
    /**
     * A single test to see if the plugin is initialized correctly, i.e. we have
     * access to the Container. This mainly implies that autoloading works.
     */
    public function testInit(): void
    {
        $container = Acumulus::create()->getAcumulusContainer();
        $environmentInfo = $container->getEnvironment()->get();
        $this->assertMatchesRegularExpression('|\d+\.\d+\.\d+|', $environmentInfo['shopVersion']);
    }
}
