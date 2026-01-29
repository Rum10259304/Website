# main.py

import os
import re
import traceback
from datetime import datetime
from urllib.parse import quote
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from fastapi.staticfiles import StaticFiles
from fastapi.responses import FileResponse
from chatbot.rag_chain import load_chain
from chatbot.llm_loader import llama_pipeline
from chatbot.config import MAX_ANSWER_WORDS, PDF_DIR
from rapidfuzz import fuzz

app = FastAPI()

# Add CORS middleware to allow frontend connections
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, replace with specific domains
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

qa_chain, vectorstore = load_chain()
chat_history = []

# Mount folders
app.mount("/static", StaticFiles(directory="static"), name="static")
app.mount("/pdfs", StaticFiles(directory=PDF_DIR), name="pdfs")

class Question(BaseModel):
    question: str

def truncate_answer(answer, max_words=MAX_ANSWER_WORDS):
    words = answer.split()
    if len(words) <= max_words:
        return answer
    truncated = " ".join(words[:max_words])
    truncated = re.sub(r'([.!?])[^.!?]*$', r'\1', truncated.strip())
    return truncated + "..."

def is_rejection_response(text: str) -> bool:
    text = text.lower()
    patterns = [
        r"i'?m not (qualified|able|equipped) to provide (a )?response",
        r"document (does not|doesn’t) (address|mention).*(personal|family)",
        r"recommend (seeking|speaking|getting).*(help|support|advice)",
        r"i can’t provide (guidance|support|advice)",
        r"this is beyond (my|the document's) scope",
        r"not able to help (with )?(that|this question)",
    ]
    return any(re.search(p, text) for p in patterns)

def is_personal_question(question: str) -> bool:
    personal_keywords = [
        "father", "mother", "brother", "sister", "family", "boyfriend", "girlfriend",
        "relationship", "love", "hate", "angry", "feel", "emotional", "personal", "sad",
        "why is my", "mental health", "feeling"
    ]
    return any(word in question.lower() for word in personal_keywords)

def is_hr_query(question: str, use_fuzzy=True) -> bool:
    keywords = [
        "leave", "policy", "hr", "human resource", "benefits", "meeting", "procedure",
        "onboarding", "offboarding", "sop", "salary", "promotion", "resignation",
        "complaint", "roles", "pantry", "email etiquette", "company policy", "form",
        "employee", "attendance", "audit", "feedback", "payroll", "document", "workflow",
        "cover page", "quality manual", "quality procedure", "controlled copy", "uncontrolled copy"
    ]
    question = question.lower()
    for kw in keywords:
        if kw in question:
            return True
        if use_fuzzy and fuzz.partial_ratio(kw, question) >= 80:
            return True
    return False

def is_hr_question_via_llm(query: str) -> bool:
    prompt = f"""Is the following question related to Human Resources, company policies, internal procedures, or work etiquette?

    Question: "{query}"

    Respond with only "Yes" or "No"."""
    result = llama_pipeline.invoke(prompt)
    return "yes" in result.content.lower()

@app.post("/chat")
def chat(question: Question):
    print("❓ User Question:", question.question)
    print("🕵️‍♀️ Chat history:", chat_history)

    # Reject clearly personal questions only
    if is_personal_question(question.question):
        with open("question_log.txt", "a", encoding="utf-8") as log_file:
            log_file.write(f"[❌ Rejected Personal] {datetime.now().isoformat()} - Q: {question.question}\n---\n")
        return {
            "answer": (
                "Sorry I am not qualified to answer this question as I am only designed to assist with Verztec's internal policies and HR-related queries. "
                "For personal matters, I would recommend speaking to someone you trust or seek professional help."
            ),
            "reference_file": None
        }

    try:
        # Only retrieve docs if it's HR-related
        is_hr_like = is_hr_query(question.question)
        is_llm_hr = is_hr_question_via_llm(question.question)

        docs_and_scores = []
        if is_hr_like or is_llm_hr:
            docs_and_scores = vectorstore.similarity_search_with_score(question.question, k=3)
            user_query = question.question.lower()

            is_physical = any(term in user_query for term in ["physical meeting", "in person", "face to face", "onsite"])
            is_digital = any(term in user_query for term in ["digital meeting", "online meeting", "virtual meeting", "zoom", "teams"])

            if is_physical:
                docs_and_scores = [(doc, score) for doc, score in docs_and_scores if doc.metadata.get("doc_type") == "physical"]
            elif is_digital:
                docs_and_scores = [(doc, score) for doc, score in docs_and_scores if doc.metadata.get("doc_type") == "digital"]

        score_threshold = 0.38
        answer = ""
        source_file = None

        system_prefix = (
            "You are a professional HR assistant at Verztec.\n"
            "Answer only using the content provided in the document — do not add anything outside of it.\n"
            "Summarize all key points mentioned in the document, not just one. Keep the tone clear and professional, and do not skip relevant sections.\n"
            "Avoid overly casual language like 'just a heads up', 'don’t worry', or 'let them know what’s going on'.\n"
            "Speak as if you're helping a colleague or employee in a business setting.\n"
            "Avoid numbered or overly formatted lists unless they already exist in the document.\n"
            "Be clear, concise, and human — not robotic or overly formal."
        )

        if docs_and_scores:
            for i, (doc, score) in enumerate(docs_and_scores):
                print(f"📄 Doc {i+1}:")
                print(f"   ↳ Title     : {doc.metadata.get('title')}")
                print(f"   ↳ File      : {doc.metadata.get('source')}")
                print(f"   ↳ Type      : {doc.metadata.get('doc_type')}")
                print(f"   ↳ Score     : {score:.4f}")

            top_doc, top_score = docs_and_scores[0]
            content = "\n".join([doc.page_content.strip() for doc, _ in docs_and_scores])
            source_file = top_doc.metadata.get("source", None)
            file_path = os.path.join("data/pdfs", source_file) if source_file else ""

            if top_score >= score_threshold and os.path.exists(file_path):
                if top_doc.metadata.get("doc_type") == "cover_page":
                    title = top_doc.metadata.get("title", "this document").upper()
                    answer = (
                        f"Yes, I can retrieve the cover page for this document. "
                        f"According to the document, the title is \"{title}\". "
                        f"It includes version control sections for Controlled and Uncontrolled Copy Numbers."
                    )
                else:
                    full_prompt = (
                        f"{system_prefix}\n"
                        f"---\n{content}\n---\n"
                        f"Based only on the content above, how would you answer this question?\n"
                        f"{question.question}"
                    )
                    result = llama_pipeline.invoke(full_prompt)
                    answer = truncate_answer(result.content)
            else:
                print("⚠️ Score too low or file not found. Skipping source.")
                result = llama_pipeline.invoke(question.question)
                answer = truncate_answer(result.content)
        else:
            # For general questions like "1+1" or "what should I eat"
            result = llama_pipeline.invoke(question.question)
            answer = truncate_answer(result.content)

        if is_rejection_response(answer):
            with open("question_log.txt", "a", encoding="utf-8") as log_file:
                log_file.write(f"[⚠️ Rejection Tone] A: {answer}\n")

        chat_history.append((question.question, answer))
        with open("question_log.txt", "a", encoding="utf-8") as log_file:
            log_file.write(f"{datetime.now().isoformat()} - Q: {question.question}\n")
            log_file.write(f"A: {answer}\n")
            if source_file:
                log_file.write(f"Source: {source_file}\n")
            log_file.write("---\n")

        return {
            "answer": answer,
            "reference_file": {
                "url": f"http://localhost:8000/pdfs/{quote(source_file)}",
                "name": source_file
            } if source_file else None
        }

    except Exception as e:
        print("❌ Exception in /chat endpoint")
        traceback.print_exc()
        return {"answer": "Sorry, something went wrong.", "reference_file": None}

@app.get("/")
def index():
    return FileResponse("static/index.html")

@app.get("/favicon.ico")
def favicon():
    return FileResponse("static/favicon.ico")


# import os
# import re
# import traceback
# from datetime import datetime
# from urllib.parse import quote
# from fastapi import FastAPI
# from pydantic import BaseModel
# from fastapi.staticfiles import StaticFiles
# from fastapi.responses import FileResponse
# from chatbot.rag_chain import load_chain
# from chatbot.llm_loader import llama_pipeline
# from chatbot.config import MAX_ANSWER_WORDS, PDF_DIR
# from rapidfuzz import fuzz

# app = FastAPI()
# qa_chain, vectorstore = load_chain()
# chat_history = []

# # Mount folders
# app.mount("/static", StaticFiles(directory="static"), name="static")
# app.mount("/pdfs", StaticFiles(directory=PDF_DIR), name="pdfs")

# class Question(BaseModel):
#     question: str

# def truncate_answer(answer, max_words=MAX_ANSWER_WORDS):
#     words = answer.split()
#     if len(words) <= max_words:
#         return answer
#     truncated = " ".join(words[:max_words])
#     truncated = re.sub(r'([.!?])[^.!?]*$', r'\1', truncated.strip())
#     return truncated + "..."

# def is_rejection_response(text: str) -> bool:
#     """Log if the answer sounds like a rejection."""
#     text = text.lower()
#     patterns = [
#         r"i'?m not (qualified|able|equipped) to provide (a )?response",
#         r"document (does not|doesn’t) (address|mention).*(personal|family)",
#         r"recommend (seeking|speaking|getting).*(help|support|advice)",
#         r"i can’t provide (guidance|support|advice)",
#         r"this is beyond (my|the document's) scope",
#         r"not able to help (with )?(that|this question)",
#     ]
#     return any(re.search(p, text) for p in patterns)

# def is_hr_query(question: str, use_fuzzy=True) -> bool:
#     """Check if question is HR-related using keyword or fuzzy match."""
#     keywords = [
#         "leave", "policy", "hr", "human resource", "benefits", "meeting", "procedure",
#         "onboarding", "offboarding", "sop", "salary", "promotion", "resignation",
#         "complaint", "roles", "pantry", "email etiquette", "company policy", "form",
#         "employee", "attendance", "audit", "feedback", "payroll", "document", "workflow",
#         "cover page", "quality manual", "quality procedure", "controlled copy", "uncontrolled copy"
#     ]
#     question = question.lower()
#     for kw in keywords:
#         if kw in question:
#             return True
#         if use_fuzzy and fuzz.partial_ratio(kw, question) >= 80:
#             return True
#     return False

# def is_hr_question_via_llm(query: str) -> bool:
#     prompt = f"""Is the following question related to Human Resources, company policies, internal procedures, or work etiquette?

#     Question: "{query}"

#     Respond with only "Yes" or "No"."""
#     result = llama_pipeline.invoke(prompt)
#     return "yes" in result.content.lower()

# @app.post("/chat")
# def chat(question: Question):
#     print("❓ User Question:", question.question)
#     print("🕵️‍♀️ Chat history:", chat_history)

#     is_hr_like = is_hr_query(question.question)
#     is_llm_hr = is_hr_question_via_llm(question.question)

#     if not is_hr_like and not is_llm_hr:
#         with open("question_log.txt", "a", encoding="utf-8") as log_file:
#             log_file.write(f"[❌ Rejected Non-HR Query] {datetime.now().isoformat()} - Q: {question.question}\n---\n")
#         return {
#             "answer": (
#                 "Sorry I am not qualified to answer this question as I am only designed to assist with Verztec's internal policies and HR-related queries. "
#                 "For personal matters, I would recommend speaking to someone you trust or seek professional help."
#             ),
#             "reference_file": None
#         }

#     try:
#         docs_and_scores = vectorstore.similarity_search_with_score(question.question, k=3)
#         user_query = question.question.lower()

#         is_physical = any(term in user_query for term in ["physical meeting", "in person", "face to face", "onsite"])
#         is_digital = any(term in user_query for term in ["digital meeting", "online meeting", "virtual meeting", "zoom", "teams"])

#         if is_physical:
#             docs_and_scores = [(doc, score) for doc, score in docs_and_scores if doc.metadata.get("doc_type") == "physical"]
#         elif is_digital:
#             docs_and_scores = [(doc, score) for doc, score in docs_and_scores if doc.metadata.get("doc_type") == "digital"]

#         score_threshold = 0.38
#         answer = ""
#         source_file = None

#         system_prefix = (
#             "You are a professional HR assistant at Verztec.\n"
#             "Answer only using the content provided in the document — do not add anything outside of it.\n"
#             "Summarize all key points mentioned in the document, not just one. Keep the tone clear and professional, and do not skip relevant sections.\n"
#             "Avoid overly casual language like 'just a heads up', 'don’t worry', or 'let them know what’s going on'.\n"
#             "Speak as if you're helping a colleague or employee in a business setting.\n"
#             "Avoid numbered or overly formatted lists unless they already exist in the document.\n"
#             "Be clear, concise, and human — not robotic or overly formal."
#         )

#         if docs_and_scores:
#             for i, (doc, score) in enumerate(docs_and_scores):
#                 print(f"📄 Doc {i+1}:")
#                 print(f"   ↳ Title     : {doc.metadata.get('title')}")
#                 print(f"   ↳ File      : {doc.metadata.get('source')}")
#                 print(f"   ↳ Type      : {doc.metadata.get('doc_type')}")
#                 print(f"   ↳ Score     : {score:.4f}")

#             top_doc, top_score = docs_and_scores[0]
#             content = "\n".join([doc.page_content.strip() for doc, _ in docs_and_scores])
#             source_file = top_doc.metadata.get("source", None)
#             file_path = os.path.join("data/pdfs", source_file) if source_file else ""

#             if top_score >= score_threshold and os.path.exists(file_path):
#                 if top_doc.metadata.get("doc_type") == "cover_page":
#                     title = top_doc.metadata.get("title", "this document").upper()
#                     answer = (
#                         f"Yes, I can retrieve the cover page. "
#                         f"According to the document, the title is \"{title}\". "
#                         f"It includes version control sections for Controlled and Uncontrolled Copy Numbers."
#                     )
#                 else:
#                     full_prompt = (
#                         f"{system_prefix}\n"
#                         f"---\n{content}\n---\n"
#                         f"Based only on the content above, how would you answer this question?\n"
#                         f"{question.question}"
#                     )
#                     result = llama_pipeline.invoke(full_prompt)
#                     answer = truncate_answer(result.content)
#             else:
#                 print("⚠️ Score too low or file not found. Skipping source.")
#                 result = llama_pipeline.invoke(question.question)
#                 answer = truncate_answer(result.content)
#         else:
#             result = llama_pipeline.invoke(question.question)
#             answer = truncate_answer(result.content)

#         if is_rejection_response(answer):
#             with open("question_log.txt", "a", encoding="utf-8") as log_file:
#                 log_file.write(f"[⚠️ Rejection Tone Detected] A: {answer}\n")

#         chat_history.append((question.question, answer))
#         with open("question_log.txt", "a", encoding="utf-8") as log_file:
#             log_file.write(f"{datetime.now().isoformat()} - Q: {question.question}\n")
#             log_file.write(f"A: {answer}\n")
#             if source_file:
#                 log_file.write(f"Source: {source_file}\n")
#             log_file.write("---\n")

#         return {
#             "answer": answer,
#             "reference_file": {
#                 "url": f"http://localhost:8000/pdfs/{quote(source_file)}",
#                 "name": source_file
#             } if source_file else None
#         }

#     except Exception as e:
#         print("❌ Exception in /chat endpoint")
#         traceback.print_exc()
#         return {"answer": "Sorry, something went wrong.", "reference_file": None}

# @app.get("/")
# def index():
#     return FileResponse("static/index.html")

# @app.get("/favicon.ico")
# def favicon():
#     return FileResponse("static/favicon.ico")



# import os # improve version + fuzzy
# import re
# import traceback
# from datetime import datetime
# from urllib.parse import quote
# from fastapi import FastAPI
# from pydantic import BaseModel
# from fastapi.staticfiles import StaticFiles
# from fastapi.responses import FileResponse
# from chatbot.rag_chain import load_chain
# from chatbot.llm_loader import llama_pipeline
# from chatbot.config import MAX_ANSWER_WORDS, PDF_DIR
# from rapidfuzz import fuzz

# app = FastAPI()
# qa_chain, vectorstore = load_chain()
# chat_history = []

# # Mount folders
# app.mount("/static", StaticFiles(directory="static"), name="static")
# app.mount("/pdfs", StaticFiles(directory=PDF_DIR), name="pdfs")

# class Question(BaseModel):
#     question: str

# def truncate_answer(answer, max_words=MAX_ANSWER_WORDS):
#     words = answer.split()
#     if len(words) <= max_words:
#         return answer
#     truncated = " ".join(words[:max_words])
#     truncated = re.sub(r'([.!?])[^.!?]*$', r'\1', truncated.strip())
#     return truncated + "..."

# def is_rejection_response(text: str) -> bool:
#     text = text.lower()
#     patterns = [
#         r"i'?m not (qualified|able|equipped) to provide (a )?response",
#         r"document (does not|doesn’t) (address|mention).*(personal|family)",
#         r"recommend (seeking|speaking|getting).*(help|support|advice)",
#         r"i can’t provide (guidance|support|advice)",
#         r"this is beyond (my|the document's) scope",
#         r"not able to help (with )?(that|this question)",
#     ]
#     return any(re.search(p, text) for p in patterns)

# def is_hr_query(question: str, use_fuzzy=True) -> bool:
#     """Check if question is HR-related using keyword or fuzzy match."""
#     keywords = [
#         "leave", "policy", "hr", "human resource", "benefits", "meeting", "procedure",
#         "onboarding", "offboarding", "sop", "salary", "promotion", "resignation",
#         "complaint", "roles", "pantry", "email etiquette", "company policy", "form",
#         "employee", "attendance", "audit", "feedback", "payroll", "document", "workflow",
#         "cover page", "quality manual", "quality procedure", "controlled copy", "uncontrolled copy"
#     ]
#     question = question.lower()
#     for kw in keywords:
#         if kw in question:
#             return True
#         if use_fuzzy and fuzz.partial_ratio(kw, question) >= 80:
#             return True
#     return False

# def is_hr_question_via_llm(query: str) -> bool:
#     prompt = f"""Is the following question related to Human Resources, company policies, internal procedures, or work etiquette?

#     Question: "{query}"

#     Respond with only "Yes" or "No"."""
#     result = llama_pipeline.invoke(prompt)
#     return "yes" in result.content.lower()

# @app.post("/chat")
# def chat(question: Question):
#     print("❓ User Question:", question.question)
#     print("🕵️‍♀️ Chat history:", chat_history)

#     # HR filter: keyword/fuzzy match + LLM fallback
#     is_hr_like = is_hr_query(question.question)
#     is_llm_hr = is_hr_question_via_llm(question.question)

#     if not is_hr_like and not is_llm_hr:
#         with open("question_log.txt", "a", encoding="utf-8") as log_file:
#             log_file.write(f"[❌ Rejected Non-HR Query] {datetime.now().isoformat()} - Q: {question.question}\n---\n")
#         return {
#             "answer": (
#                 "Sorry I am not qualified to answer this question as I am only designed to assist with Verztec's internal policies and HR-related queries. "
#                 "For personal matters, I would recommend speaking to someone you trust or seek professional help."
#             ),
#             "reference_file": None
#         }

#     try:
#         docs_and_scores = vectorstore.similarity_search_with_score(question.question, k=3)
#         user_query = question.question.lower()

#         is_physical = any(term in user_query for term in ["physical meeting", "in person", "face to face", "onsite"])
#         is_digital = any(term in user_query for term in ["digital meeting", "online meeting", "virtual meeting", "zoom", "teams"])

#         if is_physical:
#             docs_and_scores = [(doc, score) for doc, score in docs_and_scores if doc.metadata.get("doc_type") == "physical"]
#         elif is_digital:
#             docs_and_scores = [(doc, score) for doc, score in docs_and_scores if doc.metadata.get("doc_type") == "digital"]

#         score_threshold = 0.38
#         answer = ""
#         source_file = None

#         system_prefix = (
#             "You are a professional HR assistant at Verztec.\n"
#             "Answer only using the content provided in the document — do not add anything outside of it.\n"
#             "Summarize all key points mentioned in the document, not just one. Keep the tone clear and professional, and do not skip relevant sections.\n"
#             "Avoid overly casual language like 'just a heads up', 'don’t worry', or 'let them know what’s going on'.\n"
#             "Speak as if you're helping a colleague or employee in a business setting.\n"
#             "Avoid numbered or overly formatted lists unless they already exist in the document.\n"
#             "Be clear, concise, and human — not robotic or overly formal."
#         )

#         if docs_and_scores:
#             for i, (doc, score) in enumerate(docs_and_scores):
#                 print(f"📄 Doc {i+1}:")
#                 print(f"   ↳ Title     : {doc.metadata.get('title')}")
#                 print(f"   ↳ File      : {doc.metadata.get('source')}")
#                 print(f"   ↳ Type      : {doc.metadata.get('doc_type')}")
#                 print(f"   ↳ Score     : {score:.4f}")

#             top_doc, top_score = docs_and_scores[0]
#             content = "\n".join([doc.page_content.strip() for doc, _ in docs_and_scores])
#             source_file = top_doc.metadata.get("source", None)
#             file_path = os.path.join("data/pdfs", source_file) if source_file else ""

#             if top_score >= score_threshold and os.path.exists(file_path):
#                 if top_doc.metadata.get("doc_type") == "cover_page":
#                     title = top_doc.metadata.get("title", "this document").upper()
#                     answer = (
#                         f"Yes, I can retrieve the cover page for this document. "
#                         f"According to the document, the title is \"{title}\". "
#                         f"It includes version control sections for Controlled and Uncontrolled Copy Numbers."
#                     )
#                 else:
#                     full_prompt = (
#                         f"{system_prefix}\n"
#                         f"---\n{content}\n---\n"
#                         f"Based only on the content above, how would you answer this question?\n"
#                         f"{question.question}"
#                     )
#                     result = llama_pipeline.invoke(full_prompt)
#                     answer = truncate_answer(result.content)
#                     if is_rejection_response(answer):
#                         source_file = None
#             else:
#                 print("⚠️ Score too low or file not found. Skipping source.")
#                 result = llama_pipeline.invoke(question.question)
#                 answer = truncate_answer(result.content)
#                 if is_rejection_response(answer):
#                     source_file = None
#         else:
#             result = llama_pipeline.invoke(question.question)
#             answer = truncate_answer(result.content)
#             if is_rejection_response(answer):
#                 source_file = None

#         chat_history.append((question.question, answer))
#         with open("question_log.txt", "a", encoding="utf-8") as log_file:
#             log_file.write(f"{datetime.now().isoformat()} - Q: {question.question}\n")
#             log_file.write(f"A: {answer}\n")
#             if source_file:
#                 log_file.write(f"Source: {source_file}\n")
#             log_file.write("---\n")

#         return {
#             "answer": answer,
#             "reference_file": {
#                 "url": f"http://localhost:8000/pdfs/{quote(source_file)}",
#                 "name": source_file
#             } if source_file and not is_rejection_response(answer) else None
#         }

#     except Exception as e:
#         print("❌ Exception in /chat endpoint")
#         traceback.print_exc()
#         return {"answer": "Sorry, something went wrong.", "reference_file": None}

# @app.get("/")
# def index():
#     return FileResponse("static/index.html")

# @app.get("/favicon.ico")
# def favicon():
#     return FileResponse("static/favicon.ico")



