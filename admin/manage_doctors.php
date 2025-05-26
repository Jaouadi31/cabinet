<?php
require_once '../config.php';
requireRole('admin');

$pdo = getDbConnection();
$error = '';
$success = '';

// Handle doctor updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_doctor') {
        $doctor_id = $_POST['doctor_id'];
        $license_number = sanitize($_POST['license_number']);
        $consultation_fee = $_POST['consultation_fee'];
        $biography = sanitize($_POST['biography']);
        $available_days = isset($_POST['available_days']) ? implode(',', $_POST['available_days']) : '';
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $specialty_id = $_POST['specialty_id'];
        
        $stmt = $pdo->prepare("
            UPDATE doctors 
            SET license_number = ?, consultation_fee = ?, biography = ?, available_days = ?, 
                start_time = ?, end_time = ?, specialty_id = ?
            WHERE id = ?
        ");
        
        if ($stmt->execute([$license_number, $consultation_fee, $biography, $available_days, $start_time, $end_time, $specialty_id, $doctor_id])) {
            $success = 'Informations du médecin mises à jour avec succès!';
        } else {
            $error = 'Erreur lors de la mise à jour des informations.';
        }
    }
    
    if ($_POST['action'] == 'add_doctor_details') {
        $user_id = $_POST['user_id'];
        $specialty_id = $_POST['specialty_id'];
        $license_number = sanitize($_POST['license_number']);
        $consultation_fee = $_POST['consultation_fee'];
        $biography = sanitize($_POST['biography']);
        $available_days = isset($_POST['available_days']) ? implode(',', $_POST['available_days']) : '';
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        
        $stmt = $pdo->prepare("
            INSERT INTO doctors (user_id, specialty_id, license_number, consultation_fee, biography, available_days, start_time, end_time) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$user_id, $specialty_id, $license_number, $consultation_fee, $biography, $available_days, $start_time, $end_time])) {
            $success = 'Détails du médecin ajoutés avec succès!';
        } else {
            $error = 'Erreur lors de l\'ajout des détails du médecin.';
        }
    }
}

// Get all doctors with their user information
$stmt = $pdo->query("
    SELECT d.*, u.first_name, u.last_name, u.email, u.phone, u.is_active, s.name as specialty_name,
           COUNT(a.id) as total_appointments,
           COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_appointments
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    JOIN specialties s ON d.specialty_id = s.id
    LEFT JOIN appointments a ON d.id = a.doctor_id
    GROUP BY d.id
    ORDER BY u.last_name, u.first_name
");
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get specialties
$stmt = $pdo->query("SELECT * FROM specialties ORDER BY name");
$specialties = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get doctor users without doctor details (for adding details)
$stmt = $pdo->query("
    SELECT u.* FROM users u 
    LEFT JOIN doctors d ON u.id = d.user_id 
    WHERE u.role = 'doctor' AND d.id IS NULL AND u.is_active = 1
");
$doctors_without_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Médecins - Admin</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container" style="padding: 2rem 0;">
        <h1 style="color: var(--primary-green); margin-bottom: 2rem;">Gestion des Médecins</h1>
        
        <nav style="margin-bottom: 2rem;">
            <a href="dashboard.php" class="btn btn-outline">← Retour au Tableau de Bord</a>
            <a href="manage_users.php" class="btn btn-secondary">Gérer les Utilisateurs</a>
        </nav>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Doctors without details -->
        <?php if (!empty($doctors_without_details)): ?>
        <div style="background: #fff3cd; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem; border-left: 4px solid #ffc107;">
            <h3 style="color: #856404; margin-bottom: 1rem;">⚠️ Médecins sans Détails Professionnels</h3>
            <p style="color: #856404; margin-bottom: 1rem;">Les utilisateurs suivants ont le rôle "médecin" mais n'ont pas encore de détails professionnels:</p>
            
            <?php foreach ($doctors_without_details as $doctor_user): ?>
                <div style="background: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong><?= htmlspecialchars($doctor_user['first_name'] . ' ' . $doctor_user['last_name']) ?></strong><br>
                        <small><?= htmlspecialchars($doctor_user['email']) ?></small>
                    </div>
                    <button onclick="addDoctorDetails(<?= $doctor_user['id'] ?>, '<?= htmlspecialchars($doctor_user['first_name'] . ' ' . $doctor_user['last_name']) ?>')" 
                            class="btn btn-primary">
                        Ajouter Détails
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Doctors List -->
        <div style="background: var(--white); padding: 2rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
            <h2 style="color: var(--primary-green); margin-bottom: 2rem;">Liste des Médecins</h2>
            
            <?php if (empty($doctors)): ?>
                <div style="text-align: center; padding: 2rem; color: var(--text-light);">
                    Aucun médecin avec des détails professionnels trouvé.
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Médecin</th>
                                <th>Spécialité</th>
                                <th>Contact</th>
                                <th>Licence</th>
                                <th>Tarif</th>
                                <th>Horaires</th>
                                <th>Rendez-vous</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctors as $doctor): ?>
                                <tr style="<?= !$doctor['is_active'] ? 'opacity: 0.6;' : '' ?>">
                                    <td>
                                        <strong>Dr. <?= htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="specialty-badge"><?= htmlspecialchars($doctor['specialty_name']) ?></span>
                                    </td>
                                    <td>
                                        📧 <?= htmlspecialchars($doctor['email']) ?><br>
                                        <?php if ($doctor['phone']): ?>
                                            📞 <?= htmlspecialchars($doctor['phone']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($doctor['license_number']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($doctor['consultation_fee'] > 0): ?>
                                            <strong><?= $doctor['consultation_fee'] ?>€</strong>
                                        <?php else: ?>
                                            <em>Non défini</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($doctor['available_days']): ?>
                                            <small>
                                                <?= str_replace(',', ', ', $doctor['available_days']) ?><br>
                                                <?= formatTime($doctor['start_time']) ?> - <?= formatTime($doctor['end_time']) ?>
                                            </small>
                                        <?php else: ?>
                                            <em>Non défini</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="text-align: center;">
                                            <strong><?= $doctor['total_appointments'] ?></strong> total<br>
                                            <small><?= $doctor['completed_appointments'] ?> terminés</small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($doctor['is_active']): ?>
                                            <span class="status-badge status-confirmed">Actif</span>
                                        <?php else: ?>
                                            <span class="status-badge status-cancelled">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick="editDoctor(<?= htmlspecialchars(json_encode($doctor)) ?>)" 
                                                class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">
                                            Modifier
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

    <!-- Edit Doctor Modal -->
    <div id="editDoctorModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeEditDoctorModal()">&times;</span>
            <h2 id="doctorModalTitle">Modifier les Détails du Médecin</h2>
            
            <form method="POST" id="doctorForm">
                <input type="hidden" name="action" value="update_doctor">
                <input type="hidden" name="doctor_id" id="modal_doctor_id">
                <input type="hidden" name="user_id" id="modal_user_id">
                
                <div id="doctorInfo" style="background: var(--very-light-green); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;"></div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="specialty_id">Spécialité*:</label>
                        <select id="specialty_id" name="specialty_id" required>
                            <?php foreach ($specialties as $specialty): ?>
                                <option value="<?= $specialty['id'] ?>"><?= htmlspecialchars($specialty['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="license_number">Numéro de Licence*:</label>
                        <input type="text" id="license_number" name="license_number" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="consultation_fee">Tarif de Consultation (€):</label>
                        <input type="number" id="consultation_fee" name="consultation_fee" min="0" step="0.01">
                    </div>
                    
                    <div class="form-group">
                        <label for="available_days">Jours Disponibles:</label>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; margin-top: 0.5rem;">
                            <label><input type="checkbox" name="available_days[]" value="Monday"> Lundi</label>
                            <label><input type="checkbox" name="available_days[]" value="Tuesday"> Mardi</label>
                            <label><input type="checkbox" name="available_days[]" value="Wednesday"> Mercredi</label>
                            <label><input type="checkbox" name="available_days[]" value="Thursday"> Jeudi</label>
                            <label><input type="checkbox" name="available_days[]" value="Friday"> Vendredi</label>
                            <label><input type="checkbox" name="available_days[]" value="Saturday"> Samedi</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_time">Heure de Début:</label>
                        <input type="time" id="start_time" name="start_time" value="09:00">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_time">Heure de Fin:</label>
                        <input type="time" id="end_time" name="end_time" value="17:00">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="biography">Biographie:</label>
                    <textarea id="biography" name="biography" rows="4" 
                              placeholder="Expérience, formation, spécialisations..."></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                    <button type="button" onclick="closeEditDoctorModal()" class="btn btn-secondary">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <style>
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .specialty-badge {
        background: var(--primary-green);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 500;
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

    #doctorInfo {
        margin-bottom: 1rem;
    }

    .info-item {
        margin: 0.5rem 0;
    }

    .info-label {
        font-weight: bold;
        color: var(--primary-green);
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }
    </style>

    <script>
    function editDoctor(doctor) {
        document.getElementById('doctorModalTitle').textContent = 'Modifier les Détails du Médecin';
        document.getElementById('modal_doctor_id').value = doctor.id;
        document.getElementById('modal_user_id').value = doctor.user_id;
        
        // Set form action
        document.querySelector('input[name="action"]').value = 'update_doctor';
        
        // Fill doctor info
        const doctorInfo = `
            <h4 style="color: var(--primary-green); margin-bottom: 1rem;">Informations du Médecin</h4>
            <div class="info-item">
                <span class="info-label">Nom:</span> Dr. ${doctor.first_name} ${doctor.last_name}
            </div>
            <div class="info-item">
                <span class="info-label">Email:</span> ${doctor.email}
            </div>
            <div class="info-item">
                <span class="info-label">Téléphone:</span> ${doctor.phone || 'Non renseigné'}
            </div>
        `;
        
        document.getElementById('doctorInfo').innerHTML = doctorInfo;
        
        // Fill form fields
        document.getElementById('specialty_id').value = doctor.specialty_id;
        document.getElementById('license_number').value = doctor.license_number;
        document.getElementById('consultation_fee').value = doctor.consultation_fee;
        document.getElementById('start_time').value = doctor.start_time;
        document.getElementById('end_time').value = doctor.end_time;
        document.getElementById('biography').value = doctor.biography || '';
        
        // Handle available days
        const checkboxes = document.querySelectorAll('input[name="available_days[]"]');
        checkboxes.forEach(checkbox => checkbox.checked = false);
        
        if (doctor.available_days) {
            const days = doctor.available_days.split(',');
            days.forEach(day => {
                const checkbox = document.querySelector(`input[name="available_days[]"][value="${day}"]`);
                if (checkbox) checkbox.checked = true;
            });
        }
        
        document.getElementById('editDoctorModal').style.display = 'block';
    }

    function addDoctorDetails(userId, doctorName) {
        document.getElementById('doctorModalTitle').textContent = 'Ajouter Détails du Médecin';
        document.getElementById('modal_doctor_id').value = '';
        document.getElementById('modal_user_id').value = userId;
        
        // Set form action
        document.querySelector('input[name="action"]').value = 'add_doctor_details';
        
        // Fill doctor info
        const doctorInfo = `
            <h4 style="color: var(--primary-green); margin-bottom: 1rem;">Nouveau Médecin</h4>
            <div class="info-item">
                <span class="info-label">Nom:</span> Dr. ${doctorName}
            </div>
            <div class="info-item">
                <span class="info-label">Statut:</span> Ajout des détails professionnels
            </div>
        `;
        
        document.getElementById('doctorInfo').innerHTML = doctorInfo;
        
        // Reset form fields
        document.getElementById('specialty_id').value = '';
        document.getElementById('license_number').value = '';
        document.getElementById('consultation_fee').value = '';
        document.getElementById('start_time').value = '09:00';
        document.getElementById('end_time').value = '17:00';
        document.getElementById('biography').value = '';
        
        // Uncheck all days
        const checkboxes = document.querySelectorAll('input[name="available_days[]"]');
        checkboxes.forEach(checkbox => checkbox.checked = false);
        
        document.getElementById('editDoctorModal').style.display = 'block';
    }

    function closeEditDoctorModal() {
        document.getElementById('editDoctorModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('editDoctorModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
    </script>
</body>
</html>