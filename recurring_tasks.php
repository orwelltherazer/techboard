<?php
// public/recurring_tasks.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données");
}

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Fonction utilitaire de redirection
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation de base pour les actions sensibles
    if (!in_array($action, ['add', 'edit', 'archive', 'unarchive', 'delete'], true)) {
        redirect('recurring_tasks.php');
    }

    // Récupération sécurisée des données
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $recurrence_pattern = $_POST['recurrence_pattern'] ?? '';
    $recurrence_interval = isset($_POST['recurrence_interval']) ? (int)$_POST['recurrence_interval'] : 1;
    $due_day_of_week = !empty($_POST['due_day_of_week']) ? (int)$_POST['due_day_of_week'] : null;
    $due_day_of_month = !empty($_POST['due_day_of_month']) ? (int)$_POST['due_day_of_month'] : null;

    // Validation des choix autorisés
    $allowed_categories = ['it', 'maintenance', 'communication', 'security', 'network', 'other'];
    $allowed_patterns = ['daily', 'weekly', 'monthly', 'yearly'];

    if ($action === 'add' || $action === 'edit') {
        if (empty($title) || !in_array($category, $allowed_categories, true) || !in_array($recurrence_pattern, $allowed_patterns, true)) {
            // Optionnel : message d'erreur, mais pour rester minimal, on redirige
            redirect('recurring_tasks.php');
        }
    }

    switch ($action) {
        case 'add':
            $stmt = $pdo->prepare("
                INSERT INTO recurring_tasks 
                (title, description, category, recurrence_pattern, recurrence_interval, 
                 due_day_of_week, due_day_of_month) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $description, $category, $recurrence_pattern, $recurrence_interval, $due_day_of_week, $due_day_of_month]);
            redirect('recurring_tasks.php');
            break;

        case 'edit':
            if (!$id) redirect('recurring_tasks.php');
            $stmt = $pdo->prepare("
                UPDATE recurring_tasks 
                SET title = ?, description = ?, category = ?, 
                    recurrence_pattern = ?, recurrence_interval = ?,
                    due_day_of_week = ?, due_day_of_month = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $category, $recurrence_pattern, $recurrence_interval, $due_day_of_week, $due_day_of_month, $id]);
            redirect('recurring_tasks.php?success=updated');
            break;

        case 'archive':
            if (!$id) exit(json_encode(['success' => false, 'error' => 'ID manquant']));
            $stmt = $pdo->prepare("UPDATE recurring_tasks SET archived = 1 WHERE id = ?");
            $stmt->execute([$id]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;

        case 'unarchive':
            if (!$id) exit(json_encode(['success' => false, 'error' => 'ID manquant']));
            $stmt = $pdo->prepare("UPDATE recurring_tasks SET archived = 0 WHERE id = ?");
            $stmt->execute([$id]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;

        case 'delete':
            if (!$id) exit(json_encode(['success' => false, 'error' => 'ID manquant']));
            $stmt = $pdo->prepare("DELETE FROM recurring_tasks WHERE id = ?");
            $stmt->execute([$id]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
    }
}

// Initialisation de Twig
$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

// Actions GET
switch ($action) {
    case 'list':
        $show_archived = (bool)($_GET['archived'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM recurring_tasks WHERE archived = ? ORDER BY category, title");
        $stmt->execute([$show_archived ? 1 : 0]);
        $tasks = $stmt->fetchAll();
        echo $twig->render('recurring_tasks/list.html.twig', [
            'tasks' => $tasks,
            'show_archived' => $show_archived
        ]);
        break;

    case 'add':
        echo $twig->render('recurring_tasks/form.html.twig', [
            'task' => null,
            'categories' => [
                'it' => 'Informatique',
                'maintenance' => 'Maintenance',
                'communication' => 'Communication',
                'security' => 'Sécurité',
                'network' => 'Réseau',
                'other' => 'Autre'
            ],
            'patterns' => [
                'daily' => 'Quotidien',
                'weekly' => 'Hebdomadaire',
                'monthly' => 'Mensuel',
                'yearly' => 'Annuel'
            ]
        ]);
        break;

    case 'edit':
        if (!$id) redirect('recurring_tasks.php');
        $stmt = $pdo->prepare("SELECT * FROM recurring_tasks WHERE id = ?");
        $stmt->execute([$id]);
        $task = $stmt->fetch();

        if (!$task) redirect('recurring_tasks.php');

        echo $twig->render('recurring_tasks/form.html.twig', [
            'task' => $task,
            'categories' => [
                'it' => 'Informatique',
                'maintenance' => 'Maintenance',
                'communication' => 'Communication',
                'security' => 'Sécurité',
                'network' => 'Réseau',
                'other' => 'Autre'
            ],
            'patterns' => [
                'daily' => 'Quotidien',
                'weekly' => 'Hebdomadaire',
                'monthly' => 'Mensuel',
                'yearly' => 'Annuel'
            ]
        ]);
        break;

    default:
        redirect('recurring_tasks.php');
}