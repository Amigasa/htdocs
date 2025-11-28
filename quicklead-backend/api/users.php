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

// GET - получение всех пользователей (Only admin may get full list)
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $requester_role = isset($_GET['user_role']) ? $_GET['user_role'] : null;
    error_log("GET /users called by role: " . ($requester_role ?? 'null'));
    if ($requester_role !== 'admin') {
        sendResponse(['success' => false, 'message' => 'Forbidden: insufficient privileges'], 403);
    }
    // Detect if the users table has project_id column (migration may not be applied yet)
    $hasProjectCol = false;
    try {
        $colStmt = $db->prepare("SHOW COLUMNS FROM users LIKE 'project_id'");
        $colStmt->execute();
        if ($colStmt->rowCount() > 0) $hasProjectCol = true;
    } catch (Exception $e) {
        // Log and continue
        error_log("users.php: error checking project_id column: " . $e->getMessage());
    }

    try {
        $users = [];
        if ($hasProjectCol) {
            // Use users.project_id if present, otherwise use the most recent lead.project_id for that user
            $query = "SELECT u.id, u.username, u.name, u.email, u.role, u.created_at, u.is_active, u.project_id,
                COALESCE(u.project_id, (SELECT l.project_id FROM leads l WHERE l.user_id = u.id ORDER BY l.updated_at DESC LIMIT 1)) AS effective_project_id,
                (SELECT p.name FROM projects p WHERE p.id = COALESCE(u.project_id, (SELECT l2.project_id FROM leads l2 WHERE l2.user_id = u.id ORDER BY l2.updated_at DESC LIMIT 1))) AS project_name
                FROM users u ORDER BY u.created_at DESC";
        } else {
            // old schema - use the most recent lead.project_id to determine current project
            $query = "SELECT u.id, u.username, u.name, u.email, u.role, u.created_at, u.is_active,
                (SELECT p.name FROM projects p JOIN leads l ON l.project_id = p.id WHERE l.user_id = u.id ORDER BY l.updated_at DESC LIMIT 1) AS project_name
                FROM users u ORDER BY u.created_at DESC";
        }
        $stmt = $db->prepare($query);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($row['project_id'])) $row['project_id'] = NULL;
            if (!isset($row['project_name'])) $row['project_name'] = NULL;
            $users[] = $row;
        }
    } catch (PDOException $e) {
        error_log("users.php: exception fetching users: " . $e->getMessage());
        sendResponse(['success' => false, 'message' => 'DB error while fetching users'], 500);
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
        $project_id = !empty($data->project_id) ? $data->project_id : NULL;

        // Detect project_id column once
        $hasProjectCol = false;
        try {
            $colStmt = $db->prepare("SHOW COLUMNS FROM users LIKE 'project_id'");
            $colStmt->execute();
            if ($colStmt->rowCount() > 0) $hasProjectCol = true;
        } catch (Exception $e) {
            error_log("users.php (POST): error checking project_id column: " . $e->getMessage());
        }

        if ($hasProjectCol) {
            $query = "INSERT INTO users SET 
                username = :username,
                password = :password,
                name = :name,
                email = :email,
                role = :role,
                project_id = :project_id,
                is_active = TRUE";
        } else {
            $query = "INSERT INTO users SET 
                username = :username,
                password = :password,
                name = :name,
                email = :email,
                role = :role,
                is_active = TRUE";
        }

        $stmt = $db->prepare($query);
        $stmt->bindParam(":username", $data->username);
        $stmt->bindParam(":password", $hashed_password);
        $stmt->bindParam(":name", $data->name);
        $stmt->bindParam(":email", $data->email);
        $stmt->bindParam(":role", $role);
        if ($hasProjectCol) {
            $stmt->bindParam(":project_id", $project_id);
        }
        
        if ($stmt->execute()) {
            $newUserId = $db->lastInsertId();
            
            // Получаем созданного пользователя (без пароля)
            if ($hasProjectCol) {
                $getQuery = "SELECT u.id, u.username, u.name, u.email, u.role, u.created_at, u.project_id,
                    COALESCE(u.project_id, (SELECT l.project_id FROM leads l WHERE l.user_id = u.id ORDER BY l.updated_at DESC LIMIT 1)) AS effective_project_id,
                    (SELECT p.name FROM projects p WHERE p.id = COALESCE(u.project_id, (SELECT l2.project_id FROM leads l2 WHERE l2.user_id = u.id ORDER BY l2.updated_at DESC LIMIT 1))) AS project_name
                    FROM users u WHERE u.id = :id";
            } else {
                $getQuery = "SELECT u.id, u.username, u.name, u.email, u.role, u.created_at,
                    (SELECT p.name FROM projects p JOIN leads l ON l.project_id = p.id WHERE l.user_id = u.id ORDER BY l.updated_at DESC LIMIT 1) AS project_name
                    FROM users u WHERE u.id = :id";
            }
            $getStmt = $db->prepare($getQuery);
            $getStmt->bindParam(":id", $newUserId);
            $getStmt->execute();
            $newUser = $getStmt->fetch(PDO::FETCH_ASSOC);
            if (!isset($newUser['project_id'])) $newUser['project_id'] = NULL;
            if (!isset($newUser['project_name'])) $newUser['project_name'] = NULL;
            
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
        
        // Detect project_id column
        $hasProjectCol = false;
        try {
            $colStmt = $db->prepare("SHOW COLUMNS FROM users LIKE 'project_id'");
            $colStmt->execute();
            if ($colStmt->rowCount() > 0) $hasProjectCol = true;
        } catch (Exception $e) {
            error_log("users.php (PUT): error checking project_id column: " . $e->getMessage());
        }

        if ($hasProjectCol) {
            $query = "UPDATE users SET 
                name = :name,
                email = :email,
                role = :role,
                project_id = :project_id
                WHERE id = :id";
        } else {
            $query = "UPDATE users SET 
                name = :name,
                email = :email,
                role = :role
                WHERE id = :id";
        }

        $stmt = $db->prepare($query);
        $stmt->bindParam(":name", $data->name);
        $stmt->bindParam(":email", $data->email);
        $stmt->bindParam(":role", $data->role);
        if ($hasProjectCol) {
            $project_id = !empty($data->project_id) ? $data->project_id : NULL;
            $stmt->bindParam(":project_id", $project_id);
        }
        $stmt->bindParam(":id", $data->id);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                // Re-fetch user to include project_name
                if ($hasProjectCol) {
                    $getQuery = "SELECT u.id, u.username, u.name, u.email, u.role, u.created_at, u.project_id,
                        COALESCE(u.project_id, (SELECT l.project_id FROM leads l WHERE l.user_id = u.id ORDER BY l.updated_at DESC LIMIT 1)) AS effective_project_id,
                        (SELECT p.name FROM projects p WHERE p.id = COALESCE(u.project_id, (SELECT l2.project_id FROM leads l2 WHERE l2.user_id = u.id ORDER BY l2.updated_at DESC LIMIT 1))) AS project_name
                        FROM users u WHERE u.id = :id";
                } else {
                    $getQuery = "SELECT u.id, u.username, u.name, u.email, u.role, u.created_at,
                        (SELECT p.name FROM projects p JOIN leads l ON l.project_id = p.id WHERE l.user_id = u.id ORDER BY l.updated_at DESC LIMIT 1) AS project_name
                        FROM users u WHERE u.id = :id";
                }
                $getStmt = $db->prepare($getQuery);
                $getStmt->bindParam(":id", $data->id);
                $getStmt->execute();
                $updatedUser = $getStmt->fetch(PDO::FETCH_ASSOC);
                if (!isset($updatedUser['project_name'])) $updatedUser['project_name'] = NULL;
                sendResponse([
                    'success' => true,
                    'message' => 'Пользователь обновлен успешно',
                    'user' => $updatedUser
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