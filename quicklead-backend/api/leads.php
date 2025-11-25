<?php
include_once '../config/database.php';
include_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $query = "SELECT l.*, p.name as project_name 
              FROM leads l 
              LEFT JOIN projects p ON l.project_id = p.id 
              ORDER BY l.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $leads = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $leads[] = $row;
    }
    
    sendResponse(['success' => true, 'leads' => $leads]);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Для приема заявок с сайтов
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    
    if (!empty($api_key)) {
        // Проверяем API ключ
        $query = "SELECT id FROM projects WHERE api_key = :api_key";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":api_key", $api_key);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            sendResponse(['success' => false, 'message' => 'Invalid API key'], 401);
        }
        
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        $data = json_decode(file_get_contents("php://input"));
        
        $query = "INSERT INTO leads SET 
            project_id = :project_id,
            name = :name,
            phone = :phone,
            email = :email,
            message = :message";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":project_id", $project['id']);
        $stmt->bindParam(":name", $data->name);
        $stmt->bindParam(":phone", $data->phone);
        $stmt->bindParam(":email", $data->email);
        $stmt->bindParam(":message", $data->message);
        
        if ($stmt->execute()) {
            sendResponse([
                'success' => true,
                'message' => 'Lead created successfully',
                'lead_id' => $db->lastInsertId()
            ], 201);
        }
    }
    
    sendResponse(['success' => false, 'message' => 'Failed to create lead'], 500);
}
?>