<?php
require_once 'config.php';

// Destroy session and redirect
session_destroy();
header('Location: index.php?message=logged_out');
exit();
?>