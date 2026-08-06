import re
import json
import pandas as pd
from pdf2image import convert_from_path
import pytesseract
from PIL import ImageFilter

def extract_plots_with_owners(pdf_path: str, dpi: int = 300):
    print(f"Konwertuję plik {pdf_path} na obrazy...")
    try:
        pages = convert_from_path(pdf_path, dpi=dpi)
    except Exception as e:
        print(f"Błąd konwersji PDF: {e}")
        return []

    full_text = ""
    print(f"Przetwarzam {len(pages)} stron przez OCR...")
    
    for i, page in enumerate(pages):
        processed_page = page.convert('L').filter(ImageFilter.SHARPEN)
        text = pytesseract.image_to_string(processed_page, lang='pol')
        full_text += f"\n--- STRONA_NR_{i + 1} ---\n{text}"

    # Szukamy bloków odpowiadających działkom
    # Uwzględniamy też potencjalny nagłówek sekcji podmiotów/właścicieli
    pattern = re.compile(
        r"(?i)(INFORMACJA O DZIAŁCE.*?(?:INFORMACJA O PODMIOTACH|DANE WŁAŚCICIELU|Właściciel|Podmiot).*?)(?=\n\s*[A-ZĄĆĘŁŃÓŚŹŻ]{8,}\s*\n|--- STRONA_NR_|\Z)", 
        re.DOTALL
    )
    
    matches = pattern.findall(full_text)
    
    # Jeśli powyższy regex okazał się za wąski, przechodzimy na bezpieczniejsze szukanie po każdej sekcji działki
    if not matches:
        pattern = re.compile(r"(?i)(INFORMACJA O DZIAŁCE.*?)(?=\n\s*[A-ZĄĆĘŁŃÓŚŹŻ]{8,}\s*\n|--- STRONA_NR_|\Z)", re.DOTALL)
        matches = pattern.findall(full_text)

    print(f"Znaleziono {len(matches)} sekcji działek.")
    
    all_records = []
    
    for idx, match_text in enumerate(matches):
        parsed_data = {"Nr wpisu": idx + 1}
        lines = match_text.split('\n')
        
        wlasciciel_lines = []
        capture_owner = False

        for line in lines:
            # Standardowe klucz-wartość
            if ':' in line and not capture_owner:
                key, val = line.split(':', 1)
                parsed_data[key.strip()] = val.strip()
            
            # Wychwytujemy linie powiązane z własnością, jeśli pojawią się w tekście
            if any(kw in line.upper() for kw in ["WŁAŚCICIEL", "WŁADAJĄCY", "PODMIOT", "UDZIAŁ"]):
                capture_owner = True
                wlasciciel_lines.append(line.strip())
            elif capture_owner and line.strip() == "":
                # Pusta linia może kończyć blok właściciela
                capture_owner = False
            elif capture_owner:
                wlasciciel_lines.append(line.strip())

        # Jeśli znaleziono informacje o właścicielu w postaci bloku tekstowego, łączymy je w jedną kolumnę
        if wlasciciel_lines:
            parsed_data["Wykryty Właściciel / Podmiot"] = " ".join(wlasciciel_lines)
        else:
            parsed_data["Wykryty Właściciel / Podmiot"] = "Brak / Niewykryto automatycznie"
                
        all_records.append(parsed_data)

    return all_records

if __name__ == "__main__":
    pdf_filename = "wielki_dokument.pdf"
    
    dane_dzialek = extract_plots_with_owners(pdf_filename)
    
    if dane_dzialek:
        df = pd.DataFrame(dane_dzialek)
        
        # Przenosimy kolumnę właściciela na początek (zaraz po numerze i identyfikatorze), aby była od razu widoczna
        cols = list(df.columns)
        if "Wykryty Właściciel / Podmiot" in cols:
            cols.insert(2, cols.pop(cols.index("Wykryty Właściciel / Podmiot")))
            df = df[cols]
            
        df.to_excel("dzialki_z_wlascicielami.xlsx", index=False)
        df.to_csv("dzialki_z_wlascicielami.csv", index=False, encoding='utf-8-sig')
        print("Zapisano pliki z uwzględnieniem danych właścicieli.")