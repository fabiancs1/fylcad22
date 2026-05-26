<?php

class Database {

    private static ?Database $instance = null;

    private PDO $pdo;

    private function __construct() {

        $host    = 'localhost';
        $dbname  = 'fylcad_db';
        $user    = 'root';
        $pass    = '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $opciones);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'No se pudo conectar a la base de datos.']));
        }
    }

    public static function getInstance(): Database {

        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }

    private function __clone() {}

    public function __wakeup() {
        throw new \Exception("No se puede deserializar un Singleton.");
    }
}