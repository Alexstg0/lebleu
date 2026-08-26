-- Reservas / agenda del calendario (idempotente: se ejecuta en cada arranque).
-- Independiente de la contabilidad: una reserva es una salida agendada.

CREATE TABLE IF NOT EXISTS reservas (
    id             SERIAL PRIMARY KEY,
    fecha          DATE         NOT NULL,
    hora           TIME,                       -- hora de salida
    cliente        TEXT,
    socio_id       INT          REFERENCES socios(id),
    num_personas   INT,
    duracion_horas NUMERIC(5,2),               -- se llena cuando el viaje ya ocurrió
    notas          TEXT,
    created_at     TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_reservas_fecha ON reservas(fecha);
