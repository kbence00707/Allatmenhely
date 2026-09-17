<?php
/**
 * A very small router: it connects a URL + HTTP method to a controller function.
 *
 * Example: $router->add('GET', '/animals/{id}', [$animalController, 'show']);
 * The {id} part must be a number and is passed to the function as an int.
 */
class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler, bool $staffOnly = false): void
    {
        // Turn "/animals/{id}" into the regular expression "#^/animals/(\d+)$#"
        $regex = '#^' . preg_replace('#\{\w+\}#', '(\d+)', $pattern) . '$#';

        $this->routes[] = [
            'method'    => $method,
            'regex'     => $regex,
            'handler'   => $handler,
            'staffOnly' => $staffOnly,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $pathExists = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $pathExists = true;

            if ($route['method'] !== $method) {
                continue;
            }

            if ($route['staffOnly']) {
                Auth::requireStaff();
            }

            $params = array_map('intval', array_slice($matches, 1));
            ($route['handler'])(...$params);
            return;
        }

        if ($pathExists) {
            throw new HttpException(405, 'Ez a HTTP metódus nem engedélyezett ezen a címen.');
        }
        throw new HttpException(404, 'Nincs ilyen API végpont.');
    }
}
