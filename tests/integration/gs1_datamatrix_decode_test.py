"""Independent GS1 DataMatrix regression: symbology identifier and exact bytes.

A leading FNC1 codeword makes the symbol GS1 DataMatrix, which decoders report as ]d2
instead of ]d1. The GS separators inside the data must survive as 0x1D.
"""

import base64
import io
import json
import sys
import unittest

import cairosvg
from PIL import Image
import zxingcpp


CASES = [json.loads(line) for line in sys.stdin if line.strip()]


class Gs1DataMatrixDecodeTest(unittest.TestCase):
    """Check the symbology identifier and byte preservation for marking codes."""

    def test_all_cases_are_present(self):
        """Reject an empty or truncated producer stream instead of silently passing."""
        self.assertEqual(4, len(CASES))
        self.assertEqual(4, len({case["name"] for case in CASES}))

    def assert_decodes(self, image, case):
        """Require ZXing-C++ to report the expected symbology and the original bytes."""
        results = zxingcpp.read_barcodes(
            image.convert("RGB"), formats=zxingcpp.BarcodeFormat.DataMatrix, return_errors=True
        )
        self.assertEqual(1, len(results))
        self.assertTrue(results[0].valid, str(results[0].error))
        self.assertEqual(base64.b64decode(case["payload"]), results[0].bytes)
        self.assertEqual(case["symbology"], results[0].symbology_identifier)

    def test_images_decode(self):
        """Decode native PNG and rasterized SVG for every marking code."""
        for case in CASES:
            images = case["images"]
            with self.subTest(case=case["name"], format="png"):
                self.assert_decodes(Image.open(io.BytesIO(base64.b64decode(images["png"]))), case)
            with self.subTest(case=case["name"], format="svg"):
                png = cairosvg.svg2png(bytestring=base64.b64decode(images["svg"]))
                self.assert_decodes(Image.open(io.BytesIO(png)), case)


if __name__ == "__main__":
    unittest.main(verbosity=2)
