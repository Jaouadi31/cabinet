<?php
require_once 'config.php';

$error = '';
$success = '';

$pdo = getDbConnection();

// Get specialties
$stmt = $pdo->query("SELECT * FROM specialties ORDER BY name");
$specialties = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get doctors
$doctors = [];
if (isset($_GET['specialty']) || isset($_POST['specialty_id'])) {
    $specialty_id = $_GET['specialty'] ?? $_POST['specialty_id'];
    $stmt = $pdo->prepare("
        SELECT d.*, u.first_name, u.last_name, s.name as specialty_name 
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        JOIN specialties s ON d.specialty_id = s.id 
        WHERE d.specialty_id = ? AND u.is_active = 1
    ");
    $stmt->execute([$specialty_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_name = sanitize($_POST['client_name']);
    $client_email = sanitize($_POST['client_email']);
    $client_phone = sanitize($_POST['client_phone']);
    $specialty_id = $_POST['specialty_id'];
    $doctor_id = $_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $notes = sanitize($_POST['notes']);
    
    // Validation
    if (empty($client_name) || empty($client_email) || empty($specialty_id) || empty($doctor_id) || empty($appointment_date) || empty($appointment_time)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez saisir une adresse email valide.';
    } elseif (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $error = 'La date du rendez-vous ne peut pas être dans le passé.';
    } else {
        // Check if appointment slot is available
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM appointments 
            WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? 
            AND status != 'cancelled'
        ");
        $stmt->execute([$doctor_id, $appointment_date, $appointment_time]);
        
        if ($stmt->fetchColumn() > 0) {
            $error = 'Ce créneau horaire n\'est pas disponible. Veuillez choisir un autre horaire.';
        } else {
            // Create or find client user
            $client_id = null;
            
            if (isLoggedIn() && hasRole('client')) {
                $client_id = $_SESSION['user_id'];
            } else {
                // Check if email exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'client'");
                $stmt->execute([$client_email]);
                $existing_user = $stmt->fetch();
                
                if ($existing_user) {
                    $client_id = $existing_user['id'];
                } else {
                    // Create new client account
                    $username = strtolower(str_replace(' ', '.', $client_name)) . rand(100, 999);
                    $password = password_hash('temp123', PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password, role, first_name, last_name, phone) 
                        VALUES (?, ?, ?, 'client', ?, ?, ?)
                    ");
                    
                    $name_parts = explode(' ', $client_name, 2);
                    $first_name = $name_parts[0];
                    $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
                    
                    if ($stmt->execute([$username, $client_email, $password, $first_name, $last_name, $client_phone])) {
                        $client_id = $pdo->lastInsertId();
                    }
                }
            }
            
            if ($client_id) {
                // Create appointment
                $created_by = isLoggedIn() && hasRole('secretary') ? $_SESSION['user_id'] : null;
                
                $stmt = $pdo->prepare("
                    INSERT INTO appointments (client_id, doctor_id, specialty_id, appointment_date, appointment_time, notes, created_by, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                
                if ($stmt->execute([$client_id, $doctor_id, $specialty_id, $appointment_date, $appointment_time, $notes, $created_by])) {
                    $success = 'Votre demande de rendez-vous a été soumise avec succès! Un membre de notre équipe vous contactera pour confirmer.';
                    $_POST = array(); // Clear form
                } else {
                    $error = 'Erreur lors de la création du rendez-vous. Veuillez réessayer.';
                }
            } else {
                $error = 'Erreur lors de la création du compte client. Veuillez réessayer.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prendre Rendez-vous - Cabinet Médical</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container" style="padding: 2rem 0;">
        <h1 style="text-align: center; color: var(--primary-green); margin-bottom: 3rem;">Prendre Rendez-vous</h1>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <div class="appointment-container">
            <div class="appointment-info">
                <h2>Informations Importantes</h2>
                <div class="info-card">
                    <h3>📋 Avant votre rendez-vous</h3>
                    <ul>
                        <li>Apportez votre carte vitale</li>
                        <li>Apportez votre carte de mutuelle</li>
                        <li>Préparez la liste de vos médicaments</li>
                        <li>Arrivez 10 minutes en avance</li>
                    </ul>
                </div>
                
                <div class="info-card">
                    <h3>⏰ Horaires de consultation</h3>
                    <p><strong>Lundi - Vendredi:</strong> 9h00 - 18h00</p>
                    <p><strong>Samedi:</strong> 9h00 - 13h00</p>
                    <p><strong>Dimanche:</strong> Fermé</p>
                </div>
                
                <div class="info-card">
                    <h3>📞 En cas d'urgence</h3>
                    <p>Pour les urgences médicales, appelez le <strong>15 (SAMU)</strong> ou le <strong>112</strong></p>
                </div>
            </div>
            
            <div class="appointment-form">
                <h2>Formulaire de Rendez-vous</h2>
                
                <form method="POST" id="appointmentForm">
                    <?php if (!isLoggedIn() || !hasRole('client')): ?>
                    <fieldset>
                        <legend>Informations Personnelles</legend>
                        
                        <div class="form-group">
                            <label for="client_name">Nom complet*:</label>
                            <input type="text" id="client_name" name="client_name" 
                                   value="<?= isset($_POST['client_name']) ? htmlspecialchars($_POST['client_name']) : '' ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="client_email">Email*:</label>
                                <input type="email" id="client_email" name="client_email" 
                                       value="<?= isset($_POST['client_email']) ? htmlspecialchars($_POST['client_email']) : '' ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="client_phone">Téléphone:</label>
                                <input type="tel" id="client_phone" name="client_phone" 
                                       value="<?= isset($_POST['client_phone']) ? htmlspecialchars($_POST['client_phone']) : '' ?>">
                            </div>
                        </div>
                    </fieldset>
                    <?php else: ?>
                        <?php $current_user = getCurrentUser(); ?>
                        <input type="hidden" name="client_name" value="<?= htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']) ?>">
                        <input type="hidden" name="client_email" value="<?= htmlspecialchars($current_user['email']) ?>">
                        <input type="hidden" name="client_phone" value="<?= htmlspecialchars($current_user['phone']) ?>">
                        
                        <div class="client-info">
                            <h3>Patient: <?= htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']) ?></h3>
                            <p>Email: <?= htmlspecialchars($current_user['email']) ?></p>
                            <?php if ($current_user['phone']): ?>
                                <p>Téléphone: <?= htmlspecialchars($current_user['phone']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <fieldset>
                        <legend>Détails du Rendez-vous</legend>
                        
                        <div class="form-group">
                            <label for="specialty_id">Spécialité*:</label>
                            <select id="specialty_id" name="specialty_id" required onchange="loadDoctors()">
                                <option value="">Sélectionnez une spécialité</option>
                                <?php foreach ($specialties as $specialty): ?>
                                    <option value="<?= $specialty['id'] ?>" 
                                            <?= (isset($_POST['specialty_id']) && $_POST['specialty_id'] == $specialty['id']) || (isset($_GET['specialty']) && $_GET['specialty'] == $specialty['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($specialty['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="doctor_id">Médecin*:</label>
                            <select id="doctor_id" name="doctor_id" required>
                                <option value="">Sélectionnez d'abord une spécialité</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?= $doctor['id'] ?>" 
                                            <?= (isset($_POST['doctor_id']) && $_POST['doctor_id'] == $doctor['id']) ? 'selected' : '' ?>>
                                        Dr. <?= htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) ?>
                                        <?php if ($doctor['consultation_fee'] > 0): ?>
                                            - <?= $doctor['consultation_fee'] ?>€
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="appointment_date">Date*:</label>
                                <input type="date" id="appointment_date" name="appointment_date" 
                                       min="<?= date('Y-m-d') ?>"
                                       value="<?= isset($_POST['appointment_date']) ? $_POST['appointment_date'] : '' ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="appointment_time">Heure*:</label>
                                <select id="appointment_time" name="appointment_time" required>
                                    <option value="">Choisir l'heure</option>
                                    <?php
                                    for ($hour = 9; $hour < 18; $hour++) {
                                        for ($minute = 0; $minute < 60; $minute += 30) {
                                            $time = sprintf('%02d:%02d', $hour, $minute);
                                            if ($hour == 17 && $minute == 30) break; // Stop at 17:30
                                            $selected = (isset($_POST['appointment_time']) && $_POST['appointment_time'] == $time) ? 'selected' : '';
                                            echo "<option value=\"$time\" $selected>$time</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Motif de consultation (optionnel):</label>
                            <textarea id="notes" name="notes" rows="4" 
                                      placeholder="Décrivez brièvement le motif de votre consultation..."><?= isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '' ?></textarea>
                        </div>
                    </fieldset>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-large">Demander le Rendez-vous</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
    function loadDoctors() {
        const specialtyId = document.getElementById('specialty_id').value;
        const doctorSelect = document.getElementById('doctor_id');
        
        doctorSelect.innerHTML = '<option value="">Chargement...</option>';
        
        if (specialtyId) {
            fetch(`get_doctors.php?specialty_id=${specialtyId}`)
                .then(response => response.json())
                .then(doctors => {
                    doctorSelect.innerHTML = '<option value="">Sélectionnez un médecin</option>';
                    doctors.forEach(doctor => {
                        const option = document.createElement('option');
                        option.value = doctor.id;
                        option.textContent = `Dr. ${doctor.first_name} ${doctor.last_name}`;
                        if (doctor.consultation_fee > 0) {
                            option.textContent += ` - ${doctor.consultation_fee}€`;
                        }
                        doctorSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    doctorSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                });
        } else {
            doctorSelect.innerHTML = '<option value="">Sélectionnez d\'abord une spécialité</option>';
        }
    }
    </script>

    <style>
    .appointment-container {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 3rem;
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .appointment-info h2,
    .appointment-form h2 {
        color: var(--primary-green);
        margin-bottom: 2rem;
    }
    
    .info-card {
        background: var(--very-light-green);
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    
    .info-card h3 {
        color: var(--primary-green);
        margin-bottom: 1rem;
        font-size: 1.1rem;
    }
    
    .info-card ul {
        list-style: none;
        padding-left: 0;
    }
    
    .info-card li {
        padding: 0.25rem 0;
        padding-left: 1.5rem;
        position: relative;
    }
    
    .info-card li:before {
        content: "•";
        position: absolute;
        left: 0;
        color: var(--accent-green);
        font-weight: bold;
    }
    
    .appointment-form {
        background: var(--white);
        padding: 2rem;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    fieldset {
        border: 2px solid var(--light-green);
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    legend {
        color: var(--primary-green);
        font-weight: bold;
        padding: 0 1rem;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    .client-info {
        background: var(--very-light-green);
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 2rem;
    }
    
    .client-info h3 {
        color: var(--primary-green);
        margin-bottom: 0.5rem;
    }
    
    .btn-large {
        width: 100%;
        padding: 1rem 2rem;
        font-size: 1.1rem;
    }
    
    @media (max-width: 768px) {
        .appointment-container {
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .appointment-info {
            order: 2;
        }
    }
    </style>
</body>
</html>