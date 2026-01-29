# VerzTec Project - KaiXin Team

A comprehensive web application with integrated AI chatbot for VerzTec document management and employee assistance.

# VerzTec Project - KaiXin Team

A comprehensive web application with integrated AI chatbot for VerzTec document management and employee assistance.

## 🎯 New Team Member?

### 👋 **START HERE**: [GETTING_STARTED.md](GETTING_STARTED.md) 
*3-step setup guide (20 minutes total)*

### � **Need More Details?**: [SETUP_GUIDE.md](SETUP_GUIDE.md)
*Complete from-scratch installation guide*

### 🔧 **Having Issues?**: [TROUBLESHOOTING.md](TROUBLESHOOTING.md)
*Solutions for common problems*

---

## 🚀 Already Set Up?

```bash
git pull origin dev          # Get latest changes
start-application.bat        # Start everything
```
Open http://localhost:8080 and start using the application!

### Quick Stop
```bash
stop-application.bat         # Stop all services
```

## � What You Get

| Service | URL | Purpose |
|---------|-----|---------|
| **Main Website** | http://localhost:8080 | Employee portal, file management |
| **AI Chatbot** | http://localhost:8080/chatbot.html | Ask questions about VerzTec policies |
| **API Documentation** | http://localhost:8000/docs | Chatbot API reference |
| **Document Editor** | http://localhost:8081 | OnlyOffice document server |

## 💬 AI Chatbot Features

- 🤖 **Smart Q&A**: Ask about VerzTec policies, procedures, and guidelines
- � **Document Search**: Automatically finds relevant company documents
- � **Reference Links**: Direct links to PDF sources
- 💬 **Natural Conversation**: Chat naturally about work-related topics

**Try asking:**
- "What are the meeting etiquettes?"
- "What are the pantry rules?"
- "How do I request time off?"
- "What's the clean desk policy?"

## �️ For Developers

### Project Structure
```
├── 📄 start-application.bat    # One-click startup
├── 📄 SETUP_GUIDE.md          # Complete setup instructions
├── 📄 TROUBLESHOOTING.md      # Problem solving guide
├── 📁 chatbot/               # AI Backend (Python/FastAPI)
├── 📁 admin/                 # Admin panel (PHP)
├── 📄 chatbot.html           # Chat interface
├── 📄 home.php               # Main website
└── docker-compose.yml        # Service configuration
```

### Development Workflow
```bash
# Daily workflow
git pull origin dev
start-application.bat

# Make changes to code
# Test your changes

git add .
git commit -m "Your changes"
git push origin dev
```

### Key Technologies
- **Frontend**: HTML/CSS/JavaScript, PHP
- **Backend**: FastAPI (Python), MySQL
- **AI**: Ollama (llama3.2), LangChain, FAISS
- **Infrastructure**: Docker, OnlyOffice

## 🆘 Need Help?

1. **Setup Issues**: Check [SETUP_GUIDE.md](SETUP_GUIDE.md)
2. **Runtime Problems**: Check [TROUBLESHOOTING.md](TROUBLESHOOTING.md)
3. **Still Stuck**: Contact the development team

## 🤝 Contributing

1. Read the [Setup Guide](SETUP_GUIDE.md) first
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Make your changes and test locally
4. Submit a Pull Request

## 📝 License

This project is for VerzTec internal use only.

---

**🎉 Ready to get started? Check out the [Setup Guide](SETUP_GUIDE.md)!**
