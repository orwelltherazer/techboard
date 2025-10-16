<?php
// public/processes.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données");
}

$action = $_GET['action'] ?? 'list';
$process_id = $_GET['id'] ?? null;
$error = null;
$success = null;

// Charger les contacts pour l'auteur
$contacts = $pdo->query("SELECT id, name, company FROM directory ORDER BY name")->fetchAll();

if ($action === 'add' || $action === 'edit') {
    if ($action === 'edit' && $process_id) {
        $stmt = $pdo->prepare("SELECT * FROM processes WHERE id = ?");
        $stmt->execute([$process_id]);
        $process = $stmt->fetch();
        if (!$process) {
            header('Location: processes.php');
            exit;
        }
    } else {
        $process = null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        
        if (empty($name)) {
            $error = "Le nom du processes est obligatoire";
        } else {
            if ($action === 'edit') {
                $stmt = $pdo->prepare("
                    UPDATE processes 
                    SET name = ?, domain = ?, steps = ?, prerequisites = ?, 
                        risks = ?, checkpoints = ?, documentation_url = ?, 
                        validation_date = ?, author_id = ?, archived = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name,
                    $_POST['domain'] ?? null,
                    $_POST['steps'] ?? '',
                    $_POST['prerequisites'] ?? null,
                    $_POST['risks'] ?? null,
                    $_POST['checkpoints'] ?? null,
                    $_POST['documentation_url'] ?? null,
                    !empty($_POST['validation_date']) ? $_POST['validation_date'] : null,
                    !empty($_POST['author_id']) ? (int)$_POST['author_id'] : null,
                    !empty($_POST['archived']) ? 1 : 0,
                    $process_id
                ]);
                $success_message = "mis à jour";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO processes (name, domain, steps, prerequisites, risks, checkpoints, documentation_url, validation_date, author_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $name,
                    $_POST['domain'] ?? null,
                    $_POST['steps'] ?? '',
                    $_POST['prerequisites'] ?? null,
                    $_POST['risks'] ?? null,
                    $_POST['checkpoints'] ?? null,
                    $_POST['documentation_url'] ?? null,
                    !empty($_POST['validation_date']) ? $_POST['validation_date'] : null,
                    !empty($_POST['author_id']) ? (int)$_POST['author_id'] : null
                ]);
                $success_message = "ajouté";
            }
            header('Location: processes.php?success=' . $success_message);
            exit;
        }
    }

    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader);
    
    echo $twig->render('processes/form.html.twig', [
        'process' => $process,
        'contacts' => $contacts,
        'error' => $error,
        'is_edit' => $action === 'edit',
        'active_page' => 'processes'
    ]);
    exit;
}

// Action: list (par défaut)
if (isset($_GET['success'])) {
    $action_message = $_GET['success'];
    if ($action_message === 'mis à jour') {
        $success = "Le processes a été mis à jour avec succès.";
    } elseif ($action_message === 'ajouté') {
        $success = "Le processes a été ajouté avec succès.";
    }
}

$stmt = $pdo->query("
    SELECT 
        p.id, p.name, p.domain, p.steps, p.prerequisites, p.risks, p.checkpoints, p.documentation_url, p.validation_date,
        d.name AS author_name
    FROM processes p
    LEFT JOIN directory d ON p.author_id = d.id
    WHERE p.archived = FALSE
    ORDER BY p.domain, p.name
");
$processes = $stmt->fetchAll();

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('processes/list.html.twig', [
    'processes' => $processes,
    'success' => $success,
    'active_page' => 'processes'
]);