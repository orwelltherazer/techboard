<?php
// public/contracts.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données");
}

$action = $_GET['action'] ?? 'list';
$contract_id = $_GET['id'] ?? null;
$error = null;
$success = null;

// Charger les données communes
$contacts = $pdo->query("SELECT id, name, company FROM directory ORDER BY name")->fetchAll();

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'archive':
            $stmt = $pdo->prepare("UPDATE contracts SET archived = 1 WHERE id = ?");
            $stmt->execute([$contract_id]);
            echo json_encode(['success' => true]);
            exit;

        case 'unarchive':
            $stmt = $pdo->prepare("UPDATE contracts SET archived = 0 WHERE id = ?");
            $stmt->execute([$contract_id]);
            echo json_encode(['success' => true]);
            exit;

        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM contracts WHERE id = ?");
            $stmt->execute([$contract_id]);
            echo json_encode(['success' => true]);
            exit;
    }
}

if ($action === 'add' || $action === 'edit') {
    if ($action === 'edit' && $contract_id) {
        $stmt = $pdo->prepare("SELECT * FROM contracts WHERE id = ?");
        $stmt->execute([$contract_id]);
        $contract = $stmt->fetch();
        if (!$contract) {
            header('Location: contracts.php');
            exit;
        }

        // Charger les pièces jointes
        $stmt = $pdo->prepare("SELECT * FROM attachments WHERE entity_type = 'contract' AND entity_id = ? ORDER BY uploaded_at DESC");
        $stmt->execute([$contract_id]);
        $attachments = $stmt->fetchAll();
    } else {
        $contract = null;
        $attachments = [];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');

        if (empty($name)) {
            $error = "Le nom du contrat est obligatoire";
        } else {
            if ($action === 'edit') {
                $stmt = $pdo->prepare("
                    UPDATE contracts
                    SET name = ?, vendor_id = ?, contract_type = ?, start_date = ?,
                        end_date = ?, auto_renew = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name,
                    !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null,
                    $_POST['contract_type'] ?? null,
                    !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                    !empty($_POST['end_date']) ? $_POST['end_date'] : null,
                    !empty($_POST['auto_renew']) ? 1 : 0,
                    $_POST['notes'] ?? null,
                    $contract_id
                ]);
                $success_message = "mis à jour";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO contracts (name, vendor_id, contract_type, start_date, end_date, auto_renew, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $name,
                    !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null,
                    $_POST['contract_type'] ?? null,
                    !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                    !empty($_POST['end_date']) ? $_POST['end_date'] : null,
                    !empty($_POST['auto_renew']) ? 1 : 0,
                    $_POST['notes'] ?? null
                ]);
                $success_message = "ajouté";
            }
            header('Location: contracts.php?success=' . $success_message);
            exit;
        }
    }

    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader);

    echo $twig->render('contracts/form.html.twig', [
        'contract' => $contract,
        'contacts' => $contacts,
        'attachments' => $attachments,
        'error' => $error,
        'is_edit' => $action === 'edit',
        'active_page' => 'contracts'
    ]);
    exit;
}

// Action: list (par défaut)
$show_archived = $_GET['archived'] ?? 0;

if (isset($_GET['success'])) {
    $action_message = $_GET['success'];
    if ($action_message === 'mis à jour') {
        $success = "Le contrat a été mis à jour avec succès.";
    } elseif ($action_message === 'ajouté') {
        $success = "Le contrat a été ajouté avec succès.";
    } elseif ($action_message === 'archived') {
        $success = "Le contrat a été archivé avec succès.";
    } elseif ($action_message === 'unarchived') {
        $success = "Le contrat a été désarchivé avec succès.";
    } elseif ($action_message === 'deleted') {
        $success = "Le contrat a été supprimé avec succès.";
    }
}

$stmt = $pdo->prepare("
    SELECT
        c.id, c.name, c.contract_type, c.start_date, c.end_date,
        c.auto_renew, c.notes,
        d.name AS vendor_name,
        COUNT(a.id) AS attachments_count
    FROM contracts c
    LEFT JOIN directory d ON c.vendor_id = d.id
    LEFT JOIN attachments a ON a.entity_type = 'contract' AND a.entity_id = c.id
    WHERE c.archived = ?
    GROUP BY c.id
    ORDER BY c.end_date DESC, c.name
");
$stmt->execute([$show_archived]);
$contracts = $stmt->fetchAll();

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('contracts/list.html.twig', [
    'contracts' => $contracts,
    'success' => $success,
    'show_archived' => $show_archived,
    'active_page' => 'contracts'
]);
?>
