<?php
// config.php - Database configuration and utility functions
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'medical_cabinet');

// Global PDO connection
$pdo = null;

// Create database connection
function getDbConnection() {
    global $pdo;
    if ($pdo === null) {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check user role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . getBasePath() . 'login.php');
        exit();
    }
}

// Require specific role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: ' . getBasePath() . 'index.php');
        exit();
    }
}

// Get base path for redirects
function getBasePath() {
    $currentPath = $_SERVER['REQUEST_URI'];
    if (strpos($currentPath, '/admin/') !== false || 
        strpos($currentPath, '/client/') !== false || 
        strpos($currentPath, '/secretary/') !== false || 
        strpos($currentPath, '/doctor/') !== false) {
        return '../';
    }
    return '';
}

// Get current user info
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    static $currentUser = null;
    if ($currentUser === null) {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return $currentUser;
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(trim($data));
}

// Format date for display
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

// Format time for display
function formatTime($time) {
    return date('H:i', strtotime($time));
}

// Increase memory limit if needed
ini_set('memory_limit', '256M');
?>