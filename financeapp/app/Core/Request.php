<?php
declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Request Handler
 */
class Request
{
    private array $query;
    private array $post;
    private array $server;
    private array $files;
    private array $cookies;
    private string $method;
    private string $uri;
    private array $headers;

    public function __construct()
    {
        $this->query = $_GET ?? [];
        $this->post = $_POST ?? [];
        $this->server = $_SERVER ?? [];
        $this->files = $_FILES ?? [];
        $this->cookies = $_COOKIE ?? [];
        $this->method = $this->server['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $this->parseUri();
        $this->headers = $this->parseHeaders();
    }

    /**
     * Parse request URI
     */
    private function parseUri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH);
        return rtrim($uri, '/') ?: '/';
    }

    /**
     * Parse request headers
     */
    private function parseHeaders(): array
    {
        $headers = [];

        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    /**
     * Get request method
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Get request URI
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Check if method matches
     */
    public function isMethod(string $method): bool
    {
        return strtoupper($this->method) === strtoupper($method);
    }

    /**
     * Get query parameter
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * Get POST data
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }

        return $this->post[$key] ?? $default;
    }

    /**
     * Get input (from both GET and POST)
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $input = array_merge($this->query, $this->post);

        if ($key === null) {
            return $input;
        }

        return $input[$key] ?? $default;
    }

    /**
     * Get all input data
     */
    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    /**
     * Get only specified keys
     */
    public function only(array $keys): array
    {
        $input = $this->all();
        return array_intersect_key($input, array_flip($keys));
    }

    /**
     * Get except specified keys
     */
    public function except(array $keys): array
    {
        $input = $this->all();
        return array_diff_key($input, array_flip($keys));
    }

    /**
     * Check if input key exists
     */
    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    /**
     * Get uploaded file
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Get header
     */
    public function header(string $key, mixed $default = null): mixed
    {
        $key = strtoupper(str_replace('-', '_', $key));
        return $this->headers[$key] ?? $default;
    }

    /**
     * Get cookie
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Get server variable
     */
    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    /**
     * Get client IP address
     */
    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get user agent
     */
    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Check if request is AJAX
     */
    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Check if request is JSON
     */
    public function isJson(): bool
    {
        return str_contains($this->header('Content-Type', ''), 'application/json');
    }

    /**
     * Check if request expects JSON
     */
    public function expectsJson(): bool
    {
        return $this->isAjax() || $this->isJson();
    }

    /**
     * Get JSON body
     */
    public function json(): mixed
    {
        $body = file_get_contents('php://input');
        return json_decode($body, true);
    }

    /**
     * Validate input
     */
    public function validate(array $rules): array
    {
        $validator = new Validator($this->all(), $rules);

        if ($validator->fails()) {
            $errors = $validator->errors();

            if ($this->expectsJson()) {
                throw new \Exception(json_encode(['errors' => $errors]), 422);
            }

            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $this->except(['password', 'password_confirmation']);
            header('Location: ' . $this->server['HTTP_REFERER'] ?? '/');
            exit;
        }

        return $validator->validated();
    }
}
