<?php
/**
 * Twitch Chat Relay - Konfiguration
 * Alle sensiblen Daten hier einstellen
 */

// ============================================================================
// TWITCH OAUTH KONFIGURATION
// ============================================================================

// Deine Twitch Anwendung OAuth Credentials
// https://dev.twitch.tv/console/apps
define('TWITCH_CLIENT_ID', 'YOUR_CLIENT_ID_HERE');
define('TWITCH_CLIENT_SECRET', 'YOUR_CLIENT_SECRET_HERE');

// Deine Website URL (für Callback)
// Muss exakt in deiner Twitch App konfiguriert sein!
define('TWITCH_REDIRECT_URI', 'https://deine-domain.de/auth/callback.php');

// Twitch OAuth Endpoints
define('TWITCH_AUTH_URL', 'https://id.twitch.tv/oauth2/authorize');
define('TWITCH_TOKEN_URL', 'https://id.twitch.tv/oauth2/token');
define('TWITCH_USER_URL', 'https://api.twitch.tv/helix/users');

// OAuth Scopes (Berechtigungen, die benötigt werden)
define('TWITCH_SCOPES', [
    'user:read:email',        // E-Mail des Benutzers lesen
    'chat:read',              // Chat lesen (für Bot)
    'chat:edit',              // Chat schreiben (für Bot)
    'moderator:read:followers', // Follower verwalten
]);

// ============================================================================
// DATENBANK KONFIGURATION
// ============================================================================

// SQLite (lokal, keine Installation nötig) ODER MySQL
define('DB_TYPE', 'sqlite'); // 'sqlite' oder 'mysql'

// SQLite Einstellungen
define('SQLITE_PATH', __DIR__ . '/data/nextlife.db');

// MySQL Einstellungen (falls DB_TYPE = 'mysql')
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'twitchchatrelay');

// ============================================================================
// SESSION & SICHERHEIT
// ============================================================================

// Session Timeout (in Sekunden)
define('SESSION_TIMEOUT', 604800); // 7 Tage

// Maximale Session-Dauer (absolut)
define('SESSION_MAX_LIFETIME', 2592000); // 30 Tage

// CSRF Token Gültigkeitsdauer (Sekunden)
define('CSRF_TOKEN_TIMEOUT', 3600); // 1 Stunde

// ============================================================================
// ADMIN EINSTELLUNGEN
// ============================================================================

// Admin Benutzer (können alles verwalten)
define('ADMIN_TWITCH_IDS', [
    'deine_twitch_id_1', // Ersetze mit echter Twitch User ID
    'deine_twitch_id_2',
]);

// ============================================================================
// BOT KONFIGURATION
// ============================================================================

// Bot Name (der Name des Bots bei Twitch)
define('BOT_NAME', 'NextLife_Relay_Bot');

// Channels, die relayed werden
define('RELAY_CHANNELS', [
    'nextlife',
    'andere_channel',
]);

// ============================================================================
// ANWENDUNG EINSTELLUNGEN
// ============================================================================

// Anwendungs-Name
define('APP_NAME', 'NextLife Twitch Chat Relay');
define('APP_VERSION', '1.0.0');

// Website Basis URL
define('BASE_URL', 'https://deine-domain.de');

// Environment (development, staging, production)
define('ENVIRONMENT', 'production');

// Debug Modus (false in Production!)
define('DEBUG', false);

// ============================================================================
// LOGGING
// ============================================================================

// Logs speichern
define('LOG_PATH', __DIR__ . '/logs');
define('LOG_ENABLED', true);
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR

// ============================================================================
// EMAIL (optional, für Benachrichtigungen)
// ============================================================================

define('EMAIL_FROM', 'noreply@deine-domain.de');
define('EMAIL_FROM_NAME', 'NextLife Chat Relay');
define('SMTP_HOST', 'mail.deine-domain.de');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@deine-domain.de');
define('SMTP_PASS', 'YOUR_SMTP_PASSWORD');
define('SMTP_ENCRYPTION', 'tls');

// ============================================================================
// INTEGRATIONEN
// ============================================================================

// Discord Webhook (optional, für Fehlerbenachrichtigungen)
define('DISCORD_WEBHOOK_URL', 'https://discord.com/api/webhooks/YOUR_WEBHOOK');

// ============================================================================
// ZEITZONE
// ============================================================================

date_default_timezone_set('Europe/Berlin');

// ============================================================================
// ERROR HANDLING
// ============================================================================

if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// ============================================================================
// INITIALISIERUNG
// ============================================================================

// Verzeichnisse erstellen, falls nicht vorhanden
@mkdir(dirname(SQLITE_PATH), 0755, true);
@mkdir(LOG_PATH, 0755, true);

// Funktion: Sichere Umgebungsvariablen laden
function loadEnvConfig() {
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') === false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, '\'"');
                putenv("$key=$value");
            }
        }
    }
}

loadEnvConfig();

// ============================================================================
// SICHERHEIT: Wichtige Einstellungen
// ============================================================================

// Sichere Cookies nur über HTTPS
ini_set('session.cookie_secure', getenv('HTTPS') ? '1' : '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

// X-Frame-Options
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

?>
