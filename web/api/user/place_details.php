<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/api_helper.php';
require_once __DIR__ . '/../helpers/schedules_query.php';

$placeId = (int)($_GET['place_id'] ?? 0);
if ($placeId <= 0) jsonResponse(false, "Place ID is required", [], 422);
$place = fetchActivePlace($conn, $placeId);
if (!$place) jsonResponse(false, "Place not found", [], 404);

jsonResponse(true, "Place details fetched successfully", [
    "place" => placePayload($place),
    "schedules" => fetchOpenSchedules($conn, $placeId)
]);
