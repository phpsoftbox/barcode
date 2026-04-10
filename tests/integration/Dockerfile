FROM python:3.12-slim-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libdmtx0b libcairo2 poppler-utils \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt /tmp/requirements.txt
RUN pip install --no-cache-dir -r /tmp/requirements.txt

COPY datamatrix_decode_test.py /tests/datamatrix_decode_test.py
ENTRYPOINT ["python", "/tests/datamatrix_decode_test.py"]
