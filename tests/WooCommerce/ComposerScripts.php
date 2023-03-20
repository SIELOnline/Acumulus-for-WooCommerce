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
 *
 * GitHub projects:
 * - woocommerce/woocommerce
 * - wordpress/wordpress-develop
 */
class ComposerScripts
{
    public static function preAutoloadDump(Event $event): void
    {
        if ($event->isDevMode()) {
            require_once dirname(__FILE__, 2) . '/environment.php';
            // @todo: If not yet existing, make links to (part) of the test
            //   utilities of:
            //   - WooCommerce:
            //     -  link wp-content/plugins/woocommerce to the plugins/woocommerce
            //        sub folder of the woocommerce/woocommerce repo.
            //     -  Copy our feature-config.php to the includes/react-admin folder
            //        of the woocommerce plugin (which indeed is a link in itself).
            //     - Build the assets (or copy from a downloaded install).
            //   - WordPress:
            //     - link tests/phpunit/data of the wordpress/wordpress-develop repo
            //       to wp-content/plugins/acumulus/tests/wordpress-develop.
            //     - link tests/phpunit/includes of the wordpress/wordpress-develop repo
            //       to wp-content/plugins/acumulus/tests/wordpress-develop.
        }
    }
}
