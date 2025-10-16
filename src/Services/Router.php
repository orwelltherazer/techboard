<?php
namespace App\Services;

class Router {
    private $routes = [];

    public function add($pattern, $controller) {
        $this->routes[$pattern] = $controller;
    }

    public function dispatch($uri) {
        foreach ($this->routes as $pattern => $controller) {
            if (preg_match('#^' . $pattern . '$#', $uri, $matches)) {
                array_shift($matches);
                list($controllerName, $method) = explode('@', $controller);
                $controllerClass = "App\\Controllers\\$controllerName";
                $controllerInstance = new $controllerClass();
                call_user_func_array([$controllerInstance, $method], $matches);
                return;
            }
        }
        http_response_code(404);
        echo "Page non trouvÃ©e";
    }
}
