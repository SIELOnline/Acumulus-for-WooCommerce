<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\WooCommerce;

use Acumulus;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Tests\AcumulusTestUtils as BaseAcumulusTestUtils;
use wpdb;

use function define;
use function dirname;

/**
 * AcumulusTestUtils contains WC specific test functionalities
 */
trait AcumulusTestUtils
{
    use BaseAcumulusTestUtils {
        copyLatestTestSources as protected parentCopyLatestTestSources;
    }

    private string $old;

    protected static function createContainer(): Container
    {
        return Acumulus::create()->getAcumulusContainer();
    }

    protected function getTestsPath(): string
    {
        return dirname(__FILE__, 2);
    }

    /**
     * We want to test using a given set of customers, products and orders.
     * As the WP utils will clear all posts, all products and orders will be gone after
     * each test. However, WordPress allows changing the prefix during the execution of a
     * request, and we use that to switch to tables containing our known set of testdata
     * (just before each test and just before loading a plugin).
     */
    public function changePrefix(): void
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $this->old = $wpdb->set_prefix('wp_');
    }

    /**
     * Resets the table prefix changed in {@see changePrefix()}, so that the WP test
     * utilities will clear the tables that do not contain our realistic test data.
     */
    public function resetPrefix(): void
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $wpdb->set_prefix($this->old);
    }

    /**
     * @noinspection UntrustedInclusionInspection
     */
    public function copyLatestTestSources(): void
    {
        static $hasRun = false;

        if (!$hasRun) {
            $hasRun = true;
            define('ABSPATH', dirname(__FILE__, 2) . '/');
            global $argv;
            $wpRoot = substr($argv[0], 0, strpos($argv[0], 'wp-content') - 1);
            require_once "$wpRoot/wp-admin/includes/noop.php";
            require_once "$wpRoot/wp-content/plugins/acumulus/acumulus.php";
        }
        $this->parentCopyLatestTestSources();
    }
}
