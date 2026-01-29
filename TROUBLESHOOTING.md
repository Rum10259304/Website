# VerzTec Chatbot Troubleshooting Guide

## 🚨 Common Issues and Solutions

### 1. Chatbot Error: "Sorry, I encountered an error. Please make sure the chatbot server is running on port 8000."

**Symptoms:**
- The chat interface loads but shows error when sending messages
- Browser console shows connection refused errors

**Solutions:**

#### Step 1: Check if Ollama is running
```bash
ollama list
# Should show llama3.2:latest and other models

# If not working, start Ollama:
ollama serve
```

#### Step 2: Check if chatbot server is running
- Look for a terminal window titled "VerzTec Chatbot"
- Should show: `Uvicorn running on http://0.0.0.0:8000`

#### Step 3: Manual restart of chatbot
```bash
cd chatbot
python -m uvicorn main:app --reload --host 0.0.0.0 --port 8000
```

#### Step 4: Install missing dependencies
```bash
cd chatbot
pip install tf-keras
pip install -r requirements.txt
```

---

### 2. Docker Containers Not Starting

**Symptoms:**
- Website not accessible at http://localhost:8080
- Docker compose errors

**Solutions:**

#### Check Docker Desktop
1. Ensure Docker Desktop is running
2. Check system tray for Docker icon

#### Restart Docker services
```bash
docker-compose down
docker-compose up -d
```

#### Check port conflicts
```bash
# Check what's using port 8080
netstat -ano | findstr :8080

# If something else is using it, either:
# 1. Stop that service, or
# 2. Change port in docker-compose.yml
```

---

### 3. Dependency Conflicts (Python/TensorFlow/Keras)

**Symptoms:**
- ModuleNotFoundError for tf_keras
- TensorFlow version conflicts
- Keras compatibility issues

**Solutions:**

#### Quick fix
```bash
cd chatbot
pip install tf-keras
```

#### Complete dependency reset
```bash
cd chatbot
pip uninstall tensorflow tensorflow-gpu keras tf-keras -y
pip install tf-keras
pip install -r requirements.txt
```

---

### 4. Ollama Model Issues

**Symptoms:**
- Chatbot starts but gives generic error responses
- "Model not found" errors

**Solutions:**

#### Check available models
```bash
ollama list
# Should show llama3.2:latest
```

#### Pull required model
```bash
ollama pull llama3.2
```

#### Test Ollama directly
```bash
ollama run llama3.2
# Type a test question
```

---

### 5. Port Conflicts

**Common Ports Used:**
- 8080: Main website
- 8000: Chatbot API
- 8081: OnlyOffice
- 3306: MySQL Database
- 11434: Ollama service

**Check port usage:**
```bash
netstat -ano | findstr :8000
netstat -ano | findstr :8080
```

**Kill processes on ports:**
```bash
# Replace PID with the actual process ID
taskkill /pid [PID] /f
```

---

### 6. Browser/CORS Issues

**Symptoms:**
- CORS errors in browser console
- Network errors when chatbot tries to connect

**Solutions:**

#### Check browser console (F12)
- Look for CORS or network errors
- Ensure requests are going to http://localhost:8000

#### Try different browser
- Test in Chrome, Firefox, or Edge
- Clear browser cache and cookies

#### Check firewall/antivirus
- Temporarily disable Windows Firewall
- Check if antivirus is blocking local connections

---

## 🔧 Quick Diagnostic Commands

### Check all services status
```bash
# Docker containers
docker ps

# Python processes
tasklist | findstr python

# Ollama service
curl http://localhost:11434/api/version

# Chatbot API
curl -X POST http://localhost:8000/chat -H "Content-Type: application/json" -d "{\"question\":\"test\"}"
```

### Restart everything cleanly
```bash
# Stop all services
docker-compose down
taskkill /f /im python.exe
taskkill /f /im ollama.exe

# Wait 10 seconds

# Start everything
start-application.bat
```

---

## 📞 Getting Help

1. **Check logs:**
   - Chatbot terminal window for Python errors
   - Docker Desktop logs for container issues
   - Browser console (F12) for frontend errors

2. **Try the automated fix:**
   - Run `stop-application.bat`
   - Wait 30 seconds
   - Run `start-application.bat`

3. **Contact the team:**
   - Provide error messages from logs
   - Mention which step failed
   - Include your OS and Python version

---

## ✅ Verification Checklist

After starting services, verify:

- [ ] Docker containers running: `docker ps`
- [ ] Ollama responding: `ollama list`
- [ ] Chatbot API responding: Test at http://localhost:8000/docs
- [ ] Website accessible: http://localhost:8080
- [ ] Chat interface working: http://localhost:8080/chatbot.html

If all checkboxes are ✅, everything should work perfectly!
