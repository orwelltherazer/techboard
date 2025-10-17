-- Création de la table attachments pour gérer les pièces jointes
-- Cette table est générique et peut être utilisée pour n'importe quelle entité

CREATE TABLE IF NOT EXISTS attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL COMMENT 'Type d''entité (contract, task, project, etc.)',
    entity_id INT NOT NULL COMMENT 'ID de l''entité liée',
    filename VARCHAR(255) NOT NULL COMMENT 'Nom du fichier',
    original_filename VARCHAR(255) NOT NULL COMMENT 'Nom original du fichier',
    file_path VARCHAR(500) NOT NULL COMMENT 'Chemin du fichier sur le serveur',
    file_size INT NOT NULL COMMENT 'Taille du fichier en octets',
    mime_type VARCHAR(100) NOT NULL COMMENT 'Type MIME du fichier',
    uploaded_by VARCHAR(100) DEFAULT NULL COMMENT 'Utilisateur qui a uploadé',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date d''upload',
    description TEXT DEFAULT NULL COMMENT 'Description optionnelle',

    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exemples d'utilisation :
-- Pour un contrat : entity_type = 'contract', entity_id = l'ID du contrat
-- Pour une tâche : entity_type = 'task', entity_id = l'ID de la tâche
-- Pour un projet : entity_type = 'project', entity_id = l'ID du projet
