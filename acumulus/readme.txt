=== Plugin Name ===
Contributors: SIEL Acumulus.
Tags: WooCommerce, Acumulus, Financial administration
Requires at least: 4.2.3
Tested up to: 4.4
Stable tag: trunk
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl.html

The Acumulus plugin connects your Woocommerce store to the Dutch SIEL Acumulus online
financial administration application.

== Description ==

The Acumulus plugin connects your Woocommerce store to the Dutch SIEL Acumulus online
financial administration application. It can add your invoices automatically or via a
batch send form to your administration.

This plugin assumes that you have installed WooCommerce and that you have an account with
SIEL Acumulus (https://www.siel.nl/acumulus/, https://www.sielsystems.nl/acumulus).
If not, this plugin is useless and will not do anything.

== Installation ==

1. Install the plugin through the WordPress plugins screen directly or, alternatively,
   upload the plugin files to the `/wp-content/plugins/acumulus` directory manually.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the 'Settings - Acumulus' page (`wp-admin/options-general.php?page=acumulus`) to
   configure the plugin.
4. If you have set so, invoices for new orders are now automatically sent to your
   administration at Acumulus.
5. You can use the 'Woocommerce - Acumulus' page (`wp-admin/admin.php?page=acumulus_batch`)
   to send a batch of (older) orders to Acumulus.
6. To cater for specific use cases, the plugin does define some filters and
   actions. See the separate filters.txt for more information.

== Screenshots ==

TODO

== Changelog ==

The Acumulus plugin exists for multiple eCommerce solutions and are all built on
a common library. Therefore the changelog is also shared by all the plugins, see
the separate changelog.txt file.

== Upgrade Notice ==

None yet.
