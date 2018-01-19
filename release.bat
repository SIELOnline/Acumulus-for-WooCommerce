@echo off
rem Check usage and arguments.
if dummy==dummy%1 (
echo Usage: %~n0 version
exit /B 1;
)
set version=%1
set repository=svn

@echo on
call copy-wc-svn
cd %repository%
cd trunk
svn add --force . --auto-props --parents --depth infinity
cd ..
svn cp trunk tags/%version%
svn ci --username "SIEL Acumulus" --password yddez(9tCZYT -m "%version%"
cd ..
