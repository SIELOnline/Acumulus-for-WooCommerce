@echo off
rem del /s /q /f D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\svn\trunk\* | findstr /i "Unable Fail Error"
xcopy /e /q /y D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\svn\trunk
xcopy /e /q /y D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\acumulus\libraries\Siel\* D:\Projecten\Acumulus\Webkoppelingen\WooCommerce\svn\trunk\libraries\Siel
