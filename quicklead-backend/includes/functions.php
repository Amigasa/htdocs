<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function validateApiKey($api_key) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id FROM projects WHERE api_key = :api_key";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":api_key", $api_key);
    $stmt->execute();
    
    return $stmt->rowCount() > 0;
}

function getAuthenticatedUser() {
    // Read basic user info passed by frontend in headers (demo auth).
    // In production this should be replaced with proper JWT/session.
    $user = null;
    $id = isset($_SERVER['HTTP_X_USER_ID']) ? intval($_SERVER['HTTP_X_USER_ID']) : null;
    $role = isset($_SERVER['HTTP_X_USER_ROLE']) ? $_SERVER['HTTP_X_USER_ROLE'] : null;
    $username = isset($_SERVER['HTTP_X_USER_NAME']) ? $_SERVER['HTTP_X_USER_NAME'] : null;
    if ($id || $role || $username) {
        $user = ['id' => $id, 'role' => $role, 'username' => $username];
    }
    return $user;
}
?>