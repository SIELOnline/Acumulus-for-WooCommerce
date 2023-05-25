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
     * @before  See {@see \Siel\Acumulus\Tests\WooCommerce\Util::changePrefix()}.
     */
    public function beforeChangePrefix(): void
    {
        Util::changePrefix();
    }

    /**
     * @after  See {@see \Siel\Acumulus\Tests\WooCommerce\Util::resetPrefix()}
     */
    public function afterResetPrefix(): void
    {
        Util::resetPrefix();
    }

    /**
     * Returns an Acumulus Container instance.
     */
    public function getAcumulusContainer(): Container
    {
        return Acumulus::create()->getAcumulusContainer();
    }
}