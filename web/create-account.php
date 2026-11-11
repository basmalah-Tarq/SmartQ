<?php
// Old URL: it included signup.php a second time (duplicate code path). Keep the link working.
header('Location: signup.php', true, 301);
exit();
