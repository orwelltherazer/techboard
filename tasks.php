<?php
// public/tasks.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données");
}

$action = $_GET['action'] ?? 'list';
$task_id = $_GET['id'] ?? null;
$error = null;
$success = null;

// Charger les données communes
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();
$contacts = $pdo->query("SELECT id, name, company FROM directory ORDER BY name")->fetchAll();

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'archive':
            $stmt = $pdo->prepare("UPDATE tasks SET archived = 1 WHERE id = ?");
            $stmt->execute([$task_id]);
            echo json_encode(['success' => true]);
            exit;

        case 'unarchive':
            $stmt = $pdo->prepare("UPDATE tasks SET archived = 0 WHERE id = ?");
            $stmt->execute([$task_id]);
            echo json_encode(['success' => true]);
            exit;

        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            echo json_encode(['success' => true]);
            exit;
    }
}

// === GESTION DES LOGS (AJAX) ===
if ($action === 'add_log' && $_SERVER['REQUEST_METHOD'] === 'POST' && $task_id) {
    header('Content-Type: application/json');
    $log_date = $_POST['log_date'] ?? date('Y-m-d H:i:s');
    $content = trim($_POST['content'] ?? '');
    
    if (!empty($content)) {
        $stmt = $pdo->prepare("INSERT INTO task_logs (task_id, log_date, content) VALUES (?, ?, ?)");
        $stmt->execute([$task_id, $log_date, $content]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Contenu vide']);
    }
    exit;
}

if ($action === 'edit_log' && $_SERVER['REQUEST_METHOD'] === 'POST' && $task_id) {
    header('Content-Type: application/json');
    $log_id = $_POST['log_id'] ?? null;
    $log_date = $_POST['log_date'] ?? date('Y-m-d H:i:s');
    $content = trim($_POST['content'] ?? '');
    
    if ($log_id && !empty($content)) {
        $stmt = $pdo->prepare("UPDATE task_logs SET log_date = ?, content = ? WHERE id = ? AND task_id = ?");
        $stmt->execute([$log_date, $content, $log_id, $task_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Données invalides']);
    }
    exit;
}

if ($action === 'delete_log' && $task_id) {
    header('Content-Type: application/json');
    $log_id = $_GET['log_id'] ?? null;
    if ($log_id) {
        $stmt = $pdo->prepare("DELETE FROM task_logs WHERE id = ? AND task_id = ?");
        $stmt->execute([$log_id, $task_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

if ($action === 'get_log' && $task_id) {
    header('Content-Type: application/json');
    $log_id = $_GET['log_id'] ?? null;
    if ($log_id) {
        $stmt = $pdo->prepare("SELECT * FROM task_logs WHERE id = ? AND task_id = ?");
        $stmt->execute([$log_id, $task_id]);
        $log = $stmt->fetch();
        echo json_encode($log ?: ['success' => false]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// === VUE DÉTAILLÉE D'UNE TÂCHE ===
if ($action === 'view' && $task_id) {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               d.name AS assignee_name, 
               d.company AS assignee_company,
               p.name AS project_name
        FROM tasks t
        LEFT JOIN directory d ON t.assignee_id = d.id
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        header('Location: tasks.php');
        exit;
    }
    
    // Charger l'historique
    $stmt = $pdo->prepare("SELECT * FROM task_logs WHERE task_id = ? ORDER BY log_date DESC");
    $stmt->execute([$task_id]);
    $logs = $stmt->fetchAll();
    
    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader);
    
    echo $twig->render('tasks/view.html.twig', [
        'task' => $task,
        'logs' => $logs,
        'active_page' => 'tasks'
    ]);
    exit;
}

// === FORMULAIRE ADD/EDIT ===
if ($action === 'add' || $action === 'edit') {
    if ($action === 'edit' && $task_id) {
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch();
        if (!$task) {
            header('Location: tasks.php');
            exit;
        }
    } else {
        $task = null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $progress = $_POST['progress_percent'] ?? '0';
        
        if (empty($title)) {
            $error = "Le titre est obligatoire";
        } elseif (!is_numeric($progress) || $progress < 0 || $progress > 100) {
            $error = "L'avancement doit être entre 0 et 100";
        } else {
            if ($action === 'edit') {
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET title = ?, description = ?, category = ?, status = ?, progress_percent = ?, 
                        assignee_id = ?, project_id = ?, due_date = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $title,
                    $_POST['description'] ?? '',
                    $_POST['category'] ?? 'it',
                    $_POST['status'] ?? 'not_started',
                    (int)$progress,
                    !empty($_POST['assignee_id']) ? (int)$_POST['assignee_id'] : null,
                    !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
                    !empty($_POST['due_date']) ? $_POST['due_date'] : null,
                    $task_id
                ]);
                header('Location: tasks.php?action=view&id=' . $task_id);
                exit;
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO tasks (title, description, category, status, progress_percent, assignee_id, project_id, due_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $title,
                    $_POST['description'] ?? '',
                    $_POST['category'] ?? 'it',
                    $_POST['status'] ?? 'not_started',
                    (int)$progress,
                    !empty($_POST['assignee_id']) ? (int)$_POST['assignee_id'] : null,
                    !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
                    !empty($_POST['due_date']) ? $_POST['due_date'] : null
                ]);
                $new_id = $pdo->lastInsertId();
                header('Location: tasks.php?action=view&id=' . $new_id);
                exit;
            }
        }
    }

    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader);
    
    echo $twig->render('tasks/form.html.twig', [
        'task' => $task,
        'projects' => $projects,
        'contacts' => $contacts,
        'error' => $error,
        'is_edit' => $action === 'edit',
        'active_page' => 'tasks'
    ]);
    exit;
}

// === LISTE (par défaut) ===
$show_archived = $_GET['archived'] ?? 0;

if (isset($_GET['success'])) {
    $action_message = $_GET['success'];
    if ($action_message === 'mise à jour') {
        $success = "La tâche a été mise à jour avec succès.";
    } elseif ($action_message === 'créée') {
        $success = "La tâche a été créée avec succès.";
    }
}

$stmt = $pdo->prepare("
    SELECT 
        t.id, t.title, t.description, t.category, t.status, t.progress_percent, 
        t.due_date, t.created_at,
        d.name AS assignee_name,
        p.name AS project_name
    FROM tasks t
    LEFT JOIN directory d ON t.assignee_id = d.id
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE t.archived = ?
    ORDER BY t.due_date ASC, t.created_at DESC
");
$stmt->execute([$show_archived]);
$tasks = $stmt->fetchAll();

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('tasks/list.html.twig', [
    'tasks' => $tasks,
    'success' => $success,
    'show_archived' => $show_archived,
    'active_page' => 'tasks'
]);
?>