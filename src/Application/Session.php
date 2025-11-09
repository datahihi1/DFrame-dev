<?php

namespace DFrame\Application;

/**
 * #### Session management class
 *
 * Handles starting sessions, getting/setting variables, flash messages,
 * error/success messages, and destroying sessions safely.
 */
class Session
{
    /**
     * Start the session if not already started
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Get a session variable
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a session variable
     *
     * @param string $key
     * @param mixed $value
     */
    public static function set(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Flash a session variable (one-time)
     *
     * @param string $key
     * @param mixed $value
     */
    public static function flash(string $key, $value): void
    {
        self::start();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Get a flash variable and remove it
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getFlash(string $key, $default = null)
    {
        self::start();
        if (isset($_SESSION['_flash'][$key])) {
            $value = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $value;
        }
        return $default;
    }

    /**
     * Set an error message
     *
     * @param string $message
     */
    public static function withError(string $message): void
    {
        self::start();
        $_SESSION['_error'] = $message;
    }

    /**
     * Set a success message
     *
     * @param string $message
     */
    public static function withSuccess(string $message): void
    {
        self::start();
        $_SESSION['_success'] = $message;
    }

    /**
     * Get the error message (optional)
     *
     * @return string|null
     */
    public static function getError(): ?string
    {
        return self::get('_error');
    }

    /**
     * Get the success message (optional)
     *
     * @return string|null
     */
    public static function getSuccess(): ?string
    {
        return self::get('_success');
    }

    /**
     * Destroy the session safely
     */
    public static function destroy(): void
    {
        self::start();

        // Clear session data
        $_SESSION = [];

        // Remove session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // Destroy session
        session_destroy();
    }
}
