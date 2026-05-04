<?php

function getUtenteByUsername($pdo, $username) {
    try {
        $sql = "SELECT * FROM Utente WHERE username = :username LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username' => $username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
}

function registraUtente($pdo, $username, $email, $password) {
    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO Utente (Username, Email, Password) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $hash]);
        return ['success' => true];
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            return ['success' => false, 'error' => 'Username o Email già esistenti'];
        }
        return ['success' => false, 'error' => 'Errore nel database'];
    }
}
?>