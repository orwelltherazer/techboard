<?php
// public/index.php (dashboard)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données");
}

// Stats des tâches par statut
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM tasks 
    WHERE archived = FALSE
    GROUP BY status
");
$task_stats = [];
foreach ($stmt->fetchAll() as $row) {
    $task_stats[$row['status']] = $row['count'];
}

$in_progress_tasks = $task_stats['in_progress'] ?? 0;
$not_started_tasks = $task_stats['not_started'] ?? 0;

// Projets en cours (non terminés)
$stmt = $pdo->query("
    SELECT id, name, objective, progress_percent 
    FROM projects 
    WHERE archived = FALSE AND progress_percent < 100 
    ORDER BY created_at DESC 
    LIMIT 3
");
$recent_projects = $stmt->fetchAll();

// 3 dernières tâches
$stmt = $pdo->query("
    SELECT id, title, status, progress_percent, created_at 
    FROM tasks 
    WHERE archived = FALSE
    ORDER BY created_at DESC 
    LIMIT 3
");
$recent_tasks = $stmt->fetchAll();

// Communications à traiter
$stmt = $pdo->query("
    SELECT COUNT(*) as count 
    FROM communications 
    WHERE status = 'pending'
");
$pending_comms = $stmt->fetch()['count'];

// Commandes "commandées"
$stmt = $pdo->query("
    SELECT id, reference, description, order_date, vendor_id, amount
    FROM orders 
    WHERE status = 'ordered' AND archived = FALSE
    ORDER BY order_date DESC
    LIMIT 5
");
$ordered_orders = $stmt->fetchAll();

// Récupérer les noms des fournisseurs
if ($ordered_orders) {
    $vendor_ids = implode(',', array_column($ordered_orders, 'vendor_id'));
    $stmt = $pdo->query("SELECT id, name FROM directory WHERE id IN ($vendor_ids)");
    $vendors = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('dashboard.html.twig', [
    'title' => 'TechBoard - Dashboard',
    'in_progress_tasks' => $in_progress_tasks,
    'not_started_tasks' => $not_started_tasks,
    'recent_projects' => $recent_projects,
    'recent_tasks' => $recent_tasks,
    'pending_comms' => $pending_comms,
    'ordered_orders' => $ordered_orders,
    'vendors' => $vendors ?? []
]);