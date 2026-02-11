<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Template View Renderer
 */
class View
{
    private static array $sharedData = [];

    /**
     * Render a view
     */
    public static function render(string $view, array $data = []): string
    {
        $viewPath = self::getViewPath($view);

        if (!file_exists($viewPath)) {
            throw new \Exception("View not found: {$view}");
        }

        $data = array_merge(self::$sharedData, $data);

        extract($data);

        ob_start();
        include $viewPath;
        return ob_get_clean();
    }

    /**
     * Share data across all views
     */
    public static function share(string $key, mixed $value): void
    {
        self::$sharedData[$key] = $value;
    }

    /**
     * Get view file path
     */
    private static function getViewPath(string $view): string
    {
        $view = str_replace('.', '/', $view);
        return VIEW_PATH . '/' . $view . '.php';
    }

    /**
     * Check if view exists
     */
    public static function exists(string $view): bool
    {
        return file_exists(self::getViewPath($view));
    }
}
