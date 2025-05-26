<?php
require_once '../config.php';
requireRole('admin');

$pdo = getDbConnection();

// Get statistics
$stats = [];

// Total users by role
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE is_active = 1 GROUP BY role");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats[$row['role']] = $row['count'];
}

// Total appointments
$stmt = $pdo->query("SELECT COUNT(*) FROM appointments");
$stats['total_appointments'] = $stmt->fetchColumn();

// Pending appointments
$stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'");
$stats['pending_appointments'] = $stmt->fetchColumn();

// Recent appointments
$stmt = $pdo->query("
    SELECT a.*, 
           c.first_name as client_first_name, c.last_name as client_last_name,
           d_user.first_name as doctor_first_name, d_user.last_name as doctor_last_name,
           s.name as specialty_name
    FROM appointments a
    JOIN users c ON a.client_id = c.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users d_user ON d.user_id = d_user.id
    JOIN specialties s ON a.specialty_id = s.id
    ORDER BY a.created_at DESC
    LIMIT 10
");
$recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contact messages
$stmt = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
$stats['new_messages'] = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Admin - Cabinet Médical</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container dashboard">
        <div class="dashboard-header">
            <h1>Tableau de Bord Administrateur</h1>
            <p>Bienvenue, <?= htmlspecialchars($_SESSION['first_name']) ?>! Gérez votre cabinet médical depuis cette interface.</p>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions" style="margin: 2rem 0;">
            <h2>Actions Rapides</h2>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="manage_users.php" class="btn btn-primary">Gérer les Utilisateurs</a>
                <a href="manage_appointments.php" class="btn btn-secondary">Gérer les Rendez-vous</a>
                <a href="manage_doctors.php" class="btn btn-outline">Gérer les Médecins</a>
                <a href="view_messages.php" class="btn btn-outline">Messages (<?= $stats['new_messages'] ?? 0 ?>)</a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['client'] ?? 0 ?></div>
                <div class="stat-label">Clients</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['doctor'] ?? 0 ?></div>
                <div class="stat-label">Médecins</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['secretary'] ?? 0 ?></div>
                <div class="stat-label">Secrétaires</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_appointments'] ?? 0 ?></div>
                <div class="stat-label">Total Rendez-vous</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['pending_appointments'] ?? 0 ?></div>
                <div class="stat-label">Rendez-vous en Attente</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['new_messages'] ?? 0 ?></div>
                <div class="stat-label">Nouveaux Messages</div>
            </div>
        </div>

        <!-- Recent Appointments -->
        <div style="margin: 3rem 0;">
            <h2>Rendez-vous Récents</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date/Heure</th>
                            <th>Client</th>
                            <th>Médecin</th>
                            <th>Spécialité</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_appointments)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-light);">
                                    Aucun rendez-vous trouvé
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_appointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <?= formatDate($appointment['appointment_date']) ?><br>
                                        <small><?= formatTime($appointment['appointment_time']) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($appointment['client_first_name'] . ' ' . $appointment['client_last_name']) ?>
                                    </td>
                                    <td>
                                        Dr. <?= htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($appointment['specialty_name']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $appointment['status'] ?>">
                                            <?= ucfirst($appointment['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="manage_appointments.php?edit=<?= $appointment['id'] ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">Modifier</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="text-align: center; margin-top: 1rem;">
                <a href="manage_appointments.php" class="btn btn-outline">Voir Tous les Rendez-vous</a>
            </div>
        </div>

        <!-- System Information -->
        <div style="margin: 3rem 0;">
            <h2>Informations Système</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                <div class="info-card">
                    <h3>État du Système</h3>
                    <p>✅ Base de données: Connectée</p>
                    <p>✅ Serveur: Fonctionnel</p>
                    <p>📅 Dernière mise à jour: <?= date('d/m/Y H:i') ?></p>
                </div>
                
                <div class="info-card">
                    <h3>Statistiques Aujourd'hui</h3>
                    <?php
                    $today = date('Y-m-d');
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ?");
                    $stmt->execute([$today]);
                    $today_appointments = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE DATE(created_at) = ?");
                    $stmt->execute([$today]);
                    $today_messages = $stmt->fetchColumn();
                    ?>
                    <p>📅 Rendez-vous: <?= $today_appointments ?></p>
                    <p>✉️ Messages: <?= $today_messages ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <style>
    .quick-actions h2 {
        color: var(--primary-green);
        margin-bottom: 1rem;
    }
    
    .info-card {
        background: var(--white);
        padding: 2rem;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .info-card h3 {
        color: var(--primary-green);
        margin-bottom: 1rem;
    }
    
    .info-card p {
        margin: 0.5rem 0;
        color: var(--text-light);
    }
    </style>
</body>
</html>