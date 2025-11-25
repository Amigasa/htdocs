<?php
include_once '../config/database.php';
include_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    // Валидация данных
    if (empty($data->username) || empty($data->password) || empty($data->name) || empty($data->email)) {
        sendResponse(['success' => false, 'message' => 'Все поля обязательны для заполнения'], 400);
    }
    
    if (strlen($data->password) < 6) {
        sendResponse(['success' => false, 'message' => 'Пароль должен быть не менее 6 символов'], 400);
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Проверяем, не занят ли username
    $query = "SELECT id FROM users WHERE username = :username";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":username", $data->username);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => false, 'message' => 'Пользователь с таким логином уже существует'], 400);
    }
    
    // Проверяем, не занят ли email
    $query = "SELECT id FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":email", $data->email);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => false, 'message' => 'Пользователь с таким email уже существует'], 400);
    }
    
    // Создаем пользователя
    $hashed_password = password_hash($data->password, PASSWORD_DEFAULT);
    
    $query = "INSERT INTO users SET 
        username = :username,
        password = :password,
        name = :name,
        email = :email,
        role = 'client',
        is_active = TRUE";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":username", $data->username);
    $stmt->bindParam(":password", $hashed_password);
    $stmt->bindParam(":name", $data->name);
    $stmt->bindParam(":email", $data->email);
    
    if ($stmt->execute()) {
        sendResponse([
            'success' => true,
            'message' => 'Регистрация успешна! Теперь вы можете войти в систему.'
        ], 201);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Ошибка при создании пользователя'
        ], 500);
    }
}
?>