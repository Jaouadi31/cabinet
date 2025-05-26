<?php
require_once 'config.php';

$pdo = getDbConnection();

// Get all specialties with doctors
$stmt = $pdo->query("
    SELECT s.*, 
           COUNT(d.id) as doctor_count,
           GROUP_CONCAT(CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') as doctors
    FROM specialties s
    LEFT JOIN doctors d ON s.id = d.specialty_id
    LEFT JOIN users u ON d.user_id = u.id AND u.is_active = 1
    GROUP BY s.id
    ORDER BY s.name
");
$specialties = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nos Spécialités - Cabinet Médical</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container" style="padding: 2rem 0;">
        <h1 style="text-align: center; color: var(--primary-green); margin-bottom: 3rem;">Nos Spécialités Médicales</h1>
        
        <div class="specialties-detailed">
            <?php foreach ($specialties as $index => $specialty): ?>
                <div class="specialty-detail" id="<?= $specialty['id'] ?>">
                    <div class="specialty-content">
                        <div class="specialty-text">
                            <h2><?= htmlspecialchars($specialty['name']) ?></h2>
                            <p class="specialty-description"><?= htmlspecialchars($specialty['description']) ?></p>
                            
                            <?php if ($specialty['doctor_count'] > 0): ?>
                                <div class="doctor-info">
                                    <h4>Médecin(s) disponible(s):</h4>
                                    <p><?= htmlspecialchars($specialty['doctors']) ?></p>
                                    <p><strong><?= $specialty['doctor_count'] ?></strong> médecin(s) spécialisé(s)</p>
                                </div>
                            <?php else: ?>
                                <div class="doctor-info">
                                    <p><em>Aucun médecin disponible actuellement pour cette spécialité.</em></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="specialty-features">
                                <h4>Services proposés:</h4>
                                <ul>
                                    <?php
                                    // Add specific services based on specialty
                                    switch (strtolower($specialty['name'])) {
                                        case 'médecine générale':
                                            echo '<li>Consultations générales</li>';
                                            echo '<li>Bilans de santé</li>';
                                            echo '<li>Vaccinations</li>';
                                            echo '<li>Suivi médical</li>';
                                            break;
                                        case 'dentaire':
                                            echo '<li>Soins conservateurs</li>';
                                            echo '<li>Détartrage</li>';
                                            echo '<li>Prothèses dentaires</li>';
                                            echo '<li>Chirurgie dentaire</li>';
                                            break;
                                        case 'ophtalmologie':
                                            echo '<li>Examens de la vue</li>';
                                            echo '<li>Prescription de lunettes</li>';
                                            echo '<li>Traitement des pathologies oculaires</li>';
                                            echo '<li>Chirurgie de la cataracte</li>';
                                            break;
                                        case 'orl':
                                            echo '<li>Examens ORL</li>';
                                            echo '<li>Traitement des infections</li>';
                                            echo '<li>Audiométrie</li>';
                                            echo '<li>Chirurgie ORL</li>';
                                            break;
                                        case 'psychiatrie':
                                            echo '<li>Consultations psychiatriques</li>';
                                            echo '<li>Psychothérapie</li>';
                                            echo '<li>Suivi des troubles mentaux</li>';
                                            echo '<li>Prescription de traitements</li>';
                                            break;
                                        case 'ostéopathie':
                                            echo '<li>Thérapie manuelle</li>';
                                            echo '<li>Traitement des douleurs</li>';
                                            echo '<li>Rééducation posturale</li>';
                                            echo '<li>Soins préventifs</li>';
                                            break;
                                        default:
                                            echo '<li>Consultations spécialisées</li>';
                                            echo '<li>Diagnostics approfondis</li>';
                                            echo '<li>Traitements adaptés</li>';
                                            echo '<li>Suivi personnalisé</li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                            
                            <?php if ($specialty['doctor_count'] > 0): ?>
                                <a href="appointment.php?specialty=<?= $specialty['id'] ?>" class="btn btn-primary">Prendre Rendez-vous</a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="specialty-image">
                            <img src="assets/<?= strtolower(str_replace([' ', 'é', 'è'], ['_', 'e', 'e'], $specialty['name'])) ?>.jpg" 
                                 alt="<?= htmlspecialchars($specialty['name']) ?>"
                                 onerror="this.src='assets/default-medical.jpg'">
                        </div>
                    </div>
                </div>
                
                <?php if ($index < count($specialties) - 1): ?>
                    <hr style="margin: 3rem 0; border: none; border-top: 2px solid var(--light-green);">
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 4rem; padding: 2rem; background: var(--very-light-green); border-radius: 10px;">
            <h3 style="color: var(--primary-green);">Besoin d'un Rendez-vous?</h3>
            <p>Notre équipe médicale est à votre disposition pour vous offrir les meilleurs soins.</p>
            <a href="appointment.php" class="btn btn-primary">Prendre Rendez-vous Maintenant</a>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <style>
    .specialties-detailed {
        max-width: 1000px;
        margin: 0 auto;
    }
    
    .specialty-detail {
        margin-bottom: 3rem;
    }
    
    .specialty-content {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 3rem;
        align-items: start;
    }
    
    .specialty-text h2 {
        color: var(--primary-green);
        font-size: 2rem;
        margin-bottom: 1rem;
    }
    
    .specialty-description {
        font-size: 1.1rem;
        color: var(--text-light);
        margin-bottom: 2rem;
        line-height: 1.8;
    }
    
    .doctor-info {
        background: var(--very-light-green);
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 2rem;
    }
    
    .doctor-info h4 {
        color: var(--primary-green);
        margin-bottom: 0.5rem;
    }
    
    .specialty-features {
        margin-bottom: 2rem;
    }
    
    .specialty-features h4 {
        color: var(--primary-green);
        margin-bottom: 1rem;
    }
    
    .specialty-features ul {
        list-style: none;
        padding-left: 0;
    }
    
    .specialty-features li {
        padding: 0.5rem 0;
        padding-left: 1.5rem;
        position: relative;
        color: var(--text-light);
    }
    
    .specialty-features li:before {
        content: "✓";
        position: absolute;
        left: 0;
        color: var(--accent-green);
        font-weight: bold;
    }
    
    .specialty-image img {
        width: 100%;
        height: 300px;
        object-fit: cover;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    @media (max-width: 768px) {
        .specialty-content {
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        
        .specialty-image {
            order: -1;
        }
    }
    </style>
</body>
</html>