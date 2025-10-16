<?php
namespace App\Services;

use Twig\Loader\FilesystemLoader;
use Twig\Environment;

class TwigService {
    private static $instance = null;
    private $twig;

    private function __construct() {
        $loader = new FilesystemLoader(TEMPLATES_PATH);
        $this->twig = new Environment($loader, [
            'cache' => TWIG_CACHE ? __DIR__ . '/../../cache' : false,
            'debug' => TWIG_DEBUG,
        ]);
        
        // Ajouter des variables globales
        $this->twig->addGlobal('app_name', APP_NAME);
        $this->twig->addGlobal('base_url', BASE_URL);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function render($template, $data = []) {
        return $this->twig->render($template, $data);
    }
}
