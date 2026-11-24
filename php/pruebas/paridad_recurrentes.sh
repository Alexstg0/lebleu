#!/usr/bin/env bash
# Cargos recurrentes: la lógica ORIGINAL (Postgres) contra la versión PHP (MySQL) para varias fechas de "hoy".
# El original escribe en Postgres: se usa una copia desechable. Requisitos: PG_ADMIN_URL (superusuario) y PG_URL de referencia.
# Uso:  PG_ADMIN_DB=lebleu_ref bash pruebas/paridad_recurrentes.sh
set -euo pipefail
cd "$(dirname "$0")/.."
BASE="${PG_ADMIN_DB:-lebleu_ref}"; COPIA="${BASE}_recurrentes"
TMP="$(mktemp -d)"; ret=0
# El respaldo se cargó con ids explícitos: las secuencias de Postgres se alinean para que el original pueda insertar.
PGPASSWORD=lebleu_test psql -q -h 127.0.0.1 -U lebleu -d "$BASE" <<'SQL'
DO $$ DECLARE t text; BEGIN
  FOR t IN SELECT table_name FROM information_schema.columns WHERE table_schema='public' AND column_name='id' AND column_default LIKE 'nextval%' LOOP
    EXECUTE format('SELECT setval(pg_get_serial_sequence(%L, ''id''), COALESCE((SELECT MAX(id) FROM %I), 1))', t, t);
  END LOOP;
END $$;
SQL
for HOY in 2026-07-15 2026-08-26 2026-11-24; do
  PGPASSWORD=lebleu_test psql -q -h 127.0.0.1 -U lebleu -d postgres -c "DROP DATABASE IF EXISTS $COPIA" -c "CREATE DATABASE $COPIA TEMPLATE $BASE"
  DATABASE_URL="postgres://lebleu:lebleu_test@127.0.0.1/$COPIA" node --experimental-strip-types --no-warnings \
    --import ./pruebas/referencia/registro.mjs pruebas/referencia/recurrentes.mjs "$HOY" > "$TMP/ref.json"
  php pruebas/recurrentes_json.php "$HOY" > "$TMP/php.json"
  echo "--- hoy = $HOY ($(python3 -c "import json;d=json.load(open('$TMP/ref.json'));print(len(d['gastos']),'cargos en el original;',d['recibos_generados'],'recibos PDF')"))"
  python3 - "$TMP/ref.json" "$TMP/php.json" <<'PY'
import json,sys
r=json.load(open(sys.argv[1])); r.pop('recibos_generados',None)
json.dump(r,open(sys.argv[1],'w'))
PY
  php pruebas/comparar_json.php "$TMP/ref.json" "$TMP/php.json" || ret=1
done
PGPASSWORD=lebleu_test psql -q -h 127.0.0.1 -U lebleu -d postgres -c "DROP DATABASE IF EXISTS $COPIA"
exit $ret
