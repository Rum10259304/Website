@echo off
echo ================================================
echo       VerzTec Application Startup Script
echo ================================================
echo.

echo [1/4] Starting Ollama service (AI Model Server)...
start "Ollama Service" cmd /k "ollama serve"
timeout /t 3 /nobreak >nul

echo.
echo [2/4] Starting Docker containers (Website, Database, OnlyOffice)...
docker-compose up -d
if %errorlevel% neq 0 (
    echo ERROR: Failed to start Docker containers!
    echo Make sure Docker Desktop is running.
    pause
    exit /b 1
)

echo.
echo [3/4] Waiting for containers to be ready...
timeout /t 10 /nobreak >nul

echo.
echo [4/4] Starting the Chatbot server...
echo.
echo Opening chatbot in a new terminal window...
start "VerzTec Chatbot" cmd /k "cd /d \"%~dp0chatbot\" && echo Starting VerzTec Chatbot... && python -m uvicorn main:app --reload --host 0.0.0.0 --port 8000"

echo.
echo ================================================
echo           Services are starting up!
echo ================================================
echo.
echo Main Website:     http://localhost:8080
echo Chatbot API:      http://localhost:8000
echo OnlyOffice:       http://localhost:8081
echo Database:         localhost:3306
echo Ollama Service:   http://localhost:11434
echo.
echo Wait for all services to fully load:
echo - Ollama: Should show "Ollama is running"
echo - Chatbot: Should show "Uvicorn running on http://0.0.0.0:8000"
echo - Docker: All containers should be "Up"
echo.
echo To test the chatbot: Go to http://localhost:8080/chatbot.html
echo.
echo Press Ctrl+C in each terminal to stop the respective services.
echo Run 'docker-compose down' to stop Docker services.
echo.
pause
