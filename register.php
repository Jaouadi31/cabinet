<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $phone = sanitize($_POST['phone']);
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif ($password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } else {
        $pdo = getDbConnection();
        
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $error = 'Ce nom d\'utilisateur ou cette adresse email existe déjà.';
        } else {
            // Create new client account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, phone) VALUES (?, ?, ?, 'client', ?, ?, ?)");
            
            if ($stmt->execute([$username, $email, $hashed_password, $first_name, $last_name, $phone])) {
                $success = 'Compte créé avec succès! Vous pouvez maintenant vous connecter.';
            } else {
                $error = 'Erreur lors de la création du compte. Veuillez réessayer.';
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
    <title>Inscription - Cabinet Médical</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div class="form-container">
            <h2 style="text-align: center; color: var(--primary-green); margin-bottom: 2rem;">Créer un Compte Client</h2>
            
            <?php if ($error): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="message success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="first_name">Prénom*:</label>
                    <input type="text" id="first_name" name="first_name" value="<?= isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Nom*:</label>
                    <input type="text" id="last_name" name="last_name" value="<?= isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="username">Nom d'utilisateur*:</label>
                    <input type="text" id="username" name="username" value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email*:</label>
                    <input type="email" id="email" name="email" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Téléphone:</label>
                    <input type="tel" id="phone" name="phone" value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe*:</label>
                    <input type="password" id="password" name="password" required>
                    <small>Au moins 6 caractères</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe*:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Créer le Compte</button>
                </div>
            </form>
            
            <div style="text-align: center; margin-top: 2rem;">
                <p>Déjà un compte? <a href="login.php" style="color: var(--primary-green);">Se connecter</a></p>
                <p><a href="index.php" style="color: var(--secondary-green);">Retour à l'accueil</a></p>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>