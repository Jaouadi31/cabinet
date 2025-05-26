<?php
require_once 'config.php';

$pdo = getDbConnection();
$stmt = $pdo->query("SELECT * FROM specialties ORDER BY name");
$specialties = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cabinet Médical - Accueil</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Bienvenue au Cabinet Médical</h1>
            <p>Votre santé est notre priorité. Nous offrons des soins de qualité avec une équipe de professionnels expérimentés.</p>
            <?php if (!isLoggedIn()): ?>
                <div class="hero-buttons">
                    <a href="appointment.php" class="btn btn-primary">Prendre Rendez-vous</a>
                    <a href="login.php" class="btn btn-secondary">Se Connecter</a>
                </div>
            <?php else: ?>
                <div class="hero-buttons">
                    <a href="dashboard.php" class="btn btn-primary">Mon Tableau de Bord</a>
                    <a href="appointment.php" class="btn btn-secondary">Nouveau Rendez-vous</a>
                </div>
            <?php endif; ?>
        </div>
        <div class="hero-image">
            <img src="assets/medical-hero.jpg" alt="Cabinet Médical">
        </div>
    </section>

    <!-- Services Section -->
    <section class="services">
        <div class="container">
            <h2>Nos Spécialités</h2>
            <div class="services-grid">
                <?php foreach ($specialties as $specialty): ?>
                    <div class="service-card">
                        <div class="service-icon">
                            <img src="assets/<?= strtolower(str_replace([' ', 'é', 'è'], ['_', 'e', 'e'], $specialty['name'])) ?>.jpg" alt="<?= htmlspecialchars($specialty['name']) ?>">
                        </div>
                        <h3><?= htmlspecialchars($specialty['name']) ?></h3>
                        <p><?= htmlspecialchars($specialty['description']) ?></p>
                        <a href="specialties.php#<?= $specialty['id'] ?>" class="btn btn-outline">En savoir plus</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about">
        <div class="container">
            <div class="about-content">
                <div class="about-text">
                    <h2>À Propos de Notre Cabinet</h2>
                    <p>Notre cabinet médical offre des soins de santé complets avec une approche personnalisée pour chaque patient. Nous combinons expertise médicale moderne et attention humaine pour vous garantir les meilleurs soins possibles.</p>
                    <ul class="features-list">
                        <li>✓ Équipe médicale qualifiée et expérimentée</li>
                        <li>✓ Équipements médicaux de pointe</li>
                        <li>✓ Prise de rendez-vous en ligne</li>
                        <li>✓ Suivi personnalisé des patients</li>
                        <li>✓ Horaires flexibles</li>
                    </ul>
                </div>
                <div class="about-image">
                    <img src="assets/about-us.jpg" alt="Notre équipe médicale">
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Info Section -->
    <section class="contact-info">
        <div class="container">
            <h2>Nous Contacter</h2>
            <div class="contact-grid">
                <div class="contact-item">
                    <h3>📍 Adresse</h3>
                    <p>123 Rue de la Santé<br>75000 Paris, France</p>
                </div>
                <div class="contact-item">
                    <h3>📞 Téléphone</h3>
                    <p>01 23 45 67 89</p>
                </div>
                <div class="contact-item">
                    <h3>✉️ Email</h3>
                    <p>contact@cabinetmedical.fr</p>
                </div>
                <div class="contact-item">
                    <h3>🕒 Horaires</h3>
                    <p>Lun-Ven: 9h-18h<br>Sam: 9h-13h</p>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
</body>
</html>