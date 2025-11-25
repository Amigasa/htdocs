<?php
include_once '../config/database.php';
include_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $query = "SELECT * FROM projects ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $projects = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $projects[] = $row;
    }
    
    sendResponse(['success' => true, 'projects' => $projects]);
}
?>