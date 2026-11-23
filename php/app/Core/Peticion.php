<?php
namespace Lebleu\Core;

final class Peticion
{
    public readonly string $metodo;
    public readonly string $ruta;
    private ?array $json = null;

    public function __construct(?string $metodo = null, ?string $uri = null)
    {
        $this->metodo = strtoupper($metodo ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $ruta = parse_url($uri ?? ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
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
        return $_GET[$clave] ?? $defecto;
    }

    public function cookie(string $clave): ?string
    {
        $v = $_COOKIE[$clave] ?? null;
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
            || strtolower($this->cabecera('X-Forwarded-Proto')) === 'https';
    }
}
