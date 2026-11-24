#!/usr/bin/env bash
# Paridad de PDF: genera los reportes con el código ORIGINAL (Node + pdf-lib, sobre Postgres) y con PHP (sobre MySQL/MariaDB),
# con copias desechables de ambas bases, y los compara página por página (píxeles y texto).
# Requisitos: PG_URL (Postgres de referencia con los datos), mysql con la base `lebleu`, Node con `npm install --no-save` en la
# raíz del repo, Python con Pillow, y poppler (pdftoppm, pdftotext). Uso: PG_URL=postgres://... bash pruebas/paridad_pdfs.sh
set -euo pipefail
cd "$(dirname "$0")/.."
RAIZ="$(cd .. && pwd)"
: "${PG_URL:?Falta PG_URL (Postgres de referencia)}"
PY="${PYTHON:-python3}"
SAL="$(mktemp -d)"
PG_BASE="${PG_URL%/*}"
psql "$PG_BASE/postgres" -qc "DROP DATABASE IF EXISTS lebleu_pdf" -c "CREATE DATABASE lebleu_pdf TEMPLATE ${PG_URL##*/}"
mysql -e "DROP DATABASE IF EXISTS lebleu_pdf_prueba; CREATE DATABASE lebleu_pdf_prueba CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON lebleu_pdf_prueba.* TO 'lebleu'@'localhost';"
mysqldump --single-transaction --routines --triggers lebleu | mysql lebleu_pdf_prueba
CONFIG="$(mktemp)"
sed "s/'nombre' => '[^']*'/'nombre' => 'lebleu_pdf_prueba'/" config/config.php > "$CONFIG"
trap 'rm -f "$CONFIG"; mysql -e "DROP DATABASE IF EXISTS lebleu_pdf_prueba"; psql "$PG_BASE/postgres" -qc "DROP DATABASE IF EXISTS lebleu_pdf"' EXIT
(cd "$RAIZ" && DATABASE_URL="$PG_BASE/lebleu_pdf" SALIDA="$SAL/next" node --experimental-strip-types --import ./php/pruebas/referencia/registro.mjs php/pruebas/referencia/pdfs.mjs 2>&1 | grep -v -E "Warning|Reparsing|eliminate|trace-warnings" || true)
LEBLEU_CONFIG="$CONFIG" SALIDA="$SAL/php" php -d memory_limit=1G pruebas/pdfs_php.php
"$PY" -W ignore pruebas/comparar_pdfs.py "$SAL/next" "$SAL/php" "${DPI:-60}" | tee "$SAL/resultado.txt"
! grep -q -E "PÁGINAS|TAMAÑO|TEXTO DISTINTO|FALTA" "$SAL/resultado.txt"
