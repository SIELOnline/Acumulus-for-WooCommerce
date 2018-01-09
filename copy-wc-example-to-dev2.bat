@echo off
rem Copy files in our folder structure to development installation.
rmdir /s /q D:\Projecten\Acumulus\WooCommerce\www-wc2\wp-content\plugins\acumulus-customise-invoice
mklink /J D:\Projecten\Acumulus\WooCommerce\www-wc2\wp-content\plugins\acumulus-customise-invoice D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus-customise-invoice
