<?php
/**
 * @noinspection UntrustedInclusionInspection
 */

declare(strict_types=1);

/**
 * @noinspection GrazieInspection
 *
 * Bootstrap file for our PHPUnit tests.
 * - define some locations needed by the WP test framework.
 * - include includes/functions.php
 * - load plugins
 * - bootstrap WP test framework
 *
 * Note, to run the tests successfully, we need:
 *
 * 1 The dependencies as listed in composer.json:
 *   composer update --with-all-dependencies
 *
 * 2 The folders wordpress/wordpress/tests/phpunit/includes and
 *   wordpress/wordpress/tests/phpunit/data from the GitHub project
 *   https://github.com/wordpress/wordpress-develop, though we can also download
 *   just these 2 folders using svn:
 *   - svn export --quiet --ignore-externals https://develop.svn.wordpress.org/%WP_TESTS_TAG%/tests/phpunit/data/ ^
 *     wordpress/wordpress/tests/phpunit/data/data
 *   - svn export --quiet --ignore-externals https://develop.svn.wordpress.org/%WP_TESTS_TAG%/tests/phpunit/includes/ ^
 *     wordpress/wordpress/tests/phpunit/data/includes
 *   or use download-directory.github.io:
 *   - https://download-directory.github.io/?url=https://github.com/WordPress/wordpress-develop/tree/trunk/tests/phpunit/data
 *   - https://download-directory.github.io/?url=https://github.com/WordPress/wordpress-develop/tree/trunk/tests/phpunit/includes
 *   and extract the downloaded zips.
 *
 * 3 The following environment variables or constants:
 *   We define them in this script, instead of relying on, e.g, the phpunit
 *   startup settings we can define in PHPStorm.
 *   - WP_TESTS_INSTALLATION={path to WP installation};
 *     (We can use different WP core installs, e.g. to test different versions,
 *     for now we stick to the version our plugin is installed into).
 *   - WP_TESTS_CONFIG_FILE_PATH={path and name to wp-tests-config.php}
 *     (includes/bootstrap.php expects this as a constant).
 *   - WP_TESTS_DIR={path to the data and includes folders from above}
 *   - WP_TESTS_SKIP_INSTALL=1; {1 = skip install, 0 = reinstall tables}
 *   - WP_TESTS_PHPUNIT_POLYFILLS_PATH={path to yoast/phpunit-polyfills project}
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/environment.php';

// if our plugin is symlinked, we need to redefine WP_TESTS_INSTALLATION. Try to
// find it by looking at the --bootstrap option as passed to phpunit.
global $argv;
if (is_array($argv)) {
    $i = array_search('--bootstrap', $argv, true);
    // if we found --bootstrap, the value is in the next entry.
    if ($i < count($argv) - 1) {
        $bootstrapFile = $argv[$i + 1];
        $wpRoot = substr($bootstrapFile, 0, strpos($bootstrapFile, 'wp-content') - 1);
        putenv("WP_TESTS_INSTALLATION=$wpRoot");
    }
}

define('WP_TESTS_CONFIG_FILE_PATH', getenv('WP_TESTS_CONFIG_FILE_PATH'));
putenv('WP_TESTS_SKIP_INSTALL=1');

// Start up the WP, WC, and Acumulus testing environment.
require __DIR__ . '/bootstrap-acumulus.php';
