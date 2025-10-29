<?php
// This must be the very first line
require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json; charset=UTF-8');

require_once '../config/database.php';
require_once '../config/jwt_handler.php';

// 1. Authenticate the user and get their ID
$jwt_handler = new JwtHandler();
$user_id = $jwt_handler->getUserId();

if (!$user_id) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized: No valid token provided."]);
    exit();
}

// 2. Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed"]);
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$data = json_decode(file_get_contents("php://input"));

// 3. Validate the incoming data
if (empty($data->group_name)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Group name cannot be empty."]);
    exit();
}

// 4. Prepare and execute the database query
$query = "INSERT INTO `groups` (user_id, group_name, description) VALUES (?, ?, ?)";

try {
    $stmt = $conn->prepare($query);

    // Sanitize input
    $group_name = htmlspecialchars(strip_tags($data->group_name));
    $description = isset($data->description) ? htmlspecialchars(strip_tags($data->description)) : '';

    // Bind the logged-in user's ID to the new group
    $stmt->bind_param("iss", $user_id, $group_name, $description);

    // 5. Respond with success or failure
    if ($stmt->execute()) {
        http_response_code(201); // 201 Created
        echo json_encode([
            "success" => true, 
            "message" => "Group created successfully.",
            "group_id" => $conn->insert_id // Send back the ID of the new group
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