<?php
/**
 * Session Helper - Session Verwaltung
 */

require_once __DIR__ . '/AuthManager.php';

class SessionHelper {
    private static $currentUser = null;
    private static $authManager = null;

    /**
     * Initialisiere Session
     */
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        self::$authManager = new AuthManager();
        self::$currentUser = self::$authManager->loadSession();
    }

    /**
     * Prüfe ob Benutzer angemeldet ist
     */
    public static function isLoggedIn() {
        self::checkInit();
        return self::$currentUser !== null;
    }

    /**
     * Hole aktuellen Benutzer
     */
    public static function getCurrentUser() {
        self::checkInit();
        return self::$currentUser;
    }

    /**
     * Prüfe ob Benutzer Admin ist
     */
    public static function isAdmin() {
        self::checkInit();
        return self::$currentUser && self::$currentUser['is_admin'] == 1;
    }

    /**
     * Prüfe ob Bot autorisiert ist
     */
    public static function isBotAuthorized() {
        self::checkInit();
        return self::$currentUser && self::$currentUser['is_bot_authorized'] == 1;
    }

    /**
     * Hole Bot Access Token
     */
    public static function getBotAccessToken() {
        self::checkInit();
        if (self::$currentUser && self::$currentUser['bot_access_token']) {
            // Prüfe ob Token abgelaufen ist
            if (self::$currentUser['bot_expires_at'] < time()) {
                // Token erneuern
                self::$authManager->refreshAccessToken(self::$currentUser['id']);
                // Benutzer neu laden
                self::$currentUser = self::$authManager->loadSession();
            }
            return self::$currentUser['bot_access_token'];
        }
        return null;
    }

    /**
     * Prüfe ob Benutzer angemeldet ist, sonst redirect
     */
    public static function requireLogin() {
        self::checkInit();
        if (!self::isLoggedIn()) {
            header('Location: ' . BASE_URL . '/auth/login.php');
            exit;
        }
    }

    /**
     * Prüfe ob Benutzer Admin ist, sonst redirect
     */
    public static function requireAdmin() {
        self::checkInit();
        if (!self::isLoggedIn()) {
            header('Location: ' . BASE_URL . '/auth/login.php');
            exit;
        }
        if (!self::isAdmin()) {
            http_response_code(403);
            die('Zugriff verweigert. Admin-Berechtigungen erforderlich.');
        }
    }

    /**
     * Hilfsfunktion
     */
    private static function checkInit() {
        if (self::$authManager === null) {
            self::init();
        }
    }

    /**
     * Benutzer ID
     */
    public static function getUserId() {
        self::checkInit();
        return self::$currentUser ? self::$currentUser['id'] : null;
    }

    /**
     * Benutzer Display Name
     */
    public static function getDisplayName() {
        self::checkInit();
        return self::$currentUser ? self::$currentUser['display_name'] : null;
    }

    /**
     * Benutzer Twitch Login
     */
    public static function getTwitchLogin() {
        self::checkInit();
        return self::$currentUser ? self::$currentUser['twitch_login'] : null;
    }

    /**
     * Benutzer Avatar URL
     */
    public static function getProfileImageUrl() {
        self::checkInit();
        return self::$currentUser ? self::$currentUser['profile_image_url'] : null;
    }

    /**
     * CSRF Token generieren
     */
    public static function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * CSRF Token validieren
     */
    public static function validateCsrfToken($token) {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }

        // Token Gültigkeitsdauer prüfen
        if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_TIMEOUT) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Automatisch initialisieren
SessionHelper::init();

?>
