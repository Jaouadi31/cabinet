<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header('Location: admin/dashboard.php');
                    break;
                case 'secretary':
                    header('Location: secretary/dashboard.php');
                    break;
                case 'doctor':
                    header('Location: doctor/dashboard.php');
                    break;
                case 'client':
                    header('Location: client/dashboard.php');
                    break;
                default:
                    header('Location: index.php');
            }
            exit();
        } else {
            $error = 'Nom d\'utilisateur ou mot de passe incorrect.';
        }
    }
}

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'secretary':
            header('Location: secretary/dashboard.php');
            break;
        case 'doctor':
            header('Location: doctor/dashboard.php');
            break;
        case 'client':
            header('Location: client/dashboard.php');
            break;
        default:
            header('Location: index.php');
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Cabinet Médical</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div class="form-container">
            <h2 style="text-align: center; color: var(--primary-green); margin-bottom: 2rem;">Connexion</h2>
            
            <?php if ($error): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="message success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">Nom d'utilisateur:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Se Connecter</button>
                </div>
            </form>
            
            <div style="text-align: center; margin-top: 2rem;">
                <p>Pas encore de compte? <a href="register.php" style="color: var(--primary-green);">S'inscrire</a></p>
                <p><a href="index.php" style="color: var(--secondary-green);">Retour à l'accueil</a></p>
            </div>
            
            <div style="margin-top: 2rem; padding: 1rem; background: var(--very-light-green); border-radius: 5px;">
                <h4 style="color: var(--primary-green);">Comptes de test:</h4>
                <p><strong>Admin:</strong> admin / admin123</p>
                <p><strong>Secrétaire:</strong> secretary1 / secretary123</p>
                <p><strong>Docteur:</strong> dr_martin / doctor123</p>
                <p><strong>Client:</strong> client1 / client123</p>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>