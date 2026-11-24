<?php
namespace Lebleu\Compartido\Pdf;

// Une páginas de varios PDF en uno solo (sin dependencias). Cada página se copia con todo lo que usa
// (recursos, fuentes, imágenes, contenidos); los flujos se copian tal cual, sin recomprimir.
final class Fusionador
{
    /** @var array<int, mixed> número de objeto => valor (se serializa al generar) */
    private array $objetos = [];
    private ?int $fuenteSello = null;
    private array $paginas = [];
    private int $siguiente = 3; // 1 = catálogo, 2 = árbol de páginas

    /** Agrega todas las páginas de un PDF. Si falla, no queda nada a medias. @return int páginas agregadas */
    public function agregar(string $pdf, ?callable $sello = null): int
    {
        $lector = new LectorPdf($pdf);
        $origen = $lector->paginas();
        if (!$origen) {
            throw new \RuntimeException('El PDF no tiene páginas.');
        }
        $respaldo = [$this->objetos, $this->paginas, $this->siguiente, $this->fuenteSello];
        $primera = true;
        try {
            $mapa = [];
            $nuevos = [];
            foreach ($origen as $pagina) {
                $num = $this->siguiente++;
                $dic = $this->copiar($lector, $pagina, $mapa, $nuevos, true);
                $this->objetos[$num] = Valor::dic(['Type' => Valor::nombre('Page'), 'Parent' => Valor::ref(2)] + $dic->v);
                if ($sello !== null && $primera) {
                    $this->sellar($num, $sello);
                }
                $primera = false;
                $this->paginas[] = $num;
            }
            return count($origen);
        } catch (\Throwable $e) {
            [$this->objetos, $this->paginas, $this->siguiente, $this->fuenteSello] = $respaldo;
            throw $e;
        }
    }

    public function total(): int
    {
        return count($this->paginas);
    }

    public function generar(): string
    {
        $this->objetos[1] = Valor::dic(['Type' => Valor::nombre('Catalog'), 'Pages' => Valor::ref(2)]);
        $this->objetos[2] = Valor::dic([
            'Type' => Valor::nombre('Pages'),
            'Count' => count($this->paginas),
            'Kids' => array_map(fn($n) => Valor::ref($n), $this->paginas),
        ]);
        ksort($this->objetos);
        $salida = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($this->objetos as $num => $cuerpo) {
            $offsets[$num] = strlen($salida);
            $salida .= "$num 0 obj\n" . Escritor::valor($cuerpo) . "\nendobj\n";
        }
        $max = max(array_keys($this->objetos));
        $xref = strlen($salida);
        $salida .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $salida .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
        }
        return $salida . "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }

    /** Copia un valor renumerando las referencias; los objetos referenciados se copian una sola vez. */
    private function copiar(LectorPdf $l, mixed $v, array &$mapa, array &$nuevos, bool $esPagina = false, int $nivel = 0): mixed
    {
        if ($nivel > 200) {
            throw new \RuntimeException('Estructura demasiado profunda.');
        }
        if (is_array($v)) {
            return array_map(fn($x) => $this->copiar($l, $x, $mapa, $nuevos, false, $nivel + 1), $v);
        }
        if (!$v instanceof Valor) {
            return $v;
        }
        switch ($v->t) {
            case Valor::REF:
                $num = $v->v[0];
                if (isset($mapa[$num])) {
                    return Valor::ref($mapa[$num]);
                }
                $destino = $l->objeto($num);
                // No arrastrar otras páginas (enlaces internos, destinos): quedarían huérfanas.
                if ($destino instanceof Valor && $destino->es(Valor::DIC) && ($destino->clave('Type')?->v ?? '') === 'Page') {
                    return null;
                }
                $mapa[$num] = $this->siguiente++;
                $this->objetos[$mapa[$num]] = $this->copiar($l, $destino, $mapa, $nuevos, false, $nivel + 1);
                return Valor::ref($mapa[$num]);
            case Valor::DIC:
                $d = [];
                foreach ($v->v as $k => $x) {
                    if ($k === 'Parent' || ($k === 'P' && isset($v->v['Subtype'])) || ($esPagina && in_array($k, ['Thumb', 'B', 'StructParents'], true))) {
                        continue;
                    }
                    $d[$k] = $this->copiar($l, $x, $mapa, $nuevos, false, $nivel + 1);
                }
                return Valor::dic($d);
            case Valor::FLUJO:
                $dic = $this->copiar($l, $v->v[0], $mapa, $nuevos, false, $nivel + 1);
                return new Valor(Valor::FLUJO, [$dic, $v->v[1]]);
        }
        return $v;
    }

    /**
     * Dibuja una barra azul con texto blanco al pie de la página (para identificar el soporte).
     * @param callable(float): string $texto recibe el ancho de la página y devuelve el texto (ya recortado)
     */
    private function sellar(int $numPagina, callable $texto): void
    {
        $dic = $this->objetos[$numPagina]->v;
        $mb = $this->resolverLocal($dic['MediaBox'] ?? [0, 0, 595.28, 841.89]);
        $pw = (float) $mb[2] - (float) $mb[0];
        $this->fuenteSello ??= $this->nuevoObjeto(Valor::dic([
            'Type' => Valor::nombre('Font'), 'Subtype' => Valor::nombre('Type1'),
            'BaseFont' => Valor::nombre('Helvetica-Bold'), 'Encoding' => Valor::nombre('WinAnsiEncoding'),
        ]));
        $txt = strtr(Lienzo::codificar($texto($pw)), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
        $x0 = (float) $mb[0];
        $y0 = (float) $mb[1];
        $barra = sprintf("0.086 0.153 0.28 rg /LbAlfa gs %.3F %.3F %.3F 18 re f /LbAlfa0 gs BT /LbSello 8 Tf 1 1 1 rg %.3F %.3F Td (%s) Tj ET\n", $x0, $y0, $pw, $x0 + 10, $y0 + 5.5, $txt);
        $antes = $this->nuevoObjeto(new Valor(Valor::FLUJO, [Valor::dic([]), "q\n"]));
        $despues = $this->nuevoObjeto(new Valor(Valor::FLUJO, [Valor::dic([]), "Q\nq\n" . $barra . "Q\n"]));
        $alfa1 = Valor::dic(['Type' => Valor::nombre('ExtGState'), 'ca' => 0.92, 'CA' => 0.92]);
        $alfa0 = Valor::dic(['Type' => Valor::nombre('ExtGState'), 'ca' => 1, 'CA' => 1]);

        $res = $this->resolverLocal($dic['Resources'] ?? Valor::dic([]));
        $res = $res instanceof Valor ? $res->v : [];
        $fuentes = $this->resolverLocal($res['Font'] ?? Valor::dic([]));
        $estados = $this->resolverLocal($res['ExtGState'] ?? Valor::dic([]));
        $res['Font'] = Valor::dic(($fuentes instanceof Valor ? $fuentes->v : []) + ['LbSello' => Valor::ref($this->fuenteSello)]);
        $res['ExtGState'] = Valor::dic(($estados instanceof Valor ? $estados->v : []) + ['LbAlfa' => $alfa1, 'LbAlfa0' => $alfa0]);
        $dic['Resources'] = Valor::dic($res);

        $contenido = $this->resolverLocal($dic['Contents'] ?? []);
        $partes = is_array($dic['Contents'] ?? null) ? $dic['Contents'] : ($contenido instanceof Valor ? [$dic['Contents']] : []);
        $dic['Contents'] = [Valor::ref($antes), ...$partes, Valor::ref($despues)];
        $this->objetos[$numPagina] = Valor::dic($dic);
    }

    private function nuevoObjeto(mixed $valor): int
    {
        $num = $this->siguiente++;
        $this->objetos[$num] = $valor;
        return $num;
    }

    /** Resuelve una referencia a un objeto ya copiado a este documento (o devuelve el valor tal cual). */
    private function resolverLocal(mixed $v): mixed
    {
        for ($i = 0; $v instanceof Valor && $v->es(Valor::REF) && $i < 16; $i++) {
            $v = $this->objetos[$v->v[0]] ?? null;
        }
        return $v;
    }
}
