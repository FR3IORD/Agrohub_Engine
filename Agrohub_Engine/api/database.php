<?php
/**
 * Database Configuration and Connection - Fixed deprecated warnings
 */

class Database {
    private static $instance = null;
    private $host = 'localhost';
    private $dbname = 'agrohub_erp';
    private $username = 'root';
    private $password = 'root'; // Changed from empty string to 'root'
    private $charset = 'utf8mb4';
    private $pdo = null;
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    public function getPDO() {
        return $this->pdo;
    }
    
    // Fixed methods with proper return types
    #[\ReturnTypeWillChange]
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    #[\ReturnTypeWillChange]
    public function commit() {
        return $this->pdo->commit();
    }
    
    #[\ReturnTypeWillChange]
    public function rollback() {
        return $this->pdo->rollBack();
    }
    
    // Additional helper methods
    public function exec($statement) {
        return $this->pdo->exec($statement);
    }
    
    public function query($statement) {
        return $this->pdo->query($statement);
    }
    
    public function quote($string) {
        return $this->pdo->quote($string);
    }
}

// Legacy support for direct PDO usage in existing modules.php
try {
    $host = 'localhost';
    $dbname = 'agrohub_erp';
    $username = 'root';
    $password = 'root'; // Changed from empty string to 'root'

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>