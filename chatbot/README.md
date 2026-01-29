# VerzTec Chatbot Integration

This directory contains the VerzTec chatbot that has been integrated from a separate repository using git subtree.

## 🚀 Quick Start

### Method 1: Automatic Startup (Recommended)

1. **Double-click `start-application.bat`** in the root directory
2. Wait for all services to start (about 30-60 seconds)
3. Open your browser and go to **http://localhost:8080**
4. Navigate to the **Chatbot** page to start chatting!

### Method 2: Manual Startup

1. **Start the main website:**
   ```bash
   docker-compose up -d
   ```

2. **Start the chatbot:**
   ```bash
   cd chatbot
   pip install -r requirements.txt  # Only needed first time
   python -m uvicorn main:app --reload --host 0.0.0.0 --port 8000
   ```

3. **Access the application:**
   - Main Website: http://localhost:8080
   - Chatbot API: http://localhost:8000

## 🛠️ Prerequisites

1. **Docker Desktop** - Must be running
2. **Python 3.8+** - For the chatbot
3. **Ollama** - For AI language model (install from https://ollama.ai)

## 📋 Services Overview

| Service | URL | Purpose |
|---------|-----|---------|
| Main Website | http://localhost:8080 | PHP web application |
| Chatbot API | http://localhost:8000 | FastAPI chatbot backend |
| OnlyOffice | http://localhost:8081 | Document server |
| MySQL Database | localhost:3306 | Data storage |

## 💬 Using the Chatbot

1. Go to **http://localhost:8080**
2. Click on **"Chatbot"** in the navigation menu
3. Type your questions about VerzTec policies and procedures
4. The AI will respond with relevant information and document references

## 🔧 Troubleshooting

### Common Issues

**❌ "Connection refused" errors:**
- Ensure Docker Desktop is running
- Wait for all containers to fully start (30-60 seconds)

**❌ Chatbot not responding:**
- Check if the chatbot terminal shows "Uvicorn running on http://0.0.0.0:8000"
- Ensure port 8000 is not used by another application

**❌ "ModuleNotFoundError":**
- Run `pip install -r requirements.txt` in the chatbot directory

**❌ Docker containers fail to start:**
- Check if ports 8080, 8081, or 3306 are already in use
- Restart Docker Desktop

### Port Conflicts

If you encounter port conflicts, you can modify the ports in `docker-compose.yml`:

```yaml
ports:
  - "8080:80"    # Change 8080 to another port
```

## 🔄 Stopping the Application

### Method 1: Quick Stop
Double-click `stop-application.bat` in the root directory

### Method 2: Manual Stop
```bash
# Stop Docker containers
docker-compose down

# Stop chatbot (Ctrl+C in the chatbot terminal)
```
