<?php
require_once __DIR__ . '/../helpers/auth_user.php';

$user = requireUserAuth($conn);
jsonResponse(true, "Profile fetched successfully", ["user" => userPayload($user)]);
