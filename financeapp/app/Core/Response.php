<?php
declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Response Handler
 */
class Response
{
    private string $content = '';
    private int $statusCode = 200;
    private array $headers = [];

    /**
     * Set response content
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Set status code
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Set header
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * JSON response
     */
    public function json(array $data, int $code = 200): self
    {
        $this->setStatusCode($code);
        $this->setHeader('Content-Type', 'application/json');
        $this->setContent(json_encode($data));
        return $this;
    }

    /**
     * Redirect response
     */
    public function redirect(string $url, int $code = 302): self
    {
        $this->setStatusCode($code);
        $this->setHeader('Location', $url);
        return $this;
    }

    /**
     * View response
     */
    public function view(string $view, array $data = []): self
    {
        $this->setContent(View::render($view, $data));
        return $this;
    }

    /**
     * Send response
     */
    public function send(): void
    {
        // Send status code
        http_response_code($this->statusCode);

        // Send headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Send content
        echo $this->content;
    }

    /**
     * Download response
     */
    public function download(string $filePath, ?string $filename = null): self
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        $filename = $filename ?? basename($filePath);

        $this->setHeader('Content-Type', 'application/octet-stream');
        $this->setHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
        $this->setHeader('Content-Length', (string)filesize($filePath));
        $this->setContent(file_get_contents($filePath));

        return $this;
    }
}
