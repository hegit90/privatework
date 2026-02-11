<?php
declare(strict_types=1);

/**
 * Financial Management System
 * Front Controller
 */

// Security: Prevent Direct File Access Bypass
define('APP_START', true);

// Define Path Constants
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('CONFIG_PATH', APP_PATH . '/Config');
define('VIEW_PATH', APP_PATH . '/Views');

// Composer Autoloader
require_once BASE_PATH . '/vendor/autoload.php';

// Load Environment Variables
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();

// Error Handling
if (env('APP_DEBUG', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Set Timezone
date_default_timezone_set(env('APP_TIMEZONE', 'UTC'));

// Session Configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', env('SESSION_SECURE', '1'));
ini_set('session.cookie_samesite', env('SESSION_SAME_SITE', 'Strict'));
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_save_path(STORAGE_PATH . '/sessions');
    session_start();
}

// Initialize Application
try {
    $container = new App\Core\Container();

    // Register Core Services
    $container->singleton(App\Core\Config::class, function() {
        return new App\Core\Config();
    });

    $container->singleton(App\Core\Database::class, function($c) {
        return new App\Core\Database($c->get(App\Core\Config::class));
    });

    $container->singleton(App\Core\Router::class, function() {
        return new App\Core\Router();
    });

    $container->singleton(App\Core\Request::class, function() {
        return new App\Core\Request();
    });

    $container->singleton(App\Core\Response::class, function() {
        return new App\Core\Response();
    });

    $container->singleton(App\Services\AuthService::class, function($c) {
        return new App\Services\AuthService(
            $c->get(App\Repositories\UserRepository::class)
        );
    });

    // Security Middleware
    $container->singleton(App\Middleware\CsrfMiddleware::class);
    $container->singleton(App\Middleware\RateLimitMiddleware::class);
    $container->singleton(App\Middleware\AuthMiddleware::class);

    // Check if installed
    if (!env('INSTALLED', false) && !str_contains($_SERVER['REQUEST_URI'], '/install')) {
        header('Location: /install');
        exit;
    }

    // Load Routes
    $router = $container->get(App\Core\Router::class);
    require_once BASE_PATH . '/app/Config/routes.php';

    // Dispatch Request
    $request = $container->get(App\Core\Request::class);
    $response = $router->dispatch($request, $container);

    // Send Response
    $response->send();

} catch (Throwable $e) {
    // Log Error
    error_log(sprintf(
        "[%s] %s in %s:%d\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));

    // Display Error Page
    if (env('APP_DEBUG', false)) {
        echo '<h1>Application Error</h1>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        http_response_code(500);
        echo '<h1>500 - Internal Server Error</h1>';
        echo '<p>An error occurred. Please try again later.</p>';
    }
    exit(1);
}
