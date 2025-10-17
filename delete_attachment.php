<?php
// delete_attachment.php - Suppression de pièces jointes
require_once __DIR__ . '/config/config.php';

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']));
}

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'error' => 'Méthode non autorisée']));
}

// Récupérer l'ID de la pièce jointe
$attachment_id = $_POST['id'] ?? null;

if (!$attachment_id) {
    die(json_encode(['success' => false, 'error' => 'ID manquant']));
}

// Récupérer les informations du fichier
$stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
$stmt->execute([$attachment_id]);
$attachment = $stmt->fetch();

if (!$attachment) {
    die(json_encode(['success' => false, 'error' => 'Pièce jointe introuvable']));
}

// Supprimer le fichier physique
$file_path = __DIR__ . '/' . $attachment['file_path'];
if (file_exists($file_path)) {
    unlink($file_path);
}

// Supprimer l'entrée en base de données
try {
    $stmt = $pdo->prepare("DELETE FROM attachments WHERE id = ?");
    $stmt->execute([$attachment_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression en base de données']);
}
?>
