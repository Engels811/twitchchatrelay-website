<?php
/**
 * Database Klasse - SQLite & MySQL Support
 */

class Database {
    private static $instance = null;
    private $connection;
    private $type;

    private function __construct() {
        $this->type = DB_TYPE;
        $this->connect();
        $this->initializeTables();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect() {
        try {
            if ($this->type === 'sqlite') {
                $this->connection = new PDO('sqlite:' . SQLITE_PATH);
                $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->connection->exec('PRAGMA foreign_keys = ON');
            } else {
                $this->connection = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                    ]
                );
            }
        } catch (PDOException $e) {
            $this->logError("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
            die("Datenbankfehler. Bitte kontaktiere den Administrator.");
        }
    }

    private function initializeTables() {
        try {
            // Users Tabelle
            if ($this->type === 'sqlite') {
                $this->connection->exec("
                    CREATE TABLE IF NOT EXISTS users (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        twitch_id TEXT UNIQUE NOT NULL,
                        twitch_login TEXT NOT NULL,
                        display_name TEXT NOT NULL,
                        profile_image_url TEXT,
                        email TEXT,
                        is_admin INTEGER DEFAULT 0,
                        is_bot_authorized INTEGER DEFAULT 0,
                        bot_access_token TEXT,
                        bot_refresh_token TEXT,
                        bot_expires_at INTEGER,
                        access_token TEXT NOT NULL,
                        refresh_token TEXT,
                        token_expires_at INTEGER,
                        last_login DATETIME DEFAULT CURRENT_TIMESTAMP,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )
                ");

                // Sessions Tabelle
                $this->connection->exec("
                    CREATE TABLE IF NOT EXISTS sessions (
                        id TEXT PRIMARY KEY,
                        user_id INTEGER NOT NULL,
                        ip_address TEXT,
                        user_agent TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
                        expires_at DATETIME NOT NULL,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    )
                ");

                // Logs Tabelle
                $this->connection->exec("
                    CREATE TABLE IF NOT EXISTS logs (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER,
                        action TEXT NOT NULL,
                        details TEXT,
                        ip_address TEXT,
                        user_agent TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                    )
                ");

                // Relay Config Tabelle
                $this->connection->exec("
                    CREATE TABLE IF NOT EXISTS relay_config (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER NOT NULL,
                        channel_name TEXT NOT NULL,
                        is_enabled INTEGER DEFAULT 1,
                        relay_to_discord TEXT,
                        relay_to_custom TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        UNIQUE(user_id, channel_name)
                    )
                ");
            } else {
                // MySQL Versionen
                $this->connection->exec("
                    CREATE TABLE IF NOT EXISTS users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        twitch_id VARCHAR(255) UNIQUE NOT NULL,
                        twitch_login VARCHAR(255) NOT NULL,
                        display_name VARCHAR(255) NOT NULL,
                        profile_image_url TEXT,
                        email VARCHAR(255),
                        is_admin TINYINT DEFAULT 0,
                        is_bot_authorized TINYINT DEFAULT 0,
                        bot_access_token TEXT,
                        bot_refresh_token TEXT,
                        bot_expires_at BIGINT,
                        access_token TEXT NOT NULL,
                        refresh_token TEXT,
                        token_expires_at BIGINT,
                        last_login DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_twitch_id (twitch_id),
                        INDEX idx_email (email)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                $this->connection->exec("
                    CREATE TABLE IF NOT EXISTS sessions (
                        id VARCHAR(255) PRIMARY KEY,
                        user_id INT NOT NULL,
                        ip_address VARCHAR(45),
                        user_agent TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        expires_at DATETIME NOT NULL,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        INDEX idx_user_id (user_id),
                        INDEX idx_expires_at (expires_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                $this->connection->exec("
                    CREATE TABLE IF NOT EXISTS logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT,
                        action VARCHAR(255) NOT NULL,
                        details TEXT,
                        ip_address VARCHAR(45),
                        user_agent TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                        INDEX idx_user_id (user_id),
                        INDEX idx_created_at (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                $this->connection->exec("
                    CREATE TABLE IF NOT EXISTS relay_config (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        channel_name VARCHAR(255) NOT NULL,
                        is_enabled TINYINT DEFAULT 1,
                        relay_to_discord TEXT,
                        relay_to_custom TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        UNIQUE KEY unique_user_channel (user_id, channel_name),
                        INDEX idx_user_id (user_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }
        } catch (Exception $e) {
            $this->logError("Tabellen-Initialisierung fehlgeschlagen: " . $e->getMessage());
        }
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logError("Datenbankabfrage fehlgeschlagen: " . $e->getMessage() . "\nSQL: " . $sql);
            throw $e;
        }
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC);
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert($table, $data) {
        $keys = array_keys($data);
        $placeholders = array_fill(0, count($keys), '?');
        $sql = "INSERT INTO $table (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $this->query($sql, array_values($data));
        return $this->connection->lastInsertId();
    }

    public function update($table, $data, $where) {
        $setClause = [];
        $values = [];
        foreach ($data as $key => $value) {
            $setClause[] = "$key = ?";
            $values[] = $value;
        }
        
        $whereClause = [];
        foreach ($where as $key => $value) {
            $whereClause[] = "$key = ?";
            $values[] = $value;
        }
        
        $sql = "UPDATE $table SET " . implode(', ', $setClause) . " WHERE " . implode(' AND ', $whereClause);
        return $this->query($sql, $values);
    }

    public function delete($table, $where) {
        $whereClause = [];
        $values = [];
        foreach ($where as $key => $value) {
            $whereClause[] = "$key = ?";
            $values[] = $value;
        }
        
        $sql = "DELETE FROM $table WHERE " . implode(' AND ', $whereClause);
        return $this->query($sql, $values);
    }

    private function logError($message) {
        if (LOG_ENABLED) {
            $logFile = LOG_PATH . '/database.log';
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
        }
    }

    public function getConnection() {
        return $this->connection;
    }
}

?>
