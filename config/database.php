<?php
/**
 * Database Configuration for PharmaSys
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'pharmasys';
    private $username = 'root'; // Change this to your database username
    private $password = '';     // Change this to your database password
    private $charset = 'utf8mb4';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
    
    public function closeConnection() {
        $this->conn = null;
    }
}

// Database connection helper function
function getDBConnection() {
    $database = new Database();
    return $database->getConnection();
}

// Test database connection
function testDatabaseConnection() {
    try {
        $db = getDBConnection();
        if ($db) {
            echo "Database connection successful!";
            return true;
        } else {
            echo "Database connection failed!";
            return false;
        }
    } catch (Exception $e) {
        echo "Database connection error: " . $e->getMessage();
        return false;
    }
}
?>
