<?php
/**
 * @noinspection AutoloadingIssuesInspection
 */

declare(strict_types=1);

use Siel\Acumulus\Tests\WooCommerce\Util;

/**
 * Class AcumulusTestsBootstrap Bootstraps the Acumulus tests.
 *
 * This class works on top of ./wordpress-develop/tests/phpunit/includes/bootstrap.php,
 * which it includes as last action.
 */
class AcumulusTestsBootstrap
{
    protected static AcumulusTestsBootstrap $instance;

    public $wp_tests_dir;
    public string $plugins_dir;
    public string $languages_dir;

    /**
     * Setup the unit testing environment.
     *
     * @noinspection SpellCheckingInspection
     */
    public function __construct()
    {
        // WordPress framework path.
        $this->wp_tests_dir = getenv('WP_TESTS_DIR');
        // The WordPress installation path.
        $wp_tests_installation = getenv('WP_TESTS_INSTALLATION');
        // Plugins installation path.
        $this->plugins_dir = $wp_tests_installation . '/wp-content/plugins';
        $this->languages_dir = $wp_tests_installation . '/wp-content/languages';

        ini_set('display_errors', 'on');
        error_reporting(E_ALL);

        // Ensure theme install tests use direct filesystem method.
        define('FS_METHOD', 'direct');

        // Ensure server variable is set for WP email functions.
        if (!isset($_SERVER['SERVER_NAME'])) {
            $_SERVER['SERVER_NAME'] = 'localhost';
        }

        // Load test function so tests_add_filter() is available.
        require_once $this->wp_tests_dir . '/includes/functions.php';

        // Ensure that translations from our own installation are loaded.
        tests_add_filter('load_textdomain_mofile', [$this, 'load_text_domain_mo_file'], 10, 2);

        // Always load PayPal Standard for unit tests.
        tests_add_filter('woocommerce_should_load_paypal_standard', '__return_true');

        // Load WooCommerce and Acumulus.
        tests_add_filter('muplugins_loaded', [$this, 'load_wc']);
        tests_add_filter('muplugins_loaded', [$this, 'load_acumulus']);

        /*
         * Load PHPUnit Polyfills for the WP testing suite.
         * @see https://github.com/WordPress/wordpress-develop/pull/1563/
         */
        define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', getenv('WP_TESTS_PHPUNIT_POLYFILLS_PATH'));

        // load the WP testing environment.
        if (getenv('WP_TESTS_SKIP_INSTALL') === '1') {
            // WP bootstrap does not mention this state.
            echo 'Not reinstalling, running on current install.' . PHP_EOL;
        }
        require_once $this->wp_tests_dir . '/includes/bootstrap.php';
    }

    /**
     * Load Acumulus
     */
    public function load_acumulus(): void
    {
        Util::changePrefix();
        require_once $this->plugins_dir . '/acumulus/acumulus.php';
        Util::resetPrefix();
    }

    /**
     * Load WooCommerce.
     */
    public function load_wc(): void
    {
        Util::changePrefix();
        define('WC_TAX_ROUNDING_MODE', 'auto');
        define('WC_USE_TRANSACTIONS', false);
        update_option('woocommerce_enable_coupons', 'yes');
        update_option('woocommerce_calc_taxes', 'yes');
        update_option('woocommerce_onboarding_opt_in', 'yes');

        require_once $this->plugins_dir . '/woocommerce/woocommerce.php';
        Util::resetPrefix();
    }

    /**
     * Returns the single class instance, creating one if not yet existing.
     */
    public static function instance(): AcumulusTestsBootstrap
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function load_text_domain_mo_file(string $moFile, string $domain): string
    {
        if (strpos($moFile, WP_LANG_DIR) === 0 && !is_readable($moFile)) {
            $moFile = $this->languages_dir . '/plugins/' . basename($moFile);
        }
        return $moFile;
    }
}

AcumulusTestsBootstrap::instance();
