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
?>