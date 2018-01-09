@echo off
rem Link Common library to here.
mkdir D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus\lib\siel
rmdir /s /q D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus\lib\siel\acumulus 2> nul
mklink /J D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus\lib\siel\acumulus D:\Projecten\Acumulus\Webkoppelingen\libAcumulus

rem Link license files to here.
del D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus\license.txt 2> nul
mklink /H D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus\license.txt D:\Projecten\Acumulus\Webkoppelingen\libAcumulus\license.txt
del D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus\licentie-nl.pdf 2> nul
mklink /H D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus\licentie-nl.pdf D:\Projecten\Acumulus\Webkoppelingen\libAcumulus\licentie-nl.pdf
del D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus\leesmij.txt 2> nul
mklink /H D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus\leesmij.txt D:\Projecten\Acumulus\Webkoppelingen\leesmij.txt
