<?php
// Arranque: autocarga sin Composer (Lebleu\Core\X -> app/Core/X.php), zona horaria y errores.
spl_autoload_register(function (string $clase): void {
    if (!str_starts_with($clase, 'Lebleu\\')) {
        return;
    }
    $archivo = __DIR__ . '/' . str_replace('\\', '/', substr($clase, 7)) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});

use Lebleu\Core\Config;

date_default_timezone_set((string) Config::obtener('zona_horaria', 'America/Mazatlan'));
ini_set('display_errors', '0');
error_reporting(E_ALL);
