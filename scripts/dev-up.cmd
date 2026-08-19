@echo off
REM Development-only Windows entry point. The canonical launcher remains scripts/dev-up.sh.
wsl.exe --cd /home/billel/cabinetPsyAPI bash -lc "./scripts/dev-up.sh"
exit /b %errorlevel%
