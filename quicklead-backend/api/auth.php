<?php
include_once '../config/database.php';
include_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (!empty($data->username) && !empty($data->password)) {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT id, username, password, name, email, role, project_id FROM users WHERE username = :username";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":username", $data->username);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Для демо - простой пароль 'password'
            if ($data->password === 'password' || password_verify($data->password, $row['password'])) {
                $user_data = [
                    'id' => $row['id'],
                    'username' => $row['username'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'role' => $row['role']
                ];
                // include project_id if present
                if (isset($row['project_id'])) $user_data['project_id'] = $row['project_id'];
                
                sendResponse([
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => $user_data
                ]);
            }
        }
    }
    
    sendResponse([
        'success' => false,
        'message' => 'Invalid credentials'
    ], 401);
}
?>