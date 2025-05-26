<?php
require_once '../config.php';
requireRole('secretary');

$pdo = getDbConnection();
$error = '';
$success = '';

// Handle appointment status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_status') {
        $appointment_id = $_POST['appointment_id'];
        $new_status = $_POST['status'];
        $secretary_notes = sanitize($_POST['secretary_notes']);
        
        $stmt = $pdo->prepare("UPDATE appointments SET status = ?, secretary_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        if ($stmt->execute([$new_status, $secretary_notes, $appointment_id])) {
            $success = 'Statut du rendez-vous mis à jour avec succès!';
        } else {
            $error = 'Erreur lors de la mise à jour du statut.';
        }
    }
}

// Get all appointments with details
$stmt = $pdo->query("
    SELECT a.*, 
           c.first_name as client_first_name, c.last_name as client_last_name, c.email as client_email, c.phone as client_phone,
           d_user.first_name as doctor_first_name, d_user.last_name as doctor_last_name,
           s.name as specialty_name
    FROM appointments a
    JOIN users c ON a.client_id = c.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users d_user ON d.user_id = d_user.id
    JOIN specialties s ON a.specialty_id = s.id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$all_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter appointments
$pending_appointments = array_filter($all_appointments, function($apt) { return $apt['status'] == 'pending'; });
$confirmed_appointments = array_filter($all_appointments, function($apt) { return $apt['status'] == 'confirmed'; });
$today_appointments = array_filter($all_appointments, function($apt) { 
    return $apt['appointment_date'] == date('Y-m-d') && $apt['status'] == 'confirmed'; 
});

// Get statistics
$stats = [
    'total' => count($all_appointments),
    'pending' => count($pending_appointments),
    'confirmed' => count($confirmed_appointments),
    'today' => count($today_appointments)
];

// Get contact messages
$stmt = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
$new_messages = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Secrétaire - Cabinet Médical</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container dashboard">
        <div class="dashboard-header">
            <h1>Tableau de Bord Secrétaire</h1>
            <p>Bienvenue, <?= htmlspecialchars($_SESSION['first_name']) ?>! Gérez les rendez-vous et assistez les patients.</p>
        </div>

        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="quick-actions" style="margin: 2rem 0;">
            <h2>Actions Rapides</h2>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="../appointment.php" class="btn btn-primary">Nouveau Rendez-vous</a>
                <a href="view_messages.php" class="btn btn-secondary">Messages (<?= $new_messages ?>)</a>
                <a href="../specialties.php" class="btn btn-outline">Voir Spécialités</a>
                <a href="manage_appointments.php" class="btn btn-outline">Tous les Rendez-vous</a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Rendez-vous</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['pending'] ?></div>
                <div class="stat-label">En Attente</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['confirmed'] ?></div>
                <div class="stat-label">Confirmés</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['today'] ?></div>
                <div class="stat-label">Aujourd'hui</div>
            </div>
        </div>

        <!-- Pending Appointments (Need Validation) -->
        <div style="margin: 3rem 0;">
            <h2 style="color: #dc3545;">🔔 Rendez-vous en Attente de Validation</h2>
            <?php if (empty($pending_appointments)): ?>
                <div style="text-align: center; padding: 2rem; background: var(--very-light-green); border-radius: 10px;">
                    <p>✅ Aucun rendez-vous en attente de validation!</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date/Heure</th>
                                <th>Client</th>
                                <th>Contact</th>
                                <th>Médecin</th>
                                <th>Spécialité</th>
                                <th>Motif</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_appointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <strong><?= formatDate($appointment['appointment_date']) ?></strong><br>
                                        <small><?= formatTime($appointment['appointment_time']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($appointment['client_first_name'] . ' ' . $appointment['client_last_name']) ?></strong>
                                    </td>
                                    <td>
                                        📧 <?= htmlspecialchars($appointment['client_email']) ?><br>
                                        <?php if ($appointment['client_phone']): ?>
                                            📞 <?= htmlspecialchars($appointment['client_phone']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        Dr. <?= htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($appointment['specialty_name']) ?></td>
                                    <td>
                                        <?php if ($appointment['notes']): ?>
                                            <span title="<?= htmlspecialchars($appointment['notes']) ?>">
                                                <?= htmlspecialchars(substr($appointment['notes'], 0, 30)) ?><?= strlen($appointment['notes']) > 30 ? '...' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <em>Aucun motif spécifié</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick="showValidationModal(<?= htmlspecialchars(json_encode($appointment)) ?>)" 
                                                class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">
                                            Valider
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Today's Confirmed Appointments -->
        <div style="margin: 3rem 0;">
            <h2 style="color: var(--primary-green);">📅 Rendez-vous Confirmés Aujourd'hui</h2>
            <?php if (empty($today_appointments)): ?>
                <div style="text-align: center; padding: 2rem; background: var(--light-gray); border-radius: 10px;">
                    <p>Aucun rendez-vous confirmé pour aujourd'hui.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Heure</th>
                                <th>Client</th>
                                <th>Médecin</th>
                                <th>Spécialité</th>
                                <th>Contact</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($today_appointments as $appointment): ?>
                                <tr>
                                    <td><strong><?= formatTime($appointment['appointment_time']) ?></strong></td>
                                    <td><?= htmlspecialchars($appointment['client_first_name'] . ' ' . $appointment['client_last_name']) ?></td>
                                    <td>Dr. <?= htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) ?></td>
                                    <td><?= htmlspecialchars($appointment['specialty_name']) ?></td>
                                    <td>
                                        📧 <?= htmlspecialchars($appointment['client_email']) ?><br>
                                        <?php if ($appointment['client_phone']): ?>
                                            📞 <?= htmlspecialchars($appointment['client_phone']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($appointment['secretary_notes']): ?>
                                            <?= htmlspecialchars($appointment['secretary_notes']) ?>
                                        <?php else: ?>
                                            <em>Aucune note</em>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Confirmed Appointments -->
        <div style="margin: 3rem 0;">
            <h2>Rendez-vous Confirmés Récents</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date/Heure</th>
                            <th>Client</th>
                            <th>Médecin</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($confirmed_appointments, 0, 10) as $appointment): ?>
                            <tr>
                                <td>
                                    <?= formatDate($appointment['appointment_date']) ?><br>
                                    <small><?= formatTime($appointment['appointment_time']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($appointment['client_first_name'] . ' ' . $appointment['client_last_name']) ?></td>
                                <td>Dr. <?= htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) ?></td>
                                <td>
                                    <span class="status-badge status-confirmed">Confirmé</span>
                                </td>
                                <td>
                                    <button onclick="showValidationModal(<?= htmlspecialchars(json_encode($appointment)) ?>)" 
                                            class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">
                                        Modifier
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Validation Modal -->
    <div id="validationModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeValidationModal()">&times;</span>
            <h2>Valider/Modifier le Rendez-vous</h2>
            
            <form method="POST" id="validationForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="appointment_id" id="modal_appointment_id">
                
                <div id="appointmentDetails"></div>
                
                <div class="form-group">
                    <label for="status">Nouveau Statut:</label>
                    <select id="status" name="status" required>
                        <option value="pending">En Attente</option>
                        <option value="confirmed">Confirmé</option>
                        <option value="cancelled">Annulé</option>
                        <option value="completed">Terminé</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="secretary_notes">Notes de la Secrétaire:</label>
                    <textarea id="secretary_notes" name="secretary_notes" rows="3" 
                              placeholder="Ajoutez des notes sur ce rendez-vous..."></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Mettre à Jour</button>
                    <button type="button" onclick="closeValidationModal()" class="btn btn-secondary">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <style>
    .quick-actions h2 {
        color: var(--primary-green);
        margin-bottom: 1rem;
    }

    .modal {
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }

    .modal-content {
        background-color: var(--white);
        margin: 10% auto;
        padding: 2rem;
        border-radius: 10px;
        width: 80%;
        max-width: 600px;
        position: relative;
    }

    .close {
        position: absolute;
        right: 1rem;
        top: 1rem;
        font-size: 2rem;
        cursor: pointer;
        color: var(--text-light);
    }

    .close:hover {
        color: var(--primary-green);
    }

    #appointmentDetails {
        background: var(--very-light-green);
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .detail-item {
        margin: 0.5rem 0;
    }

    .detail-label {
        font-weight: bold;
        color: var(--primary-green);
    }
    </style>

    <script>
    function showValidationModal(appointment) {
        document.getElementById('modal_appointment_id').value = appointment.id;
        document.getElementById('status').value = appointment.status;
        document.getElementById('secretary_notes').value = appointment.secretary_notes || '';
        
        const details = `
            <div class="detail-item">
                <span class="detail-label">Client:</span> ${appointment.client_first_name} ${appointment.client_last_name}
            </div>
            <div class="detail-item">
                <span class="detail-label">Email:</span> ${appointment.client_email}
            </div>
            <div class="detail-item">
                <span class="detail-label">Téléphone:</span> ${appointment.client_phone || 'Non renseigné'}
            </div>
            <div class="detail-item">
                <span class="detail-label">Date:</span> ${new Date(appointment.appointment_date).toLocaleDateString('fr-FR')}
            </div>
            <div class="detail-item">
                <span class="detail-label">Heure:</span> ${appointment.appointment_time}
            </div>
            <div class="detail-item">
                <span class="detail-label">Médecin:</span> Dr. ${appointment.doctor_first_name} ${appointment.doctor_last_name}
            </div>
            <div class="detail-item">
                <span class="detail-label">Spécialité:</span> ${appointment.specialty_name}
            </div>
            <div class="detail-item">
                <span class="detail-label">Motif:</span> ${appointment.notes || 'Aucun motif spécifié'}
            </div>
        `;
        
        document.getElementById('appointmentDetails').innerHTML = details;
        document.getElementById('validationModal').style.display = 'block';
    }

    function closeValidationModal() {
        document.getElementById('validationModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('validationModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
    </script>
</body>
</html>