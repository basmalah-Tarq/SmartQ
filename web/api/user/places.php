<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/api_helper.php';

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$city = trim($_GET['city'] ?? '');

$sql = "SELECT id, place_name, category, city, address, description, place_image FROM places WHERE active = 1";
$params = [];
$types = "";
if ($search !== '') {
    $sql .= " AND (place_name LIKE ? OR city LIKE ? OR category LIKE ? OR address LIKE ? OR description LIKE ?)";
    $like = "%{$search}%";
    array_push($params, $like, $like, $like, $like, $like);
    $types .= "sssss";
}
if ($category !== '' && strtolower($category) !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}
if ($city !== '' && strtolower($city) !== 'all') {
    $sql .= " AND city = ?";
    $params[] = $city;
    $types .= "s";
}
$sql .= " ORDER BY place_name ASC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$places = [];
while ($row = $res->fetch_assoc()) $places[] = placePayload($row);

$cats = [];
$cr = $conn->query("SELECT DISTINCT category FROM places WHERE active = 1 ORDER BY category");
while ($c = $cr->fetch_assoc()) $cats[] = $c['category'];

jsonResponse(true, "Places fetched successfully", ["places" => $places, "categories" => $cats]);
