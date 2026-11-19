@echo off
setlocal DisableDelayedExpansion
where pwsh.exe >nul 2>&1
if errorlevel 1 (
    echo PowerShell 7.2 or newer is required. Ask your developer to complete setup.
    pause
    exit /b 1
)
pwsh.exe -NoProfile -File "%~dp0resources\windows\Prism.ps1" -Action Start
if errorlevel 1 pause
