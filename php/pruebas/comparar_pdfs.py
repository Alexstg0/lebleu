#!/usr/bin/env python3
"""Compara los PDF de la referencia (Next/pdf-lib) con los de PHP: páginas, tamaño y píxeles de cada página.
Uso: python3 pruebas/comparar_pdfs.py carpeta_next carpeta_php [dpi]"""
import sys, os, subprocess, tempfile, glob
from PIL import Image, ImageChops

a_dir, b_dir = sys.argv[1], sys.argv[2]
dpi = sys.argv[3] if len(sys.argv) > 3 else '60'
UMBRAL = 40

def rasterizar(pdf, destino):
    subprocess.run(['pdftoppm', '-r', dpi, '-png', pdf, destino], check=True, stderr=subprocess.DEVNULL)
    return sorted(glob.glob(destino + '-*.png'), key=lambda f: int(f.rsplit('-', 1)[1][:-4]))

import re
def normalizar(t):
    # Desviaciones documentadas (docs/MIGRACION-PHP.md): fecha de hoy con la hora de la embarcación (el original usa la del servidor)
    # y la mitad de un monto con centavos impares (el original la redondea con coma flotante; PHP es exacto).
    t = re.sub(r'Fecha: \d+ de \w+ de \d{4}', 'Fecha: HOY', t)
    t = re.sub(r'(50% c/u: |50% por socio\s+)\$[\d,]+\.\d\d', r'\1$X', t)
    return ' '.join(t.split())

total = malos = 0
for ruta in sorted(glob.glob(a_dir + '/*.pdf')):
    nombre = os.path.basename(ruta)[:-4]
    otra = f'{b_dir}/{nombre}.pdf'
    if not os.path.exists(otra):
        print(f'{nombre:34s} FALTA en PHP'); malos += 1; continue
    with tempfile.TemporaryDirectory() as t:
        A = rasterizar(ruta, t + '/a'); B = rasterizar(otra, t + '/b')
        ta = subprocess.run(['pdftotext', '-layout', ruta, '-'], capture_output=True, text=True).stdout
        tb = subprocess.run(['pdftotext', '-layout', otra, '-'], capture_output=True, text=True).stdout
        pa = [normalizar(x) for x in ta.split('\f')]; pb = [normalizar(x) for x in tb.split('\f')]
        if len(A) != len(B):
            print(f'{nombre:34s} PÁGINAS next={len(A)} php={len(B)}'); malos += 1; continue
        peor = 0.0; pag_peor = 0; tam = False
        for i, (x, y) in enumerate(zip(A, B), 1):
            ia, ib = Image.open(x).convert('L'), Image.open(y).convert('L')
            if ia.size != ib.size:
                tam = True; break
            d = ImageChops.difference(ia, ib).point(lambda v: 255 if v > UMBRAL else 0)
            pct = 100 * sum(1 for v in d.getdata() if v) / (d.width * d.height)
            if pct > peor: peor, pag_peor = pct, i
            total += 1
        igual_texto = pa == pb
        if (tam or peor > 0.05 or not igual_texto) and pa != pb and sorted(pa) == sorted(pb):
            # El original no ordena los comprobantes de un mismo gasto (el orden queda al azar): mismas páginas, otro orden.
            print(f'{nombre:34s} mismas páginas, distinto orden (empate sin orden en el original)')
            continue
        estado = 'TAMAÑO' if tam else f'{peor:6.3f}% (pág {pag_peor}/{len(A)})' + ('' if igual_texto else '  TEXTO DISTINTO')
        if tam or peor > 0.05 or not igual_texto:
            malos += 1
            if not igual_texto:
                import difflib
                for l in list(difflib.unified_diff(ta.splitlines(), tb.splitlines(), 'next', 'php', lineterm='', n=0))[:8]:
                    print('      ' + l)
        print(f'{nombre:34s} {estado}')
print(f'\nPáginas comparadas: {total} · archivos con diferencia notable: {malos}')
