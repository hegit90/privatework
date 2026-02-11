<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Configuration Manager
 */
class Config
{
    private array $config = [];

    public function __construct()
    {
        $this->loadConfigurations();
    }

    /**
     * Load all configuration files
     */
    private function loadConfigurations(): void
    {
        $this->config = [
            'app' => [
                'name' => env('APP_NAME', 'Financial Management System'),
                'env' => env('APP_ENV', 'production'),
                'debug' => env('APP_DEBUG', false),
                'url' => env('APP_URL', 'http://localhost'),
                'timezone' => env('APP_TIMEZONE', 'UTC'),
                'key' => env('APP_KEY'),
            ],
            'database' => [
                'host' => env('DB_HOST', 'localhost'),
                'port' => env('DB_PORT', 3306),
                'database' => env('DB_DATABASE', 'financeapp'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => env('DB_CHARSET', 'utf8mb4'),
                'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            ],
            'session' => [
                'lifetime' => env('SESSION_LIFETIME', 120),
                'secure' => env('SESSION_SECURE', true),
                'http_only' => env('SESSION_HTTP_ONLY', true),
                'same_site' => env('SESSION_SAME_SITE', 'strict'),
            ],
            'security' => [
                'csrf_token_name' => env('CSRF_TOKEN_NAME', 'csrf_token'),
                'password_min_length' => env('PASSWORD_MIN_LENGTH', 12),
                'password_require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
                'password_require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
                'password_require_number' => env('PASSWORD_REQUIRE_NUMBER', true),
                'password_require_special' => env('PASSWORD_REQUIRE_SPECIAL', true),
            ],
            'rate_limit' => [
                'enabled' => env('RATE_LIMIT_ENABLED', true),
                'max_attempts' => env('RATE_LIMIT_MAX_ATTEMPTS', 5),
                'decay_minutes' => env('RATE_LIMIT_DECAY_MINUTES', 15),
            ],
            'mail' => [
                'mailer' => env('MAIL_MAILER', 'smtp'),
                'host' => env('MAIL_HOST', 'smtp.mailtrap.io'),
                'port' => env('MAIL_PORT', 2525),
                'username' => env('MAIL_USERNAME'),
                'password' => env('MAIL_PASSWORD'),
                'encryption' => env('MAIL_ENCRYPTION', 'tls'),
                'from' => [
                    'address' => env('MAIL_FROM_ADDRESS', 'noreply@financeapp.com'),
                    'name' => env('MAIL_FROM_NAME', env('APP_NAME')),
                ],
            ],
            'upload' => [
                'max_size' => env('MAX_UPLOAD_SIZE', 10485760),
                'allowed_types' => explode(',', env('ALLOWED_FILE_TYPES', 'pdf,jpg,jpeg,png,doc,docx,xls,xlsx')),
            ],
            'backup' => [
                'enabled' => env('BACKUP_ENABLED', true),
                'encryption' => env('BACKUP_ENCRYPTION', true),
                'retention_days' => env('BACKUP_RETENTION_DAYS', 30),
            ],
            'tax' => [
                'default_country' => env('DEFAULT_COUNTRY', 'US'),
                'default_state' => env('DEFAULT_STATE', 'CA'),
                'mileage_rate' => env('MILEAGE_RATE', 0.655),
            ],
            'api' => [
                'enabled' => env('API_ENABLED', true),
                'rate_limit' => env('API_RATE_LIMIT', 60),
            ],
            'two_fa' => [
                'enabled' => env('TWO_FA_ENABLED', true),
                'issuer' => env('TWO_FA_ISSUER', env('APP_NAME')),
            ],
            'logging' => [
                'level' => env('LOG_LEVEL', 'info'),
                'channel' => env('LOG_CHANNEL', 'file'),
                'max_files' => env('LOG_MAX_FILES', 14),
            ],
        ];
    }

    /**
     * Get configuration value using dot notation
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set configuration value
     */
    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    /**
     * Check if configuration key exists
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Get all configuration
     */
    public function all(): array
    {
        return $this->config;
    }
}
