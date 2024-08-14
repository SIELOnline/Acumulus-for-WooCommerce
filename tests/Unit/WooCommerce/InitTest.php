<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\WooCommerce;

use Acumulus;
use Siel\Acumulus\Tests\WooCommerce\Acumulus_WooCommerce_TestCase;

/**
 * Tests that WooCommerce and Acumulus have been initialized.
 */
class InitTest extends Acumulus_WooCommerce_TestCase
{
    /**
     * A single test to see if the test framework (including the plugins) has been
     * initialized correctly:
     * 1 We have access to the Container.
     * 2 WooCommerce has been initialized.
     */
    public function testInit(): void
    {
        // 1.
        $container = Acumulus::create()->getAcumulusContainer();
        $environmentInfo = $container->getEnvironment()->get();
        // 2.
        $this->assertMatchesRegularExpression('|\d+\.\d+\.\d+|', $environmentInfo['shopVersion']);
    }
}
