<?php
// public/projects.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données");
}

$action = $_GET['action'] ?? 'list';
$project_id = $_GET['id'] ?? null;
$error = null;
$success = null;

// DEBUG
error_log("Action: " . $action);
error_log("Project ID: " . ($project_id ?? 'null'));
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("GET params: " . print_r($_GET, true));
error_log("POST params: " . print_r($_POST, true));

// Charger les données communes
$contacts = $pdo->query("SELECT id, name, company FROM directory ORDER BY name")->fetchAll();

// === GESTION DES LOGS (AJAX) ===
if ($action === 'add_log' && $_SERVER['REQUEST_METHOD'] === 'POST' && $project_id) {
    header('Content-Type: application/json');
    $log_date = $_POST['log_date'] ?? date('Y-m-d H:i:s');
    $content = trim($_POST['content'] ?? '');
    
    if (!empty($content)) {
        $stmt = $pdo->prepare("INSERT INTO project_logs (project_id, log_date, content) VALUES (?, ?, ?)");
        $stmt->execute([$project_id, $log_date, $content]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Contenu vide']);
    }
    exit;
}

if ($action === 'edit_log' && $_SERVER['REQUEST_METHOD'] === 'POST' && $project_id) {
    header('Content-Type: application/json');
    $log_id = $_POST['log_id'] ?? null;
    $log_date = $_POST['log_date'] ?? date('Y-m-d H:i:s');
    $content = trim($_POST['content'] ?? '');
    
    if ($log_id && !empty($content)) {
        $stmt = $pdo->prepare("UPDATE project_logs SET log_date = ?, content = ? WHERE id = ? AND project_id = ?");
        $stmt->execute([$log_date, $content, $log_id, $project_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Données invalides']);
    }
    exit;
}

if ($action === 'delete_log' && $project_id) {
    header('Content-Type: application/json');
    $log_id = $_GET['log_id'] ?? null;
    if ($log_id) {
        $stmt = $pdo->prepare("DELETE FROM project_logs WHERE id = ? AND project_id = ?");
        $stmt->execute([$log_id, $project_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

if ($action === 'get_log' && $project_id) {
    header('Content-Type: application/json');
    $log_id = $_GET['log_id'] ?? null;
    if ($log_id) {
        $stmt = $pdo->prepare("SELECT * FROM project_logs WHERE id = ? AND project_id = ?");
        $stmt->execute([$log_id, $project_id]);
        $log = $stmt->fetch();
        echo json_encode($log ?: ['success' => false]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// === VUE DÉTAILLÉE D'UN PROJET ===
if ($action === 'view' && $project_id) {
    $stmt = $pdo->prepare("
        SELECT p.*, d.name AS manager_name, d.company AS manager_company
        FROM projects p
        LEFT JOIN directory d ON p.manager_id = d.id
        WHERE p.id = ?
    ");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();
    
    if (!$project) {
        header('Location: projects.php');
        exit;
    }
    
    // Charger l'historique
    $stmt = $pdo->prepare("SELECT * FROM project_logs WHERE project_id = ? ORDER BY log_date DESC");
    $stmt->execute([$project_id]);
    $logs = $stmt->fetchAll();
    
    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader);
    
    echo $twig->render('projects/view.html.twig', [
        'project' => $project,
        'logs' => $logs,
        'active_page' => 'projects'
    ]);
    exit;
}

// === FORMULAIRE ADD/EDIT ===
if ($action === 'add' || $action === 'edit') {
    error_log("=== ENTREE DANS FORMULAIRE ===");
    error_log("Action: " . $action);
    error_log("Project ID: " . ($project_id ?? 'null'));
    
    if ($action === 'edit' && $project_id) {
        error_log("Mode EDIT avec ID: " . $project_id);
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$project_id]);
        $project = $stmt->fetch();
        if (!$project) {
            error_log("ERREUR: Projet non trouvé");
            header('Location: projects.php');
            exit;
        }
        error_log("Projet trouvé: " . $project['name']);
    } else {
        error_log("Mode ADD");
        $project = null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("=== SOUMISSION FORMULAIRE ===");
        $name = trim($_POST['name'] ?? '');
        error_log("Nom du projet: " . $name);
        
        if (empty($name)) {
            $error = "Le nom du projet est obligatoire";
            error_log("ERREUR: Nom vide");
        } else {
            if ($action === 'edit' && $project_id) {
                error_log("UPDATE du projet ID: " . $project_id);
                $stmt = $pdo->prepare("
                    UPDATE projects 
                    SET name = ?, objective = ?, context = ?, progress_percent = ?, 
                        start_date = ?, estimated_end_date = ?, internal_notes = ?, 
                        manager_id = ?, archived = ?
                    WHERE id = ?
                ");
                $result = $stmt->execute([
                    $name,
                    $_POST['objective'] ?? '',
                    $_POST['context'] ?? '',
                    $_POST['progress_percent'] ?? 0,
                    !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                    !empty($_POST['estimated_end_date']) ? $_POST['estimated_end_date'] : null,
                    $_POST['internal_notes'] ?? '',
                    !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null,
                    !empty($_POST['archived']) ? 1 : 0,
                    $project_id
                ]);
                error_log("Résultat UPDATE: " . ($result ? 'OK' : 'ERREUR'));
                error_log("Redirection vers: projects.php?action=view&id=" . $project_id);
                header('Location: projects.php?action=view&id=' . $project_id);
                exit;
            } else {
                error_log("INSERT nouveau projet");
                $stmt = $pdo->prepare("
                    INSERT INTO projects (name, objective, context, progress_percent, start_date, estimated_end_date, internal_notes, manager_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $result = $stmt->execute([
                    $name,
                    $_POST['objective'] ?? '',
                    $_POST['context'] ?? '',
                    $_POST['progress_percent'] ?? 0,
                    !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                    !empty($_POST['estimated_end_date']) ? $_POST['estimated_end_date'] : null,
                    $_POST['internal_notes'] ?? '',
                    !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null
                ]);
                $new_id = $pdo->lastInsertId();
                error_log("Résultat INSERT: " . ($result ? 'OK' : 'ERREUR'));
                error_log("Nouvel ID: " . $new_id);
                error_log("Redirection vers: projects.php?action=view&id=" . $new_id);
                header('Location: projects.php?action=view&id=' . $new_id);
                exit;
            }
        }
    }

    error_log("Affichage du formulaire");
    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader);
    
    echo $twig->render('projects/form.html.twig', [
        'project' => $project,
        'contacts' => $contacts,
        'error' => $error,
        'is_edit' => $action === 'edit',
        'active_page' => 'projects'
    ]);
    exit;
}

// === LISTE (par défaut) ===
if (isset($_GET['success'])) {
    $action_message = $_GET['success'];
    if ($action_message === 'mise à jour') {
        $success = "Le projet a été mis à jour avec succès.";
    } elseif ($action_message === 'créé') {
        $success = "Le projet a été créé avec succès.";
    }
}

$stmt = $pdo->query("
    SELECT 
        p.id, p.name, p.objective, p.context, p.progress_percent, 
        p.start_date, p.estimated_end_date, p.created_at,
        d.name AS manager_name
    FROM projects p
    LEFT JOIN directory d ON p.manager_id = d.id
    WHERE p.archived = FALSE
    ORDER BY p.estimated_end_date ASC, p.created_at DESC
");
$projects = $stmt->fetchAll();

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('projects/list.html.twig', [
    'projects' => $projects,
    'success' => $success,
    'active_page' => 'projects'
]);