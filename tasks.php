<?php
// public/tasks.php (modifié pour gérer les messages de succès spécifiques)
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
                        assignee_id = ?, project_id = ?, due_date = ?, archived = ?
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
                    !empty($_POST['archived']) ? 1 : 0,
                    $task_id
                ]);
                $success_message = "mise à jour";
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
                $success_message = "créée";
            }
            // Stocker le message dans la session ou passer via GET
            header('Location: tasks.php?success=' . $success_message);
            exit;
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

// Action: list (par défaut)
if (isset($_GET['success'])) {
    $action_message = $_GET['success'];
    if ($action_message === 'mise à jour') {
        $success = "La tâche a été mise à jour avec succès.";
    } elseif ($action_message === 'créée') {
        $success = "La tâche a été créée avec succès.";
    }
}

$stmt = $pdo->query("
    SELECT 
        t.id, t.title, t.description, t.category, t.status, t.progress_percent, 
        t.due_date, t.created_at,
        d.name AS assignee_name,
        p.name AS project_name
    FROM tasks t
    LEFT JOIN directory d ON t.assignee_id = d.id
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE t.archived = FALSE
    ORDER BY t.due_date ASC, t.created_at DESC
");
$tasks = $stmt->fetchAll();

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('tasks/list.html.twig', [
    'tasks' => $tasks,
    'success' => $success,
    'active_page' => 'tasks'
]);