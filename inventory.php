<?php
// public/inventory.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données");
}

$action = $_GET['action'] ?? 'list';
$item_id = $_GET['id'] ?? null;
$error = null;
$success = null;

// Charger les données communes
$contacts = $pdo->query("SELECT id, name, company FROM directory ORDER BY name")->fetchAll();

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'archive':
            $stmt = $pdo->prepare("UPDATE inventory SET archived = 1 WHERE id = ?");
            $stmt->execute([$item_id]);
            echo json_encode(['success' => true]);
            exit;

        case 'unarchive':
            $stmt = $pdo->prepare("UPDATE inventory SET archived = 0 WHERE id = ?");
            $stmt->execute([$item_id]);
            echo json_encode(['success' => true]);
            exit;

        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
            $stmt->execute([$item_id]);
            echo json_encode(['success' => true]);
            exit;
    }
}

if ($action === 'add' || $action === 'edit') {
    if ($action === 'edit' && $item_id) {
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        if (!$item) {
            header('Location: inventory.php');
            exit;
        }
    } else {
        $item = null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $label = trim($_POST['label'] ?? '');

        if (empty($label)) {
            $error = "La désignation est obligatoire";
        } else {
            if ($action === 'edit') {
                $stmt = $pdo->prepare("
                    UPDATE inventory
                    SET category = ?, label = ?, model = ?, serial_number = ?, location = ?,
                        status = ?, installation_date = ?, last_status_update = ?,
                        technician_id = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['category'] ?? 'other',
                    $label,
                    $_POST['model'] ?? null,
                    $_POST['serial_number'] ?? null,
                    $_POST['location'] ?? null,
                    $_POST['status'] ?? 'active',
                    !empty($_POST['installation_date']) ? $_POST['installation_date'] : null,
                    !empty($_POST['last_status_update']) ? $_POST['last_status_update'] : null,
                    !empty($_POST['technician_id']) ? (int)$_POST['technician_id'] : null,
                    $_POST['notes'] ?? null,
                    $item_id
                ]);
                $success_message = "mis à jour";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO inventory (category, label, model, serial_number, location, status, installation_date, technician_id, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['category'] ?? 'other',
                    $label,
                    $_POST['model'] ?? null,
                    $_POST['serial_number'] ?? null,
                    $_POST['location'] ?? null,
                    $_POST['status'] ?? 'active',
                    !empty($_POST['installation_date']) ? $_POST['installation_date'] : null,
                    !empty($_POST['technician_id']) ? (int)$_POST['technician_id'] : null,
                    $_POST['notes'] ?? null
                ]);
                $success_message = "ajouté";
            }
            header('Location: inventory.php?success=' . $success_message);
            exit;
        }
    }

    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader);

    echo $twig->render('inventory/form.html.twig', [
        'item' => $item,
        'contacts' => $contacts,
        'error' => $error,
        'is_edit' => $action === 'edit',
        'active_page' => 'inventory'
    ]);
    exit;
}

// Action: list (par défaut)
$show_archived = $_GET['archived'] ?? 0;

if (isset($_GET['success'])) {
    $action_message = $_GET['success'];
    if ($action_message === 'mis à jour') {
        $success = "L'équipement a été mis à jour avec succès.";
    } elseif ($action_message === 'ajouté') {
        $success = "L'équipement a été ajouté avec succès.";
    }
}

$stmt = $pdo->prepare("
    SELECT
        i.id, i.category, i.label, i.model, i.serial_number, i.location,
        i.status, i.installation_date, i.last_status_update, i.notes,
        d.name AS technician_name
    FROM inventory i
    LEFT JOIN directory d ON i.technician_id = d.id
    WHERE i.archived = ?
    ORDER BY i.category, i.label
");
$stmt->execute([$show_archived]);
$items = $stmt->fetchAll();

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('inventory/list.html.twig', [
    'items' => $items,
    'success' => $success,
    'show_archived' => $show_archived,
    'active_page' => 'inventory'
]);
?>