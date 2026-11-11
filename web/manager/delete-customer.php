<?php
require_once '../config/db.php';
require_once '../config/security.php';

$user = require_role('manager');
require_post();
verify_csrf();

$managerId = (int)$user['id'];
$email = trim($_POST['email'] ?? '');

if ($email !== '') {
    $stmt = $conn->prepare(""
        . "DELETE a FROM appointments a "
        . "JOIN schedules s ON a.schedule_id = s.id "
        . "JOIN places p ON s.place_id = p.id "
        . "WHERE a.customer_email = ? AND p.manager_id = ?"
    );
    $stmt->bind_param('si', $email, $managerId);
    $stmt->execute();
}

header('Location: customers.php');
exit();
?>
