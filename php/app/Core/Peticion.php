<?php
namespace Lebleu\Core;

final class Peticion
{
    public readonly string $metodo;
    public readonly string $ruta;
    private ?array $json = null;

    /**
     * Normalmente se arma sola desde la petición HTTP. Las pruebas pueden darle cuerpo JSON, cookies y
     * parámetros de consulta propios para ejercitar un controlador sin servidor web.
     */
    public function __construct(
        ?string $metodo = null,
        ?string $uri = null,
        ?array $json = null,
        private ?array $cookies = null,
        private ?array $parametros = null
    ) {
        $this->json = $json;
        $this->metodo = strtoupper($metodo ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        // Bajo IIS con URL Rewrite, REQUEST_URI puede traer la ruta reescrita (/index.php): la original viene en X-Original-URL.
        $ruta = parse_url($uri ?? ($_SERVER['HTTP_X_ORIGINAL_URL'] ?? $_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $base = rtrim((string) Config::obtener('base_url', ''), '/');
        if ($base !== '' && str_starts_with($ruta, $base)) {
            $ruta = substr($ruta, strlen($base)) ?: '/';
        }
        $this->ruta = '/' . trim(rawurldecode($ruta), '/');
    }

    public function esApi(): bool
    {
        return $this->ruta === '/api' || str_starts_with($this->ruta, '/api/');
    }

    public function json(): array
    {
        if ($this->json === null) {
            $dec = json_decode((string) file_get_contents('php://input'), true);
            $this->json = is_array($dec) ? $dec : [];
        }
        return $this->json;
    }

    public function consulta(string $clave, mixed $defecto = null): mixed
    {
        return ($this->parametros ?? $_GET)[$clave] ?? $defecto;
    }

    public function cookie(string $clave): ?string
    {
        $v = ($this->cookies ?? $_COOKIE)[$clave] ?? null;
        return is_string($v) ? $v : null;
    }

    public function cabecera(string $nombre): string
    {
        $k = 'HTTP_' . strtoupper(str_replace('-', '_', $nombre));
        return (string) ($_SERVER[$k] ?? '');
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'local');
    }

    public function esHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            // X-Forwarded-Proto solo se cree si el servidor está declarado detrás de un proxy (config 'confiar_proxy').
            || (Config::obtener('confiar_proxy', false) && strtolower($this->cabecera('X-Forwarded-Proto')) === 'https');
    }
}
