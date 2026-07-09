import sys
import os
import json
import fitz
import pytesseract
import cv2
import numpy as np
from PIL import Image
from pdf2image import convert_from_path
import io
from datetime import datetime
import unicodedata
import warnings

warnings.filterwarnings("ignore")
# sys.stderr = open(os.devnull, 'w')

import os

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.dirname(CURRENT_DIR)
STAMPS_DIR = os.path.join(PROJECT_ROOT, "stamps")


# =========================================================
# UTILITAIRE NORMALISATION TEXTE
# =========================================================
def normalize_text(text):
    text = text.lower()
    text = unicodedata.normalize('NFD', text)
    text = ''.join(c for c in text if unicodedata.category(c) != 'Mn')
    return text


# =========================================================
# EXTRACTION TEXTE PRINCIPAL
# =========================================================
def extract_text(file_path):
    ext = file_path.lower().split(".")[-1]
    text = ""

    try:
        if ext == "pdf":
            doc = fitz.open(file_path)
            page = doc[0]
            content = page.get_text()

            if content.strip():
                text = content
            else:
                pix = page.get_pixmap(matrix=fitz.Matrix(3, 3))
                img = Image.open(io.BytesIO(pix.tobytes("png")))
                text = pytesseract.image_to_string(img, lang="fra")

            doc.close()
        else:
            img = Image.open(file_path)
            text = pytesseract.image_to_string(img, lang="fra")

    except Exception:
        text = ""

    return text.strip(), 0.90


# =========================================================
# CLASSIFICATION CATEGORIE
# =========================================================
def classify_category(text, categories):
    best_score = 0
    best_category = None
    text_lower = normalize_text(text)

    for cat in categories:
        keywords = normalize_text(cat["keywords"]).split(",")
        keywords = [k.strip() for k in keywords if k.strip()]

        matches = sum(1 for k in keywords if k in text_lower)
        score = matches / len(keywords) if keywords else 0

        if score > best_score:
            best_score = score
            best_category = cat

    return best_category, round(best_score, 2)


# =========================================================
# DETECTION CACHEt PAR OCR CIBLE
# =========================================================

# ===============================
# 📌 Charger image (PNG/JPG/PDF)
# ===============================

# ---------------------------
# Chargement des templates
# ---------------------------

def load_stamp_templates(stamp_json_path):
    with open(stamp_json_path, "r", encoding="utf-8") as f:
        stamps = json.load(f)

    templates = {}

    for stamp in stamps:
        service_id = stamp["service_id"]
        file_name = stamp["file_name"]

        if not os.path.exists(file_name):
            continue

        img = cv2.imread(file_name, 0)
        if img is not None:
            templates[service_id] = img

    return templates


# ---------------------------
# Extraction zone probable cachet
# ---------------------------

def extract_stamp_candidate(img):

    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

    # Améliorer contraste
    clahe = cv2.createCLAHE(clipLimit=3.0, tileGridSize=(8,8))
    enhanced = clahe.apply(gray)

    # Détection de contours
    edges = cv2.Canny(enhanced, 50, 150)

    contours, _ = cv2.findContours(edges, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    candidates = []

    for cnt in contours:
        area = cv2.contourArea(cnt)

        if area < 1500:
            continue

        x, y, w, h = cv2.boundingRect(cnt)

        ratio = w / float(h)

        # On cherche formes quasi carrées / circulaires
        if 0.5 < ratio < 1.5:
            candidates.append((x, y, w, h))

    if not candidates:
        return None

    # Prendre la plus grande
    x, y, w, h = max(candidates, key=lambda b: b[2]*b[3])

    return gray[y:y+h, x:x+w]


# ---------------------------
# Matching ORB
# ---------------------------

def match_stamp(candidate, templates):

    orb = cv2.ORB_create(1500)

    kp1, des1 = orb.detectAndCompute(candidate, None)

    if des1 is None:
        return None, 0.0

    bf = cv2.BFMatcher(cv2.NORM_HAMMING, crossCheck=True)

    best_score = 0
    best_service = None

    for service_id, template in templates.items():

        kp2, des2 = orb.detectAndCompute(template, None)

        if des2 is None:
            continue

        matches = bf.match(des1, des2)

        if not matches:
            continue

        score = len(matches)

        if score > best_score:
            best_score = score
            best_service = service_id

    normalized_score = min(best_score / 50.0, 1.0)

    return best_service, normalized_score


# ---------------------------
# MAIN DETECT STAMP
# ---------------------------

def detect_stamps(image_path, stamps):

    ext = image_path.lower().split(".")[-1]

    # ===============================
    # Charger image correctement
    # ===============================
    if ext == "pdf":

        doc = fitz.open(image_path)
        page = doc[0]

        zoom = 3
        mat = fitz.Matrix(zoom, zoom)
        pix = page.get_pixmap(matrix=mat)

        img = np.frombuffer(pix.samples, dtype=np.uint8)
        img = img.reshape(pix.height, pix.width, pix.n)

        if pix.n == 4:
            img = cv2.cvtColor(img, cv2.COLOR_RGBA2BGR)
        else:
            img = cv2.cvtColor(img, cv2.COLOR_RGB2BGR)

        doc.close()

    else:
        img = cv2.imread(image_path)

    if img is None:
        return None, 0.0

    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    gray = cv2.GaussianBlur(gray, (3, 3), 0)
    gray = cv2.equalizeHist(gray)

    best_service = None
    best_score = 0

    scales = np.linspace(0.3, 2.5, 30)

    for stamp in stamps:

        # ✅ CHEMIN ABSOLU CORRECT
        template_path = os.path.join(STAMPS_DIR, stamp["file_name"])
        template_original = cv2.imread(template_path, 0)

        # 🔥 SÉCURITÉ IMPORTANTE
        if template_original is None:
            print("Impossible de charger :", template_path)
            continue

        template_original = cv2.GaussianBlur(template_original, (3, 3), 0)
        template_original = cv2.equalizeHist(template_original)

        for scale in scales:

            new_w = int(template_original.shape[1] * scale)
            new_h = int(template_original.shape[0] * scale)

            if new_w < 20 or new_h < 20:
                continue

            template = cv2.resize(template_original, (new_w, new_h))

            if template.shape[0] > gray.shape[0] or template.shape[1] > gray.shape[1]:
                continue

            result = cv2.matchTemplate(gray, template, cv2.TM_CCOEFF_NORMED)
            _, max_val, _, _ = cv2.minMaxLoc(result)

            if max_val > best_score:
                best_score = max_val
                best_service = stamp["service_id"]

    if best_score < 0.45:
        return None, 0.0

    print("BEST SERVICE:", best_service)
    print("BEST SCORE:", best_score)

    return best_service, float(best_score)


# =========================================================
# SCORE GLOBAL
# =========================================================
def compute_global_score(cat_score, service_score, ocr_score):
    return round(
        (0.35 * cat_score) +
        (0.45 * service_score) +
        (0.20 * ocr_score),
        2
    )


def confidence_level(score):
    if score >= 0.80:
        return "HIGH"
    elif score >= 0.55:
        return "MEDIUM"
    return "LOW"


# =========================================================
# MAIN
# =========================================================
def main():

    file_path = sys.argv[1]
    categories_file = sys.argv[2]
    stamps_file = sys.argv[3]

    with open(categories_file, "r", encoding="utf-8") as f:
        categories = json.load(f)

    with open(stamps_file, "r", encoding="utf-8") as f:
        stamps = json.load(f)

    text, ocr_score = extract_text(file_path)
    category, cat_score = classify_category(text, categories)

    if not category:
        print(json.dumps({"status": "error"}))
        sys.exit(0)

    category_id = category["id"]
    default_service_id = category.get("default_service_id")

    service_id, stamp_score = detect_stamps(file_path, stamps)

    if not service_id:
        service_id = default_service_id

    is_incoherent = (
        service_id is not None
        and default_service_id is not None
        and service_id != default_service_id
    )

    print("DETECTED SERVICE:", service_id)
    print("STAMP SCORE:", stamp_score)

    global_score = compute_global_score(cat_score, stamp_score, ocr_score)
    level = confidence_level(global_score)

    result = {
        "status": "success",
        "extracted_text": text,
        "confidence_score": ocr_score,
        "score_global": global_score,
        "detected_category_id": category_id,
        "detected_service_id": service_id,
        "category_confidence": cat_score,
        "service_confidence": stamp_score,
        "confidence_level": level,
        "is_incoherent": is_incoherent
    }

    print("REAL STAMP SCORE:", stamp_score)

    sys.stdout.write(json.dumps(result, ensure_ascii=False))
    sys.stdout.flush()


if __name__ == "__main__":
    main()