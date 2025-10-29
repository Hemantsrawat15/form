<?php
// --- Centralized CORS Handling ---

// 1. Specify the EXACT trusted origin (your Vite/React app's URL)
header("Access-Control-Allow-Origin: http://localhost:5173");

// 2. Specify the allowed HTTP methods
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// 3. Specify the allowed headers required by your application
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// 4. Handle the browser's pre-flight 'OPTIONS' request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}