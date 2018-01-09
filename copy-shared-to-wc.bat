@echo off
rem Link Common library to here.
mkdir D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus\lib\siel
mklink /J D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus\lib\siel\acumulus D:\Projecten\Acumulus\Webkoppelingen\libAcumulus

rem Link license files to here.
mklink /H D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus\license.txt D:\Projecten\Acumulus\Webkoppelingen\libAcumulus\license.txt
mklink /H D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus\licentie-nl.pdf D:\Projecten\Acumulus\Webkoppelingen\libAcumulus\licentie-nl.pdf
mklink /H D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus\leesmij.txt D:\Projecten\Acumulus\Webkoppelingen\leesmij.txt
