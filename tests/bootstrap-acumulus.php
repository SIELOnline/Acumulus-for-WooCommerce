<?php
/**
 * @noinspection GrazieInspection
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection PhpUnhandledExceptionInspection
 * @noinspection DuplicatedCode
 * @noinspection AutoloadingIssuesInspection
 */

declare(strict_types=1);

use Automattic\WooCommerce\Proxies\LegacyProxy;
use Automattic\WooCommerce\Internal\Admin\FeaturePlugin;
use Automattic\WooCommerce\Testing\Tools\CodeHacking\CodeHacker;
use Automattic\WooCommerce\Testing\Tools\CodeHacking\Hacks\StaticMockerHack;
use Automattic\WooCommerce\Testing\Tools\CodeHacking\Hacks\FunctionsMockerHack;
use Automattic\WooCommerce\Testing\Tools\CodeHacking\Hacks\BypassFinalsHack;
use Automattic\WooCommerce\Testing\Tools\DependencyManagement\MockableLegacyProxy;

/**
 * Class AcumulusTestsBootstrap
 *
 * Bootstrap Acumulus tests, based on WC_Unit_Tests_Bootstrap
 */
class AcumulusTestsBootstrap
{

    protected static AcumulusTestsBootstrap $instance;

    public $wp_tests_dir;
    public string $wc_legacy_dir;
    public string $wc_plugin_dir;

    /**
     * Setup the unit testing environment.
     */
    public function __construct()
    {
        // WordPress framework path.
        $this->wp_tests_dir = getenv('WP_TESTS_DIR');
        // WordPress installation path.
        $wp_tests_installation = getenv('WP_TESTS_INSTALLATION');
        // WooCommerce installation path.
        $this->wc_plugin_dir = $wp_tests_installation . '/wp-content/plugins/woocommerce';
        $this->wc_legacy_dir = $this->wc_plugin_dir . '/tests/legacy';
        $this->wc_tools_dir = $this->wc_plugin_dir . '/tests/Tools';

        $this->register_autoloader_for_testing_tools();

        $this->initialize_code_hacker();

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

        // Always load PayPal Standard for unit tests.
        tests_add_filter('woocommerce_should_load_paypal_standard', '__return_true');

        // Load WooCommerce and Acumulus.
        tests_add_filter('muplugins_loaded', [$this, 'load_wc']);
        tests_add_filter('muplugins_loaded', [$this, 'load_acumulus']);

        // Install WooCommerce.
        tests_add_filter('setup_theme', [$this, 'install_wc']);

        // Set up WC-Admin config.
        tests_add_filter('woocommerce_admin_get_feature_config', [$this, 'add_development_features']);

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

        // load WooCommerce testing framework.
        $this->includes();

        // Re-initialize dependency injection, this needs to be the last operation after everything else is in place.
        $this->initialize_dependency_injection();
    }

    /**
     * Register autoloader for the files in the 'tests/tools' directory, for the root namespace 'Automattic\WooCommerce\Testing\Tools'.
     */
    protected function register_autoloader_for_testing_tools(): bool
    {
        return spl_autoload_register(
            function ($class) {
                $prefix = 'Automattic\\WooCommerce\\Testing\\Tools\\';
                $base_dir = $this->wc_tools_dir . '/';
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    // no, move to the next registered autoloader.
                    return;
                }
                $relative_class = substr($class, $len);
                $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
                if (!file_exists($file)) {
                    throw new RuntimeException('Autoloader for unit tests: file not found: ' . $file);
                }
                require $file;
            }
        );
    }

    /**
     * Initialize the code hacker.
     *
     * @throws Exception Error when initializing one of the hacks.
     *
     * @noinspection UsingInclusionReturnValueInspection
     */
    private function initialize_code_hacker(): void
    {
        CodeHacker::initialize([$this->wc_plugin_dir . '/includes/']);

        $replaceable_functions = include $this->wc_legacy_dir . '/mockable-functions.php';
        if (!empty($replaceable_functions)) {
            FunctionsMockerHack::initialize($replaceable_functions);
            CodeHacker::add_hack(FunctionsMockerHack::get_hack_instance());
        }

        $mockable_static_classes = include$this->wc_legacy_dir . '/classes-with-mockable-static-methods.php';
        if (!empty($mockable_static_classes)) {
            StaticMockerHack::initialize($mockable_static_classes);
            CodeHacker::add_hack(StaticMockerHack::get_hack_instance());
        }

        CodeHacker::add_hack(new BypassFinalsHack());
        CodeHacker::enable();
    }

    /**
     * Re-initialize the dependency injection engine.
     *
     * The dependency injection engine has been already initialized as part of the Woo initialization, but we need
     * to replace the registered read-only container with a fully configurable one for testing.
     * To this end we hack a bit and use reflection to grab the underlying container that the read-only one stores
     * in a private property.
     *
     * Additionally, we replace the legacy/function proxies with mockable versions to easily replace anything
     * in tests as appropriate.
     *
     * @throws \Exception The Container class doesn't have a 'container' property.
     */
    private function initialize_dependency_injection(): void
    {
        try {
            $inner_container_property = new ReflectionProperty(\Automattic\WooCommerce\Container::class, 'container');
        } /** @noinspection PhpRedundantCatchClauseInspection */
        catch (ReflectionException $ex) {
            throw new RuntimeException(
                "Error when trying to get the private 'container' property from the " . \Automattic\WooCommerce\Container::class
                . ' class using reflection during unit testing bootstrap, has the property been removed or renamed?'
            );
        }

        $inner_container_property->setAccessible(true);
        $inner_container = $inner_container_property->getValue(wc_get_container());

        $inner_container->replace(LegacyProxy::class, MockableLegacyProxy::class);

        $GLOBALS['wc_container'] = $inner_container;
    }

    /**
     * Load Acumulus
     */
    public function load_acumulus(): void
    {
        require_once getenv('WP_TESTS_INSTALLATION') . '/wp-content/plugins/acumulus/acumulus.php';
    }

    /**
     * Load WooCommerce.
     */
    public function load_wc(): void
    {
        define('WC_TAX_ROUNDING_MODE', 'auto');
        define('WC_USE_TRANSACTIONS', false);
        update_option('woocommerce_enable_coupons', 'yes');
        update_option('woocommerce_calc_taxes', 'yes');
        update_option('woocommerce_onboarding_opt_in', 'yes');

        require_once $this->wc_plugin_dir . '/woocommerce.php';
        FeaturePlugin::instance()->init();
    }

    /**
     * Install WooCommerce after the test environment and WC have been loaded.
     *
     * @since 2.2
     */
    public function install_wc(): void
    {
        // Clean existing install first.
        define('WP_UNINSTALL_PLUGIN', true);
        define('WC_REMOVE_ALL_DATA', true);
        include $this->wc_plugin_dir . '/uninstall.php';

        WC_Install::install();

        // Reload capabilities after install, see https://core.trac.wordpress.org/ticket/28374.
        if (version_compare($GLOBALS['wp_version'], '4.7', '<')) {
            $GLOBALS['wp_roles']->reinit();
        } else {
            $GLOBALS['wp_roles'] = null;
            wp_roles();
        }

        echo esc_html('Installing WooCommerce...' . PHP_EOL);
    }

    /**
     * Load WC-specific test cases and factories.
     */
    public function includes(): void
    {
        // framework.
        require_once $this->wc_legacy_dir . '/framework/class-wc-unit-test-factory.php';
        require_once $this->wc_legacy_dir . '/framework/class-wc-mock-session-handler.php';
        require_once $this->wc_legacy_dir . '/framework/class-wc-mock-wc-data.php';
        require_once $this->wc_legacy_dir . '/framework/class-wc-mock-wc-object-query.php';
        require_once $this->wc_legacy_dir . '/framework/class-wc-mock-payment-gateway.php';
        require_once $this->wc_legacy_dir . '/framework/class-wc-mock-enhanced-payment-gateway.php';
        require_once $this->wc_legacy_dir . '/framework/class-wc-payment-token-stub.php';
        require_once $this->wc_legacy_dir . '/framework/vendor/class-wp-test-spy-rest-server.php';

        // test cases.
        require_once $this->wc_legacy_dir . '/includes/wp-http-testcase.php';
        require_once $this->wc_legacy_dir . '/framework/class-wc-unit-test-case.php';
        require_once $this->wc_legacy_dir . '/framework/class-wc-api-unit-test-case.php';
        require_once $this->wc_legacy_dir . '/framework/class-wc-rest-unit-test-case.php';

        // Helpers.
        require_once $this->wc_legacy_dir . '/framework/helpers/class-wc-helper-product.php';
        require_once $this->wc_legacy_dir . '/framework/helpers/class-wc-helper-coupon.php';
        require_once $this->wc_legacy_dir . '/framework/helpers/class-wc-helper-fee.php';
        require_once $this->wc_legacy_dir . '/framework/helpers/class-wc-helper-shipping.php';
        require_once $this->wc_legacy_dir . '/framework/helpers/class-wc-helper-customer.php';
        require_once $this->wc_legacy_dir . '/framework/helpers/class-wc-helper-order.php';
        require_once $this->wc_legacy_dir . '/framework/helpers/class-wc-helper-shipping-zones.php';
        require_once $this->wc_legacy_dir . '/framework/helpers/class-wc-helper-payment-token.php';
        require_once $this->wc_legacy_dir . '/framework/helpers/class-wc-helper-settings.php';
        require_once $this->wc_legacy_dir . '/framework/helpers/class-wc-helper-reports.php';
        require_once $this->wc_legacy_dir . '/framework/helpers/class-wc-helper-admin-notes.php';
        require_once $this->wc_legacy_dir . '/framework/helpers/class-wc-test-action-queue.php';
        require_once $this->wc_legacy_dir . '/framework/helpers/class-wc-helper-queue.php';

        // Traits.
        require_once $this->wc_legacy_dir . '/framework/traits/trait-wc-rest-api-complex-meta.php';
    }

    /**
     * Use the `development` features for testing.
     *
     * @param array $flags Existing feature flags.
     *
     * @return array Filtered feature flags.
     *
     * @throws JsonException
     */
    public function add_development_features(array $flags): array
    {
        $config = json_decode(
            file_get_contents($this->wc_plugin_dir . '/client/admin/config/development.json'),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
        foreach ($config->features as $feature => $bool) {
            $flags[$feature] = $bool;
        }
        return $flags;
    }

    /**
     * Get the single class instance.
     */
    public static function instance(): AcumulusTestsBootstrap
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

AcumulusTestsBootstrap::instance();
