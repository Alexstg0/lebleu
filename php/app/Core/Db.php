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
            self::$pdo->exec("SET time_zone = '+00:00'");
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

    public static function transaccion(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function reiniciar(): void
    {
        self::$pdo = null;
    }
}
