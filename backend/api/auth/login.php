<?php
require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json; charset=UTF-8');

require_once '../config/database.php';
require_once '../config/jwt_handler.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Only POST method is accepted."]);
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$data = json_decode(file_get_contents("php://input"));

if (empty($data->email) || empty($data->password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Email and password are required."]);
    exit();
}

$email = $conn->real_escape_string($data->email);
$password = $data->password;

$query = "SELECT id, email, password_hash FROM users WHERE email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$conn->close();

if ($user && password_verify($password, $user['password_hash'])) {
    $jwt = new JwtHandler();
    $token = $jwt->encode(["user_id" => $user['id']]);

    http_response_code(200);
    echo json_encode([
        "success" => true, "message" => "Login successful.",
        "token" => $token, "user" => ["id" => $user['id'], "email" => $user['email']]
    ]);
} else {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Invalid email or password."]);
}
?>