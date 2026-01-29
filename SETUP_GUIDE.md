# VerzTec Project Setup Guide - From Scratch

Welcome to the VerzTec project! This guide will help you set up everything from scratch, even if you've never used Docker, Python, or Git before.

## 📋 Table of Contents
1. [Prerequisites & Installation](#prerequisites--installation)
2. [Clone the Repository](#clone-the-repository)
3. [Quick Start (Automated)](#quick-start-automated)
4. [Manual Setup (If needed)](#manual-setup-if-needed)
5. [Verification & Testing](#verification--testing)
6. [Troubleshooting](#troubleshooting)
7. [Development Workflow](#development-workflow)

---

## 🛠️ Prerequisites & Installation

### Step 1: Install Git
1. Download Git from [https://git-scm.com/download/windows](https://git-scm.com/download/windows)
2. Run the installer with default settings
3. Verify installation:
   ```bash
   git --version
   ```

### Step 2: Install Docker Desktop
1. Download Docker Desktop from [https://www.docker.com/products/docker-desktop/](https://www.docker.com/products/docker-desktop/)
2. Install with default settings
3. **Important**: Start Docker Desktop and wait for it to fully load
4. Verify installation:
   ```bash
   docker --version
   docker-compose --version
   ```

### Step 3: Install Python 3.8+
1. Download Python from [https://www.python.org/downloads/](https://www.python.org/downloads/)
2. **IMPORTANT**: Check "Add Python to PATH" during installation
3. Verify installation:
   ```bash
   python --version
   pip --version
   ```

### Step 4: Install Ollama (AI Model Server)
1. Download Ollama from [https://ollama.ai](https://ollama.ai)
2. Install with default settings
3. Open Command Prompt and run:
   ```bash
   ollama pull llama3.2
   ```
   This downloads the AI model (about 2GB, may take 5-10 minutes)

---

## 📥 Clone the Repository

1. **Choose a location** (e.g., `C:\Projects\`)
2. **Open Command Prompt** in that location
3. **Clone the repository:**
   ```bash
   git clone [YOUR_REPOSITORY_URL]
   cd VerzTec---KaiXin-Team
   ```

---

## 🚀 Quick Start (Automated)

### Method 1: One-Click Setup ⚡ (Recommended)

1. **Double-click** `start-application.bat` in the project folder
2. **Wait** for all services to start (2-3 minutes first time)
3. **Open browser** and go to `http://localhost:8080`
4. **Test chatbot** by clicking "Chatbot" in the navigation

**That's it! Skip to [Verification & Testing](#verification--testing) if this worked.**

---

## 🔧 Manual Setup (If needed)

If the automated setup doesn't work, follow these steps:

### Step 1: Install Python Dependencies
```bash
cd chatbot
pip install -r requirements.txt
```

### Step 2: Start Ollama Service
```bash
ollama serve
```
Keep this terminal open.

### Step 3: Start Docker Services
```bash
docker-compose up -d
```

### Step 4: Start Chatbot Server
Open a new terminal:
```bash
cd chatbot
python -m uvicorn main:app --reload --host 0.0.0.0 --port 8000
```

---

## ✅ Verification & Testing

### Check All Services Are Running

1. **Website**: Go to `http://localhost:8080`
   - Should show VerzTec homepage
   - Login page should be accessible

2. **Chatbot**: Go to `http://localhost:8080/chatbot.html`
   - Should show chat interface
   - Type: "What are VerzTec's meeting etiquettes?"
   - Should get a response about meeting guidelines

3. **Docker Status**: Run `docker ps`
   - Should show 3 containers: web, db, onlyoffice

4. **API Status**: Go to `http://localhost:8000/docs`
   - Should show FastAPI documentation

### Test the Complete Workflow

1. **Ask a question**: "What are the pantry rules?"
2. **Verify response**: Should mention VerzTec pantry guidelines
3. **Check file reference**: Should provide PDF reference link
4. **Navigate website**: Try Home, Files, Admin sections

---

## 🚨 Troubleshooting

### Issue: "Docker containers won't start"
**Solution:**
1. Ensure Docker Desktop is running (check system tray)
2. Restart Docker Desktop
3. Run: `docker-compose down` then `docker-compose up -d`

### Issue: "Chatbot says connection error"
**Solution:**
1. Check if Python process is running: `tasklist | findstr python`
2. Restart chatbot:
   ```bash
   cd chatbot
   python -m uvicorn main:app --reload --host 0.0.0.0 --port 8000
   ```

### Issue: "ModuleNotFoundError" when starting chatbot
**Solution:**
```bash
cd chatbot
pip install -r requirements.txt
pip install tf-keras
```

### Issue: "Ollama model not found"
**Solution:**
```bash
ollama pull llama3.2
ollama list  # Verify model is installed
```

### Issue: "Port already in use"
**Solution:**
1. Find what's using the port:
   ```bash
   netstat -ano | findstr :8080
   netstat -ano | findstr :8000
   ```
2. Kill the process:
   ```bash
   taskkill /pid [PID] /f
   ```

### Complete Reset (When Everything Fails)
```bash
# Stop everything
docker-compose down
taskkill /f /im python.exe
taskkill /f /im ollama.exe

# Wait 30 seconds, then restart
start-application.bat
```

---

## 🔄 Development Workflow

### Daily Startup
```bash
git pull origin dev          # Get latest changes
start-application.bat        # Start all services
```

### Daily Shutdown
```bash
stop-application.bat         # Stop all services
```

### Making Changes
```bash
git checkout -b feature/your-feature-name
# Make your changes
git add .
git commit -m "Your changes"
git push origin feature/your-feature-name
# Create Pull Request
```

### Updating the Chatbot (Maintainers Only)
```bash
git subtree pull --prefix=chatbot https://github.com/juliazhou1415/Verztec_Chatbot----Ollama----new-data.git main --squash
```

---

## 📁 Project Structure

```
VerzTec---KaiXin-Team/
├── 📄 start-application.bat    # One-click startup
├── 📄 stop-application.bat     # One-click shutdown
├── 📄 README.md               # This file
├── 📄 TROUBLESHOOTING.md      # Detailed troubleshooting
├── 📄 docker-compose.yml      # Docker configuration
├── 📁 chatbot/               # AI Chatbot (Python/FastAPI)
├── 📁 admin/                 # Admin panel
├── 📁 css/                   # Stylesheets
├── 📁 js/                    # JavaScript
├── 📁 files/                 # Uploaded documents
├── 📄 home.php               # Homepage
├── 📄 login.php              # Login system
├── 📄 chatbot.html           # Chat interface
└── ... (other PHP files)
```

---

## 🆘 Getting Help

### Self-Help Resources
1. **Check logs** in terminal windows
2. **Read** `TROUBLESHOOTING.md` for detailed solutions
3. **Restart everything** using the stop/start scripts

### Contact Team
If you're still stuck, contact the development team with:
- Error messages (copy from terminal)
- What step failed
- Your OS version and Python version
- Screenshots if helpful

---

## 🎯 Success Checklist

After setup, you should be able to:

- [ ] Access website at `http://localhost:8080`
- [ ] Login to the system
- [ ] Chat with AI at `http://localhost:8080/chatbot.html`
- [ ] Ask questions about VerzTec policies
- [ ] View and download PDF references
- [ ] Access admin panel (if admin user)
- [ ] Upload and manage files

**🎉 Welcome to the VerzTec team! You're all set up!**

---

## 📚 Additional Resources

- **FastAPI Documentation**: [https://fastapi.tiangolo.com/](https://fastapi.tiangolo.com/)
- **Docker Documentation**: [https://docs.docker.com/](https://docs.docker.com/)
- **Ollama Documentation**: [https://ollama.ai/docs](https://ollama.ai/docs)
- **Git Tutorial**: [https://git-scm.com/docs/gittutorial](https://git-scm.com/docs/gittutorial)

---

*Last updated: July 2, 2025*
