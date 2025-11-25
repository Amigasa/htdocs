<?php
include_once '../config/database.php';
include_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

// Разрешаем CORS для всех запросов
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// GET - получение всех заявок
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

// POST - создание новой заявки
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    // Логируем полученные данные
    error_log("Received lead data: " . print_r($data, true));
    
    // Проверяем обязательные поля
    if (empty($data->name) || empty($data->phone)) {
        sendResponse(['success' => false, 'message' => 'Имя и телефон обязательны для заполнения'], 400);
    }
    
    // Проверяем существование проекта
    if (!empty($data->project_id)) {
        $checkProject = "SELECT id FROM projects WHERE id = :project_id";
        $stmtCheck = $db->prepare($checkProject);
        $stmtCheck->bindParam(":project_id", $data->project_id);
        $stmtCheck->execute();
        
        if ($stmtCheck->rowCount() === 0) {
            sendResponse(['success' => false, 'message' => 'Указанный проект не существует'], 400);
        }
    }
    
    try {
        $query = "INSERT INTO leads SET 
            project_id = :project_id,
            name = :name,
            phone = :phone,
            email = :email,
            message = :message,
            status = 'new'";
        
        $stmt = $db->prepare($query);
        
        // Привязываем параметры
        $project_id = !empty($data->project_id) ? $data->project_id : NULL;
        $email = !empty($data->email) ? $data->email : NULL;
        $message = !empty($data->message) ? $data->message : NULL;
        
        $stmt->bindParam(":project_id", $project_id);
        $stmt->bindParam(":name", $data->name);
        $stmt->bindParam(":phone", $data->phone);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":message", $message);
        
        if ($stmt->execute()) {
            $newLeadId = $db->lastInsertId();
            
            // Получаем созданную заявку для ответа
            $getQuery = "SELECT l.*, p.name as project_name 
                        FROM leads l 
                        LEFT JOIN projects p ON l.project_id = p.id 
                        WHERE l.id = :id";
            $getStmt = $db->prepare($getQuery);
            $getStmt->bindParam(":id", $newLeadId);
            $getStmt->execute();
            $newLead = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse([
                'success' => true,
                'message' => 'Заявка создана успешно',
                'lead_id' => $newLeadId,
                'lead' => $newLead
            ], 201);
        } else {
            $errorInfo = $stmt->errorInfo();
            sendResponse([
                'success' => false,
                'message' => 'Ошибка при создании заявки: ' . $errorInfo[2]
            ], 500);
        }
    } catch (PDOException $e) {
        sendResponse([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ], 500);
    }
}

// PUT - обновление статуса заявки
if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    $data = json_decode(file_get_contents("php://input"));
    
    // Логируем данные
    error_log("Updating lead status: " . print_r($data, true));
    
    // Проверяем обязательные поля
    if (empty($data->id) || empty($data->status)) {
        sendResponse(['success' => false, 'message' => 'ID заявки и статус обязательны'], 400);
    }
    
    // Проверяем валидность статуса
    $allowedStatuses = ['new', 'in_progress', 'success'];
    if (!in_array($data->status, $allowedStatuses)) {
        sendResponse(['success' => false, 'message' => 'Неверный статус. Допустимые значения: new, in_progress, success'], 400);
    }
    
    try {
        $query = "UPDATE leads SET 
            status = :status,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":status", $data->status);
        $stmt->bindParam(":id", $data->id);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                // Получаем обновленную заявку
                $getQuery = "SELECT l.*, p.name as project_name 
                            FROM leads l 
                            LEFT JOIN projects p ON l.project_id = p.id 
                            WHERE l.id = :id";
                $getStmt = $db->prepare($getQuery);
                $getStmt->bindParam(":id", $data->id);
                $getStmt->execute();
                $updatedLead = $getStmt->fetch(PDO::FETCH_ASSOC);
                
                sendResponse([
                    'success' => true,
                    'message' => 'Статус заявки обновлен',
                    'lead' => $updatedLead
                ]);
            } else {
                sendResponse(['success' => false, 'message' => 'Заявка не найдена'], 404);
            }
        } else {
            $errorInfo = $stmt->errorInfo();
            sendResponse([
                'success' => false,
                'message' => 'Ошибка при обновлении заявки: ' . $errorInfo[2]
            ], 500);
        }
    } catch (PDOException $e) {
        sendResponse([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ], 500);
    }
}
?>