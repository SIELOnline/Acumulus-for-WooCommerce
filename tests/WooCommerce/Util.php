<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\WooCommerce;

use wpdb;

/**
 * Utils contains test utility functions.
 */
class Util
{
    private static string $old;

    /**
     * We want to test on a given set of customers, products and orders. As the WP utils
     * will clear all posts, all products and orders will be gone after each test.
     * However, WordPress allows to change the prefix during the execution of a request,
     * and we use that to switch to tables containing our known set of testdata (just
     * before each test and just before loading a plugin).
     */
    public static function changePrefix(): void
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        self::$old = $wpdb->set_prefix('wp_');
    }

    /**
     * Resets the table prefix changed in {@see changePrefix()}, so that the WP test
     * utilities will clear the tables that do not contain our realistic test data.
     */
    public static function resetPrefix(): void
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $wpdb->set_prefix(self::$old);
    }
}
