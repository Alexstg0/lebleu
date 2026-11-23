#!/usr/bin/env python3
"""Compara pares next__X.png / php__X.png de una carpeta y reporta el % de píxeles distintos.
Guarda diff__X.png (rojo = distinto). Uso: python3 pruebas/comparar_capturas.py carpeta"""
import sys, os
from PIL import Image, ImageChops

carpeta = sys.argv[1]
UMBRAL = 24  # diferencia por canal que se considera "distinto" (absorbe el suavizado de fuentes)
pares = sorted(f[len('next__'):] for f in os.listdir(carpeta) if f.startswith('next__'))
for nombre in pares:
    a = Image.open(f'{carpeta}/next__{nombre}').convert('RGB')
    b = Image.open(f'{carpeta}/php__{nombre}').convert('RGB')
    if a.size != b.size:
        print(f'{nombre[:-4]:34s} TAMAÑO DISTINTO next={a.size} php={b.size}')
        h = min(a.height, b.height)
        a, b = a.crop((0, 0, min(a.width, b.width), h)), b.crop((0, 0, min(a.width, b.width), h))
    d = ImageChops.difference(a, b).convert('L').point(lambda v: 255 if v > UMBRAL else 0)
    malos = sum(1 for v in d.getdata() if v)
    pct = 100 * malos / (d.width * d.height)
    sup = b.copy(); sup.paste((255, 0, 0), mask=d); sup.save(f'{carpeta}/diff__{nombre}')
    caja = d.getbbox()
    print(f'{nombre[:-4]:34s} {pct:6.3f}% distinto' + (f'  zona {caja}' if caja else '  idéntico'))
