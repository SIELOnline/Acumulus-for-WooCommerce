<?php
/**
 * @noinspection UntrustedInclusionInspection
 */

declare(strict_types=1);

/**
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
 */

// Get some paths, being aware that our plugin may be symlinked.
$testsRoot = __DIR__;
$pluginRoot = dirname(__DIR__, 2);
$wpRoot = substr($pluginRoot, 0, strpos($pluginRoot, 'wp-content') - 1);
// if out plugin is symlinked, we ty to find $wpRoot by looking at the
// --bootstrap option as passed to phpunit.
global $argv;
if (is_array($argv) && count($argv) >= 3) {
    $i = array_search('--bootstrap', $argv, true);
    // if we found --bootstrap, the value is in the next entry.
    if ($i < count($argv) - 1) {
        $bootstrapFile = $argv[$i + 1];
        $wpRoot = substr($bootstrapFile, 0, strpos($bootstrapFile, 'wp-content') - 1);
    }
}

putenv("WP_TESTS_INSTALLATION=$wpRoot");
define('WP_TESTS_CONFIG_FILE_PATH', "$testsRoot/wp-tests-config.php");
putenv("WP_TESTS_PHPUNIT_POLYFILLS_PATH=$pluginRoot/vendor/yoast/phpunit-polyfills");
putenv("WP_TESTS_DIR=$testsRoot/wordpress/wordpress/tests/phpunit");
putenv('WP_TESTS_SKIP_INSTALL=1');

$tests_dir = getenv('WP_TESTS_DIR');
if (!$tests_dir) {
    $tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv('WP_TESTS_PHPUNIT_POLYFILLS_PATH');
if ($_phpunit_polyfills_path !== false) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path);
}

if (!file_exists("$tests_dir/includes/functions.php")) {
    echo "Could not find $tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL;
    exit(1);
}

// Give access to tests_add_filter() function.
require_once "$tests_dir/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin(): void
{
    require getenv('WP_TESTS_INSTALLATION') . '/wp-content/plugins/acumulus/acumulus.php';
    require getenv('WP_TESTS_INSTALLATION') . '/wp-content/plugins/woocommerce/woocommerce.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

if (getenv('WP_TESTS_SKIP_INSTALL') === '1') {
    echo 'Not reinstalling, running on current install.' . PHP_EOL;
}
// Start up the WP testing environment.
require "$tests_dir/includes/bootstrap.php";
