<header>
    <div class="container">
        <div class="header-content">
            <a href="<?= strpos($_SERVER['REQUEST_URI'], '/admin/') !== false || strpos($_SERVER['REQUEST_URI'], '/client/') !== false || strpos($_SERVER['REQUEST_URI'], '/secretary/') !== false || strpos($_SERVER['REQUEST_URI'], '/doctor/') !== false ? '../' : '' ?>index.php" class="logo">
                <img src="<?= strpos($_SERVER['REQUEST_URI'], '/admin/') !== false || strpos($_SERVER['REQUEST_URI'], '/client/') !== false || strpos($_SERVER['REQUEST_URI'], '/secretary/') !== false || strpos($_SERVER['REQUEST_URI'], '/doctor/') !== false ? '../' : '' ?>assets/logo.png" alt="Logo" onerror="this.style.display='none'">
                Cabinet Médical
            </a>
            
            <nav class="nav">
                <ul>
                    <?php 
                    $base_path = (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false || strpos($_SERVER['REQUEST_URI'], '/client/') !== false || strpos($_SERVER['REQUEST_URI'], '/secretary/') !== false || strpos($_SERVER['REQUEST_URI'], '/doctor/') !== false) ? '../' : '';
                    ?>
                    <li><a href="<?= $base_path ?>index.php">Accueil</a></li>
                    <li><a href="<?= $base_path ?>specialties.php">Spécialités</a></li>
                    <li><a href="<?= $base_path ?>appointment.php">Rendez-vous</a></li>
                    <li><a href="<?= $base_path ?>contact.php">Contact</a></li>
                </ul>
            </nav>
            
            <div class="user-menu">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="user-info">
                        Bonjour, <?= htmlspecialchars($_SESSION['first_name'] ?? 'Utilisateur') ?>
                        (<?= ucfirst($_SESSION['role'] ?? 'user') ?>)
                    </span>
                    <a href="<?= $base_path ?>dashboard.php" class="btn btn-outline">Tableau de bord</a>
                    <a href="<?= $base_path ?>logout.php" class="btn btn-secondary">Déconnexion</a>
                <?php else: ?>
                    <a href="<?= $base_path ?>login.php" class="btn btn-outline">Connexion</a>
                    <a href="<?= $base_path ?>register.php" class="btn btn-primary">S'inscrire</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>