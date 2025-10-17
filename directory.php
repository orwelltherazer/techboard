<?php
// public/directory.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données");
}

$action = $_GET['action'] ?? 'list';
$contact_id = $_GET['id'] ?? null;
$error = null;
$success = null;

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'archive':
            $stmt = $pdo->prepare("UPDATE directory SET archived = 1 WHERE id = ?");
            $stmt->execute([$contact_id]);
            echo json_encode(['success' => true]);
            exit;

        case 'unarchive':
            $stmt = $pdo->prepare("UPDATE directory SET archived = 0 WHERE id = ?");
            $stmt->execute([$contact_id]);
            echo json_encode(['success' => true]);
            exit;

        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM directory WHERE id = ?");
            $stmt->execute([$contact_id]);
            echo json_encode(['success' => true]);
            exit;
    }
}

if ($action === 'add' || $action === 'edit') {
    if ($action === 'edit' && $contact_id) {
        $stmt = $pdo->prepare("SELECT * FROM directory WHERE id = ?");
        $stmt->execute([$contact_id]);
        $contact = $stmt->fetch();
        if (!$contact) {
            header('Location: directory.php');
            exit;
        }
    } else {
        $contact = null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        
        if (empty($name)) {
            $error = "Le nom est obligatoire";
        } else {
            if ($action === 'edit') {
                $stmt = $pdo->prepare("
                    UPDATE directory 
                    SET name = ?, company = ?, contact_type = ?, phone = ?, email = ?, 
                        address = ?, position = ?, expertise_area = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name,
                    $_POST['company'] ?? null,
                    $_POST['contact_type'] ?? 'internal_staff',
                    $_POST['phone'] ?? null,
                    $_POST['email'] ?? null,
                    $_POST['address'] ?? null,
                    $_POST['position'] ?? null,
                    $_POST['expertise_area'] ?? null,
                    $_POST['notes'] ?? null,
                    $contact_id
                ]);
                $success_message = "mis à jour";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO directory (name, company, contact_type, phone, email, address, position, expertise_area, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $name,
                    $_POST['company'] ?? null,
                    $_POST['contact_type'] ?? 'internal_staff',
                    $_POST['phone'] ?? null,
                    $_POST['email'] ?? null,
                    $_POST['address'] ?? null,
                    $_POST['position'] ?? null,
                    $_POST['expertise_area'] ?? null,
                    $_POST['notes'] ?? null
                ]);
                $success_message = "créé";
            }
            header('Location: directory.php?success=' . $success_message);
            exit;
        }
    }

    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader);
    
    echo $twig->render('directory/form.html.twig', [
        'contact' => $contact,
        'error' => $error,
        'is_edit' => $action === 'edit',
        'active_page' => 'directory'
    ]);
    exit;
}

// Action: list (par défaut)
$show_archived = $_GET['archived'] ?? 0;

if (isset($_GET['success'])) {
    $action_message = $_GET['success'];
    if ($action_message === 'mis à jour') {
        $success = "Le contact a été mis à jour avec succès.";
    } elseif ($action_message === 'créé') {
        $success = "Le contact a été créé avec succès.";
    }
}

$stmt = $pdo->prepare("
    SELECT * FROM directory
    WHERE archived = ?
    ORDER BY contact_type, name
");
$stmt->execute([$show_archived]);
$contacts = $stmt->fetchAll();

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('directory/list.html.twig', [
    'contacts' => $contacts,
    'success' => $success,
    'show_archived' => $show_archived,
    'active_page' => 'directory'
]);
?>