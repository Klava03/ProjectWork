<?php
    function getConnection(): PDO {
        $host = 'localhost';
        $dbname = 'Pulse';
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4"; 
        $username = 'root';
        $password = '';
        
        try {
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            die("Errore di connessione al database."); 
        }
    }
?>