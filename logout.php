<?php
// logout.php - Clear sessions and redirect to login page

session_start();
session_unset();
session_destroy();

header("Location: index.html");
exit;
?>
