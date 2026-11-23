<?php
namespace Lebleu\Core;

// Motor de plantillas mínimo: PHP puro con búfer de salida. Las plantillas escapan con Formato::e().
final class Vista
{
    public static function parcial(string $archivo, array $datos = []): string
    {
        extract($datos, EXTR_SKIP);
        ob_start();
        try {
            include $archivo;
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /** Envuelve $contenido en el documento HTML común. $scripts: archivos de public/recursos/js. */
    public static function pagina(string $titulo, string $contenido, array $scripts = [], string $claseCuerpo = ''): string
    {
        return self::parcial(dirname(__DIR__) . '/Compartido/vistas/layout.php', [
            'titulo' => $titulo, 'contenido' => $contenido, 'scripts' => $scripts, 'claseCuerpo' => $claseCuerpo,
        ]);
    }
}
