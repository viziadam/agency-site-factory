@echo off
setlocal
set "PHP_EXE="
for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"
if not defined PHP_EXE (
	for /f "delims=" %%D in ('dir /b /ad /o-n "%APPDATA%\Local\lightning-services\php-*" 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%APPDATA%\Local\lightning-services\%%D\bin\win64\php.exe"
)
if not defined PHP_EXE (
	for /f "delims=" %%D in ('dir /b /ad /o-n "%LOCALAPPDATA%\Programs\Local\resources\extraResources\lightning-services\php-*" 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%LOCALAPPDATA%\Programs\Local\resources\extraResources\lightning-services\%%D\bin\win64\php.exe"
)
if not exist "%PHP_EXE%" (
	echo PHP 8 nem talalhato. Allitsd be a PHP-t a PATH kornyezeti valtozoban.
	pause
	exit /b 1
)
start "" "http://127.0.0.1:8765"
"%PHP_EXE%" -S 127.0.0.1:8765 -t "%~dp0tools\factory-manager"
endlocal
