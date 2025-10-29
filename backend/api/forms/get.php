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
$form_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($form_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "A valid form ID is required."]);
    exit();
}

$query = "SELECT id, group_id, form_name, form_template FROM forms WHERE id = ? AND is_active = TRUE";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $form_id);
$stmt->execute();
$result = $stmt->get_result();
$form = $result->fetch_assoc();
$conn->close();

if ($form) {
    $form['form_template'] = json_decode($form['form_template']);
    echo json_encode($form);
} else {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Form not found or is inactive."]);
}
?>