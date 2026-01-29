# config.py

import os
from dotenv import load_dotenv

# Load .env variables (for secrets)
load_dotenv()

# 🔐 Sensitive values
HF_TOKEN = os.getenv("HF_TOKEN")
MODEL_ID = os.getenv("MODEL_ID", "BAAI/bge-base-en-v1.5")  # Fallback model

# 📦 Embedding & Vector Store
EMBEDDING_MODEL_NAME = MODEL_ID
VECTORSTORE_DIR = "models/faiss_index"

# 📄 Chunking
CHUNK_SIZE = 1000 #500 
CHUNK_OVERLAP = 200 #100

# 📁 Directories
PDF_DIR = "data/pdfs"
CLEANED_DIR = "data/Cleaned"

# 📝 Answer Control
MAX_ANSWER_WORDS = 300
