<?php
/**
 * config/database.php
 * Connexion PDO — Base : files_attente
 * Compatible WampServer (MySQL 8 / MariaDB)
 */
class Database
{
    private string $host    = 'localhost';
    private string $dbname  = 'files_attente';
    private string $user    = 'root';
    private string $pass    = '';
    private string $charset = 'utf8mb4';
    private int    $port    = 3306;

    private static ?Database $instance = null;
    private ?PDO $pdo = null;

    private function __construct() {
        date_default_timezone_set('Africa/Douala');
    }
    private function __clone()    {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        if ($this->pdo === null) {
            $dsn = "mysql:host={$this->host};port={$this->port}"
                 . ";dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            try {
                $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
            } catch (PDOException $e) {
                error_log('[DB] ' . $e->getMessage());
                die('Erreur de connexion à la base de données.');
            }
        }
        return $this->pdo;
    }
}
