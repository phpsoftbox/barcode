"""Independent QR regression: raw matrices, native PNG and rasterized SVG, with and without a logo.

Every image must be read by two independent engines, ZXing-C++ and ZBar, as exactly the original bytes.
"""

import base64
import io
import json
import sys
import unittest

import cairosvg
from PIL import Image
from pyzbar import pyzbar
import zxingcpp


CASES = [json.loads(line) for line in sys.stdin if line.strip()]

EXPECTED_VERSIONS = {
    "short-m": 1,
    "short-h": 2,
    "utf8-m": 4,
    "v10-h-full": 10,
    "v11-h-first": 11,
    "v26-m-full": 26,
    "v27-m-first": 27,
    "v40-m-full": 40,
    "v40-h-full": 40,
    "subscription-url": 11,
    "subscription-url-logo": 11,
    "subscription-url-192css": 11,
    "long-url-logo": 18,
    "subscription-url-large-logo": 11,
}


def matrix_image(matrix, scale=4, quiet=4):
    """Draw the encoder matrix without the PHP renderer: black modules, four-module quiet zone."""
    size = len(matrix)
    side = (size + quiet * 2) * scale
    image = Image.new("L", (side, side), 255)
    pixels = image.load()
    for y, row in enumerate(matrix):
        for x, dark in enumerate(row):
            if dark:
                for dy in range(scale):
                    for dx in range(scale):
                        pixels[(x + quiet) * scale + dx, (y + quiet) * scale + dy] = 0
    return image


def svg_image(svg, width=None):
    """Rasterize SVG with CairoSVG at its own size or at a device width, e.g. 192 CSS px at 1x, 2x, 3x."""
    png = cairosvg.svg2png(bytestring=svg, output_width=width, output_height=width)
    image = Image.open(io.BytesIO(png))
    # Прозрачный фон SVG кладём на белый, как в браузере на светлой странице.
    background = Image.new("RGB", image.size, (255, 255, 255))
    background.paste(image, mask=image.convert("RGBA").split()[3])
    return background


class QrDecodeTest(unittest.TestCase):
    """Check that real decoders recover exactly the original bytes."""

    def test_all_cases_are_present(self):
        """Reject an empty or truncated producer stream instead of silently passing."""
        self.assertEqual(sorted(EXPECTED_VERSIONS), sorted(case["name"] for case in CASES))

    def test_versions(self):
        """Ensure capacity boundaries and long URLs really use the expected QR versions."""
        for case in CASES:
            with self.subTest(case=case["name"]):
                self.assertEqual(EXPECTED_VERSIONS[case["name"]], case["version"])

    def assert_decodes(self, image, expected):
        """Require ZXing-C++ and ZBar to read the same bytes."""
        image = image.convert("RGB")
        with self.subTest(decoder="ZXing-C++"):
            results = zxingcpp.read_barcodes(image, formats=zxingcpp.BarcodeFormat.QRCode, return_errors=True)
            self.assertEqual(1, len(results))
            self.assertTrue(results[0].valid, str(results[0].error))
            self.assertEqual(expected, results[0].bytes)
        with self.subTest(decoder="ZBar"):
            results = pyzbar.decode(image, symbols=[pyzbar.ZBarSymbol.QRCODE])
            self.assertEqual(1, len(results))
            # ZBar отдаёт байты в своей кодировке по умолчанию; сверяем текст, декодированный как UTF-8.
            self.assertEqual(expected.decode("utf-8"), results[0].data.decode("utf-8"))

    def test_matrix_and_images_decode(self):
        """Decode raw matrices (without a logo), native PNG and rasterized SVG for every case."""
        for case in CASES:
            expected = base64.b64decode(case["payload"])
            images = case["images"]
            if case["matrix"] is not None:
                with self.subTest(case=case["name"], format="matrix"):
                    self.assert_decodes(matrix_image(case["matrix"]), expected)
            with self.subTest(case=case["name"], format="png"):
                self.assert_decodes(Image.open(io.BytesIO(base64.b64decode(images["png"]))), expected)
            svg = base64.b64decode(images["svg"])
            for width in case["rasterWidths"] or [None]:
                with self.subTest(case=case["name"], format="svg", width=width):
                    self.assert_decodes(svg_image(svg, width), expected)


if __name__ == "__main__":
    unittest.main(verbosity=2)
