<?php

use Siel\Acumulus\Helpers\ContainerInterface;

class AcumulusSetup {

  /** @var string */
  private $version;

  /** @var array */
  private $messages = array();

  /** @var \Siel\Acumulus\Helpers\ContainerInterface */
  private $container;

  /**
   * AcumulusSetup constructor.
   *
   * @param \Siel\Acumulus\Helpers\ContainerInterface $container
   * @param string $version
   */
  public function __construct(ContainerInterface $container, $version = '') {
    $this->container = $container;
    $this->version = $version;
  }

  /**
   * Activates the plugin.
   *
   * Note that on installing a plugin (copying the files) nothing else happens.
   * Only on activating, a plugin can do its initial work.
   *
   * @return bool
   *   Success.
   */
  public function activate() {
    $result = FALSE;
    // Check user access.
    if (current_user_can('activate_plugins')) {
      $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
      check_admin_referer("activate-plugin_{$plugin}");

      // Check plugin requirements.
      if ($this->checkRequirements()) {
        // Install.
        $model = $this->container->getAcumulusEntryManager();
        $result = $model->install();
        add_option('acumulus_version', $this->version);
      }
    }
    return $result;
  }

  /**
   * Upgrades the plugin.
   *
   * @return bool
   *   Success.
   */
  public function upgrade() {
    $result = TRUE;

    // Only execute if we are really upgrading.
    $dbVersion = get_option('acumulus_version');
    if (!empty($dbVersion) && version_compare($dbVersion, $this->version, '<')) {
      $result = $this->container->getConfig()->upgrade($dbVersion);
      if (version_compare($dbVersion, '4.7.2', '<')) {
        $result = $this->upgrade472() && $result;
      } elseif (version_compare($dbVersion, '5.0.1', '<')) {
        $result = $this->upgrade501() && $result;
      }
      update_option('acumulus_version', $this->version);
    } else if (empty($dbVersion)) {
      // Set it so we can compare it in the future.
      update_option('acumulus_version', $this->version);
    }

    return $result;
  }

  /**
   * Deactivates the plugin.
   *
   * @return bool
   *   Success.
   */
  public function deactivate() {
    if (!current_user_can('activate_plugins')) {
      return FALSE;
    }
    $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
    check_admin_referer("deactivate-plugin_{$plugin}");

    // Deactivate.
    // None so far.
    return TRUE;
  }

  /**
   * Uninstalls the plugin.
   *
   * @return bool
   *   Success.
   */
  public function uninstall() {
    if (!current_user_can('delete_plugins')) {
      return FALSE;
    }

    // Uninstall.
    delete_option('acumulus');

    $model = $this->container->getAcumulusEntryManager();
    return $model->uninstall();
  }

  /**
   * Checks the requirements for this module (CURL, DOMXML, ...).
   *
   * @return bool
   *   Success.
   */
  public function checkRequirements() {
    $requirements = $this->container->getRequirements();
    $this->messages = $requirements->check();

    // Check that WooCommerce is active.
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
      $this->messages[] = "The Acumulus component (version = {$this->version}) requires WooCommerce to be installed and enabled.";
    }

    if (!empty($this->messages)) {
      add_action('admin_notices', array($this, 'adminNotice'));
    }

    return empty($this->messages);
  }

  /**
   * Action hook that adds administrator notices to the admin screen.
   */
  public function adminNotice() {
    $output = '';
    foreach ($this->messages as $message) {
      $output .= $this->renderNotice($message, 'error');
    }
    echo $output;
  }

  /**
   * Renders a notice.
   *
   * @param string $message
   * @param string $type
   *
   * @return string
   *   The rendered notice.
   */
  protected function renderNotice($message, $type) {
    return sprintf('<div class="notice notice-%s is-dismissble"><p>%s</p></div>', $type, $message);
  }

  /**
   * 4.7.2 upgrade.
   *
   * - WC: Strip slashes and remove values that are not one of our keys.
   *
   * @return bool
   */
  protected function upgrade472() {
    $configurationValues = get_option('acumulus');
    // Not sure that this operation can be performed safely on just any value,
    // so I decided to not strip slashes, users can do this manually in the
    // settings forms.
//    $configurationValues = stripslashes_deep($configurationValues);
    $keys = $this->container->getConfig()->getKeys();
    $defaults = $this->container->getConfig()->getDefaults();
    $result = array();
    foreach ($keys as $key) {
      if (isset($configurationValues[$key]) && $configurationValues[$key] != $defaults[$key]) {
        $result[$key] = $configurationValues[$key];
      }
    }
    return update_option('acumulus', $result);
  }

  protected function upgrade501() {
    add_action('admin_notices', array($this, 'removeLibrariesFolder'));
    return TRUE;
  }

  public function removeLibrariesFolder() {
    $dir = strtr(__DIR__ . '/libraries', array('\\' => DIRECTORY_SEPARATOR, '/' => DIRECTORY_SEPARATOR));
    $message = 'Version 5 of the Acumulus plugin renamed its libraries folder to lib. Check if the folder %s still exists and, if so, remove it manually';
    echo $this->renderNotice(sprintf($message, $dir), 'warning');
  }

}
