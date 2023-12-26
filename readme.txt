=== Acumulus ===
Contributors: SIEL Acumulus.
Tags: Acumulus, administratie, boekhouding, boekhoudpakket, boekhoudsoftware
Requires at least: 5.9
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: trunk
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl.html

The Acumulus plugin connects your Woocommerce store to the Dutch [SIEL Acumulus online financial administration application](https://www.siel.nl/acumulus/).

== Description ==

Over deze plugin.

== WooCommerce boekhouden plugin voor Acumulus ==

The Acumulus plugin connects your Woocommerce store to the Dutch SIEL Acumulus online financial administration application. It can add your invoices automatically or via a batch send form to your administration, saving you a lot of manual, error-prone work.

The Acumulus plugin:

* Reacts to order status changes via actions.
* Does have 4 admin screens: a register, settings, advanced settings, and a batch send screen.
* Offers a meta box with an overview of the status of the invoice in Acumulus on the edit order screen.
* Does not in any way interfere with the front-end UI.

The Acumulus plugin assumes that:

* You have installed [WooCommerce](https://wordpress.org/plugins/woocommerce/).
* You have an account with [SIEL Acumulus](https://www.siel.nl/acumulus/), also see [Overview of webshop connections](https://www.siel.nl/acumulus/koppelingen/webwinkels/WooCommerce/).

If not, this plugin is useless and will not do anything.

== Installation ==

1. Install the plugin through the WordPress plugins screen directly or, alternatively, upload the plugin files to the `/wp-content/plugins/acumulus` directory manually.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the 'Settings - Acumulus' page (`wp-admin/options-general.php?page=acumulus_config`) to configure the plugin.
4. If you do not have yet an account with Acumulus, you can apply for one by clicking on the register now button.
5. Complete the (basic) configuration of the plugin.
6. Go to the 'Settings - Acumulus advanced settings' page (`wp-admin/options-general.php?page=acumulus_advanced`) to configure the plugin.
7. If you have set so, invoices for new orders are now automatically sent to your administration at Acumulus.
8. You can use the 'Woocommerce - Acumulus' page (`wp-admin/admin.php?page=acumulus_batch`) to send a batch of (older) orders to Acumulus.
9. To cater for specific use cases, the plugin does define some filters and actions, so you can intercept and influence the actions it performs. See the separate [filters.txt](http://plugins.svn.wordpress.org/acumulus/trunk/filters.txt) for more information.

== Installation using composer and GitHub ==

Note: this is only recommended for developers, not if you only want to use this plugin in your webshop.

1. Open a cmd prompt and go to the plugins folder: cd wp-content/plugins
3. Download the zip from https://github.com/SIELOnline/Acumulus-for-WooCommerce, either:
   - The latest version: https://github.com/SIELOnline/Acumulus-for-WooCommerce/archive/refs/heads/master.zip
   - A specific version, e.g: https://github.com/SIELOnline/Acumulus-for-WooCommerce/archive/refs/tags/7.6.5.zip
4. Extract the zip and rename the folder Acumulus-for-WooCommerce-[master|7.6.5] to acumulus
5. cd acumulus
6. composer update --no-dev

Note this won't install the test classes from WooCommerce and WordPress that are required by our own tests.
Installing the test environment for this plugin is yet to be described here.

== Screenshots ==

1. Settings form
2. Advanced settings form
3. Batch form
4. Acumulus invoice status overview

== Changelog ==
The Acumulus plugin exists for multiple eCommerce solutions and are all built on a common library. Most changes take place in that common library, therefore there's only 1 changelog that is part of the library, see [changelog.txt](https://plugins.svn.wordpress.org/acumulus/trunk/vendor/siel/acumulus/changelog.txt).

== Support ==
See the [Acumulus forum](https://forum.acumulus.nl/index.php?board=17.0).

== Upgrade Notice ==
With each new version you should visit the settings (and advanced settings) page to see if there are new settings that apply to your situation.
