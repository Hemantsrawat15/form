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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed"]);
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$data = json_decode(file_get_contents("php://input"));

// The payload is expected to be an array: [{...}]
$form_data = $data[0] ?? null;

if (!$form_data || empty($form_data->group_id) || empty($form_data->form_name) || empty($form_data->form_template->html)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid payload. group_id, form_name, and form_template.html are required."]);
    exit();
}

$query = "INSERT INTO forms (group_id, form_name, form_template) VALUES (?, ?, ?)";
$stmt = $conn->prepare($query);

$group_id = (int)$form_data->group_id;
$form_name = htmlspecialchars(strip_tags($form_data->form_name));

// The form_template is an object, so we re-encode it as a string for the DB
$form_template_json = json_encode($form_data->form_template);

$stmt->bind_param("iss", $group_id, $form_name, $form_template_json);

if ($stmt->execute()) {
    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Form template created successfully.",
        "form_id" => $conn->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to create form template."]);
}

$conn->close();
?>
