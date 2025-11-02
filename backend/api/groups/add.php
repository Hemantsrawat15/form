<?php 
require_once __DIR__ . '/../cors.php'; 
header('Content-Type: application/json; charset=UTF-8');

require_once '../config/database.php';
require_once '../config/jwt_handler.php';

$jwt_handler = new JwtHandler();
$user_id = $jwt_handler->getUserId();

if (!$user_id) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized: No valid token provided."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed"]);
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$data = json_decode(file_get_contents("php://input"));

if (empty($data->group_name)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Group name cannot be empty."]);
    exit();
}

// Updated query - now using created_by instead of user_id
$query = "INSERT INTO groups (created_by, group_name, description) VALUES (?, ?, ?)";

try {
    $stmt = $conn->prepare($query);
    
    $group_name = htmlspecialchars(strip_tags($data->group_name));
    $description = isset($data->description) ? htmlspecialchars(strip_tags($data->description)) : '';
    
    // Store who created it, but don't restrict access
    $stmt->bind_param("iss", $user_id, $group_name, $description);
    
    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode([
            "success" => true, 
            "message" => "Group created successfully. This group is now accessible to all users.",
            "group_id" => $conn->insert_id
        ]);
    } else {
        throw new Exception("Database execution failed.");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to create group. " . $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
