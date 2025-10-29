<?php
require_once __DIR__ . '/../cors.php'; // The first and most important line
header('Content-Type: application/json; charset=UTF-8');

require_once '../config/database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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
$password_hash = password_hash($data->password, PASSWORD_BCRYPT);
$username = explode('@', $email)[0];

$query = "INSERT INTO users (username, full_name, email, password_hash) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("ssss", $username, $username, $email, $password_hash);

try {
    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["success" => true, "message" => "User was successfully registered."]);
    }
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) {
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "This email or username is already registered."]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database error during registration."]);
    }
} finally {
    $conn->close();
}
?>