<?php
// Use absolute path based on document root
require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Only POST method is accepted."]);
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$data = json_decode(file_get_contents("php://input"));

// Basic Validation
if (empty($data->email) || empty($data->password) || empty($data->first_name) || empty($data->last_name)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Required fields missing."]);
    exit();
}

// Sanitize Inputs
$email = $conn->real_escape_string($data->email);
$password_hash = password_hash($data->password, PASSWORD_BCRYPT);
$first_name = $conn->real_escape_string($data->first_name);
$middle_name = isset($data->middle_name) ? $conn->real_escape_string($data->middle_name) : '';
$last_name = $conn->real_escape_string($data->last_name);
$gen = isset($data->gen) ? $conn->real_escape_string($data->gen) : 'Not Specified';
$dob = isset($data->dob) ? $conn->real_escape_string($data->dob) : date('Y-m-d');
$mobile_no = isset($data->mobile_no) ? $conn->real_escape_string($data->mobile_no) : '0000000000';

// Handle Foreign Keys (Ensure integer)
// If frontend sends empty string, default to 1
$cadre_id = (!empty($data->cadre_id)) ? (int)$data->cadre_id : 1;
$desig_id = (!empty($data->desig_id)) ? (int)$data->desig_id : 1;
$internal_desig_id = (!empty($data->internal_desig_id)) ? (int)$data->internal_desig_id : 1;
$group_id = (!empty($data->group_id)) ? (int)$data->group_id : 1;

$user_type = isset($data->user_type) ? $conn->real_escape_string($data->user_type) : 'Temporary';
$telephone_no = isset($data->telephone_no) ? $conn->real_escape_string($data->telephone_no) : '';
$user_name = isset($data->user_name) ? $conn->real_escape_string($data->user_name) : explode('@', $email)[0];
$is_gazetted = isset($data->is_gazetted) ? $conn->real_escape_string($data->is_gazetted) : 'no';
$status = 1;
$is_deleted = 'no';

$query = "INSERT INTO id_emp (
    first_name, middle_name, last_name, gen, dob, mobile_no, email_id, 
    cadre_id, desig_id, internal_desig_id, group_id, user_type, 
    telephone_no, user_name, password, status, is_gazetted, is_deleted
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($query);

// 18 items = 18 chars in string
$stmt->bind_param(
    "sssssssiiiissssiss", 
    $first_name, $middle_name, $last_name, $gen, $dob, $mobile_no, $email, 
    $cadre_id, $desig_id, $internal_desig_id, $group_id, 
    $user_type, $telephone_no, $user_name, $password_hash, 
    $status, $is_gazetted, $is_deleted
);

try {
    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["success" => true, "message" => "User successfully registered."]);
    }
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) {
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "Email already registered."]);
    } elseif ($e->getCode() == 1452) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid Dropdown Selection (ID not found in DB)."]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
    }
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>