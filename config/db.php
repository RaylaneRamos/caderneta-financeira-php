<?php
// config/db.php
$DB_HOST = '127.0.0.1';
$DB_NAME = 'caderneta';
$DB_USER = 'root';
$DB_PASS = ''; // se você usa senha no MySQL, coloque aqui

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
}
?>