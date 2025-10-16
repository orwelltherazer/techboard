<?php
// public/notes.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données");
}

$action = $_GET['action'] ?? 'list';
$note_id = $_GET['id'] ?? null;
$error = null;
$success = null;

// Charger les projets et tâches pour les liens
$projects = $pdo->query("SELECT id, name FROM projects WHERE archived = FALSE ORDER BY name")->fetchAll();
$tasks = $pdo->query("SELECT id, title FROM tasks WHERE archived = FALSE ORDER BY title")->fetchAll();

if ($action === 'add' || $action === 'edit') {
    if ($action === 'edit' && $note_id) {
        $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ?");
        $stmt->execute([$note_id]);
        $note = $stmt->fetch();
        if (!$note) {
            header('Location: notes.php');
            exit;
        }
    } else {
        $note = null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        
        if (empty($title) || empty($content)) {
            $error = "Le titre et le contenu sont obligatoires";
        } else {
            if ($action === 'edit') {
                $stmt = $pdo->prepare("
                    UPDATE notes 
                    SET title = ?, content = ?, category = ?, 
                        related_task_id = ?, related_project_id = ?, archived = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $title,
                    $content,
                    $_POST['category'] ?? null,
                    !empty($_POST['related_task_id']) ? (int)$_POST['related_task_id'] : null,
                    !empty($_POST['related_project_id']) ? (int)$_POST['related_project_id'] : null,
                    !empty($_POST['archived']) ? 1 : 0,
                    $note_id
                ]);
                $success_message = "mise à jour";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO notes (title, content, category, related_task_id, related_project_id)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $title,
                    $content,
                    $_POST['category'] ?? null,
                    !empty($_POST['related_task_id']) ? (int)$_POST['related_task_id'] : null,
                    !empty($_POST['related_project_id']) ? (int)$_POST['related_project_id'] : null
                ]);
                $success_message = "créée";
            }
            header('Location: notes.php?success=' . $success_message);
            exit;
        }
    }

    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader);
    
    echo $twig->render('notes/form.html.twig', [
        'note' => $note,
        'projects' => $projects,
        'tasks' => $tasks,
        'error' => $error,
        'is_edit' => $action === 'edit',
        'active_page' => 'notes'
    ]);
    exit;
}

// Action: list (par défaut)
if (isset($_GET['success'])) {
    $action_message = $_GET['success'];
    if ($action_message === 'mise à jour') {
        $success = "La note a été mise à jour avec succès.";
    } elseif ($action_message === 'créée') {
        $success = "La note a été créée avec succès.";
    }
}

$stmt = $pdo->query("
    SELECT 
        n.id, n.title, n.content, n.category, n.created_at, n.updated_at,
        t.title AS related_task_title,
        p.name AS related_project_name
    FROM notes n
    LEFT JOIN tasks t ON n.related_task_id = t.id AND t.archived = FALSE
    LEFT JOIN projects p ON n.related_project_id = p.id AND p.archived = FALSE
    WHERE n.archived = FALSE
    ORDER BY n.created_at DESC
");
$notes = $stmt->fetchAll();

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('notes/list.html.twig', [
    'notes' => $notes,
    'success' => $success,
    'active_page' => 'notes'
]);