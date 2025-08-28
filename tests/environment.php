<?php
/**
 * @noinspection UntrustedInclusionInspection
 */

declare(strict_types=1);

/**
 * File that sets environment variables for our PHPUnit tests.
 * - WP_TESTS_INSTALLATION={path to WP installation};
 *   (We can use different WP core installs, e.g. to test different versions,
 *   for now we stick to the version our plugin is installed into).
 * - WP_TESTS_CONFIG_FILE_PATH={path and name to wp-tests-config.php}
 *   (includes/bootstrap.php expects this as a constant).
 * - WP_TESTS_DIR={path to the data and includes folders from the WordPress test framework}
 * - WP_TESTS_SKIP_INSTALL=1; {1 = skip installation, 0 = reinstall tables}
 */

// Get some paths, unaware that our plugin may be symlinked.
$testsRoot = __DIR__;
$pluginRoot = dirname(__DIR__, 2);
$wpRoot = substr($pluginRoot, 0, strpos($pluginRoot, 'wp-content') - 1);
putenv("WP_TESTS_INSTALLATION=$wpRoot");
putenv("WP_TESTS_CONFIG_FILE_PATH=$testsRoot/wp-tests-config.php");
putenv("WP_TESTS_PHPUNIT_POLYFILLS_PATH=$pluginRoot/vendor/yoast/phpunit-polyfills");
putenv("WP_TESTS_DIR=$testsRoot/wordpress-develop/tests/phpunit");
unset($testsRoot, $pluginRoot, $wpRoot);

putenv('WORDPRESS_WORDPRESS_DEVELOP_REPO=C:/Projecten/WordPress/develop');
