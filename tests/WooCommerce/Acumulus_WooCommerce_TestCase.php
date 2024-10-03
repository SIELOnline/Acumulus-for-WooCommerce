<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection class TestCase has indeed several
 *   polyfill versions.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\WooCommerce;

use Acumulus;
use Siel\Acumulus\Helpers\Container;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Acumulus_WooCommerce_TestCase is the base class for Acumulus WooCommerce tests.
 */
class Acumulus_WooCommerce_TestCase extends TestCase
{
    use AcumulusTestUtils;

    protected static function getAcumulusContainer(): Container
    {
        return Acumulus::create()->getAcumulusContainer();
    }

    /**
     * This override prevents that its parent (at
     * {@see WP_UnitTestCase_Base::tear_down_after_class()}) gets called, thereby assuring
     * that {@see \_delete_all_data()} does not get called and our test posts remain
     * stored in the database.
     */
    public static function tear_down_after_class(): void
    {
        if (method_exists(static::class, 'wpTearDownAfterClass')) {
            /** @noinspection PhpUndefinedMethodInspection  Well, we check for its existence first */
            static::wpTearDownAfterClass();
        }
        static::flush_cache();
        static::commit_transaction();
    }
}
