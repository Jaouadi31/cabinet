<?php
require_once '../config.php';
requireRole('client');

$pdo = getDbConnection();
$client_id = $_SESSION['user_id'];

// Get client's appointments
$stmt = $pdo->prepare("
    SELECT a.*, 
           d_user.first_name as doctor_first_name, d_user.last_name as doctor_last_name,
           s.name as specialty_name,
           d.consultation_fee,
           r.id as report_id, r.diagnosis, r.recommendations, r.prescription, r.created_at as report_date
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users d_user ON d.user_id = d_user.id
    JOIN specialties s ON a.specialty_id = s.id
    LEFT JOIN medical_reports r ON a.id = r.appointment_id
    WHERE a.client_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([$client_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter appointments by status
$pending_appointments = array_filter($appointments, function($apt) { 
    return $apt['status'] == 'pending'; 
});

$confirmed_appointments = array_filter($appointments, function($apt) { 
    return $apt['status'] == 'confirmed' && strtotime($apt['appointment_date']) >= strtotime(date('Y-m-d')); 
});

$completed_appointments = array_filter($appointments, function($apt) { 
    return $apt['status'] == 'completed'; 
});

$cancelled_appointments = array_filter($appointments, function($apt) { 
    return $apt['status'] == 'cancelled'; 
});

// Get appointment stats
$stats = [
    'total' => count($appointments),
    'pending' => count($pending_appointments),
    'confirmed' => count($confirmed_appointments),
    'completed' => count($completed_appointments),
    'cancelled' => count($cancelled_appointments)
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace Client - Cabinet Médical</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container dashboard">
        <div class="dashboard-header">
            <h1>Mon Espace Client</h1>
            <p>Bienvenue, <?= htmlspecialchars($_SESSION['first_name']) ?>! Gérez vos rendez-vous et consultez votre historique médical.</p>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions" style="margin: 2rem 0;">
            <h2>Actions Rapides</h2>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="../appointment.php" class="btn btn-primary">Nouveau Rendez-vous</a>
                <a href="../specialties.php" class="btn btn-secondary">Nos Spécialités</a>
                <a href="../contact.php" class="btn btn-outline">Nous Contacter</a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
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
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total</div>
            </div>
        </div>

        <!-- Pending Appointments -->
        <?php if (!empty($pending_appointments)): ?>
        <div style="margin: 3rem 0;">
            <h2 style="color: #ffc107;">⏳ Rendez-vous en Attente de Validation</h2>
            <div class="alert-info" style="background: #fff3cd; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #ffc107;">
                <strong>Information:</strong> Ces rendez-vous sont en attente de confirmation par notre secrétaire. Vous serez contacté sous peu.
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Heure</th>
                            <th>Médecin</th>
                            <th>Spécialité</th>
                            <th>Motif</th>
                            <th>Statut</th>
                            <th>Demandé le</th>
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
                                    Dr. <?= htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) ?>
                                </td>
                                <td><?= htmlspecialchars($appointment['specialty_name']) ?></td>
                                <td>
                                    <?php if ($appointment['notes']): ?>
                                        <?= htmlspecialchars($appointment['notes']) ?>
                                    <?php else: ?>
                                        <em style="color: var(--text-light);">Aucun motif spécifié</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-pending">
                                        ⏳ En Attente
                                    </span>
                                </td>
                                <td><?= formatDate($appointment['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Confirmed Upcoming Appointments -->
        <?php if (!empty($confirmed_appointments)): ?>
        <div style="margin: 3rem 0;">
            <h2 style="color: var(--accent-green);">✅ Rendez-vous Confirmés</h2>
            <div class="alert-success" style="background: #d4edda; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #28a745;">
                <strong>Confirmés!</strong> Ces rendez-vous sont confirmés. Veuillez arriver 10 minutes en avance avec votre carte vitale.
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Heure</th>
                            <th>Médecin</th>
                            <th>Spécialité</th>
                            <th>Tarif</th>
                            <th>Notes Secrétaire</th>
                            <th>Confirmé le</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($confirmed_appointments as $appointment): ?>
                            <tr>
                                <td>
                                    <strong><?= formatDate($appointment['appointment_date']) ?></strong><br>
                                    <small><?= formatTime($appointment['appointment_time']) ?></small>
                                </td>
                                <td>
                                    Dr. <?= htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) ?>
                                </td>
                                <td><?= htmlspecialchars($appointment['specialty_name']) ?></td>
                                <td>
                                    <?php if ($appointment['consultation_fee'] > 0): ?>
                                        <strong><?= $appointment['consultation_fee'] ?>€</strong>
                                    <?php else: ?>
                                        <em>Selon tarifs</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($appointment['secretary_notes']): ?>
                                        <?= htmlspecialchars($appointment['secretary_notes']) ?>
                                    <?php else: ?>
                                        <em style="color: var(--text-light);">Aucune note</em>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($appointment['updated_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Completed Appointments with Reports -->
        <?php if (!empty($completed_appointments)): ?>
        <div style="margin: 3rem 0;">
            <h2 style="color: var(--primary-green);">📋 Consultations Terminées</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date Consultation</th>
                            <th>Médecin</th>
                            <th>Spécialité</th>
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
                                    Dr. <?= htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) ?>
                                </td>
                                <td><?= htmlspecialchars($appointment['specialty_name']) ?></td>
                                <td>
                                    <?php if ($appointment['report_id']): ?>
                                        <span class="status-badge status-confirmed">
                                            ✅ Rapport Disponible
                                        </span><br>
                                        <small>Créé le <?= formatDate($appointment['report_date']) ?></small>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">
                                            ⏳ En Préparation
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($appointment['report_id']): ?>
                                        <button onclick="showReportModal(<?= htmlspecialchars(json_encode($appointment)) ?>)" 
                                                class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">
                                            Voir Rapport
                                        </button>
                                    <?php else: ?>
                                        <em style="color: var(--text-light);">Pas encore disponible</em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- No Appointments Message -->
        <?php if (empty($appointments)): ?>
        <div style="text-align: center; padding: 3rem; background: var(--very-light-green); border-radius: 10px; margin: 3rem 0;">
            <h3 style="color: var(--primary-green);">Aucun rendez-vous trouvé</h3>
            <p>Vous n'avez encore aucun rendez-vous. Commencez par prendre votre premier rendez-vous!</p>
            <a href="../appointment.php" class="btn btn-primary">Prendre Rendez-vous</a>
        </div>
        <?php endif; ?>

        <!-- Cancelled Appointments (if any) -->
        <?php if (!empty($cancelled_appointments)): ?>
        <div style="margin: 3rem 0;">
            <h2 style="color: #dc3545;">❌ Rendez-vous Annulés</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Médecin</th>
                            <th>Spécialité</th>
                            <th>Raison</th>
                            <th>Annulé le</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cancelled_appointments as $appointment): ?>
                            <tr style="opacity: 0.7;">
                                <td>
                                    <?= formatDate($appointment['appointment_date']) ?><br>
                                    <small><?= formatTime($appointment['appointment_time']) ?></small>
                                </td>
                                <td>Dr. <?= htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) ?></td>
                                <td><?= htmlspecialchars($appointment['specialty_name']) ?></td>
                                <td>
                                    <?php if ($appointment['secretary_notes']): ?>
                                        <?= htmlspecialchars($appointment['secretary_notes']) ?>
                                    <?php else: ?>
                                        <em>Aucune raison spécifiée</em>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($appointment['updated_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Health Tips -->
        <div style="background: var(--very-light-green); padding: 2rem; border-radius: 10px; margin: 3rem 0;">
            <h3 style="color: var(--primary-green); margin-bottom: 1rem;">💡 Conseils Santé</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <div style="background: var(--white); padding: 1rem; border-radius: 8px;">
                    <h4>🏃‍♂️ Activité Physique</h4>
                    <p>30 minutes d'exercice par jour améliorent votre santé cardiovasculaire.</p>
                </div>
                <div style="background: var(--white); padding: 1rem; border-radius: 8px;">
                    <h4>🥗 Alimentation</h4>
                    <p>Consommez 5 fruits et légumes par jour pour une alimentation équilibrée.</p>
                </div>
                <div style="background: var(--white); padding: 1rem; border-radius: 8px;">
                    <h4>💤 Sommeil</h4>
                    <p>7-8 heures de sommeil par nuit sont essentielles pour votre bien-être.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Medical Report Modal -->
    <div id="reportModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeReportModal()">&times;</span>
            <h2>📋 Rapport Médical</h2>
            <div id="reportContent"></div>
            <div style="margin-top: 2rem; text-align: center;">
                <button onclick="printReport()" class="btn btn-primary">🖨️ Imprimer</button>
                <button onclick="closeReportModal()" class="btn btn-secondary">Fermer</button>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <style>
    .quick-actions h2 {
        color: var(--primary-green);
        margin-bottom: 1rem;
    }

    .alert-info, .alert-success {
        border-radius: 8px;
        padding: 1rem;
        margin: 1rem 0;
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

    @media print {
        /* Hide everything except the modal content when printing */
        body * {
            visibility: hidden;
        }
        
        #reportModal, #reportModal * {
            visibility: visible;
        }
        
        #reportModal {
            position: static !important;
            background: white !important;
            width: 100% !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .modal-content {
            box-shadow: none !important;
            margin: 0 !important;
            max-height: none !important;
            overflow: visible !important;
            width: 100% !important;
            padding: 1rem !important;
        }
        
        /* Hide buttons and close icon when printing */
        .close, 
        button, 
        .btn {
            display: none !important;
        }
        
        /* Style the report sections for print */
        .report-section {
            page-break-inside: avoid;
            margin-bottom: 1rem !important;
        }
        
        /* Ensure good print formatting */
        h2, h3, h4 {
            page-break-after: avoid;
        }
        
        /* Print-specific styling */
        body {
            font-size: 12pt;
            line-height: 1.4;
        }
        
        .report-section h4 {
            font-size: 14pt;
            font-weight: bold;
        }
    }
    </style>

    <script>
    function showReportModal(appointment) {
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
                <p><strong>Dr. ${appointment.doctor_first_name} ${appointment.doctor_last_name}</strong></p>
                <p><strong>Spécialité:</strong> ${appointment.specialty_name}</p>
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
                <p>Rapport généré le ${new Date(appointment.report_date).toLocaleDateString('fr-FR')}</p>
                <p><em>Ce document est confidentiel et destiné uniquement au patient concerné.</em></p>
            </div>
        `;
        
        document.getElementById('reportContent').innerHTML = reportContent;
        document.getElementById('reportModal').style.display = 'block';
    }

    function closeReportModal() {
        document.getElementById('reportModal').style.display = 'none';
    }

    function printReport() {
        // Hide all content except the modal
        const originalDisplay = document.body.style.display;
        const modal = document.getElementById('reportModal');
        
        // Create a new window for printing
        const printWindow = window.open('', '_blank');
        
        // Get the modal content
        const modalContent = document.getElementById('reportContent').innerHTML;
        
        // Create the print document
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Rapport Médical</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        margin: 20px;
                        line-height: 1.6;
                        color: #333;
                    }
                    h2, h3 {
                        color: #2d5a41;
                        text-align: center;
                        margin-bottom: 1rem;
                    }
                    .report-section {
                        background: #f8f9fa;
                        padding: 1rem;
                        margin: 1rem 0;
                        border-radius: 8px;
                        border-left: 4px solid #2d5a41;
                        page-break-inside: avoid;
                    }
                    .report-section h4 {
                        color: #2d5a41;
                        margin-bottom: 0.5rem;
                        font-size: 14pt;
                        font-weight: bold;
                    }
                    p {
                        margin: 0.5rem 0;
                    }
                    strong {
                        font-weight: bold;
                    }
                    em {
                        font-style: italic;
                        color: #666;
                    }
                    .header-section {
                        text-align: center;
                        margin-bottom: 2rem;
                        padding-bottom: 1rem;
                        border-bottom: 2px solid #2d5a41;
                    }
                    .footer-section {
                        margin-top: 2rem;
                        text-align: center;
                        color: #666;
                        font-size: 10pt;
                    }
                    @media print {
                        body { margin: 0; }
                        .report-section { page-break-inside: avoid; }
                    }
                </style>
            </head>
            <body>
                ${modalContent}
            </body>
            </html>
        `);
        
        printWindow.document.close();
        
        // Wait for content to load then print
        printWindow.onload = function() {
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        };
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('reportModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
    </script>
</body>
</html>