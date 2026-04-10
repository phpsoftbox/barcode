"""Independent ECC200 regression: PHP matrices, SVG, PNG and rasterized PDF."""

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
from pylibdmtx.pylibdmtx import decode, encode
import zxingcpp


CASES = [json.loads(line) for line in sys.stdin if line.strip()]


def matrix_image(matrix):
    """Render raw modules independently of the PHP renderer, with a quiet zone."""
    size = len(matrix)
    image = Image.new("L", (size + 8, size + 8), 255)
    for y, row in enumerate(matrix):
        for x, dark in enumerate(row):
            image.putpixel((x + 4, y + 4), 0 if dark else 255)
    return image.resize(((size + 8) * 4,) * 2, Image.Resampling.NEAREST)


class DataMatrixDecodeTest(unittest.TestCase):
    """Check byte preservation using two different decoder implementations."""

    def test_all_cases_are_present(self):
        """Reject an empty or truncated producer stream instead of silently passing."""
        self.assertEqual(61, len(CASES))
        self.assertEqual(61, len({case["name"] for case in CASES}))
        self.assertEqual("regression-36", CASES[0]["name"])

    def assert_decodes(self, image, expected):
        """Require both engines to recover exactly the original bytes, including GS."""
        image = image.convert("RGB")
        with self.subTest(decoder="ZXing-C++"):
            results = zxingcpp.read_barcodes(
                image, formats=zxingcpp.BarcodeFormat.DataMatrix, return_errors=True
            )
            self.assertEqual(1, len(results))
            self.assertTrue(results[0].valid, str(results[0].error))
            self.assertEqual(expected, results[0].bytes)
        with self.subTest(decoder="libdmtx"):
            results = decode(image, timeout=5000, max_count=1)
            self.assertEqual(1, len(results))
            self.assertEqual(expected, results[0].data)

    def test_matrix_and_images_decode(self):
        """Decode raw matrices, native PNG and rasterized SVG for every capacity case."""
        for case in CASES:
            expected = base64.b64decode(case["payload"])
            images = case["images"]
            with self.subTest(case=case["name"], format="matrix"):
                self.assertEqual(case["size"], len(case["matrix"]))
                self.assert_decodes(matrix_image(case["matrix"]), expected)
            with self.subTest(case=case["name"], format="png"):
                self.assert_decodes(Image.open(io.BytesIO(base64.b64decode(images["png"]))), expected)
            with self.subTest(case=case["name"], format="svg"):
                png = cairosvg.svg2png(bytestring=base64.b64decode(images["svg"]))
                self.assert_decodes(Image.open(io.BytesIO(png)), expected)

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
                self.assert_decodes(Image.open(output.with_suffix(".png")), base64.b64decode(case["payload"]))

    def test_matches_independent_ascii_encoder(self):
        """Compare every module with libdmtx using the same ASCII mode and symbol size."""
        for case in CASES:
            with self.subTest(case=case["name"]):
                size = case["size"]
                reference = encode(base64.b64decode(case["payload"]), scheme="Ascii", size=f"{size}x{size}")
                image = Image.frombytes("RGB", (reference.width, reference.height), reference.pixels)
                # libdmtx defaults: 5 pixels per module and a 10-pixel margin.
                self.assertEqual(size * 5 + 20, image.width)
                self.assertEqual(image.width, image.height)
                matrix = [
                    [image.getpixel((10 + x * 5 + 2, 10 + y * 5 + 2))[0] == 0 for x in range(size)]
                    for y in range(size)
                ]
                self.assertEqual(matrix, case["matrix"])


if __name__ == "__main__":
    unittest.main(verbosity=2)
