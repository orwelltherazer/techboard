<?php
// public/communications.php (modifié pour gérer les nouvelles dates)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données");
}

$action = $_GET['action'] ?? 'list';
$comm_id = $_GET['id'] ?? null;
$error = null;
$success = null;

// Charger les contacts pour l'assignation
$contacts = $pdo->query("SELECT id, name, company FROM directory ORDER BY name")->fetchAll();

if ($action === 'add' || $action === 'edit') {
    if ($action === 'edit' && $comm_id) {
        $stmt = $pdo->prepare("SELECT * FROM communications WHERE id = ?");
        $stmt->execute([$comm_id]);
        $comm = $stmt->fetch();
        if (!$comm) {
            header('Location: communications.php');
            exit;
        }
        
        // Charger les supports existants
        $stmt = $pdo->prepare("SELECT channel FROM communication_channels WHERE communication_id = ?");
        $stmt->execute([$comm_id]);
        $existing_channels = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $comm = null;
        $existing_channels = [];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $subject = trim($_POST['subject'] ?? '');
        
        if (empty($subject)) {
            $error = "L'objet est obligatoire";
        } else {
            $channels = $_POST['channels'] ?? [];
            
            if ($action === 'edit') {
                $stmt = $pdo->prepare("
                    UPDATE communications 
                    SET subject = ?, requester = ?, message = ?, 
                        status = ?, assigned_to = ?, internal_comments = ?, 
                        processed_at = ?, event_date = ?, publication_start_date = ?, 
                        publication_end_date = ?
                    WHERE id = ?
                ");
                $processed_at = (!empty($_POST['processed_at']) && $_POST['status'] !== 'pending') ? $_POST['processed_at'] : null;
                $stmt->execute([
                    $subject,
                    $_POST['requester'] ?? '',
                    $_POST['message'] ?? '',
                    $_POST['status'] ?? 'pending',
                    !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null,
                    $_POST['internal_comments'] ?? '',
                    $processed_at,
                    !empty($_POST['event_date']) ? $_POST['event_date'] : null,
                    !empty($_POST['publication_start_date']) ? $_POST['publication_start_date'] : null,
                    !empty($_POST['publication_end_date']) ? $_POST['publication_end_date'] : null,
                    $comm_id
                ]);
                
                // Supprimer les anciens supports
                $stmt = $pdo->prepare("DELETE FROM communication_channels WHERE communication_id = ?");
                $stmt->execute([$comm_id]);
                
                // Ajouter les nouveaux supports
                foreach ($channels as $channel) {
                    $stmt = $pdo->prepare("INSERT INTO communication_channels (communication_id, channel) VALUES (?, ?)");
                    $stmt->execute([$comm_id, $channel]);
                }
                
                $success_message = "mise à jour";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO communications (subject, requester, message, status, assigned_to, internal_comments, event_date, publication_start_date, publication_end_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $subject,
                    $_POST['requester'] ?? '',
                    $_POST['message'] ?? '',
                    $_POST['status'] ?? 'pending',
                    !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null,
                    $_POST['internal_comments'] ?? '',
                    !empty($_POST['event_date']) ? $_POST['event_date'] : null,
                    !empty($_POST['publication_start_date']) ? $_POST['publication_start_date'] : null,
                    !empty($_POST['publication_end_date']) ? $_POST['publication_end_date'] : null
                ]);
                
                $new_comm_id = $pdo->lastInsertId();
                
                // Ajouter les supports
                foreach ($channels as $channel) {
                    $stmt = $pdo->prepare("INSERT INTO communication_channels (communication_id, channel) VALUES (?, ?)");
                    $stmt->execute([$new_comm_id, $channel]);
                }
                
                $success_message = "créée";
            }
            header('Location: communications.php?success=' . $success_message);
            exit;
        }
    }

    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader);
    
    echo $twig->render('communications/form.html.twig', [
        'comm' => $comm,
        'existing_channels' => $existing_channels,
        'contacts' => $contacts,
        'error' => $error,
        'is_edit' => $action === 'edit',
        'active_page' => 'communications'
    ]);
    exit;
}

// Action: list (par défaut)
if (isset($_GET['success'])) {
    $action_message = $_GET['success'];
    if ($action_message === 'mise à jour') {
        $success = "La demande de communication a été mise à jour avec succès.";
    } elseif ($action_message === 'créée') {
        $success = "La demande de communication a été créée avec succès.";
    }
}

$stmt = $pdo->query("
    SELECT 
        c.id, c.subject, c.requester, c.message, c.status, 
        c.received_at, c.processed_at, c.event_date, c.publication_start_date, c.publication_end_date,
        d.name AS assigned_name,
        GROUP_CONCAT(cc.channel SEPARATOR ', ') AS channels
    FROM communications c
    LEFT JOIN directory d ON c.assigned_to = d.id
    LEFT JOIN communication_channels cc ON c.id = cc.communication_id
    GROUP BY c.id
    ORDER BY c.received_at DESC
");
$communications = $stmt->fetchAll();

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('communications/list.html.twig', [
    'communications' => $communications,
    'success' => $success,
    'active_page' => 'communications'
]);