<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez saisir une adresse email valide.';
    } else {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$name, $email, $phone, $subject, $message])) {
            $success = 'Votre message a été envoyé avec succès! Nous vous répondrons dans les plus brefs délais.';
            // Clear form data
            $_POST = array();
        } else {
            $error = 'Erreur lors de l\'envoi du message. Veuillez réessayer.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - Cabinet Médical</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container" style="padding: 2rem 0;">
        <h1 style="text-align: center; color: var(--primary-green); margin-bottom: 3rem;">Nous Contacter</h1>
        
        <div class="contact-page">
            <!-- Contact Information -->
            <div class="contact-info-section">
                <h2>Informations de Contact</h2>
                <div class="contact-details">
                    <div class="contact-item">
                        <h3>📍 Adresse</h3>
                        <p>123 Rue de la Santé<br>75000 Paris, France</p>
                    </div>
                    
                    <div class="contact-item">
                        <h3>📞 Téléphone</h3>
                        <p>01 23 45 67 89</p>
                        <small>Lundi - Vendredi: 9h - 18h<br>Samedi: 9h - 13h</small>
                    </div>
                    
                    <div class="contact-item">
                        <h3>✉️ Email</h3>
                        <p>contact@cabinetmedical.fr</p>
                    </div>
                    
                    <div class="contact-item">
                        <h3>🚨 Urgences</h3>
                        <p>En cas d'urgence médicale:</p>
                        <p><strong>15 (SAMU)</strong> ou <strong>112</strong></p>
                    </div>
                </div>
                
                <div class="hours-section">
                    <h3>Horaires d'Ouverture</h3>
                    <table class="hours-table">
                        <tr>
                            <td>Lundi - Vendredi</td>
                            <td>9h00 - 18h00</td>
                        </tr>
                        <tr>
                            <td>Samedi</td>
                            <td>9h00 - 13h00</td>
                        </tr>
                        <tr>
                            <td>Dimanche</td>
                            <td>Fermé</td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Contact Form -->
            <div class="contact-form-section">
                <h2>Envoyez-nous un Message</h2>
                
                <?php if ($error): ?>
                    <div class="message error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="message success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <form method="POST" class="contact-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Nom complet*:</label>
                            <input type="text" id="name" name="name" 
                                   value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email*:</label>
                            <input type="email" id="email" name="email" 
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Téléphone:</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Sujet*:</label>
                            <select id="subject" name="subject" required>
                                <option value="">Sélectionnez un sujet</option>
                                <option value="Rendez-vous" <?= (isset($_POST['subject']) && $_POST['subject'] == 'Rendez-vous') ? 'selected' : '' ?>>Prise de rendez-vous</option>
                                <option value="Information" <?= (isset($_POST['subject']) && $_POST['subject'] == 'Information') ? 'selected' : '' ?>>Demande d'information</option>
                                <option value="Urgence" <?= (isset($_POST['subject']) && $_POST['subject'] == 'Urgence') ? 'selected' : '' ?>>Urgence médicale</option>
                                <option value="Réclamation" <?= (isset($_POST['subject']) && $_POST['subject'] == 'Réclamation') ? 'selected' : '' ?>>Réclamation</option>
                                <option value="Autre" <?= (isset($_POST['subject']) && $_POST['subject'] == 'Autre') ? 'selected' : '' ?>>Autre</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message*:</label>
                        <textarea id="message" name="message" rows="6" required 
                                  placeholder="Décrivez votre demande en détail..."><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Envoyer le Message</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Map Section (placeholder) -->
        <div class="map-section">
            <h2>Localisation</h2>
            <div class="map-placeholder">
                <img src="assets/map-placeholder.jpg" alt="Plan d'accès" style="width: 100%; height: 300px; object-fit: cover; border-radius: 10px;">
                <p style="text-align: center; margin-top: 1rem;">
                    <strong>123 Rue de la Santé, 75000 Paris</strong><br>
                    Métro: Ligne 6 - Station Santé<br>
                    Bus: Lignes 21, 27, 38
                </p>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <style>
    .contact-page {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        margin-bottom: 4rem;
    }
    
    .contact-info-section h2,
    .contact-form-section h2 {
        color: var(--primary-green);
        margin-bottom: 2rem;
        font-size: 1.8rem;
    }
    
    .contact-details {
        margin-bottom: 3rem;
    }
    
    .contact-details .contact-item {
        background: var(--very-light-green);
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    
    .contact-details .contact-item h3 {
        color: var(--primary-green);
        margin-bottom: 1rem;
        font-size: 1.2rem;
    }
    
    .hours-section {
        background: var(--white);
        padding: 2rem;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .hours-section h3 {
        color: var(--primary-green);
        margin-bottom: 1rem;
    }
    
    .hours-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .hours-table td {
        padding: 0.75rem;
        border-bottom: 1px solid var(--border-color);
    }
    
    .hours-table td:first-child {
        font-weight: 500;
        color: var(--text-dark);
    }
    
    .hours-table td:last-child {
        text-align: right;
        color: var(--primary-green);
        font-weight: 500;
    }
    
    .contact-form {
        background: var(--white);
        padding: 2rem;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    .map-section {
        margin-top: 4rem;
        text-align: center;
    }
    
    .map-section h2 {
        color: var(--primary-green);
        margin-bottom: 2rem;
    }
    
    .map-placeholder {
        background: var(--light-gray);
        padding: 2rem;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    @media (max-width: 768px) {
        .contact-page {
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
    }
    </style>