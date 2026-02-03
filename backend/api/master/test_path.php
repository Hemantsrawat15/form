<?php
echo "Current directory: " . __DIR__ . "<br>";
echo "Parent directory: " . dirname(__DIR__) . "<br>";
echo "Grandparent directory: " . dirname(dirname(__DIR__)) . "<br>";

$db_path = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
echo "Database path: " . $db_path . "<br>";
echo "File exists: " . (file_exists($db_path) ? 'YES' : 'NO') . "<br>";
?>