<?php
// public/resources.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données");
}

$action = $_GET['action'] ?? 'list';
$resource_id = $_GET['id'] ?? null;
$error = null;
$success = null;

// Charger les contacts pour le fournisseur
$contacts = $pdo->query("SELECT id, name, company FROM directory ORDER BY name")->fetchAll();

if ($action === 'add' || $action === 'edit') {
    if ($action === 'edit' && $resource_id) {
        $stmt = $pdo->prepare("SELECT * FROM resources WHERE id = ?");
        $stmt->execute([$resource_id]);
        $resource = $stmt->fetch();
        if (!$resource) {
            header('Location: resources.php');
            exit;
        }
    } else {
        $resource = null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        
        if (empty($name)) {
            $error = "Le nom de la ressource est obligatoire";
        } else {
            if ($action === 'edit') {
                $stmt = $pdo->prepare("
                    UPDATE resources 
                    SET resource_type = ?, name = ?, vendor_id = ?, location = ?, 
                        usage_description = ?, documentation_url = ?, archived = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['resource_type'] ?? 'other',
                    $name,
                    !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null,
                    $_POST['location'] ?? null,
                    $_POST['usage_description'] ?? null,
                    $_POST['documentation_url'] ?? null,
                    !empty($_POST['archived']) ? 1 : 0,
                    $resource_id
                ]);
                $success_message = "mise à jour";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO resources (resource_type, name, vendor_id, location, usage_description, documentation_url)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['resource_type'] ?? 'other',
                    $name,
                    !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null,
                    $_POST['location'] ?? null,
                    $_POST['usage_description'] ?? null,
                    $_POST['documentation_url'] ?? null
                ]);
                $success_message = "ajoutée";
            }
            header('Location: resources.php?success=' . $success_message);
            exit;
        }
    }

    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader);
    
    echo $twig->render('resources/form.html.twig', [
        'resource' => $resource,
        'contacts' => $contacts,
        'error' => $error,
        'is_edit' => $action === 'edit',
        'active_page' => 'resources'
    ]);
    exit;
}

// Action: list (par défaut)
if (isset($_GET['success'])) {
    $action_message = $_GET['success'];
    if ($action_message === 'mise à jour') {
        $success = "La ressource a été mise à jour avec succès.";
    } elseif ($action_message === 'ajoutée') {
        $success = "La ressource a été ajoutée avec succès.";
    }
}

$stmt = $pdo->query("
    SELECT 
        r.id, r.resource_type, r.name, r.location, r.usage_description,
        d.name AS vendor_name
    FROM resources r
    LEFT JOIN directory d ON r.vendor_id = d.id
    WHERE r.archived = FALSE
    ORDER BY r.resource_type, r.name
");
$resources = $stmt->fetchAll();

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('resources/list.html.twig', [
    'resources' => $resources,
    'success' => $success,
    'active_page' => 'resources'
]);