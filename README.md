# TechBoard

Application de gestion pour technicien de maintenance informatique et communication.

## Installation

1. Installer les dependances :
```
composer install
```

2. Configurer la base de donnees dans config/database.php

3. Importer la base de donnees :
```
mysql -u root -p < database/migrations/001_create_tables.sql
mysql -u root -p < database/seeds/seed_data.sql
```

4. Lancer le serveur :
```
php -S localhost:8000 -t public
```

## Structure

- config/ : Configuration
- src/ : Code PHP (Controllers, Models, Services)
- 	emplates/ : Vues Twig
- public/ : Assets publics (CSS, JS, images)
- database/ : Scripts SQL

## Technologies

- PHP 7.4+
- MySQL
- Twig 3
- CSS3 / JavaScript
