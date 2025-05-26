<?php
require_once '../config.php';
requireRole('admin');

$pdo = getDbConnection();
$error = '';
$success = '';

// Handle appointment status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_status') {
        $appointment_id = $_POST['appointment_id'];
        $new_status = $_POST['status'];
        $admin_notes = sanitize($_POST['admin_notes']);
        
        $stmt = $pdo->prepare("UPDATE appointments SET status = ?, secretary_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        if ($stmt->execute([$new_status, $admin_notes, $appointment_id])) {
            $success = 'Statut du rendez-vous mis à jour avec succès!';
        } else {
            $error = 'Erreur lors de la mise à jour du statut.';
        }
    }
    
    if ($_POST['action'] == 'delete_appointment') {
        $appointment_id = $_POST['appointment_id'];
        
        $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
        if ($stmt->execute([$appointment_id])) {
            $success = 'Rendez-vous supprimé avec succès!';
        } else {
            $error = 'Erreur lors de la suppression du rendez-vous.';
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$doctor_filter = $_GET['doctor'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    $where_conditions[] = "a.appointment_date = ?";
    $params[] = $date_filter;
}

if ($doctor_filter) {
    $where_conditions[] = "a.doctor_id = ?";
    $params[] = $doctor_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get all appointments with details
$stmt = $pdo->prepare("
    SELECT a.*, 
           c.first_name as client_first_name, c.last_name as client_last_name, c.email as client_email, c.phone as client_phone,
           d_user.first_name as doctor_first_name, d_user.last_name as doctor_last_name,
           s.name as specialty_name,
           r.id as report_id
    FROM appointments a
    JOIN users c ON a.client_id = c.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users d_user ON d.user_id = d_user.id
    JOIN specialties s ON a.specialty_id = s.id
    LEFT JOIN medical_reports r ON a.id = r.appointment_id
    $where_clause
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");

$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get doctors for filter
$stmt = $pdo->query("
    SELECT d.id, u.first_name, u.last_name 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    WHERE u.is_active = 1 
    ORDER BY u.last_name, u.first_name
");
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [
    'total' => count($appointments),
    'pending' => count(array_filter($appointments, function($apt) { return $apt['status'] == 'pending'; })),
    'confirmed' => count(array_filter($appointments, function($apt) { return $apt['status'] == 'confirmed'; })),
    'completed' => count(array_filter($appointments, function($apt) { return $apt['status'] == 'completed'; })),
    'cancelled' => count(array_filter($appointments, function($apt) { return $apt['status'] == 'cancelled'; }))
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Rendez-vous - Admin</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container" style="padding: 2rem 0;">
        <h1 style="color: var(--primary-green); margin-bottom: 2rem;">Gestion des Rendez-vous</h1>
        
        <nav style="margin-bottom: 2rem;">
            <a href="dashboard.php" class="btn btn-outline">← Retour au Tableau de Bord</a>
        </nav>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid" style="margin: 2rem 0;">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total</div>
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
                <div class="stat-number"><?= $stats['completed'] ?></div>
                <div class="stat-label">Terminés</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['cancelled'] ?></div>
                <div class="stat-label">Annulés</div>
            </div>
        </div>

        <!-- Filters -->
        <div style="background: var(--white); padding: 2rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 2rem;">
            <h3 style="color: var(--primary-green); margin-bottom: 1rem;">Filtres</h3>
            
            <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                <div class="form-group">
                    <label for="status">Statut:</label>
                    <select id="status" name="status">
                        <option value="">Tous les statuts</option>
                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>En Attente</option>
                        <option value="confirmed" <?= $status_filter == 'confirmed' ? 'selected' : '' ?>>Confirmé</option>
                        <option value="completed" <?= $status_filter == 'completed' ? 'selected' : '' ?>>Terminé</option>
                        <option value="cancelled" <?= $status_filter == 'cancelled' ? 'selected' : '' ?>>Annulé</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date">Date:</label>
                    <input type="date" id="date" name="date" value="<?= htmlspecialchars($date_filter) ?>">
                </div>
                
                <div class="form-group">
                    <label for="doctor">Médecin:</label>
                    <select id="doctor" name="doctor">
                        <option value="">Tous les médecins</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?= $doctor['id'] ?>" <?= $doctor_filter == $doctor['id'] ? 'selected' : '' ?>>
                                Dr. <?= htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="manage_appointments.php" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>

        <!-- Appointments List -->
        <div style="background: var(--white); padding: 2rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
            <h2 style="color: var(--primary-green); margin-bottom: 2rem;">
                Liste des Rendez-vous 
                <?php if ($where_conditions): ?>
                    <small style="color: var(--text-light);">(<?= count($appointments) ?> résultat<?= count($appointments) > 1 ? 's' : '' ?>)</small>
                <?php endif; ?>
            </h2>
            
            <?php if (empty($appointments)): ?>
                <div style="text-align: center; padding: 2rem; color: var(--text-light);">
                    Aucun rendez-vous trouvé avec ces critères.
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
                                <th>Statut</th>
                                <th>Rapport</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
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
                                        <span class="status-badge status-<?= $appointment['status'] ?>">
                                            <?php
                                            switch($appointment['status']) {
                                                case 'pending': echo 'En Attente'; break;
                                                case 'confirmed': echo 'Confirmé'; break;
                                                case 'cancelled': echo 'Annulé'; break;
                                                case 'completed': echo 'Terminé'; break;
                                                default: echo ucfirst($appointment['status']);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($appointment['report_id']): ?>
                                            <span class="status-badge status-confirmed">✅ Rapport</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-light);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick="editAppointment(<?= htmlspecialchars(json_encode($appointment)) ?>)" 
                                                class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">
                                            Modifier
                                        </button>
                                        <button onclick="deleteAppointment(<?= $appointment['id'] ?>)" 
                                                class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.875rem; margin-left: 0.25rem;">
                                            Supprimer
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Appointment Modal -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Modifier le Rendez-vous</h2>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="appointment_id" id="modal_appointment_id">
                
                <div id="appointmentDetails"></div>
                
                <div class="form-group">
                    <label for="status">Statut:</label>
                    <select id="modal_status" name="status" required>
                        <option value="pending">En Attente</option>
                        <option value="confirmed">Confirmé</option>
                        <option value="cancelled">Annulé</option>
                        <option value="completed">Terminé</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="admin_notes">Notes Administrateur:</label>
                    <textarea id="admin_notes" name="admin_notes" rows="3" 
                              placeholder="Ajoutez des notes administratives..."></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Mettre à Jour</button>
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <style>
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
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
    function editAppointment(appointment) {
        document.getElementById('modal_appointment_id').value = appointment.id;
        document.getElementById('modal_status').value = appointment.status;
        document.getElementById('admin_notes').value = appointment.secretary_notes || '';
        
        const details = `
            <div class="detail-item">
                <span class="detail-label">Client:</span> ${appointment.client_first_name} ${appointment.client_last_name}
            </div>
            <div class="detail-item">
                <span class="detail-label">Email:</span> ${appointment.client_email}
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
        document.getElementById('editModal').style.display = 'block';
    }

    function deleteAppointment(appointmentId) {
        if (confirm('Êtes-vous sûr de vouloir supprimer ce rendez-vous? Cette action est irréversible.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_appointment">
                <input type="hidden" name="appointment_id" value="${appointmentId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('editModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
    </script>
</body>
</html>