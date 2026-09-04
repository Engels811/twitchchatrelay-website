<?php
/**
 * AuthManager - Twitch OAuth & Session Management
 */

require_once __DIR__ . '/Database.php';

class AuthManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Generiert den Twitch OAuth Login URL
     */
    public function getLoginUrl($state = null) {
        if (!$state) {
            $state = bin2hex(random_bytes(16));
            $_SESSION['oauth_state'] = $state;
        }

        $params = [
            'client_id' => TWITCH_CLIENT_ID,
            'redirect_uri' => TWITCH_REDIRECT_URI,
            'response_type' => 'code',
            'scope' => implode(' ', TWITCH_SCOPES),
            'state' => $state,
            'force_verify' => 'true',
        ];

        return TWITCH_AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Verarbeitet den OAuth Callback
     */
    public function handleCallback($code, $state) {
        // State Validierung
        if (!isset($_SESSION['oauth_state']) || $_SESSION['oauth_state'] !== $state) {
            throw new Exception('Ungültiger State Parameter. Sicherheitsprobleme erkannt.');
        }

        unset($_SESSION['oauth_state']);

        // Access Token vom Twitch Server holen
        $tokenData = $this->exchangeCodeForToken($code);

        if (!$tokenData) {
            throw new Exception('Token-Austausch fehlgeschlagen.');
        }

        // Benutzerinformationen abrufen
        $userData = $this->getTwitchUserData($tokenData['access_token']);

        if (!$userData) {
            throw new Exception('Benutzerinformationen konnten nicht abgerufen werden.');
        }

        // Benutzer in Datenbank erstellen oder aktualisieren
        $user = $this->createOrUpdateUser($userData, $tokenData);

        // Session erstellen
        $this->createSession($user['id']);

        // Login Action loggen
        $this->logAction($user['id'], 'LOGIN', 'Benutzer angemeldet über Twitch OAuth');

        return $user;
    }

    /**
     * Twitch OAuth Token Austausch
     */
    private function exchangeCodeForToken($code) {
        $postData = [
            'client_id' => TWITCH_CLIENT_ID,
            'client_secret' => TWITCH_CLIENT_SECRET,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => TWITCH_REDIRECT_URI,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => TWITCH_TOKEN_URL,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->logError("Token-Austausch fehlgeschlagen. HTTP Code: $httpCode");
            return false;
        }

        $data = json_decode($response, true);

        if (!isset($data['access_token'])) {
            return false;
        }

        return $data;
    }

    /**
     * Holt Benutzerinformationen von Twitch
     */
    private function getTwitchUserData($accessToken) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => TWITCH_USER_URL,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Client-ID: ' . TWITCH_CLIENT_ID,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->logError("Twitch User Daten abruf fehlgeschlagen. HTTP Code: $httpCode");
            return false;
        }

        $data = json_decode($response, true);

        if (!isset($data['data'][0])) {
            return false;
        }

        return $data['data'][0];
    }

    /**
     * Benutzer erstellen oder aktualisieren
     */
    private function createOrUpdateUser($twitchData, $tokenData) {
        $twitch_id = $twitchData['id'];
        
        // Benutzer prüfen
        $existingUser = $this->db->fetch(
            "SELECT * FROM users WHERE twitch_id = ?",
            [$twitch_id]
        );

        // Benutzer Admin-Status berechnen
        $isAdmin = in_array($twitch_id, ADMIN_TWITCH_IDS) ? 1 : 0;

        // Token Ablaufzeit
        $expiresAt = time() + $tokenData['expires_in'];

        if ($existingUser) {
            // Benutzer aktualisieren
            $this->db->update('users', [
                'display_name' => $twitchData['display_name'],
                'twitch_login' => $twitchData['login'],
                'profile_image_url' => $twitchData['profile_image_url'],
                'email' => $twitchData['email'] ?? null,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'token_expires_at' => $expiresAt,
                'is_admin' => $isAdmin,
                'last_login' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $existingUser['id']]);

            return $this->db->fetch("SELECT * FROM users WHERE id = ?", [$existingUser['id']]);
        } else {
            // Neuen Benutzer erstellen
            $userId = $this->db->insert('users', [
                'twitch_id' => $twitch_id,
                'twitch_login' => $twitchData['login'],
                'display_name' => $twitchData['display_name'],
                'profile_image_url' => $twitchData['profile_image_url'],
                'email' => $twitchData['email'] ?? null,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'token_expires_at' => $expiresAt,
                'is_admin' => $isAdmin,
                'last_login' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        }
    }

    /**
     * Session erstellen
     */
    private function createSession($userId) {
        session_regenerate_id(true);
        
        $sessionId = session_id();
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);

        $this->db->insert('sessions', [
            'id' => $sessionId,
            'user_id' => $userId,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
        ]);

        $_SESSION['user_id'] = $userId;
        $_SESSION['created_at'] = time();
    }

    /**
     * Benutzer Session laden
     */
    public function loadSession() {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $sessionId = session_id();
        
        // Session validieren
        $session = $this->db->fetch(
            "SELECT * FROM sessions WHERE id = ? AND expires_at > datetime('now')",
            [$sessionId]
        );

        if (!$session) {
            $this->destroySession();
            return null;
        }

        // Benutzer laden
        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$session['user_id']]);

        if (!$user) {
            $this->destroySession();
            return null;
        }

        // Token aktualisieren falls nötig
        if ($user['token_expires_at'] < time() && $user['refresh_token']) {
            $this->refreshAccessToken($user['id']);
            $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$user['id']]);
        }

        // Last Activity aktualisieren
        $this->db->update('sessions', [
            'last_activity' => date('Y-m-d H:i:s'),
        ], ['id' => $sessionId]);

        return $user;
    }

    /**
     * Access Token erneuern
     */
    public function refreshAccessToken($userId) {
        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);

        if (!$user || !$user['refresh_token']) {
            return false;
        }

        $postData = [
            'client_id' => TWITCH_CLIENT_ID,
            'client_secret' => TWITCH_CLIENT_SECRET,
            'grant_type' => 'refresh_token',
            'refresh_token' => $user['refresh_token'],
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => TWITCH_TOKEN_URL,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return false;
        }

        $data = json_decode($response, true);

        if (!isset($data['access_token'])) {
            return false;
        }

        $expiresAt = time() + $data['expires_in'];

        $this->db->update('users', [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $user['refresh_token'],
            'token_expires_at' => $expiresAt,
        ], ['id' => $userId]);

        return true;
    }

    /**
     * Logout
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logAction($_SESSION['user_id'], 'LOGOUT', 'Benutzer abgemeldet');
            
            $this->db->delete('sessions', ['id' => session_id()]);
        }

        session_destroy();
    }

    /**
     * Bot-Autorisierung für einen Benutzer speichern
     */
    public function authorizeBotForUser($userId, $botAccessToken, $botRefreshToken, $expiresIn) {
        $expiresAt = time() + $expiresIn;

        $this->db->update('users', [
            'is_bot_authorized' => 1,
            'bot_access_token' => $botAccessToken,
            'bot_refresh_token' => $botRefreshToken,
            'bot_expires_at' => $expiresAt,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $userId]);

        $this->logAction($userId, 'BOT_AUTH', 'Bot-Autorisierung gewährt');
    }

    /**
     * Bot-Autorisierung widerrufen
     */
    public function revokeBotAuth($userId) {
        $this->db->update('users', [
            'is_bot_authorized' => 0,
            'bot_access_token' => null,
            'bot_refresh_token' => null,
            'bot_expires_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $userId]);

        $this->logAction($userId, 'BOT_REVOKE', 'Bot-Autorisierung widerrufen');
    }

    /**
     * Hilfsfunktion: Client IP
     */
    private function getClientIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
    }

    /**
     * Action loggen
     */
    private function logAction($userId, $action, $details = '') {
        if (!LOG_ENABLED) return;

        $this->db->insert('logs', [
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Fehler loggen
     */
    private function logError($message) {
        if (LOG_ENABLED) {
            $logFile = LOG_PATH . '/auth.log';
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
        }
    }

    /**
     * Session zerstören
     */
    private function destroySession() {
        $sessionId = session_id();
        if ($sessionId) {
            $this->db->delete('sessions', ['id' => $sessionId]);
        }
        session_destroy();
    }
}

?>
