<?php
namespace Lebleu\Core;

final class Ruteador
{
    /** @var array<int, array{string, string, array, string}> */
    private array $rutas = [];

    public function agregar(string $metodo, string $patron, array $manejador): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $patron) . '$#';
        $this->rutas[] = [strtoupper($metodo), $regex, $manejador, $patron];
    }

    /** Rutas registradas (para el inventario de paridad con la versión original). @return array<int, array{0: string, 1: string}> */
    public function rutas(): array
    {
        return array_map(fn($r) => [$r[0], $r[3]], $this->rutas);
    }

    public function despachar(Peticion $p): Respuesta
    {
        $rutaExiste = false;
        foreach ($this->rutas as [$metodo, $regex, $manejador, ]) {
            if (!preg_match($regex, $p->ruta, $m)) {
                continue;
            }
            $rutaExiste = true;
            if ($metodo !== $p->metodo) {
                continue;
            }
            // Defensa extra contra CSRF: una escritura de la API enviada por otro sitio (aunque sea del mismo dominio padre) se rechaza.
            // Los navegadores modernos mandan Sec-Fetch-Site; las pruebas y los scripts de servidor no lo mandan y pasan.
            if ($p->esApi() && !in_array($p->metodo, ['GET', 'HEAD'], true)
                && in_array(strtolower($p->cabecera('Sec-Fetch-Site')), ['cross-site', 'same-site'], true)) {
                return Respuesta::error('Origen no permitido.', 403);
            }
            $args = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            [$clase, $accion] = $manejador;
            return (new $clase())->$accion($p, $args);
        }
        if ($rutaExiste) {
            return $p->esApi() ? Respuesta::error('Método no permitido.', 405)
                               : Respuesta::html('Método no permitido.', 405);
        }
        return $p->esApi() ? Respuesta::error('No encontrado.', 404)
                           : Respuesta::html('<h1>404</h1><p>Página no encontrada.</p>', 404);
    }
}
