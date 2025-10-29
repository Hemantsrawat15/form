<?php
require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json; charset=UTF-8');

require_once '../config/database.php';
require_once '../config/jwt_handler.php';

$jwt_handler = new JwtHandler();
if (!$jwt_handler->getUserId()) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

if ($group_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "A valid group_id is required."]);
    exit();
}

$query = "SELECT id, form_name, created_at FROM forms WHERE group_id = ? AND is_active = TRUE ORDER BY form_name";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $group_id);
$stmt->execute();
$result = $stmt->get_result();
$forms = $result->fetch_all(MYSQLI_ASSOC);
$conn->close();

echo json_encode($forms);
?>