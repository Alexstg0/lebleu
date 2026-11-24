-- Un cargo recurrente (nómina, administrativo) no puede existir dos veces: la clave pasa a ser ÚNICA.
-- (MySQL admite varios NULL: los gastos normales no llevan clave.) Evita duplicados si el cron y el botón corren a la vez.
ALTER TABLE gastos_operativos DROP INDEX idx_operativos_clave, ADD UNIQUE KEY uq_operativos_clave (clave_recurrente);
