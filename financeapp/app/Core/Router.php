<?php
declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Router
 */
class Router
{
    private array $routes = [];
    private array $middleware = [];
    private string $prefix = '';
    private array $groupMiddleware = [];

    /**
     * Register GET route
     */
    public function get(string $uri, callable|array|string $action): Route
    {
        return $this->addRoute('GET', $uri, $action);
    }

    /**
     * Register POST route
     */
    public function post(string $uri, callable|array|string $action): Route
    {
        return $this->addRoute('POST', $uri, $action);
    }

    /**
     * Register PUT route
     */
    public function put(string $uri, callable|array|string $action): Route
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    /**
     * Register DELETE route
     */
    public function delete(string $uri, callable|array|string $action): Route
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    /**
     * Register route for any method
     */
    public function any(string $uri, callable|array|string $action): Route
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'DELETE'], $uri, $action);
    }

    /**
     * Group routes
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;

        if (isset($attributes['prefix'])) {
            $this->prefix .= '/' . trim($attributes['prefix'], '/');
        }

        if (isset($attributes['middleware'])) {
            $this->groupMiddleware = array_merge(
                $this->groupMiddleware,
                (array)$attributes['middleware']
            );
        }

        $callback($this);

        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * Add route to collection
     */
    private function addRoute(array|string $methods, string $uri, callable|array|string $action): Route
    {
        $methods = (array)$methods;
        $uri = $this->prefix . '/' . trim($uri, '/');
        $uri = '/' . trim($uri, '/');

        $route = new Route($methods, $uri, $action);
        $route->middleware($this->groupMiddleware);

        foreach ($methods as $method) {
            $this->routes[$method][$uri] = $route;
        }

        return $route;
    }

    /**
     * Dispatch request to matched route
     */
    public function dispatch(Request $request, Container $container): Response
    {
        $method = $request->method();
        $uri = $request->uri();

        // Find matching route
        $route = $this->findRoute($method, $uri);

        if ($route === null) {
            return $this->notFound();
        }

        // Extract route parameters
        $params = $this->extractParams($route->getUri(), $uri);

        // Run middleware
        foreach ($route->getMiddleware() as $middlewareClass) {
            $middleware = $container->get($middlewareClass);
            $result = $middleware->handle($request);

            if ($result instanceof Response) {
                return $result;
            }
        }

        // Execute action
        $action = $route->getAction();

        if (is_callable($action)) {
            $response = $action($request, ...$params);
        } elseif (is_array($action)) {
            [$controller, $method] = $action;
            $controllerInstance = $container->get($controller);
            $response = $controllerInstance->$method($request, ...$params);
        } elseif (is_string($action) && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action);
            $controllerInstance = $container->get($controller);
            $response = $controllerInstance->$method($request, ...$params);
        } else {
            throw new \Exception("Invalid route action");
        }

        // Convert to Response object if needed
        if (!$response instanceof Response) {
            $responseObj = new Response();
            $responseObj->setContent((string)$response);
            return $responseObj;
        }

        return $response;
    }

    /**
     * Find matching route
     */
    private function findRoute(string $method, string $uri): ?Route
    {
        // Exact match
        if (isset($this->routes[$method][$uri])) {
            return $this->routes[$method][$uri];
        }

        // Pattern match
        foreach ($this->routes[$method] ?? [] as $routeUri => $route) {
            if ($this->matches($routeUri, $uri)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Check if URI matches route pattern
     */
    private function matches(string $routeUri, string $uri): bool
    {
        $pattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([^/]+)', $routeUri);
        $pattern = '#^' . $pattern . '$#';
        return preg_match($pattern, $uri) === 1;
    }

    /**
     * Extract route parameters
     */
    private function extractParams(string $routeUri, string $uri): array
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $routeUri, $paramNames);
        $pattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([^/]+)', $routeUri);
        $pattern = '#^' . $pattern . '$#';

        preg_match($pattern, $uri, $values);
        array_shift($values);

        $params = [];
        foreach ($paramNames[1] as $index => $name) {
            $params[$name] = $values[$index] ?? null;
        }

        return $params;
    }

    /**
     * 404 Not Found response
     */
    private function notFound(): Response
    {
        $response = new Response();
        $response->setStatusCode(404);
        $response->setContent('<h1>404 - Not Found</h1>');
        return $response;
    }
}

/**
 * Route Class
 */
class Route
{
    private array $methods;
    private string $uri;
    private mixed $action;
    private array $middleware = [];

    public function __construct(array $methods, string $uri, mixed $action)
    {
        $this->methods = $methods;
        $this->uri = $uri;
        $this->action = $action;
    }

    public function middleware(array|string $middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array)$middleware);
        return $this;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getAction(): mixed
    {
        return $this->action;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
