#!/usr/bin/env bash
# Corre todas las pruebas que no necesitan navegador. Con PG_URL también corre las de cuadre contra Postgres.
# Uso:  bash pruebas/correr_todas.sh            (o con PG_URL=postgres://... para incluir el cuadre)
cd "$(dirname "$0")/.."
fallos=0
correr() { echo "== $1"; shift; "$@" || fallos=$((fallos+1)); echo; }
correr "Tamaño de archivos" php pruebas/tamano_de_archivos.php
correr "Capas (SQL solo en Repositorios)" php pruebas/capas.php
correr "Periodos" php pruebas/periodos.php
correr "Captura (alta, edición, baja, permisos, auditoría)" php pruebas/captura.php
if [ -n "${PG_URL:-}" ]; then
  correr "Cuadre de tablas y vistas contra Postgres" env PG_DSN="${PG_DSN:-}" php pruebas/paridad_postgres.php
  correr "Estado de cuenta contra la lógica original" bash pruebas/paridad_estado_cuenta.sh
else
  echo "(sin PG_URL: se omiten las pruebas de cuadre contra Postgres)"
fi
[ "$fallos" -eq 0 ] && echo "TODAS LAS PRUEBAS OK" || { echo "$fallos grupo(s) de pruebas fallaron"; exit 1; }
