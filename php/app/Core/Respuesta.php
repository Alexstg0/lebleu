<?php
namespace Lebleu\Core;

final class Respuesta
{
    public function __construct(
        private int $estado = 200,
        private string $cuerpo = '',
        private array $cabeceras = []
    ) {}

    public static function json(array $datos, int $estado = 200): self
    {
        return new self(
            $estado,
            json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function error(string $mensaje, int $estado = 400): self
    {
        return self::json(['ok' => false, 'error' => $mensaje], $estado);
    }

    public static function html(string $html, int $estado = 200): self
    {
        return new self($estado, $html, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function redirigir(string $url, int $estado = 302): self
    {
        return new self($estado, '', ['Location' => $url]);
    }

    public static function binario(string $datos, string $mime, array $extra = []): self
    {
        return new self(200, $datos, ['Content-Type' => $mime] + $extra);
    }

    public function estado(): int
    {
        return $this->estado;
    }

    public function cuerpo(): string
    {
        return $this->cuerpo;
    }

    public function cabecera(string $nombre): ?string
    {
        return $this->cabeceras[$nombre] ?? null;
    }

    /** Cuerpo decodificado si la respuesta es JSON (útil en pruebas). */
    public function datos(): ?array
    {
        $d = json_decode($this->cuerpo, true);
        return is_array($d) ? $d : null;
    }

    public function enviar(bool $https): void
    {
        http_response_code($this->estado);
        $seguridad = [
            'X-Frame-Options'        => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=()',
        ];
        if ($https) {
            $seguridad['Strict-Transport-Security'] = 'max-age=63072000; includeSubDomains; preload';
        }
        foreach ($seguridad + $this->cabeceras as $k => $v) {
            header("$k: $v");
        }
        echo $this->cuerpo;
    }
}
