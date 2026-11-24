#!/usr/bin/env bash
# Corre pruebas/interfaz_captura.mjs e interfaz_fase4.mjs contra una COPIA desechable de la base (la real no se toca).
# Requisitos: mysql/mysqldump con acceso a la base `lebleu`, PHP, Node y Playwright; un admin y un capitán de prueba.
# Uso:  PRUEBA_EMAIL=... PRUEBA_CAPITAN=... PRUEBA_CLAVE=... bash pruebas/interfaz_captura.sh
set -euo pipefail
cd "$(dirname "$0")/.."
COPIA=lebleu_ui_prueba
PUERTO=8098
mysql -e "DROP DATABASE IF EXISTS $COPIA; CREATE DATABASE $COPIA CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON $COPIA.* TO 'lebleu'@'localhost';"
mysqldump --single-transaction --routines --triggers lebleu | mysql "$COPIA"
CONFIG="$(mktemp)"
sed "s/'nombre' => '[^']*'/'nombre' => '$COPIA'/" config/config.php > "$CONFIG"
LEBLEU_CONFIG="$CONFIG" php -S "127.0.0.1:$PUERTO" -t public public/index.php >/tmp/php_ui.log 2>&1 &
SERVIDOR=$!
trap 'kill $SERVIDOR 2>/dev/null; mysql -e "DROP DATABASE IF EXISTS $COPIA"; rm -f "$CONFIG"' EXIT
sleep 1
# Archivos de prueba para los respaldos (un PDF y una foto PNG)
ARCHIVOS="$(mktemp -d)"
php -r 'require "vendor/fpdf/fpdf.php"; $p=new FPDF(); $p->AddPage(); $p->SetFont("Helvetica","",14); $p->Cell(0,10,"Soporte de prueba"); $p->Output("F", $argv[1]."/soporte.pdf");' "$ARCHIVOS"
php -r '$i=imagecreatetruecolor(320,200); imagefill($i,0,0,imagecolorallocate($i,10,120,180)); imagepng($i,$argv[1]."/foto.png");' "$ARCHIVOS"
export PRUEBA_PDF="$ARCHIVOS/soporte.pdf" PRUEBA_PNG="$ARCHIVOS/foto.png"
PHP_URL="http://127.0.0.1:$PUERTO" node pruebas/interfaz_captura.mjs
PHP_URL="http://127.0.0.1:$PUERTO" node pruebas/interfaz_fase4.mjs
