<?php
declare(strict_types=1);

/**
 * Global Helper Functions
 */

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed {
        static $config = null;

        if ($config === null) {
            $config = new App\Core\Config();
        }

        return $config->get($key, $default);
    }
}

if (!function_exists('app')) {
    function app(?string $abstract = null): mixed {
        static $container = null;

        if ($container === null) {
            $container = new App\Core\Container();
        }

        if ($abstract === null) {
            return $container;
        }

        return $container->get($abstract);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string {
        return BASE_PATH . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string {
        return STORAGE_PATH . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

if (!function_exists('view')) {
    function view(string $view, array $data = []): string {
        return App\Core\View::render($view, $data);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $code = 302): App\Core\Response {
        return (new App\Core\Response())->redirect($url, $code);
    }
}

if (!function_exists('json_response')) {
    function json_response(array $data, int $code = 200): App\Core\Response {
        return (new App\Core\Response())->json($data, $code);
    }
}

if (!function_exists('e')) {
    function e(?string $value): string {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="' . env('CSRF_TOKEN_NAME', 'csrf_token') . '" value="' . csrf_token() . '">';
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = null): mixed {
        return $_SESSION['old'][$key] ?? $default;
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $value = null): mixed {
        if ($value === null) {
            $result = $_SESSION['flash'][$key] ?? null;
            unset($_SESSION['flash'][$key]);
            return $result;
        }

        $_SESSION['flash'][$key] = $value;
        return null;
    }
}

if (!function_exists('auth')) {
    function auth(): ?App\Models\User {
        return app(App\Services\AuthService::class)->user();
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool {
        $user = auth();
        return $user ? $user->hasPermission($permission) : false;
    }
}

if (!function_exists('has_role')) {
    function has_role(string $role): bool {
        $user = auth();
        return $user ? $user->hasRole($role) : false;
    }
}

if (!function_exists('sanitize_input')) {
    function sanitize_input(mixed $input): mixed {
        if (is_array($input)) {
            return array_map('sanitize_input', $input);
        }

        if (is_string($input)) {
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }

        return $input;
    }
}

if (!function_exists('format_currency')) {
    function format_currency(float $amount, string $currency = 'USD'): string {
        return '$' . number_format($amount, 2);
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date, string $format = 'Y-m-d'): string {
        if (!$date) return '';
        return date($format, strtotime($date));
    }
}

if (!function_exists('calculate_mileage_deduction')) {
    function calculate_mileage_deduction(float $miles): float {
        return $miles * (float)env('MILEAGE_RATE', 0.655);
    }
}

if (!function_exists('encrypt_string')) {
    function encrypt_string(string $data): string {
        $key = env('APP_KEY');
        $cipher = env('ENCRYPTION_CIPHER', 'AES-256-CBC');
        $iv = random_bytes(openssl_cipher_iv_length($cipher));
        $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
}

if (!function_exists('decrypt_string')) {
    function decrypt_string(string $data): string {
        $key = env('APP_KEY');
        $cipher = env('ENCRYPTION_CIPHER', 'AES-256-CBC');
        $data = base64_decode($data);
        $ivLength = openssl_cipher_iv_length($cipher);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        return openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
    }
}

if (!function_exists('generate_app_key')) {
    function generate_app_key(): string {
        return 'base64:' . base64_encode(random_bytes(32));
    }
}

if (!function_exists('logger')) {
    function logger(): App\Core\Logger {
        return app(App\Core\Logger::class);
    }
}

if (!function_exists('now')) {
    function now(string $format = 'Y-m-d H:i:s'): string {
        return date($format);
    }
}

if (!function_exists('uuid')) {
    function uuid(): string {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
