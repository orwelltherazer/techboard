<?php
// download.php - Gestionnaire de téléchargement/visualisation de fichiers
require_once __DIR__ . '/config/config.php';

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données");
}

// Récupérer l'ID de la pièce jointe
$attachment_id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? 'view'; // 'view' ou 'download'

if (!$attachment_id) {
    die("ID de pièce jointe manquant");
}

// Récupérer les informations du fichier
$stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
$stmt->execute([$attachment_id]);
$attachment = $stmt->fetch();

if (!$attachment) {
    die("Pièce jointe introuvable");
}

$file_path = __DIR__ . '/' . $attachment['file_path'];

// Vérifier que le fichier existe
if (!file_exists($file_path)) {
    die("Fichier introuvable sur le serveur");
}

// Définir les en-têtes HTTP
header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . $attachment['file_size']);

if ($action === 'download') {
    // Forcer le téléchargement
    header('Content-Disposition: attachment; filename="' . $attachment['original_filename'] . '"');
} else {
    // Afficher dans le navigateur (pour les PDFs notamment)
    header('Content-Disposition: inline; filename="' . $attachment['original_filename'] . '"');
}

// Empêcher la mise en cache
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Envoyer le fichier
readfile($file_path);
exit;
?>
