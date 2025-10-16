<?php
// public/administration.php (modifié pour la création et modification)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données");
}

$action = $_GET['action'] ?? 'main';
$section = $_GET['section'] ?? null;
$success = null;

// Charger les contacts pour les fournisseurs
$contacts = $pdo->query("SELECT id, name, company FROM directory ORDER BY name")->fetchAll();
$projects = $pdo->query("SELECT id, name FROM projects WHERE archived = FALSE ORDER BY name")->fetchAll();

// Gestion des contrats
if ($action === 'contract_add' || $action === 'contract_edit') {
    $contract_id = $_GET['id'] ?? null;
    $contract = null;
    $error = null;
    
    if ($action === 'contract_edit' && $contract_id) {
        $stmt = $pdo->prepare("SELECT * FROM contracts WHERE id = ?");
        $stmt->execute([$contract_id]);
        $contract = $stmt->fetch();
        if (!$contract) {
            header('Location: administration.php?section=contracts');
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        
        if (empty($name)) {
            $error = "Le nom du contrat est obligatoire";
        } else {
            if ($action === 'contract_edit') {
                $stmt = $pdo->prepare("
                    UPDATE contracts 
                    SET name = ?, vendor_id = ?, contract_type = ?, start_date = ?, 
                        end_date = ?, auto_renew = ?, annual_amount = ?, notes = ?, archived = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name,
                    (int)$_POST['vendor_id'],
                    $_POST['contract_type'] ?? '',
                    $_POST['start_date'] ?? null,
                    $_POST['end_date'] ?? null,
                    !empty($_POST['auto_renew']) ? 1 : 0,
                    !empty($_POST['annual_amount']) ? (float)$_POST['annual_amount'] : null,
                    $_POST['notes'] ?? null,
                    !empty($_POST['archived']) ? 1 : 0,
                    $contract_id
                ]);
                $success_message = "mis à jour";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO contracts (name, vendor_id, contract_type, start_date, end_date, auto_renew, annual_amount, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $name,
                    (int)$_POST['vendor_id'],
                    $_POST['contract_type'] ?? '',
                    $_POST['start_date'] ?? null,
                    $_POST['end_date'] ?? null,
                    !empty($_POST['auto_renew']) ? 1 : 0,
                    !empty($_POST['annual_amount']) ? (float)$_POST['annual_amount'] : null,
                    $_POST['notes'] ?? null
                ]);
                $success_message = "ajouté";
            }
            header('Location: administration.php?section=contracts&success=' . $success_message);
            exit;
        }
    }

    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader);
    
    echo $twig->render('admin/contract_form.html.twig', [
        'contract' => $contract,
        'contacts' => $contacts,
        'error' => $error ?? null,
        'is_edit' => $action === 'contract_edit',
        'active_page' => 'administration'
    ]);
    exit;
}

// Gestion des commandes
if ($action === 'order_add' || $action === 'order_edit') {
    $order_id = $_GET['id'] ?? null;
    $order = null;
    $error = null;
    
    if ($action === 'order_edit' && $order_id) {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        if (!$order) {
            header('Location: administration.php?section=orders');
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $reference = trim($_POST['reference'] ?? '');
        
        if (empty($reference)) {
            $error = "La référence est obligatoire";
        } else {
            if ($action === 'order_edit') {
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET reference = ?, vendor_id = ?, description = ?, amount = ?, 
                        order_date = ?, status = ?, related_project_id = ?, archived = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $reference,
                    (int)$_POST['vendor_id'],
                    $_POST['description'] ?? '',
                    !empty($_POST['amount']) ? (float)$_POST['amount'] : null,
                    $_POST['order_date'] ?? null,
                    $_POST['status'] ?? 'ordered',
                    !empty($_POST['related_project_id']) ? (int)$_POST['related_project_id'] : null,
                    !empty($_POST['archived']) ? 1 : 0,
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
                    (int)$_POST['vendor_id'],
                    $_POST['description'] ?? '',
                    !empty($_POST['amount']) ? (float)$_POST['amount'] : null,
                    $_POST['order_date'] ?? null,
                    $_POST['status'] ?? 'ordered',
                    !empty($_POST['related_project_id']) ? (int)$_POST['related_project_id'] : null
                ]);
                $success_message = "ajoutée";
            }
            header('Location: administration.php?section=orders&success=' . $success_message);
            exit;
        }
    }

    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader);
    
    echo $twig->render('admin/order_form.html.twig', [
        'order' => $order,
        'contacts' => $contacts,
        'projects' => $projects,
        'error' => $error ?? null,
        'is_edit' => $action === 'order_edit',
        'active_page' => 'administration'
    ]);
    exit;
}

// Action: list (par défaut)
if (isset($_GET['success'])) {
    $action_message = $_GET['success'];
    if ($action_message === 'mise à jour') {
        $success = "L'élément a été mis à jour avec succès.";
    } elseif ($action_message === 'ajouté' || $action_message === 'ajoutée') {
        $success = "L'élément a été ajouté avec succès.";
    }
}

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

if ($section === 'contracts') {
    $stmt = $pdo->query("
        SELECT 
            c.id, c.name, c.contract_type, c.start_date, c.end_date, 
            c.auto_renew, c.annual_amount, c.notes,
            d.name AS vendor_name
        FROM contracts c
        LEFT JOIN directory d ON c.vendor_id = d.id
        WHERE c.archived = FALSE
        ORDER BY c.end_date DESC, c.name
    ");
    $contracts = $stmt->fetchAll();
    
    echo $twig->render('admin/contracts_list.html.twig', [
        'contracts' => $contracts,
        'success' => $success,
        'active_page' => 'administration'
    ]);
} elseif ($section === 'orders') {
    $stmt = $pdo->query("
        SELECT 
            o.id, o.reference, o.description, o.amount, o.order_date, o.status,
            d.name AS vendor_name,
            p.name AS project_name
        FROM orders o
        LEFT JOIN directory d ON o.vendor_id = d.id
        LEFT JOIN projects p ON o.related_project_id = p.id AND p.archived = FALSE
        WHERE o.archived = FALSE
        ORDER BY o.order_date DESC
    ");
    $orders = $stmt->fetchAll();
    
    echo $twig->render('admin/orders_list.html.twig', [
        'orders' => $orders,
        'success' => $success,
        'active_page' => 'administration'
    ]);
} else {
    // Page principale d'administration
    echo $twig->render('admin/main.html.twig', [
        'active_page' => 'administration'
    ]);
}