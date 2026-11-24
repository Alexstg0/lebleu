<?php
namespace Lebleu\Core;

use PDO;

// Único punto de conexión. Solo los Repositorios deben llamar a esta clase.
final class Db
{
    private static ?PDO $pdo = null;

    private static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                Config::obtener('db.host', '127.0.0.1'),
                (int) Config::obtener('db.puerto', 3306),
                Config::obtener('db.nombre', 'lebleu')
            );
            self::$pdo = new PDO($dsn, Config::obtener('db.usuario'), Config::obtener('db.clave'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            // Zona UTC y modo estricto: un dato inválido es un error, nunca se recorta ni se convierte en silencio.
            self::$pdo->exec("SET time_zone = '+00:00'");
            self::$pdo->exec("SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        }
        return self::$pdo;
    }

    public static function filas(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function fila(string $sql, array $params = []): ?array
    {
        $filas = self::filas($sql, $params);
        return $filas[0] ?? null;
    }

    public static function ejecutar(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public static function ultimoId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    private static int $nivel = 0;

    // Transacción con anidamiento (los niveles internos usan SAVEPOINT).
    public static function transaccion(callable $fn): mixed
    {
        $pdo = self::pdo();
        $punto = 'sp' . self::$nivel;
        self::$nivel === 0 ? $pdo->beginTransaction() : $pdo->exec("SAVEPOINT $punto");
        self::$nivel++;
        try {
            $r = $fn();
            self::$nivel--;
            self::$nivel === 0 ? $pdo->commit() : $pdo->exec("RELEASE SAVEPOINT $punto");
            return $r;
        } catch (\Throwable $e) {
            self::$nivel--;
            self::$nivel === 0 ? $pdo->rollBack() : $pdo->exec("ROLLBACK TO SAVEPOINT $punto");
            throw $e;
        }
    }

    // Para pruebas: ejecuta $fn dentro de una transacción que SIEMPRE se revierte.
    public static function simular(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        self::$nivel++;
        try {
            return $fn();
        } finally {
            self::$nivel--;
            $pdo->rollBack();
        }
    }

    public static function reiniciar(): void
    {
        self::$pdo = null;
    }
}
