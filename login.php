<?php
/**
 * Login Seite
 * Benutzer können sich hier über Twitch anmelden
 */

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/AuthManager.php';

// Falls bereits angemeldet, redirect
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit;
}

$auth = new AuthManager();
$loginUrl = $auth->getLoginUrl();

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Anmeldung</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 450px;
            width: 100%;
            padding: 50px 40px;
            text-align: center;
        }

        .logo-section {
            margin-bottom: 40px;
        }

        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
        }

        h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .features {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            font-size: 14px;
            color: #555;
        }

        .feature-item:last-child {
            margin-bottom: 0;
        }

        .feature-icon {
            color: #667eea;
            font-weight: bold;
            margin-right: 10px;
            font-size: 16px;
        }

        .login-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #9146ff;
            color: white;
            border: none;
            padding: 14px 40px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            width: 100%;
            gap: 10px;
        }

        .login-button:hover {
            background: #7d3cc0;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(145, 70, 255, 0.3);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .twitch-icon {
            width: 20px;
            height: 20px;
            display: inline-block;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #95a5a6;
        }

        .footer a {
            color: #667eea;
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 40px 25px;
            }

            h1 {
                font-size: 24px;
            }

            .features {
                padding: 15px;
            }

            .feature-item {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <div class="logo">🎮</div>
            <h1><?php echo APP_NAME; ?></h1>
            <p class="subtitle">Verwalte deinen Chat Relay einfach</p>
        </div>

        <div class="features">
            <div class="feature-item">
                <span class="feature-icon">✓</span>
                <span>Twitch Chat Management</span>
            </div>
            <div class="feature-item">
                <span class="feature-icon">✓</span>
                <span>Bot Autorisierung</span>
            </div>
            <div class="feature-item">
                <span class="feature-icon">✓</span>
                <span>Admin Bereich</span>
            </div>
        </div>

        <a href="<?php echo htmlspecialchars($loginUrl); ?>" class="login-button">
            <svg class="twitch-icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M11 2H2v16h3v4l4-4h4l7-8V2h-3V0h-3v2zm10 10l-4 4h-4l-3 3v-3H2V3h14v9z"/>
            </svg>
            Mit Twitch anmelden
        </a>

        <div class="footer">
            <p>Mit der Anmeldung akzeptierst du unsere <a href="#">Nutzungsbedingungen</a></p>
            <p>Fragen? Kontaktiere unseren <a href="#">Support</a></p>
        </div>
    </div>
</body>
</html>
