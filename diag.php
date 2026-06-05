<?php
if (($_GET['token'] ?? '') !== 'deluxediag2024') die('Unauthorized');
$pdo = new PDO("mysql:host=".getenv('DB_HOST').";dbname=".getenv('DB_NAME').";charset=utf8", getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
echo "<h2>ea_roles</h2><pre>";
foreach ($pdo->query("SELECT * FROM ea_roles")->fetchAll(PDO::FETCH_ASSOC) as $r) echo json_encode($r)."\n";
echo "</pre><h2>ea_users</h2><pre>";
foreach ($pdo->query("SELECT id, first_name, last_name, email, id_roles FROM ea_users")->fetchAll(PDO::FETCH_ASSOC) as $r) echo json_encode($r)."\n";
echo "</pre><h2>ea_user_settings</h2><pre>";
foreach ($pdo->query("SELECT id_users, username, LENGTH(password) as pwd_len FROM ea_user_settings")->fetchAll(PDO::FETCH_ASSOC) as $r) echo json_encode($r)."\n";
echo "</pre>";
