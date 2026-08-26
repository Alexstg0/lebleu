-- Autenticación y roles (idempotente: se ejecuta en cada arranque).

CREATE TABLE IF NOT EXISTS usuarios (
    id            SERIAL PRIMARY KEY,
    nombre        TEXT        NOT NULL,
    email         TEXT        NOT NULL UNIQUE,
    password_hash TEXT        NOT NULL,
    rol           TEXT        NOT NULL CHECK (rol IN ('admin', 'capitan', 'socio')),
    socio_id      INT         REFERENCES socios(id),
    activo        BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS sesiones (
    token       TEXT        PRIMARY KEY,
    usuario_id  INT         NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    expires_at  TIMESTAMPTZ NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
