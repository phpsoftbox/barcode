"""Independent Code 128 regression: PNG, rasterized SVG and SVG embedded in PDF.

ZXing validates the mandatory mod 103 check symbol, so a wrong checksum fails here.
"""

import base64
import io
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

import cairosvg
from PIL import Image
import zxingcpp


CASES = [json.loads(line) for line in sys.stdin if line.strip()]


class Code128DecodeTest(unittest.TestCase):
    """Check that a real decoder recovers exactly the expected text."""

    def test_all_cases_are_present(self):
        """Reject an empty or truncated producer stream instead of silently passing."""
        self.assertEqual(11, len(CASES))
        self.assertEqual(11, len({case["name"] for case in CASES}))
        self.assertEqual("text", CASES[0]["name"])

    def assert_decodes(self, image, case):
        """Require ZXing-C++ to read the barcode as the expected text."""
        results = zxingcpp.read_barcodes(
            image.convert("RGB"), formats=zxingcpp.BarcodeFormat.Code128, return_errors=True
        )
        self.assertEqual(1, len(results))
        self.assertTrue(results[0].valid, str(results[0].error))
        self.assertEqual(base64.b64decode(case["expected"]).decode("ascii"), results[0].text)

    def test_images_decode(self):
        """Decode native PNG and rasterized SVG for every case."""
        for case in CASES:
            images = case["images"]
            with self.subTest(case=case["name"], format="png"):
                self.assert_decodes(Image.open(io.BytesIO(base64.b64decode(images["png"]))), case)
            with self.subTest(case=case["name"], format="svg"):
                png = cairosvg.svg2png(bytestring=base64.b64decode(images["svg"]))
                self.assert_decodes(Image.open(io.BytesIO(png)), case)

    def test_pdf_decodes(self):
        """Decode SVG embedded in PDF after Poppler rasterization at 300 DPI."""
        for case in CASES:
            with self.subTest(case=case["name"]), tempfile.TemporaryDirectory() as directory:
                pdf = Path(directory) / "barcode.pdf"
                output = Path(directory) / "page"
                cairosvg.svg2pdf(bytestring=base64.b64decode(case["images"]["svg"]), write_to=str(pdf))
                subprocess.run(
                    ["pdftoppm", "-singlefile", "-r", "300", "-png", str(pdf), str(output)],
                    check=True, capture_output=True, timeout=30,
                )
                self.assert_decodes(Image.open(output.with_suffix(".png")), case)


if __name__ == "__main__":
    unittest.main(verbosity=2)
