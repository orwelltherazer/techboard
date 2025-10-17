<?php
// upload.php - Gestionnaire générique d'upload de fichiers
require_once __DIR__ . '/config/config.php';

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']));
}

// Configuration
$allowed_types = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
    'image/jpeg',
    'image/png',
    'image/gif'
];

$max_file_size = 10 * 1024 * 1024; // 10 MB

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'error' => 'Méthode non autorisée']));
}

// Récupérer les paramètres
$entity_type = $_POST['entity_type'] ?? null;
$entity_id = $_POST['entity_id'] ?? null;
$description = $_POST['description'] ?? null;

// Validation
if (!$entity_type || !$entity_id) {
    die(json_encode(['success' => false, 'error' => 'Paramètres manquants']));
}

// Vérifier qu'un fichier a été uploadé
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $error_message = 'Erreur lors de l\'upload du fichier';
    if (isset($_FILES['file']['error'])) {
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_message = 'Le fichier est trop volumineux';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message = 'Aucun fichier n\'a été uploadé';
                break;
        }
    }
    die(json_encode(['success' => false, 'error' => $error_message]));
}

$file = $_FILES['file'];
$original_filename = basename($file['name']);
$file_size = $file['size'];
$mime_type = mime_content_type($file['tmp_name']);

// Vérifier le type de fichier
if (!in_array($mime_type, $allowed_types)) {
    die(json_encode(['success' => false, 'error' => 'Type de fichier non autorisé']));
}

// Vérifier la taille
if ($file_size > $max_file_size) {
    die(json_encode(['success' => false, 'error' => 'Le fichier est trop volumineux (max 10 MB)']));
}

// Générer un nom de fichier unique
$extension = pathinfo($original_filename, PATHINFO_EXTENSION);
$filename = uniqid() . '_' . time() . '.' . $extension;

// Déterminer le dossier de destination
$upload_dir = __DIR__ . '/uploads/' . $entity_type . 's/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$file_path = $upload_dir . $filename;
$relative_path = 'uploads/' . $entity_type . 's/' . $filename;

// Déplacer le fichier
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    die(json_encode(['success' => false, 'error' => 'Erreur lors de l\'enregistrement du fichier']));
}

// Enregistrer en base de données
try {
    $stmt = $pdo->prepare("
        INSERT INTO attachments (entity_type, entity_id, filename, original_filename, file_path, file_size, mime_type, description, uploaded_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $entity_type,
        $entity_id,
        $filename,
        $original_filename,
        $relative_path,
        $file_size,
        $mime_type,
        $description
    ]);

    $attachment_id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'attachment_id' => $attachment_id,
        'filename' => $original_filename,
        'file_size' => $file_size
    ]);

} catch (PDOException $e) {
    // Supprimer le fichier si l'insertion en base échoue
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    die(json_encode(['success' => false, 'error' => 'Erreur lors de l\'enregistrement en base de données']));
}
?>
