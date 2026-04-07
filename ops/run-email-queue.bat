@echo off
set PHP_PATH=C:\xampp\php\php.exe
set PROJECT_ROOT=%~dp0..

if not exist "%PHP_PATH%" (
  echo PHP executable not found at %PHP_PATH%
  exit /b 1
)

"%PHP_PATH%" "%PROJECT_ROOT%\cron\process_emails.php"