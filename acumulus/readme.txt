=== Plugin Name ===
Contributors: SIEL Acumulus.
Tags: WooCommerce, Acumulus, financial, bookkeeping, accounting, administratie, bank, boekhouden, boekhoudpakket, boekhoudprogramma, e-commerce, free, gratis, koppeling, online boekhouden
Requires at least: 4.2.3
Tested up to: 4.7
Stable tag: trunk
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl.html

The Acumulus plugin connects your Woocommerce store to the Dutch [SIEL Acumulus online financial administration application](https://www.siel.nl/acumulus/).

== Description ==

The Acumulus plugin connects your Woocommerce store to the Dutch SIEL Acumulus online financial administration application. It can add your invoices automatically or via a batch send form to your administration, saving you a lot of manual, error prone work.

The Acumulus plugin:

* Reacts to order status changes via actions.
* Does have 3 admin screens: a settings, advanced settings, and a batch send screen.
* Does not in any way interfere with the front-end UI.

The Acumulus plugin assumes that:

* You have installed [WooCommerce](https://wordpress.org/plugins/woocommerce/).
* You have an account with [SIEL Acumulus](https://www.siel.nl/acumulus/), also see [Overview of webshop connections](https://www.siel.nl/acumulus/koppelingen/webwinkels/WooCommerce/).

If not, this plugin is useless and will not do anything.

== Installation ==

1. Install the plugin through the WordPress plugins screen directly or, alternatively, upload the plugin files to the `/wp-content/plugins/acumulus` directory manually.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the 'Settings - Acumulus' page (`wp-admin/options-general.php?page=acumulus_config`) to configure the plugin.
4. Go to the 'Settings - Acumulus advanced settings' page (`wp-admin/options-general.php?page=acumulus_advanced`) to configure the plugin.
5. If you have set so, invoices for new orders are now automatically sent to your administration at Acumulus.
6. You can use the 'Woocommerce - Acumulus' page (`wp-admin/admin.php?page=acumulus_batch`) to send a batch of (older) orders to Acumulus.
7. To cater for specific use cases, the plugin does define some filters and actions, so you can intercept and influence the actions it performs. See the separate [filters.txt](http://plugins.svn.wordpress.org/acumulus/trunk/filters.txt) for more information.

== Screenshots ==

1. Settings form (1 of 2)
2. Settings form (2 of 2)
3. Batch form

== Changelog ==
The Acumulus plugin exists for multiple eCommerce solutions and are all built on a common library. Therefore the changelog is also shared by all the plugins, see the separate [changelog.txt](http://plugins.svn.wordpress.org/acumulus/trunk/changelog.txt) file.

== Support ==
See the [Acumulus forum](https://forum.acumulus.nl/index.php?board=17.0).

== Upgrade Notice ==
With each new version you should visit the settings (and advanced settings) page to see if there are new settings that apply to your situation.
