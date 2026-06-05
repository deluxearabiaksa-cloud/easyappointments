<?php
// Temporary password reset script - DELETE AFTER USE
$token = $_GET['token'] ?? '';
if ($token !== 'deluxe2024reset') {
    die('Unauthorized');
}

$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USERNAME');
$pass = getenv('DB_PASSWORD');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $hash = password_hash('Muslim92', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE ea_user_settings SET password = ? WHERE id_users = 1");
    $stmt->execute([$hash]);
    echo "Done. Rows: " . $stmt->rowCount() . " | Hash: " . $hash;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
