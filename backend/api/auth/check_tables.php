<?php
require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json');
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

function checkId1($conn, $table) {
    $result = $conn->query("SELECT id FROM $table WHERE id = 1");
    if ($result && $result->num_rows > 0) {
        return "OK (Found ID 1)";
    }
    return "CRITICAL ERROR: ID 1 is missing!";
}

echo json_encode([
    "id_cadre" => checkId1($conn, 'id_cadre'),
    "id_desig" => checkId1($conn, 'id_desig'),
    "id_internaldesig" => checkId1($conn, 'id_internaldesig'),
    "id_group" => checkId1($conn, 'id_group')
], JSON_PRETTY_PRINT);
?>