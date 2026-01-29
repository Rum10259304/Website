@echo off
echo ================================================
echo       VerzTec Application Stop Script
echo ================================================
echo.

echo [1/2] Stopping Docker containers...
docker-compose down
if %errorlevel% neq 0 (
    echo WARNING: Some containers may still be running
)

echo.
echo [2/2] Stopping any running chatbot processes...
for /f "tokens=2" %%i in ('tasklist /fi "imagename eq python.exe" /fo table /nh ^| findstr uvicorn') do (
    echo Stopping Python process %%i...
    taskkill /pid %%i /f >nul 2>&1
)

echo.
echo ================================================
echo      All VerzTec services have been stopped
echo ================================================
echo.
echo Note: If the chatbot is running in a separate terminal,
echo please close that terminal window manually.
echo.
pause
