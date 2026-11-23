<?php
// Crea el administrador inicial si no existe ninguno.
// Uso:  ADMIN_EMAIL=correo ADMIN_PW=minimo-8-caracteres php scripts/crear_admin.php
require __DIR__ . '/../app/arranque.php';

use Lebleu\Core\Clave;
use Lebleu\Core\Db;

$email = trim((string) getenv('ADMIN_EMAIL'));
$pw = (string) getenv('ADMIN_PW');
$nombre = (string) (getenv('ADMIN_NOMBRE') ?: 'Administrador');

if ($email === '' || strlen($pw) < 8) {
    fwrite(STDERR, "Faltan ADMIN_EMAIL y/o ADMIN_PW (mínimo 8 caracteres).\n");
    exit(1);
}
if (Db::fila("SELECT 1 FROM usuarios WHERE rol = 'admin' LIMIT 1")) {
    echo "Ya existe un administrador; no se crea otro.\n";
    exit(0);
}
Db::ejecutar(
    "INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, 'admin')",
    [$nombre, $email, Clave::crear($pw)]
);
echo "Administrador creado: $email\n";
