<?php
// get_attachment.php - Récupérer l'ID de la première pièce jointe d'une entité
require_once __DIR__ . '/config/config.php';

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']));
}

$contract_id = $_GET['contract_id'] ?? null;

if (!$contract_id) {
    die(json_encode(['success' => false, 'error' => 'ID manquant']));
}

// Récupérer la première pièce jointe du contrat
$stmt = $pdo->prepare("SELECT id FROM attachments WHERE entity_type = 'contract' AND entity_id = ? LIMIT 1");
$stmt->execute([$contract_id]);
$attachment = $stmt->fetch();

if ($attachment) {
    echo json_encode(['success' => true, 'attachment_id' => $attachment['id']]);
} else {
    echo json_encode(['success' => false, 'error' => 'Aucune pièce jointe trouvée']);
}
?>
