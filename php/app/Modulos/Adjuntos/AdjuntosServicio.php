<?php
namespace Lebleu\Modulos\Adjuntos;

use Lebleu\Compartido\Pdf\ImagenAPdf;
use Lebleu\Modulos\Auditoria\AuditoriaServicio;

// Respaldos (PDF o foto) de gastos, insumos, extraordinarios y caja chica. Las fotos se guardan como PDF.
final class AdjuntosServicio
{
    private const MAX_BYTES = 3355443; // 3.2 MB, límite práctico del servidor

    private AdjuntosRepositorio $repo;

    public function __construct(?AdjuntosRepositorio $repo = null)
    {
        $this->repo = $repo ?? new AdjuntosRepositorio();
    }

    /**
     * @throws \InvalidArgumentException con el mensaje que ve el usuario
     * @return int id del adjunto creado
     */
    public function subir(array $usuario, array $b): int
    {
        [$columna, $registroId] = $this->destino($b);
        if ($usuario['rol'] === 'capitan' && $columna !== 'caja_id') {
            throw new \DomainException('No autorizado.');
        }

        $base64 = preg_replace('/^data:[^,]+,/', '', (string) ($b['base64'] ?? ''));
        if ($base64 === '') {
            throw new \InvalidArgumentException('Archivo vacío.');
        }
        if (strlen($base64) * 3 / 4 > self::MAX_BYTES) {
            throw new \InvalidArgumentException('El archivo supera 3 MB. Toma la foto en tamaño reducido o comprímela.');
        }
        $binario = base64_decode($base64, true);
        if ($binario === false) {
            throw new \InvalidArgumentException('El archivo no es válido.');
        }

        $mime = (string) ($b['mime'] ?? 'application/pdf');
        $nombre = mb_substr((string) ($b['nombre'] ?? 'documento.pdf'), 0, 200);
        if (preg_match('#^image/(jpe?g|png)$#i', $mime)) {
            // Las imágenes se convierten a PDF para que el visor y los reportes sean uniformes.
            $binario = ImagenAPdf::convertir($binario);
            $nombre = preg_replace('/\.(jpe?g|png)$/i', '', $nombre) . '.pdf';
        } elseif ($mime !== 'application/pdf') {
            throw new \InvalidArgumentException('Solo se permiten PDF o imágenes (JPG/PNG).');
        } elseif (!str_starts_with($binario, '%PDF-')) {
            throw new \InvalidArgumentException('El archivo no es un PDF válido.');
        }

        $id = $this->repo->insertar($columna, $registroId, $nombre, 'application/pdf', base64_encode($binario));
        AuditoriaServicio::registrar($usuario, 'subir', 'adjuntos', $id, "Subió \"$nombre\"");
        return $id;
    }

    /** @return bool false si el archivo no existe */
    public function borrar(array $usuario, int $id): bool
    {
        $fila = $this->repo->porId($id);
        if (!$fila) {
            return false;
        }
        if ($usuario['rol'] === 'capitan' && !$fila['caja_id']) {
            throw new \DomainException('No autorizado.');
        }
        $this->repo->borrar($id);
        AuditoriaServicio::registrar($usuario, 'borrar', 'adjuntos', $id, "Eliminó el archivo \"{$fila['nombre']}\"");
        return true;
    }

    public function delPeriodo(string $tabla, int $periodoId): array
    {
        return $this->repo->delPeriodo($tabla, $periodoId);
    }

    public function deCajaChica(): array
    {
        return $this->repo->deCajaChica();
    }

    /** El archivo pertenece a exactamente un registro (gasto, insumo, extraordinario o movimiento de caja). */
    private function destino(array $b): array
    {
        $dados = [];
        foreach (AdjuntosRepositorio::COLUMNAS as $col) {
            if (!empty($b[$col])) {
                $dados[$col] = (int) $b[$col];
            }
        }
        if (count($dados) !== 1) {
            throw new \InvalidArgumentException('Falta el registro al que pertenece el archivo.');
        }
        return [array_key_first($dados), reset($dados)];
    }
}
