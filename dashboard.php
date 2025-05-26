<?php
require_once 'config.php';
requireLogin();

// Redirect to appropriate dashboard based on user role
switch ($_SESSION['role']) {
    case 'admin':
        header('Location: admin/dashboard.php');
        break;
    case 'secretary':
        header('Location: secretary/dashboard.php');
        break;
    case 'doctor':
        header('Location: doctor/dashboard.php');
        break;
    case 'client':
        header('Location: client/dashboard.php');
        break;
    default:
        header('Location: index.php');
}
exit();
?>