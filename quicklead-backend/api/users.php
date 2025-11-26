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

// GET - получение всех пользователей
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $query = "SELECT id, username, name, email, role, created_at, is_active FROM users ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $users = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $users[] = $row;
    }
    
    sendResponse(['success' => true, 'users' => $users]);
}

// POST - создание пользователя
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->username) || empty($data->password) || empty($data->name) || empty($data->email)) {
        sendResponse(['success' => false, 'message' => 'Все поля обязательны'], 400);
    }
    
    try {
        // Проверяем уникальность username и email
        $checkQuery = "SELECT id FROM users WHERE username = :username OR email = :email";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(":username", $data->username);
        $checkStmt->bindParam(":email", $data->email);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            sendResponse(['success' => false, 'message' => 'Пользователь с таким логином или email уже существует'], 400);
        }
        
        $hashed_password = password_hash($data->password, PASSWORD_DEFAULT);
        $role = !empty($data->role) ? $data->role : 'client';
        
        $query = "INSERT INTO users SET 
            username = :username,
            password = :password,
            name = :name,
            email = :email,
            role = :role,
            is_active = TRUE";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":username", $data->username);
        $stmt->bindParam(":password", $hashed_password);
        $stmt->bindParam(":name", $data->name);
        $stmt->bindParam(":email", $data->email);
        $stmt->bindParam(":role", $role);
        
        if ($stmt->execute()) {
            $newUserId = $db->lastInsertId();
            
            // Получаем созданного пользователя (без пароля)
            $getQuery = "SELECT id, username, name, email, role, created_at FROM users WHERE id = :id";
            $getStmt = $db->prepare($getQuery);
            $getStmt->bindParam(":id", $newUserId);
            $getStmt->execute();
            $newUser = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse([
                'success' => true,
                'message' => 'Пользователь создан успешно',
                'user' => $newUser
            ], 201);
        } else {
            throw new Exception('Ошибка при создании пользователя');
        }
    } catch (Exception $e) {
        sendResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// PUT - обновление пользователя
if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->id) || empty($data->name) || empty($data->email) || empty($data->role)) {
        sendResponse(['success' => false, 'message' => 'Все поля обязательны'], 400);
    }
    
    try {
        // Проверяем уникальность email для других пользователей
        $checkQuery = "SELECT id FROM users WHERE email = :email AND id != :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(":email", $data->email);
        $checkStmt->bindParam(":id", $data->id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            sendResponse(['success' => false, 'message' => 'Пользователь с таким email уже существует'], 400);
        }
        
        $query = "UPDATE users SET 
            name = :name,
            email = :email,
            role = :role
            WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":name", $data->name);
        $stmt->bindParam(":email", $data->email);
        $stmt->bindParam(":role", $data->role);
        $stmt->bindParam(":id", $data->id);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                sendResponse([
                    'success' => true,
                    'message' => 'Пользователь обновлен успешно'
                ]);
            } else {
                sendResponse(['success' => false, 'message' => 'Пользователь не найден'], 404);
            }
        } else {
            throw new Exception('Ошибка при обновлении пользователя');
        }
    } catch (Exception $e) {
        sendResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// DELETE - удаление пользователя
if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->id)) {
        sendResponse(['success' => false, 'message' => 'ID пользователя обязателен'], 400);
    }
    
    try {
        // Нельзя удалить самого себя
        if ($data->id == 1) { // ID администратора
            sendResponse(['success' => false, 'message' => 'Нельзя удалить администратора системы'], 400);
        }
        
        // Проверяем есть ли связанные заявки
        $checkQuery = "SELECT COUNT(*) as lead_count FROM leads WHERE user_id = :user_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(":user_id", $data->id);
        $checkStmt->execute();
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['lead_count'] > 0) {
            sendResponse([
                'success' => false, 
                'message' => 'Невозможно удалить пользователя: есть связанные заявки'
            ], 400);
        }
        
        $query = "DELETE FROM users WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $data->id);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                sendResponse([
                    'success' => true,
                    'message' => 'Пользователь удален успешно'
                ]);
            } else {
                sendResponse(['success' => false, 'message' => 'Пользователь не найден'], 404);
            }
        } else {
            throw new Exception('Ошибка при удалении пользователя');
        }
    } catch (Exception $e) {
        sendResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
?>