<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\WooCommerce;

use Acumulus;
use Siel\Acumulus\Helpers\Container;
use WP_UnitTestCase;
use wpdb;

/**
 * Acumulus_WooCommerce_TestCase does foo.
 */
class Acumulus_WooCommerce_TestCase extends WP_UnitTestCase
{
    /**
     * @before  We want to test on a given set of customers, products and
     *   orders. As the WP utils will clear all posts, all posts (products and
     *   orders) will be gone. However, WP allows to change the prefix during
     *   the execution of a request, and we use that to switch to tables
     *   containing this known set of testdata.
     */
    public function beforeChangePrefix(): void
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $wpdb->set_prefix('wp_');
    }

    /**
     * @after  {@see beforeChangePrefix}
     */
    public function afterResetPrefix(): void
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $wpdb->set_prefix('wptests_');
    }

    /**
     * Returns an Acumulus Container instance.
     */
    public function getAcumulusContainer(): Container
    {
        return Acumulus::create()->getAcumulusContainer();
    }
}
