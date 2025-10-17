<?php
// public/orders.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données");
}

$action = $_GET['action'] ?? 'list';
$order_id = $_GET['id'] ?? null;
$error = null;
$success = null;

// Charger les données communes
$contacts = $pdo->query("SELECT id, name, company FROM directory ORDER BY name")->fetchAll();
$projects = $pdo->query("SELECT id, name FROM projects WHERE archived = FALSE ORDER BY name")->fetchAll();

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'archive':
            $stmt = $pdo->prepare("UPDATE orders SET archived = 1 WHERE id = ?");
            $stmt->execute([$order_id]);
            echo json_encode(['success' => true]);
            exit;

        case 'unarchive':
            $stmt = $pdo->prepare("UPDATE orders SET archived = 0 WHERE id = ?");
            $stmt->execute([$order_id]);
            echo json_encode(['success' => true]);
            exit;

        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            echo json_encode(['success' => true]);
            exit;
    }
}

if ($action === 'add' || $action === 'edit') {
    if ($action === 'edit' && $order_id) {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        if (!$order) {
            header('Location: orders.php');
            exit;
        }
    } else {
        $order = null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $reference = trim($_POST['reference'] ?? '');

        if (empty($reference)) {
            $error = "La référence est obligatoire";
        } else {
            if ($action === 'edit') {
                $stmt = $pdo->prepare("
                    UPDATE orders
                    SET reference = ?, vendor_id = ?, description = ?, amount = ?,
                        order_date = ?, status = ?, related_project_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $reference,
                    !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null,
                    $_POST['description'] ?? '',
                    !empty($_POST['amount']) ? (float)$_POST['amount'] : null,
                    !empty($_POST['order_date']) ? $_POST['order_date'] : null,
                    $_POST['status'] ?? 'ordered',
                    !empty($_POST['related_project_id']) ? (int)$_POST['related_project_id'] : null,
                    $order_id
                ]);
                $success_message = "mise à jour";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO orders (reference, vendor_id, description, amount, order_date, status, related_project_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $reference,
                    !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null,
                    $_POST['description'] ?? '',
                    !empty($_POST['amount']) ? (float)$_POST['amount'] : null,
                    !empty($_POST['order_date']) ? $_POST['order_date'] : null,
                    $_POST['status'] ?? 'ordered',
                    !empty($_POST['related_project_id']) ? (int)$_POST['related_project_id'] : null
                ]);
                $success_message = "ajoutée";
            }
            header('Location: orders.php?success=' . $success_message);
            exit;
        }
    }

    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader);

    echo $twig->render('orders/form.html.twig', [
        'order' => $order,
        'contacts' => $contacts,
        'projects' => $projects,
        'error' => $error,
        'is_edit' => $action === 'edit',
        'active_page' => 'orders'
    ]);
    exit;
}

// Action: list (par défaut)
$show_archived = $_GET['archived'] ?? 0;

if (isset($_GET['success'])) {
    $action_message = $_GET['success'];
    if ($action_message === 'mise à jour') {
        $success = "La commande a été mise à jour avec succès.";
    } elseif ($action_message === 'ajoutée') {
        $success = "La commande a été ajoutée avec succès.";
    } elseif ($action_message === 'archived') {
        $success = "La commande a été archivée avec succès.";
    } elseif ($action_message === 'unarchived') {
        $success = "La commande a été désarchivée avec succès.";
    } elseif ($action_message === 'deleted') {
        $success = "La commande a été supprimée avec succès.";
    }
}

$stmt = $pdo->prepare("
    SELECT
        o.id, o.reference, o.description, o.amount, o.order_date, o.status,
        d.name AS vendor_name, d.company AS vendor_company,
        p.name AS project_name
    FROM orders o
    LEFT JOIN directory d ON o.vendor_id = d.id
    LEFT JOIN projects p ON o.related_project_id = p.id
    WHERE o.archived = ?
    ORDER BY o.order_date DESC, o.reference
");
$stmt->execute([$show_archived]);
$orders = $stmt->fetchAll();

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('orders/list.html.twig', [
    'orders' => $orders,
    'success' => $success,
    'show_archived' => $show_archived,
    'active_page' => 'orders'
]);
?>
