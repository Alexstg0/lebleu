<?php
namespace Lebleu\Compartido\Pdf;

// Valor de la sintaxis PDF que no tiene equivalente nativo en PHP.
// Los números, booleanos, null y listas se representan con tipos nativos de PHP.
final class Valor
{
    public const NOMBRE = 'nombre';
    public const CADENA = 'cadena';
    public const REF = 'ref';
    public const DIC = 'dic';
    public const FLUJO = 'flujo';

    /** @param mixed $v nombre: string · cadena: bytes · ref: [num, gen] · dic: array clave=>valor · flujo: [Valor dic, bytes] */
    public function __construct(public readonly string $t, public readonly mixed $v) {}

    public static function nombre(string $n): self { return new self(self::NOMBRE, $n); }
    public static function ref(int $num, int $gen = 0): self { return new self(self::REF, [$num, $gen]); }
    public static function dic(array $d): self { return new self(self::DIC, $d); }

    public function es(string $t): bool { return $this->t === $t; }

    /** Valor de una clave si esto es un diccionario (o el diccionario de un flujo). */
    public function clave(string $k): mixed
    {
        $d = $this->t === self::FLUJO ? $this->v[0]->v : ($this->t === self::DIC ? $this->v : []);
        return $d[$k] ?? null;
    }

    public function diccionario(): array
    {
        return $this->t === self::FLUJO ? $this->v[0]->v : ($this->t === self::DIC ? $this->v : []);
    }
}
