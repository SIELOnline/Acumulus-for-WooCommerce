@echo off
rem Copy files in our folder structure to development installation.
rmdir /s /q D:\Projecten\Acumulus\WooCommerce\www\wp-content\plugins\acumulus
mklink /J D:\Projecten\Acumulus\WooCommerce\www\wp-content\plugins\acumulus D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus
