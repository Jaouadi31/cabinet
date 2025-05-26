<?php
require_once '../config.php';
requireRole('admin');

$pdo = getDbConnection();
$error = '';
$success = '';

// Handle message status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_status') {
        $message_id = $_POST['message_id'];
        $new_status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $message_id])) {
            $success = 'Statut du message mis à jour avec succès!';
        } else {
            $error = 'Erreur lors de la mise à jour du statut.';
        }
    }
    
    if ($_POST['action'] == 'delete_message') {
        $message_id = $_POST['message_id'];
        
        $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
        if ($stmt->execute([$message_id])) {
            $success = 'Message supprimé avec succès!';
        } else {
            $error = 'Erreur lors de la suppression du message.';
        }
    }
    
    if ($_POST['action'] == 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'read' WHERE status = 'new'");
        if ($stmt->execute()) {
            $success = 'Tous les nouveaux messages ont été marqués comme lus!';
        } else {
            $error = 'Erreur lors de la mise à jour.';
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$subject_filter = $_GET['subject'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if ($subject_filter) {
    $where_conditions[] = "subject = ?";
    $params[] = $subject_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get all messages
$stmt = $pdo->prepare("
    SELECT * FROM contact_messages 
    $where_clause
    ORDER BY created_at DESC
");
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique subjects for filter
$stmt = $pdo->query("SELECT DISTINCT subject FROM contact_messages ORDER BY subject");
$subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get statistics
$stats = [
    'total' => count($messages),
    'new' => count(array_filter($messages, function($msg) { return $msg['status'] == 'new'; })),
    'read' => count(array_filter($messages, function($msg) { return $msg['status'] == 'read'; })),
    'replied' => count(array_filter($messages, function($msg) { return $msg['status'] == 'replied'; }))
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Messages - Admin</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container" style="padding: 2rem 0;">
        <h1 style="color: var(--primary-green); margin-bottom: 2rem;">Gestion des Messages de Contact</h1>
        
        <nav style="margin-bottom: 2rem;">
            <a href="dashboard.php" class="btn btn-outline">← Retour au Tableau de Bord</a>
        </nav>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div style="margin: 2rem 0;">
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-secondary" 
                            onclick="return confirm('Marquer tous les nouveaux messages comme lus?')">
                        Marquer Tout comme Lu
                    </button>
                </form>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid" style="margin: 2rem 0;">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Messages</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['new'] ?></div>
                <div class="stat-label">Nouveaux</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['read'] ?></div>
                <div class="stat-label">Lus</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['replied'] ?></div>
                <div class="stat-label">Répondus</div>
            </div>
        </div>

        <!-- Filters -->
        <div style="background: var(--white); padding: 2rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 2rem;">
            <h3 style="color: var(--primary-green); margin-bottom: 1rem;">Filtres</h3>
            
            <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                <div class="form-group">
                    <label for="status">Statut:</label>
                    <select id="status" name="status">
                        <option value="">Tous les statuts</option>
                        <option value="new" <?= $status_filter == 'new' ? 'selected' : '' ?>>Nouveau</option>
                        <option value="read" <?= $status_filter == 'read' ? 'selected' : '' ?>>Lu</option>
                        <option value="replied" <?= $status_filter == 'replied' ? 'selected' : '' ?>>Répondu</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="subject">Sujet:</label>
                    <select id="subject" name="subject">
                        <option value="">Tous les sujets</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= htmlspecialchars($subject) ?>" <?= $subject_filter == $subject ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subject) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="view_messages.php" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>

        <!-- Messages List -->
        <div style="background: var(--white); padding: 2rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
            <h2 style="color: var(--primary-green); margin-bottom: 2rem;">
                Messages de Contact 
                <?php if ($where_conditions): ?>
                    <small style="color: var(--text-light);">(<?= count($messages) ?> résultat<?= count($messages) > 1 ? 's' : '' ?>)</small>
                <?php endif; ?>
            </h2>
            
            <?php if (empty($messages)): ?>
                <div style="text-align: center; padding: 2rem; color: var(--text-light);">
                    Aucun message trouvé avec ces critères.
                </div>
            <?php else: ?>
                <div class="messages-list">
                    <?php foreach ($messages as $message): ?>
                        <div class="message-card <?= $message['status'] == 'new' ? 'message-new' : '' ?>" 
                             style="background: <?= $message['status'] == 'new' ? '#fff3cd' : 'white' ?>; 
                                    border: 1px solid <?= $message['status'] == 'new' ? '#ffc107' : 'var(--border-color)' ?>; 
                                    border-radius: 8px; 
                                    padding: 1.5rem; 
                                    margin-bottom: 1rem;">
                            
                            <div style="display: flex; justify-content: between; align-items: start; margin-bottom: 1rem;">
                                <div style="flex: 1;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <h4 style="color: var(--primary-green); margin: 0;">
                                            <?= htmlspecialchars($message['subject']) ?>
                                            <?php if ($message['status'] == 'new'): ?>
                                                <span style="background: #dc3545; color: white; padding: 0.2rem 0.5rem; border-radius: 12px; font-size: 0.75rem; margin-left: 0.5rem;">NOUVEAU</span>
                                            <?php endif; ?>
                                        </h4>
                                        
                                        <div style="display: flex; gap: 0.5rem;">
                                            <button onclick="viewMessage(<?= htmlspecialchars(json_encode($message)) ?>)" 
                                                    class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">
                                                Voir
                                            </button>
                                            <button onclick="updateMessageStatus(<?= $message['id'] ?>)" 
                                                    class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">
                                                Statut
                                            </button>
                                            <button onclick="deleteMessage(<?= $message['id'] ?>)" 
                                                    class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">
                                                Supprimer
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                                        <div>
                                            <strong>De:</strong> <?= htmlspecialchars($message['name']) ?><br>
                                            <small>📧 <?= htmlspecialchars($message['email']) ?></small>
                                            <?php if ($message['phone']): ?>
                                                <br><small>📞 <?= htmlspecialchars($message['phone']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div>
                                            <strong>Reçu le:</strong> <?= formatDate($message['created_at']) ?><br>
                                            <small><?= date('H:i', strtotime($message['created_at'])) ?></small>
                                        </div>
                                        
                                        <div>
                                            <strong>Statut:</strong>
                                            <span class="status-badge status-<?= $message['status'] ?>">
                                                <?php
                                                switch($message['status']) {
                                                    case 'new': echo 'Nouveau'; break;
                                                    case 'read': echo 'Lu'; break;
                                                    case 'replied': echo 'Répondu'; break;
                                                    default: echo ucfirst($message['status']);
                                                }
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div style="background: var(--light-gray); padding: 1rem; border-radius: 6px;">
                                        <strong>Message:</strong><br>
                                        <p style="margin: 0.5rem 0; line-height: 1.5;">
                                            <?= nl2br(htmlspecialchars(substr($message['message'], 0, 200))) ?>
                                            <?php if (strlen($message['message']) > 200): ?>
                                                <span style="color: var(--text-light);">... <em>(cliquez sur "Voir" pour le message complet)</em></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Message Modal -->
    <div id="messageModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeMessageModal()">&times;</span>
            <h2>📧 Détails du Message</h2>
            <div id="messageContent"></div>
            <div style="margin-top: 2rem; text-align: center;">
                <button onclick="closeMessageModal()" class="btn btn-secondary">Fermer</button>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="statusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeStatusModal()">&times;</span>
            <h2>Modifier le Statut</h2>
            
            <form method="POST" id="statusForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="message_id" id="modal_message_id">
                
                <div class="form-group">
                    <label for="modal_status">Nouveau Statut:</label>
                    <select id="modal_status" name="status" required>
                        <option value="new">Nouveau</option>
                        <option value="read">Lu</option>
                        <option value="replied">Répondu</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Mettre à Jour</button>
                    <button type="button" onclick="closeStatusModal()" class="btn btn-secondary">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <style>
    .messages-list {
        max-height: none;
    }
    
    .message-card {
        transition: all 0.3s ease;
    }
    
    .message-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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

    .message-section {
        background: var(--very-light-green);
        padding: 1rem;
        margin: 1rem 0;
        border-radius: 8px;
        border-left: 4px solid var(--primary-green);
    }

    .message-section h4 {
        color: var(--primary-green);
        margin-bottom: 0.5rem;
    }

    @media (max-width: 768px) {
        .message-card {
            padding: 1rem;
        }
        
        .message-card > div:first-child {
            flex-direction: column;
            gap: 1rem;
        }
    }
    </style>

    <script>
    function viewMessage(message) {
        const messageContent = `
            <div class="message-section">
                <h4>Informations de Contact</h4>
                <p><strong>Nom:</strong> ${message.name}</p>
                <p><strong>Email:</strong> ${message.email}</p>
                <p><strong>Téléphone:</strong> ${message.phone || 'Non renseigné'}</p>
                <p><strong>Sujet:</strong> ${message.subject}</p>
                <p><strong>Reçu le:</strong> ${new Date(message.created_at).toLocaleDateString('fr-FR')} à ${new Date(message.created_at).toLocaleTimeString('fr-FR')}</p>
            </div>
            
            <div class="message-section">
                <h4>Message</h4>
                <p style="white-space: pre-line; line-height: 1.6;">${message.message}</p>
            </div>
            
            <div class="message-section">
                <h4>Statut</h4>
                <p>
                    <span class="status-badge status-${message.status}">
                        ${message.status === 'new' ? 'Nouveau' : message.status === 'read' ? 'Lu' : 'Répondu'}
                    </span>
                </p>
            </div>
        `;
        
        document.getElementById('messageContent').innerHTML = messageContent;
        document.getElementById('messageModal').style.display = 'block';
        
        // Mark as read if it's new
        if (message.status === 'new') {
            updateMessageStatusSilent(message.id, 'read');
        }
    }

    function updateMessageStatus(messageId) {
        document.getElementById('modal_message_id').value = messageId;
        document.getElementById('statusModal').style.display = 'block';
    }

    function updateMessageStatusSilent(messageId, status) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="message_id" value="${messageId}">
            <input type="hidden" name="status" value="${status}">
        `;
        document.body.appendChild(form);
        form.submit();
    }

    function deleteMessage(messageId) {
        if (confirm('Êtes-vous sûr de vouloir supprimer ce message? Cette action est irréversible.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_message">
                <input type="hidden" name="message_id" value="${messageId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function closeMessageModal() {
        document.getElementById('messageModal').style.display = 'none';
    }

    function closeStatusModal() {
        document.getElementById('statusModal').style.display = 'none';
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const messageModal = document.getElementById('messageModal');
        const statusModal = document.getElementById('statusModal');
        
        if (event.target == messageModal) {
            messageModal.style.display = 'none';
        }
        if (event.target == statusModal) {
            statusModal.style.display = 'none';
        }
    }
    </script>
</body>
</html>