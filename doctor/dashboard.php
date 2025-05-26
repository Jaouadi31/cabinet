<?php
require_once '../config.php';
requireRole('doctor');

$pdo = getDbConnection();
$error = '';
$success = '';

// Get doctor's info
$stmt = $pdo->prepare("
    SELECT d.*, u.first_name, u.last_name, s.name as specialty_name 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    JOIN specialties s ON d.specialty_id = s.id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$doctor_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor_info) {
    die("Erreur: Informations médecin introuvables.");
}

// Handle report creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'create_report') {
        $appointment_id = $_POST['appointment_id'];
        $diagnosis = sanitize($_POST['diagnosis']);
        $recommendations = sanitize($_POST['recommendations']);
        $prescription = sanitize($_POST['prescription']);
        
        // Check if report already exists
        $stmt = $pdo->prepare("SELECT id FROM medical_reports WHERE appointment_id = ?");
        $stmt->execute([$appointment_id]);
        
        if ($stmt->fetch()) {
            // Update existing report
            $stmt = $pdo->prepare("
                UPDATE medical_reports 
                SET diagnosis = ?, recommendations = ?, prescription = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE appointment_id = ?
            ");
            if ($stmt->execute([$diagnosis, $recommendations, $prescription, $appointment_id])) {
                $success = 'Rapport médical mis à jour avec succès!';
            } else {
                $error = 'Erreur lors de la mise à jour du rapport médical.';
            }
        } else {
            // Create new report
            $stmt = $pdo->prepare("
                INSERT INTO medical_reports (appointment_id, doctor_id, diagnosis, recommendations, prescription) 
                VALUES (?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$appointment_id, $doctor_info['id'], $diagnosis, $recommendations, $prescription])) {
                $success = 'Rapport médical créé avec succès!';
            } else {
                $error = 'Erreur lors de la création du rapport médical.';
            }
        }
        
        // Mark appointment as completed if not already
        if ($success) {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE id = ? AND status = 'confirmed'");
            $stmt->execute([$appointment_id]);
        }
    }
    
    if ($_POST['action'] == 'update_appointment_status') {
        $appointment_id = $_POST['appointment_id'];
        $new_status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?");
        if ($stmt->execute([$new_status, $appointment_id, $doctor_info['id']])) {
            $success = 'Statut du rendez-vous mis à jour!';
        } else {
            $error = 'Erreur lors de la mise à jour du statut.';
        }
    }
}

// Get doctor's appointments
$stmt = $pdo->prepare("
    SELECT a.*, 
           c.first_name as client_first_name, c.last_name as client_last_name, c.email as client_email, c.phone as client_phone,
           s.name as specialty_name,
           r.id as report_id, r.diagnosis, r.recommendations, r.prescription, r.created_at as report_date
    FROM appointments a
    JOIN users c ON a.client_id = c.id
    JOIN specialties s ON a.specialty_id = s.id
    LEFT JOIN medical_reports r ON a.id = r.appointment_id
    WHERE a.doctor_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([$doctor_info['id']]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter appointments
$today_appointments = array_filter($appointments, function($apt) {
    return $apt['appointment_date'] == date('Y-m-d') && $apt['status'] == 'confirmed';
});

$upcoming_appointments = array_filter($appointments, function($apt) {
    return strtotime($apt['appointment_date']) > strtotime(date('Y-m-d')) && $apt['status'] == 'confirmed';
});

$completed_appointments = array_filter($appointments, function($apt) {
    return $apt['status'] == 'completed';
});

// Get statistics
$stats = [
    'total' => count($appointments),
    'today' => count($today_appointments),
    'upcoming' => count($upcoming_appointments),
    'completed' => count($completed_appointments),
    'reports_pending' => count(array_filter($completed_appointments, function($apt) { return !$apt['report_id']; }))
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Médecin - Cabinet Médical</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container dashboard">
        <div class="dashboard-header">
            <h1>Tableau de Bord Médecin</h1>
            <p>Bienvenue, Dr. <?= htmlspecialchars($_SESSION['first_name']) ?>!</p>
            <p><strong>Spécialité:</strong> <?= htmlspecialchars($doctor_info['specialty_name']) ?></p>
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
                <a href="#today" class="btn btn-primary">Consultations Aujourd'hui</a>
                <a href="#upcoming" class="btn btn-secondary">Rendez-vous à Venir</a>
                <a href="#reports" class="btn btn-outline">Rapports en Attente</a>
                <a href="../specialties.php" class="btn btn-outline">Voir Spécialités</a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['today'] ?></div>
                <div class="stat-label">Aujourd'hui</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['upcoming'] ?></div>
                <div class="stat-label">À Venir</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['completed'] ?></div>
                <div class="stat-label">Terminées</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['reports_pending'] ?></div>
                <div class="stat-label">Rapports à Créer</div>
            </div>
        </div>

        <!-- Today's Appointments -->
        <div id="today" style="margin: 3rem 0;">
            <h2 style="color: var(--primary-green);">📅 Consultations d'Aujourd'hui</h2>
            <?php if (empty($today_appointments)): ?>
                <div style="text-align: center; padding: 2rem; background: var(--very-light-green); border-radius: 10px;">
                    <p>✅ Aucune consultation prévue aujourd'hui!</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Heure</th>
                                <th>Patient</th>
                                <th>Contact</th>
                                <th>Motif</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($today_appointments as $appointment): ?>
                                <tr>
                                    <td><strong><?= formatTime($appointment['appointment_time']) ?></strong></td>
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
                                        <?php if ($appointment['notes']): ?>
                                            <?= htmlspecialchars($appointment['notes']) ?>
                                        <?php else: ?>
                                            <em>Consultation générale</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick="completeAppointment(<?= $appointment['id'] ?>)" 
                                                class="btn btn-success" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">
                                            Terminer
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Upcoming Appointments -->
        <div id="upcoming" style="margin: 3rem 0;">
            <h2 style="color: var(--secondary-green);">🗓️ Rendez-vous à Venir</h2>
            <?php if (empty($upcoming_appointments)): ?>
                <div style="text-align: center; padding: 2rem; background: var(--light-gray); border-radius: 10px;">
                    <p>Aucun rendez-vous à venir.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date & Heure</th>
                                <th>Patient</th>
                                <th>Contact</th>
                                <th>Motif</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <strong><?= formatDate($appointment['appointment_date']) ?></strong><br>
                                        <small><?= formatTime($appointment['appointment_time']) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($appointment['client_first_name'] . ' ' . $appointment['client_last_name']) ?>
                                    </td>
                                    <td>
                                        📧 <?= htmlspecialchars($appointment['client_email']) ?><br>
                                        <?php if ($appointment['client_phone']): ?>
                                            📞 <?= htmlspecialchars($appointment['client_phone']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($appointment['notes']): ?>
                                            <?= htmlspecialchars($appointment['notes']) ?>
                                        <?php else: ?>
                                            <em>Consultation générale</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-confirmed">Confirmé</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Completed Appointments - Reports Management -->
        <div id="reports" style="margin: 3rem 0;">
            <h2 style="color: var(--dark-green);">📋 Consultations Terminées & Rapports</h2>
            <?php if (empty($completed_appointments)): ?>
                <div style="text-align: center; padding: 2rem; background: var(--light-gray); border-radius: 10px;">
                    <p>Aucune consultation terminée.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>Motif</th>
                                <th>Rapport Médical</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completed_appointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <strong><?= formatDate($appointment['appointment_date']) ?></strong><br>
                                        <small><?= formatTime($appointment['appointment_time']) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($appointment['client_first_name'] . ' ' . $appointment['client_last_name']) ?>
                                    </td>
                                    <td>
                                        <?php if ($appointment['notes']): ?>
                                            <?= htmlspecialchars($appointment['notes']) ?>
                                        <?php else: ?>
                                            <em>Consultation générale</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($appointment['report_id']): ?>
                                            <span class="status-badge status-completed">✅ Rapport Créé</span><br>
                                            <small><?= formatDate($appointment['report_date']) ?></small>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">⏳ Rapport à Créer</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($appointment['report_id']): ?>
                                            <button onclick="editReport(<?= htmlspecialchars(json_encode($appointment)) ?>)" 
                                                    class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">
                                                Modifier
                                            </button>
                                            <button onclick="viewReport(<?= htmlspecialchars(json_encode($appointment)) ?>)" 
                                                    class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.875rem; margin-left: 0.25rem;">
                                                Voir
                                            </button>
                                        <?php else: ?>
                                            <button onclick="createReport(<?= htmlspecialchars(json_encode($appointment)) ?>)" 
                                                    class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">
                                                Créer Rapport
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Report Creation/Edit Modal -->
    <div id="reportModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeReportModal()">&times;</span>
            <h2 id="reportModalTitle">📋 Créer Rapport Médical</h2>
            
            <form method="POST" id="reportForm">
                <input type="hidden" name="action" value="create_report">
                <input type="hidden" name="appointment_id" id="modal_appointment_id">
                
                <div id="patientInfo" style="background: var(--very-light-green); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;"></div>
                
                <div class="form-group">
                    <label for="diagnosis">Diagnostic*:</label>
                    <textarea id="diagnosis" name="diagnosis" rows="4" required 
                              placeholder="Décrivez le diagnostic de la consultation..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="recommendations">Recommandations:</label>
                    <textarea id="recommendations" name="recommendations" rows="4" 
                              placeholder="Recommandations pour le patient (repos, exercices, suivi...)"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="prescription">Prescription:</label>
                    <textarea id="prescription" name="prescription" rows="4" 
                              placeholder="Médicaments prescrits, posologie..."></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Enregistrer le Rapport</button>
                    <button type="button" onclick="closeReportModal()" class="btn btn-secondary">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report View Modal -->
    <div id="viewReportModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeViewReportModal()">&times;</span>
            <h2>📋 Rapport Médical</h2>
            <div id="viewReportContent"></div>
            <div style="margin-top: 2rem; text-align: center;">
                <button onclick="closeViewReportModal()" class="btn btn-secondary">Fermer</button>
            </div>
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
        margin: 5% auto;
        padding: 2rem;
        border-radius: 10px;
        width: 80%;
        max-width: 700px;
        position: relative;
        max-height: 80vh;
        overflow-y: auto;
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

    .report-section {
        background: var(--very-light-green);
        padding: 1rem;
        margin: 1rem 0;
        border-radius: 8px;
        border-left: 4px solid var(--primary-green);
    }

    .report-section h4 {
        color: var(--primary-green);
        margin-bottom: 0.5rem;
    }
    </style>

    <script>
    function completeAppointment(appointmentId) {
        if (confirm('Marquer cette consultation comme terminée?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="update_appointment_status">
                <input type="hidden" name="appointment_id" value="${appointmentId}">
                <input type="hidden" name="status" value="completed">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function createReport(appointment) {
        document.getElementById('reportModalTitle').textContent = '📋 Créer Rapport Médical';
        showReportModal(appointment, false);
    }

    function editReport(appointment) {
        document.getElementById('reportModalTitle').textContent = '📋 Modifier Rapport Médical';
        showReportModal(appointment, true);
    }

    function showReportModal(appointment, isEdit = false) {
        document.getElementById('modal_appointment_id').value = appointment.id;
        
        const patientInfo = `
            <h4 style="color: var(--primary-green); margin-bottom: 1rem;">Informations Patient</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div><strong>Patient:</strong> ${appointment.client_first_name} ${appointment.client_last_name}</div>
                <div><strong>Date:</strong> ${new Date(appointment.appointment_date).toLocaleDateString('fr-FR')}</div>
                <div><strong>Heure:</strong> ${appointment.appointment_time}</div>
                <div><strong>Motif:</strong> ${appointment.notes || 'Consultation générale'}</div>
            </div>
        `;
        
        document.getElementById('patientInfo').innerHTML = patientInfo;
        
        if (isEdit) {
            document.getElementById('diagnosis').value = appointment.diagnosis || '';
            document.getElementById('recommendations').value = appointment.recommendations || '';
            document.getElementById('prescription').value = appointment.prescription || '';
        } else {
            document.getElementById('diagnosis').value = '';
            document.getElementById('recommendations').value = '';
            document.getElementById('prescription').value = '';
        }
        
        document.getElementById('reportModal').style.display = 'block';
    }

    function viewReport(appointment) {
        const reportContent = `
            <div style="text-align: center; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 2px solid var(--primary-green);">
                <h3 style="color: var(--primary-green);">Cabinet Médical</h3>
                <p>123 Rue de la Santé, 75000 Paris<br>Tél: 01 23 45 67 89</p>
            </div>
            
            <div class="report-section">
                <h4>Informations Patient</h4>
                <p><strong>Nom:</strong> ${appointment.client_first_name} ${appointment.client_last_name}</p>
                <p><strong>Date de consultation:</strong> ${new Date(appointment.appointment_date).toLocaleDateString('fr-FR')}</p>
                <p><strong>Heure:</strong> ${appointment.appointment_time}</p>
            </div>
            
            <div class="report-section">
                <h4>Médecin Traitant</h4>
                <p><strong>Dr. <?= htmlspecialchars($doctor_info['first_name'] . ' ' . $doctor_info['last_name']) ?></strong></p>
                <p><strong>Spécialité:</strong> <?= htmlspecialchars($doctor_info['specialty_name']) ?></p>
            </div>
            
            <div class="report-section">
                <h4>Diagnostic</h4>
                <p>${appointment.diagnosis || 'Non spécifié'}</p>
            </div>
            
            <div class="report-section">
                <h4>Recommandations</h4>
                <p>${appointment.recommendations || 'Aucune recommandation spécifique'}</p>
            </div>
            
            <div class="report-section">
                <h4>Prescription</h4>
                <p>${appointment.prescription || 'Aucune prescription'}</p>
            </div>
            
            <div style="margin-top: 2rem; text-align: center; color: var(--text-light); font-size: 0.9rem;">
                <p>Rapport créé le ${new Date(appointment.report_date).toLocaleDateString('fr-FR')}</p>
                <p><em>Ce document est confidentiel et destiné uniquement au patient concerné.</em></p>
            </div>
        `;
        
        document.getElementById('viewReportContent').innerHTML = reportContent;
        document.getElementById('viewReportModal').style.display = 'block';
    }

    function closeReportModal() {
        document.getElementById('reportModal').style.display = 'none';
    }

    function closeViewReportModal() {
        document.getElementById('viewReportModal').style.display = 'none';
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const reportModal = document.getElementById('reportModal');
        const viewReportModal = document.getElementById('viewReportModal');
        
        if (event.target == reportModal) {
            reportModal.style.display = 'none';
        }
        if (event.target == viewReportModal) {
            viewReportModal.style.display = 'none';
        }
    }
    </script>
</body>
</html>