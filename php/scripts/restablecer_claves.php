<?php
// Genera una contraseña temporal para cada usuario activo y la muestra UNA sola vez.
// Uso:  php scripts/restablecer_claves.php
require __DIR__ . '/../app/arranque.php';

use Lebleu\Core\Clave;
use Lebleu\Core\Db;

foreach (Db::filas('SELECT id, nombre, email, rol FROM usuarios WHERE activo = 1 ORDER BY id') as $u) {
    $temporal = substr(strtr(base64_encode(random_bytes(9)), '+/=', 'xyz'), 0, 10) . '7';
    Db::ejecutar('UPDATE usuarios SET password_hash = ? WHERE id = ?', [Clave::crear($temporal), $u['id']]);
    Db::ejecutar('DELETE FROM sesiones WHERE usuario_id = ?', [$u['id']]);
    printf("%-8s %-28s %-28s %s\n", $u['rol'], $u['nombre'], $u['email'], $temporal);
}
echo "\nEntregue cada contraseña a su dueño. Estas contraseñas no se vuelven a mostrar.\n";
