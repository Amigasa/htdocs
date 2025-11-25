<?php
include_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db) {
    echo "✅ База данных подключена успешно!";
    
    // Проверим таблицы
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<br>📊 Таблицы в базе: " . implode(', ', $tables);
} else {
    echo "❌ Ошибка подключения к базе данных";
}
?>