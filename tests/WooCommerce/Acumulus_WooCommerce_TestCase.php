<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\WooCommerce;

use Acumulus;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Tests\AcumulusTestUtils;
use WP_UnitTestCase;

/**
 * Acumulus_WooCommerce_TestCase does foo.
 */
class Acumulus_WooCommerce_TestCase extends WP_UnitTestCase
{
    use AcumulusTestUtils;

    protected static function getAcumulusContainer(): Container
    {
        return Acumulus::create()->getAcumulusContainer();
    }

    /**
     * @before  See {@see \Siel\Acumulus\Tests\WooCommerce\Util::changePrefix()}.
     */
    public function beforeChangePrefix(): void
    {
        Util::changePrefix();
    }

    /**
     * @after  See {@see \Siel\Acumulus\Tests\WooCommerce\Util::resetPrefix()}.
     */
    public function afterResetPrefix(): void
    {
        Util::resetPrefix();
    }
}
