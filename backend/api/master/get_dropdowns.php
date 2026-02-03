<?php
// Use absolute path based on document root
require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$response = [
    "cadres" => [],
    "designations" => [],
    "internal_designations" => [],
    "groups" => []
];

// 1. Fetch Cadres
$cadre_res = $conn->query("SELECT id, name FROM id_cadre WHERE is_deleted = 'no' ORDER BY id ASC");
if($cadre_res) {
    $response["cadres"] = $cadre_res->fetch_all(MYSQLI_ASSOC);
}

// 2. Fetch Designations (Include cadre_id for filtering in React)
$desig_res = $conn->query("SELECT id, name, desig_fullname, cadre_id FROM id_desig WHERE is_deleted = 'no' ORDER BY name ASC");
if($desig_res) {
    $response["designations"] = $desig_res->fetch_all(MYSQLI_ASSOC);
}

// 3. Fetch Internal Designations (Roles)
$internal_res = $conn->query("SELECT id, shortname, fullname FROM id_internaldesig WHERE is_deleted = 'no' ORDER BY shortname ASC");
if($internal_res) {
    $response["internal_designations"] = $internal_res->fetch_all(MYSQLI_ASSOC);
}

// 4. Fetch Groups
$group_res = $conn->query("SELECT id, name, fullname FROM id_group WHERE is_deleted = 'no' ORDER BY name ASC");
if($group_res) {
    $response["groups"] = $group_res->fetch_all(MYSQLI_ASSOC);
}

$conn->close();

echo json_encode($response);
?>