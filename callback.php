<?php
/**
 * Twitch OAuth Callback Endpoint
 * 
 * Diese Datei wird aufgerufen, wenn der Benutzer sich bei Twitch autorisiert hat
 * URL: https://deine-domain.de/auth/callback.php
 */

session_start();

// Konfiguration laden
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/AuthManager.php';

// Header setzen
header('Content-Type: application/json; charset=utf-8');

// Fehlerbehandlung
try {
    // GET Parameter validieren
    if (!isset($_GET['code']) || !isset($_GET['state'])) {
        throw new Exception('Code oder State Parameter fehlen.');
    }

    // Error Parameter prüfen (Benutzer hat abgelehnt)
    if (isset($_GET['error'])) {
        $error = $_GET['error'];
        if ($error === 'access_denied') {
            throw new Exception('Autorisierung abgelehnt. Zugriff verweigert.');
        } else {
            throw new Exception('Twitch Fehler: ' . htmlspecialchars($error));
        }
    }

    // AuthManager initialisieren
    $auth = new AuthManager();

    // OAuth Callback verarbeiten
    $user = $auth->handleCallback($_GET['code'], $_GET['state']);

    // Erfolg - Redirect zum Dashboard
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit;

} catch (Exception $e) {
    // Fehlerbehandlung
    $errorMessage = $e->getMessage();
    
    // Fehler in Log schreiben
    if (LOG_ENABLED) {
        $logFile = LOG_PATH . '/auth_error.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] OAuth Callback Error: $errorMessage\n", FILE_APPEND);
    }

    // Fehler-HTML anzeigen
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Anmeldefehler</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .container {
                background: white;
                border-radius: 8px;
                padding: 40px;
                max-width: 500px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            }
            h1 {
                color: #e74c3c;
                margin-top: 0;
            }
            p {
                color: #555;
                line-height: 1.6;
            }
            .error-box {
                background: #fef5e7;
                border-left: 4px solid #e74c3c;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
                color: #c0392b;
            }
            .error-code {
                font-family: 'Courier New', monospace;
                font-size: 12px;
                word-break: break-all;
            }
            .button {
                display: inline-block;
                background: #667eea;
                color: white;
                padding: 12px 30px;
                border-radius: 4px;
                text-decoration: none;
                margin-top: 20px;
                transition: background 0.3s;
            }
            .button:hover {
                background: #5568d3;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔒 Anmeldefehler</h1>
            <p>Es gab ein Problem bei der Anmeldung über Twitch. Bitte versuche es erneut.</p>
            
            <div class="error-box">
                <strong>Fehlerdetails:</strong>
                <div class="error-code"><?php echo htmlspecialchars($errorMessage); ?></div>
            </div>

            <p><strong>Was kannst du tun?</strong></p>
            <ul>
                <li>Überprüfe deine Internetverbindung</li>
                <li>Versuche es in einem anderen Browser</li>
                <li>Kontaktiere den Administrator</li>
            </ul>

            <a href="<?php echo htmlspecialchars(BASE_URL); ?>/index.php" class="button">← Zurück zur Anmeldung</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

?>
