<?php
namespace Lebleu\Core;

final class Ruteador
{
    /** @var array<int, array{string, string, array}> */
    private array $rutas = [];

    public function agregar(string $metodo, string $patron, array $manejador): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $patron) . '$#';
        $this->rutas[] = [strtoupper($metodo), $regex, $manejador];
    }

    public function despachar(Peticion $p): Respuesta
    {
        $rutaExiste = false;
        foreach ($this->rutas as [$metodo, $regex, $manejador]) {
            if (!preg_match($regex, $p->ruta, $m)) {
                continue;
            }
            $rutaExiste = true;
            if ($metodo !== $p->metodo) {
                continue;
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
