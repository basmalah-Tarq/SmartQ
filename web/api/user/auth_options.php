<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/api_helper.php';
require_once __DIR__ . '/../../config/auth_service.php';

// Which sign-in methods are switched on (the app shows/hides buttons accordingly).
jsonResponse(true, "Auth options", smartq_auth_options());
