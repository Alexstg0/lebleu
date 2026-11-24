#!/usr/bin/env bash
# Corre todas las pruebas que no necesitan navegador. Con PG_URL también corre las de cuadre contra Postgres.
# Uso:  bash pruebas/correr_todas.sh            (o con PG_URL=postgres://... para incluir el cuadre)
cd "$(dirname "$0")/.."
fallos=0
correr() { echo "== $1"; shift; "$@" || fallos=$((fallos+1)); echo; }
correr "Tamaño de archivos" php pruebas/tamano_de_archivos.php
correr "Capas (SQL solo en Repositorios)" php pruebas/capas.php
correr "Rutas: todo lo del original existe en PHP" php pruebas/rutas_paridad.php
correr "Roles: permisos de cada ruta contra el original" php pruebas/roles.php
correr "Periodos" php pruebas/periodos.php
correr "Captura (alta, edición, baja, permisos, auditoría)" php pruebas/captura.php
correr "Fase 4 (reservas, caja chica, usuarios, adjuntos, recurrentes, auditoría)" php pruebas/fase4.php
correr "Fase 5 (reportes PDF, fusionador, recibos, archivo al cerrar)" php -d memory_limit=1G pruebas/fase5.php
if [ -n "${PG_URL:-}" ]; then
  correr "Cuadre de tablas y vistas contra Postgres" env PG_DSN="${PG_DSN:-}" php pruebas/paridad_postgres.php
  correr "Estado de cuenta contra la lógica original" bash pruebas/paridad_estado_cuenta.sh
  correr "Cargos recurrentes contra la lógica original" bash pruebas/paridad_recurrentes.sh
  correr "Reportes PDF contra el original (página por página)" bash pruebas/paridad_pdfs.sh
  correr "Ensayo de corte (exportar, importar, cuadrar)" bash pruebas/ensayo_corte.sh
else
  echo "(sin PG_URL: se omiten las pruebas de cuadre contra Postgres)"
fi
[ "$fallos" -eq 0 ] && echo "TODAS LAS PRUEBAS OK" || { echo "$fallos grupo(s) de pruebas fallaron"; exit 1; }
