<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['specialty_id'])) {
    echo json_encode([]);
    exit();
}

$specialty_id = $_GET['specialty_id'];
$pdo = getDbConnection();

$stmt = $pdo->prepare("
    SELECT d.id, d.consultation_fee, u.first_name, u.last_name 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.specialty_id = ? AND u.is_active = 1
    ORDER BY u.last_name, u.first_name
");

$stmt->execute([$specialty_id]);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($doctors);
?>