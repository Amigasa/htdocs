<?php
include_once '../config/database.php';
include_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// GET - получение всех проектов
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $query = "SELECT p.*, COUNT(l.id) as lead_count 
              FROM projects p 
              LEFT JOIN leads l ON p.id = l.project_id 
              GROUP BY p.id 
              ORDER BY p.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $projects = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $projects[] = $row;
    }
    
    sendResponse(['success' => true, 'projects' => $projects]);
}

// POST - создание нового проекта
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->name)) {
        sendResponse(['success' => false, 'message' => 'Название проекта обязательно'], 400);
    }
    
    try {
        // Генерируем уникальный API ключ
        $api_key = 'qlm_' . bin2hex(random_bytes(8));
        
        $query = "INSERT INTO projects SET 
            name = :name,
            description = :description,
            api_key = :api_key";
        
        $stmt = $db->prepare($query);
        $description = !empty($data->description) ? $data->description : NULL;
        
        $stmt->bindParam(":name", $data->name);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":api_key", $api_key);
        
        if ($stmt->execute()) {
            $newProjectId = $db->lastInsertId();
            
            // Получаем созданный проект
            $getQuery = "SELECT * FROM projects WHERE id = :id";
            $getStmt = $db->prepare($getQuery);
            $getStmt->bindParam(":id", $newProjectId);
            $getStmt->execute();
            $newProject = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse([
                'success' => true,
                'message' => 'Проект создан успешно',
                'project' => $newProject
            ], 201);
        } else {
            throw new Exception('Ошибка при создании проекта');
        }
    } catch (Exception $e) {
        sendResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// PUT - обновление проекта
if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->id) || empty($data->name)) {
        sendResponse(['success' => false, 'message' => 'ID и название проекта обязательны'], 400);
    }
    
    try {
        $query = "UPDATE projects SET 
            name = :name,
            description = :description
            WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $description = !empty($data->description) ? $data->description : NULL;
        
        $stmt->bindParam(":name", $data->name);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":id", $data->id);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                sendResponse([
                    'success' => true,
                    'message' => 'Проект обновлен успешно'
                ]);
            } else {
                sendResponse(['success' => false, 'message' => 'Проект не найден'], 404);
            }
        } else {
            throw new Exception('Ошибка при обновлении проекта');
        }
    } catch (Exception $e) {
        sendResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// DELETE - удаление проекта
if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->id)) {
        sendResponse(['success' => false, 'message' => 'ID проекта обязателен'], 400);
    }
    
    try {
        // Проверяем есть ли связанные заявки
        $checkQuery = "SELECT COUNT(*) as lead_count FROM leads WHERE project_id = :project_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(":project_id", $data->id);
        $checkStmt->execute();
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['lead_count'] > 0) {
            sendResponse([
                'success' => false, 
                'message' => 'Невозможно удалить проект: есть связанные заявки'
            ], 400);
        }
        
        $query = "DELETE FROM projects WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $data->id);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                sendResponse([
                    'success' => true,
                    'message' => 'Проект удален успешно'
                ]);
            } else {
                sendResponse(['success' => false, 'message' => 'Проект не найден'], 404);
            }
        } else {
            throw new Exception('Ошибка при удалении проекта');
        }
    } catch (Exception $e) {
        sendResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
?>