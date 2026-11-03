"""Render the isolated album fixture and check that private values do not enter its text."""
from pathlib import Path
import sys

root = Path(__file__).resolve().parent
sys.path.insert(0, str(root / ".runtime" / "python"))
import pypdfium2 as pdfium

document = pdfium.PdfDocument(root / ".runtime" / "album-layout-qa.pdf")
assert len(document) == 2, f"Expected two physical pages, got {len(document)}"
for number, page in enumerate(document):
    text = page.get_textpage().get_text_range()
    assert "SECRET-" not in text, "Private data leaked into album"
    assert "Selected covers and stamps" in text, "Album header missing"
    output = root / ".runtime" / f"album-qa-page-{number + 1}.png"
    page.render(scale=1.5).to_pil().save(output)
    print(f"Rendered {output}")
