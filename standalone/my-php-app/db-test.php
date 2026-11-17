<?php
$host = getenv("DB_HOST");
$dbname = getenv("DB_NAME");
$username = getenv("DB_USER");
$password = getenv("DB_PASSWORD");

if ($host === false || $dbname === false || $username === false || $password === false) {
    http_response_code(500);
    echo "Database configuration is incomplete.";
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "PHP connected to MySQL successfully!";
} catch (PDOException $e) {
    http_response_code(500);
    echo "Database connection failed.";
}
?>
