<?php
require_once '../config.php';
requireRole('admin');

$pdo = getDbConnection();
$error = '';
$success = '';

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $phone = sanitize($_POST['phone']);
    
    if (empty($username) || empty($email) || empty($password) || empty($role) || empty($first_name) || empty($last_name)) {
        $error = 'Tous les champs sont obligatoires.';
    } else {
        // Check if username or email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $error = 'Ce nom d\'utilisateur ou cette adresse email existe déjà.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$username, $email, $hashed_password, $role, $first_name, $last_name, $phone])) {
                $success = 'Utilisateur créé avec succès!';
                
                // If creating a doctor, redirect to add doctor details
                if ($role == 'doctor') {
                    $user_id = $pdo->lastInsertId();
                    header("Location: manage_doctors.php?add_details=$user_id");
                    exit();
                }
            } else {
                $error = 'Erreur lors de la création de l\'utilisateur.';
            }
        }
    }
}

// Handle user modification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $user_id = $_POST['user_id'];
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $role = $_POST['role'];
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $phone = sanitize($_POST['phone']);
    $new_password = $_POST['new_password'];
    
    if (empty($username) || empty($email) || empty($role) || empty($first_name) || empty($last_name)) {
        $error = 'Tous les champs obligatoires doivent être remplis.';
    } else {
        // Check if username or email exists for other users
        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $user_id]);
        
        if ($stmt->fetch()) {
            $error = 'Ce nom d\'utilisateur ou cette adresse email est déjà utilisé par un autre utilisateur.';
        } else {
            // Update user
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ?, first_name = ?, last_name = ?, phone = ? WHERE id = ?");
                $params = [$username, $email, $hashed_password, $role, $first_name, $last_name, $phone, $user_id];
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, first_name = ?, last_name = ?, phone = ? WHERE id = ?");
                $params = [$username, $email, $role, $first_name, $last_name, $phone, $user_id];
            }
            
            if ($stmt->execute($params)) {
                $success = 'Utilisateur modifié avec succès!';
            } else {
                $error = 'Erreur lors de la modification de l\'utilisateur.';
            }
        }
    }
}

// Handle user status toggle
if (isset($_GET['toggle']) && isset($_GET['status'])) {
    $user_id = $_GET['toggle'];
    $new_status = $_GET['status'] == '1' ? 0 : 1;
    
    $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role != 'admin'");
    if ($stmt->execute([$new_status, $user_id])) {
        $success = 'Statut de l\'utilisateur mis à jour.';
    }
}

// Get all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY role, last_name, first_name");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get specialties for doctor creation
$stmt = $pdo->query("SELECT * FROM specialties ORDER BY name");
$specialties = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - Admin</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container" style="padding: 2rem 0;">
        <h1 style="color: var(--primary-green); margin-bottom: 2rem;">Gestion des Utilisateurs</h1>
        
        <nav style="margin-bottom: 2rem;">
            <a href="dashboard.php" class="btn btn-outline">← Retour au Tableau de Bord</a>
        </nav>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Create User Form -->
        <div style="background: var(--white); padding: 2rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 3rem;">
            <h2 style="color: var(--primary-green); margin-bottom: 2rem;">Créer un Nouvel Utilisateur</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">Prénom*:</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Nom*:</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Nom d'utilisateur*:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email*:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Mot de passe*:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Téléphone:</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="role">Rôle*:</label>
                    <select id="role" name="role" required>
                        <option value="">Sélectionnez un rôle</option>
                        <option value="client">Client</option>
                        <option value="secretary">Secrétaire</option>
                        <option value="doctor">Médecin</option>
                        <option value="admin">Administrateur</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Créer l'Utilisateur</button>
            </form>
        </div>

        <!-- Users List -->
        <div style="background: var(--white); padding: 2rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
            <h2 style="color: var(--primary-green); margin-bottom: 2rem;">Liste des Utilisateurs</h2>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Téléphone</th>
                            <th>Statut</th>
                            <th>Créé le</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr style="<?= !$user['is_active'] ? 'opacity: 0.6;' : '' ?>">
                                <td>
                                    <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="role-badge role-<?= $user['role'] ?>">
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($user['phone'] ?: '-') ?></td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="status-badge status-confirmed">Actif</span>
                                    <?php else: ?>
                                        <span class="status-badge status-cancelled">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($user['created_at']) ?></td>
                                <td>
                                    <?php if ($user['role'] != 'admin'): ?>
                                        <a href="?toggle=<?= $user['id'] ?>&status=<?= $user['is_active'] ?>" 
                                           class="btn <?= $user['is_active'] ? 'btn-danger' : 'btn-success' ?>" 
                                           style="padding: 0.25rem 0.5rem; font-size: 0.875rem;"
                                           onclick="return confirm('Êtes-vous sûr de vouloir <?= $user['is_active'] ? 'désactiver' : 'activer' ?> cet utilisateur?')">
                                            <?= $user['is_active'] ? 'Désactiver' : 'Activer' ?>
                                        </a>
                                        
                                        <button onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)" 
                                                class="btn btn-outline" 
                                                style="padding: 0.25rem 0.5rem; font-size: 0.875rem; margin-left: 0.25rem;">
                                            Modifier
                                        </button>
                                        
                                        <?php if ($user['role'] == 'doctor'): ?>
                                            <a href="manage_doctors.php?doctor_user_id=<?= $user['id'] ?>" 
                                               class="btn btn-secondary" 
                                               style="padding: 0.25rem 0.5rem; font-size: 0.875rem; margin-left: 0.25rem;">
                                                Détails Médecin
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-light); font-size: 0.875rem;">Admin</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeEditUserModal()">&times;</span>
            <h2>Modifier l'Utilisateur</h2>
            
            <form method="POST" id="editUserForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_first_name">Prénom*:</label>
                        <input type="text" id="edit_first_name" name="first_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_last_name">Nom*:</label>
                        <input type="text" id="edit_last_name" name="last_name" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_username">Nom d'utilisateur*:</label>
                        <input type="text" id="edit_username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_email">Email*:</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_phone">Téléphone:</label>
                        <input type="tel" id="edit_phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_role">Rôle*:</label>
                        <select id="edit_role" name="role" required>
                            <option value="client">Client</option>
                            <option value="secretary">Secrétaire</option>
                            <option value="doctor">Médecin</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_new_password">Nouveau mot de passe (laisser vide pour ne pas changer):</label>
                    <input type="password" id="edit_new_password" name="new_password">
                    <small>Minimum 6 caractères si vous souhaitez changer le mot de passe</small>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Modifier l'Utilisateur</button>
                    <button type="button" onclick="closeEditUserModal()" class="btn btn-secondary">Annuler</button>
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
        max-width: 600px;
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
    
    .role-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 500;
        text-transform: uppercase;
    }
    
    .role-admin {
        background: #dc3545;
        color: white;
    }
    
    .role-doctor {
        background: #007bff;
        color: white;
    }
    
    .role-secretary {
        background: #6f42c1;
        color: white;
    }
    
    .role-client {
        background: #28a745;
        color: white;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .table-container {
            overflow-x: auto;
        }
    }
    </style>

    <script>
    function editUser(user) {
        document.getElementById('edit_user_id').value = user.id;
        document.getElementById('edit_first_name').value = user.first_name;
        document.getElementById('edit_last_name').value = user.last_name;
        document.getElementById('edit_username').value = user.username;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_phone').value = user.phone || '';
        document.getElementById('edit_role').value = user.role;
        document.getElementById('edit_new_password').value = '';
        
        document.getElementById('editUserModal').style.display = 'block';
    }

    function closeEditUserModal() {
        document.getElementById('editUserModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('editUserModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
    </script>
</body>
</html>