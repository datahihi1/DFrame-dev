<?php

use DFrame\Application\Router;
use DFrame\Application\View;
use DFrame\Application\Session;

if (!function_exists('old')) {
    /**
     * Get old input value from the previous request.
     * @param string $key
     * @param mixed $default
     * @return mixed|null
     */
    function old(string $key, $default = null)
    {
        if (isset($GLOBALS['old'][$key])) {
            return $GLOBALS['old'][$key];
        }
        if (isset($GLOBALS['old']) && is_array($GLOBALS['old']) && array_key_exists($key, $GLOBALS['old'])) {
            return $GLOBALS['old'][$key];
        }
        return $default;
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect helper:
     * - redirect()->route('name') : Redirect to a named route.
     * - redirect($url) : Redirect to a specific URL.
     * @param string|null $url URL to redirect to.
     * @return object|void
     */
    function redirect(?string $url = null)
    {
        if ($url !== null) {
            header('Location: ' . $url);
            exit;
        }
        return new class {
            /**
             * Redirect to a named route.
             */
            public function route($name, $params = [])
            {
                $url = route($name, $params);
                header('Location: ' . $url);
                exit;
            }
            /**
             * Redirect to a specific URL.
             */
            public function to($url)
            {
                header('Location: ' . $url);
                exit;
            }
        };
    }
}

if (!function_exists('route')) {
    /**
     * Generate a URL for a named route.
     *
     * @param string $name The route name.
     * @param array $params The route parameters.
     * @return string|null The generated URL or null if the route does not exist.
     */
    function route(string $name, array $params = []): ?string
    {
        return Router::route($name, $params);
    }
}

if (!function_exists('session')) {
    /**
     * Helper function for session get/set.
     * - session(): get all session variables.
     * - session($key): get session value.
     * - session($key, $value): set session value.
     *
     * @param string|null $key The session key.
     * @param mixed|null $value The session value.
     * @return mixed|null
     */
    function session(?string $key = null, $value = null)
    {

        if (is_null($key) && is_null($value)) {
            return $_SESSION;
        }

        if (!is_null($key) && is_null($value)) {
            return Session::get($key);
        }

        if (!is_null($key) && !is_null($value)) {
            Session::set($key, $value);
            return null;
        }

        return null;
    }
}

if (!function_exists("flash")) {
    /**
     * Flash data to session.
     * @param string $key The session flash key.
     * @param mixed $value The session flash value.
     * @return void
     */
    function flash(string $key, $value): void
    {
        Session::flash($key, $value);
    }
}

if (!function_exists('getFlash')) {
    /**
     * Get flash data from session.
     * @param string $key
     * @return mixed|null
     */
    function getFlash(string $key)
    {
        return Session::getFlash($key);
    }
}

if (!function_exists('getBaseUrl')) {
    /**
     * Get the base URL of the application.
     *
     * @return string
     */
    function getBaseUrl(): string
    {
        $scheme =
            ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null)
            ?: ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http");

        $host = $_SERVER['HTTP_X_FORWARDED_HOST']
            ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        return $scheme . '://' . $host . ($basePath === '' ? '/' : $basePath . '/');
    }

}

if (!function_exists('csrf_field')) {
    /**
     * Generate a hidden input field with CSRF token.
     * @return string
     */
    function csrf_field(): string
    {
        $token = TokenGenerator::csrf_generate();
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('view')) {
    /**
     * Render a view file with optional data.
     * @param string $view The view file name (without .php).
     * @param array $data The data to pass to the view.
     * @return string The rendered view content.
     * @throws Exception If the view file does not exist.
     */
    function view(string $view, array $data = []): string
    {
        return View::render($view, $data);
    }
}

if (!function_exists('df_config')) {
    /**
     * DFrame configuration helper.
     *
     * - df_config()               → get all config
     * - df_config('app.name')     → get specific config value
     * @param string|null $name The config key (dot notation for nested).
     * @return mixed|null The config value or null if not found.
     */
    function df_config(?string $key = null)
    {
        static $configs = null;

        if ($configs === null) {
            if (!defined('ROOT_DIR')) {
                throw new \Exception("ROOT_DIR is not defined.");
            }

            $configPath = rtrim(ROOT_DIR, '/\\') . '/config/';
            $configs = [];

            foreach (glob($configPath . '*.php') as $file) {
                $name = basename($file, '.php');
                $configs[$name] = include $file;
            }
        }

        if ($key === null) {
            return $configs;
        }

        $segments = explode('.', $key);
        $value = $configs;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
