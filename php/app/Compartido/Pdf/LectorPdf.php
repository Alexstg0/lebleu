<?php
namespace Lebleu\Compartido\Pdf;

// Lector de PDF existentes (los comprobantes que suben los usuarios): tablas xref clásicas y comprimidas,
// flujos de objetos (PDF 1.5+) y reconstrucción cuando el archivo viene dañado. No descifra.
final class LectorPdf
{
    private const MAX_PROFUNDIDAD = 64;
    private const MAX_PAGINAS = 2000;

    /** @var array<int, array{o?: int, s?: int, i?: int}> */
    private array $xref = [];
    private array $cacheObjetos = [];
    private array $cacheFlujosObj = [];
    private ?Valor $trailer = null;

    public function __construct(private string $d)
    {
        if (strpos(substr($d, 0, 1024), '%PDF-') === false) {
            throw new \RuntimeException('No es un PDF.');
        }
        try {
            $this->leerXref();
        } catch (\Throwable) {
            $this->xref = [];
            $this->trailer = null;
        }
        if (!$this->trailer || !$this->catalogoValido()) {
            $this->cacheObjetos = [];
            $this->reconstruir();
        }
        if ($this->trailer && $this->trailer->clave('Encrypt') !== null) {
            throw new \RuntimeException('PDF cifrado.');
        }
        if (!$this->catalogoValido()) {
            throw new \RuntimeException('PDF sin catálogo.');
        }
    }

    /** Resuelve referencias hasta llegar a un valor concreto. */
    public function resolver(mixed $v): mixed
    {
        for ($i = 0; $v instanceof Valor && $v->es(Valor::REF) && $i < 32; $i++) {
            $v = $this->objeto($v->v[0]);
        }
        return $v;
    }

    public function objeto(int $num): mixed
    {
        if (array_key_exists($num, $this->cacheObjetos)) {
            return $this->cacheObjetos[$num];
        }
        $this->cacheObjetos[$num] = null; // corta ciclos
        $e = $this->xref[$num] ?? null;
        $valor = null;
        if ($e !== null && isset($e['o'])) {
            $valor = $this->objetoEn($e['o']);
        } elseif ($e !== null && isset($e['s'])) {
            $valor = $this->deFlujoDeObjetos($e['s'], $e['i'] ?? 0, $num);
        }
        return $this->cacheObjetos[$num] = $valor;
    }

    /** @return array<int, Valor> diccionarios de página con los atributos heredados ya aplicados */
    public function paginas(): array
    {
        $raiz = $this->resolver($this->trailer->clave('Root'));
        $paginas = [];
        $visitados = [];
        $this->recorrer($raiz->clave('Pages'), [], $paginas, $visitados, 0);
        return $paginas;
    }

    private function recorrer(mixed $nodo, array $heredado, array &$salida, array &$visitados, int $nivel): void
    {
        if (count($salida) > self::MAX_PAGINAS) {
            throw new \RuntimeException('El PDF tiene demasiadas páginas.');
        }
        $ref = $nodo instanceof Valor && $nodo->es(Valor::REF) ? $nodo->v[0] : null;
        if ($ref !== null) {
            if (isset($visitados[$ref]) || $nivel > self::MAX_PROFUNDIDAD) {
                return;
            }
            $visitados[$ref] = true;
        }
        $n = $this->resolver($nodo);
        if (!$n instanceof Valor || !$n->es(Valor::DIC)) {
            return;
        }
        foreach (['Resources', 'MediaBox', 'CropBox', 'Rotate'] as $k) {
            if (isset($n->v[$k])) {
                $heredado[$k] = $n->v[$k];
            }
        }
        $hijos = $this->resolver($n->v['Kids'] ?? null);
        if (is_array($hijos)) {
            foreach ($hijos as $h) {
                $this->recorrer($h, $heredado, $salida, $visitados, $nivel + 1);
            }
            return;
        }
        $salida[] = Valor::dic($n->v + $heredado);
    }

    // ---------- Lectura de objetos

    private function objetoEn(int $pos): mixed
    {
        if (!preg_match('/\G\s*(\d+)\s+(\d+)\s+obj/A', $this->d, $m, 0, $pos)) {
            return null;
        }
        $p = $pos + strlen($m[0]);
        [$v, $p] = Sintaxis::leer($this->d, $p);
        if ($v instanceof Valor && $v->es(Valor::DIC) && preg_match('/\G\s*stream(\r\n|\n|\r)/A', $this->d, $s, 0, $p)) {
            $ini = $p + strlen($s[0]);
            $largo = $this->resolver($v->v['Length'] ?? null);
            $datos = null;
            if (is_int($largo) && $largo >= 0 && preg_match('/\G\s*endstream/A', $this->d, $x, 0, $ini + $largo)) {
                $datos = substr($this->d, $ini, $largo);
            } else {
                $fin = strpos($this->d, 'endstream', $ini);
                if ($fin === false) {
                    return null;
                }
                $datos = rtrim(substr($this->d, $ini, $fin - $ini), "\r\n");
            }
            return new Valor(Valor::FLUJO, [$v, $datos]);
        }
        return $v;
    }

    private function deFlujoDeObjetos(int $numFlujo, int $indice, int $numObjeto): mixed
    {
        if (!isset($this->cacheFlujosObj[$numFlujo])) {
            $f = $this->objeto($numFlujo);
            if (!$f instanceof Valor || !$f->es(Valor::FLUJO)) {
                return null;
            }
            $texto = $this->decodificar($f);
            $n = (int) $this->resolver($f->clave('N'));
            $primero = (int) $this->resolver($f->clave('First'));
            $pares = [];
            $p = 0;
            for ($i = 0; $i < $n; $i++) {
                [$num, $p] = Sintaxis::leer($texto, $p);
                [$off, $p] = Sintaxis::leer($texto, $p);
                $pares[] = [(int) $num, (int) $off];
            }
            $this->cacheFlujosObj[$numFlujo] = [$texto, $primero, $pares];
        }
        [$texto, $primero, $pares] = $this->cacheFlujosObj[$numFlujo];
        $i = (isset($pares[$indice]) && $pares[$indice][0] === $numObjeto) ? $indice : array_search($numObjeto, array_column($pares, 0), true);
        if ($i === false) {
            return null;
        }
        return Sintaxis::leer($texto, $primero + $pares[$i][1])[0];
    }

    // ---------- Tabla de referencias cruzadas

    private function leerXref(): void
    {
        if (!preg_match_all('/startxref\s+(\d+)/', substr($this->d, -2048), $m) || !$m[1]) {
            throw new \RuntimeException('Sin startxref.');
        }
        $pos = (int) end($m[1]);
        $vistos = [];
        while ($pos !== null && !isset($vistos[$pos])) {
            $vistos[$pos] = true;
            $trailer = preg_match('/\G\s*xref/A', $this->d, $x, 0, $pos) ? $this->xrefTabla($pos + strlen($x[0])) : $this->xrefFlujo($pos);
            $this->trailer ??= $trailer;
            if (isset($trailer->v['XRefStm']) && is_int($trailer->v['XRefStm']) && !isset($vistos[$trailer->v['XRefStm']])) {
                $vistos[$trailer->v['XRefStm']] = true;
                $this->xrefFlujo($trailer->v['XRefStm']);
            }
            $prev = $trailer->v['Prev'] ?? null;
            $pos = is_int($prev) || is_float($prev) ? (int) $prev : null;
        }
    }

    private function xrefTabla(int $p): Valor
    {
        while (true) {
            $p = Sintaxis::saltar($this->d, $p);
            if (substr($this->d, $p, 7) === 'trailer') {
                $t = Sintaxis::leer($this->d, $p + 7)[0];
                if (!$t instanceof Valor || !$t->es(Valor::DIC)) {
                    throw new \RuntimeException('Trailer no válido.');
                }
                return $t;
            }
            if (!preg_match('/\G(\d+)\s+(\d+)[ \t]*(\r\n|\n|\r)?/A', $this->d, $m, 0, $p)) {
                throw new \RuntimeException('Subsección xref no válida.');
            }
            $p += strlen($m[0]);
            for ($i = 0, $n = (int) $m[2]; $i < $n; $i++) {
                if (!preg_match('/\G\s*(\d{10})\s+(\d{5})\s+([nf])/A', $this->d, $e, 0, $p)) {
                    throw new \RuntimeException('Entrada xref no válida.');
                }
                $p += strlen($e[0]);
                $num = (int) $m[1] + $i;
                if ($e[3] === 'n' && !isset($this->xref[$num])) {
                    $this->xref[$num] = ['o' => (int) $e[1]];
                }
            }
        }
    }

    private function xrefFlujo(int $pos): Valor
    {
        $f = $this->objetoEn($pos);
        if (!$f instanceof Valor || !$f->es(Valor::FLUJO)) {
            throw new \RuntimeException('Flujo xref no válido.');
        }
        $w = $f->clave('W');
        if (!is_array($w) || count($w) !== 3 || array_filter($w, fn($x) => !is_int($x) || $x < 0 || $x > 8) || array_sum($w) === 0) {
            throw new \RuntimeException('Anchos del flujo xref no válidos.');
        }
        $datos = $this->decodificar($f);
        $indice = $f->clave('Index') ?? [0, (int) $f->clave('Size')];
        $ancho = $w[0] + $w[1] + $w[2];
        $p = 0;
        for ($k = 0; $k + 1 < count($indice); $k += 2) {
            // Nunca más entradas de las que caben en los datos (un /Index enorme no debe agotar la memoria).
            for ($i = 0, $n = min((int) $indice[$k + 1], intdiv(strlen($datos), $ancho)); $i < $n; $i++, $p += $ancho) {
                if ($p + $ancho > strlen($datos)) {
                    break 2;
                }
                $c = [];
                $q = $p;
                foreach ($w as $largo) {
                    $c[] = $largo ? (int) hexdec(bin2hex(substr($datos, $q, $largo))) : null;
                    $q += $largo;
                }
                $tipo = $c[0] ?? 1;
                $num = $indice[$k] + $i;
                if (isset($this->xref[$num])) {
                    continue;
                }
                if ($tipo === 1) {
                    $this->xref[$num] = ['o' => $c[1]];
                } elseif ($tipo === 2) {
                    $this->xref[$num] = ['s' => $c[1], 'i' => $c[2]];
                }
            }
        }
        return Valor::dic($f->diccionario());
    }

    /** Reconstruye la tabla escaneando "N G obj" cuando la original falta o está dañada. */
    private function reconstruir(): void
    {
        $this->xref = [];
        $this->cacheFlujosObj = [];
        if (preg_match_all('/(?<![0-9])(\d+)\s+(\d+)\s+obj\b/', $this->d, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as $i => [$num, $off]) {
                $this->xref[(int) $num] = ['o' => $m[0][$i][1]];
            }
        }
        $raiz = null;
        $this->trailer = null;
        foreach (array_keys($this->xref) as $num) {
            $o = $this->objeto($num);
            if ($o instanceof Valor && $o->es(Valor::FLUJO) && ($o->clave('Type')?->v ?? '') === 'ObjStm') {
                $this->indexarFlujoObjetos($num, $o);
            }
        }
        foreach (array_keys($this->xref) as $num) {
            $o = $this->objeto($num);
            if ($o instanceof Valor && $o->es(Valor::DIC) && ($o->clave('Type')?->v ?? '') === 'Catalog') {
                $raiz = $num;
            }
        }
        if ($raiz !== null) {
            $this->trailer = Valor::dic(['Root' => Valor::ref($raiz)]);
        }
        if (preg_match('/\/Encrypt\b/', substr($this->d, -4096)) && $this->trailer) {
            $this->trailer = Valor::dic($this->trailer->v + ['Encrypt' => true]);
        }
    }

    private function indexarFlujoObjetos(int $numFlujo, Valor $f): void
    {
        try {
            $texto = $this->decodificar($f);
            $p = 0;
            for ($i = 0, $n = (int) $f->clave('N'); $i < $n; $i++) {
                [$num, $p] = Sintaxis::leer($texto, $p);
                [, $p] = Sintaxis::leer($texto, $p);
                if (!isset($this->xref[(int) $num])) {
                    $this->xref[(int) $num] = ['s' => $numFlujo, 'i' => $i];
                }
            }
        } catch (\Throwable) {
        }
    }

    private function catalogoValido(): bool
    {
        if (!$this->trailer) {
            return false;
        }
        $raiz = $this->resolver($this->trailer->clave('Root'));
        return $raiz instanceof Valor && $raiz->es(Valor::DIC) && $raiz->clave('Pages') !== null;
    }

    /** Descomprime un flujo (solo FlateDecode, con predictores PNG). */
    public function decodificar(Valor $f): string
    {
        return Flujos::decodificar($f, fn($v) => $this->resolver($v));
    }
}
