<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers/api_helper.php';
jsonResponse(true, "SmartQ API is running", [
    "server_time" => date('c'),
    "api_version" => 2
]);
