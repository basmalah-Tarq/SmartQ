<?php
// Old success page: it showed any booking by id without checking its owner.
// Redirect to the secure page that verifies the booking belongs to the logged-in user.
$id = (int)($_GET['appointment_id'] ?? $_GET['id'] ?? 0);
header('Location: booking-complete.php?id=' . $id);
exit();
