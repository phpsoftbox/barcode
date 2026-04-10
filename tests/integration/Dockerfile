FROM python:3.12-slim-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libdmtx0b libcairo2 poppler-utils \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt /tmp/requirements.txt
RUN pip install --no-cache-dir -r /tmp/requirements.txt

COPY datamatrix_decode_test.py /tests/datamatrix_decode_test.py
COPY code39_decode_test.py /tests/code39_decode_test.py
COPY code128_decode_test.py /tests/code128_decode_test.py
COPY gs1_datamatrix_decode_test.py /tests/gs1_datamatrix_decode_test.py

# Без аргументов запускается проверка DataMatrix; путь к другому тесту передаётся аргументом.
ENTRYPOINT ["python"]
CMD ["/tests/datamatrix_decode_test.py"]
