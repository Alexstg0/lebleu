<?php
// Copia este archivo como config/config.php y ajusta. config.php NO se sube al repositorio.
return [
    'db' => [
        'host'    => '127.0.0.1',
        'puerto'  => 3306,
        'nombre'  => 'lebleu',
        'usuario' => 'lebleu',
        'clave'   => 'cambiar',
    ],
    // Prefijo de URL si la app vive en una subcarpeta (ej. '/lebleu' en XAMPP). En producción ''.
    'base_url'    => '',
    'zona_horaria' => 'America/Mazatlan',
];
