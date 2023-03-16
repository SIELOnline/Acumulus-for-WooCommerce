<?php
/**
 * @noinspection PhpUnused  Called by composer based on composer.json settings.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\WooCommerce;

use Composer\Script\Event;

use function dirname;

/**
 * ComposerScripts installs dependencies not managed with composer.
 *
 * We should install the parts of the test frameworks from WordPress and
 * WooCommerce that these tests depend upon.
 */
class ComposerScripts
{
    public static function preAutoloadDump(Event $event): void
    {
        if ($event->isDevMode()) {
            require_once dirname(__FILE__, 2) . '/environment.php';
            // @todo: If not yet existing, mMake links to WordPress and
            //   WooCommerce frameworks,  .../plugins/acumulus/tests/frameworks:
            //   - .../wordpress/test/phpunit/data
            //   - .../wordpress/test/phpunit/includes
            //   - .../woocommerce/test/legacy/framework
            //   - .../woocommerce/test/legacy/includes
        }
    }
}
