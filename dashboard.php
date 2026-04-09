<?php

require __DIR__ . '/vendor/autoload.php';

session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Database connection
$db = new PDO('sqlite:' . __DIR__ . '/users.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$client = new Google\Client();
$client->setAuthConfig(__DIR__ . '/credentials.json');
$client->addScope(Google\Service\Gmail::GMAIL_READONLY);
$client->setAccessType('offline');
$client->setPrompt('select_account');

// Set redirect URI directly
$client->setRedirectUri('https://peekmail.codesbyrafael.com/dashboard.php');

// Fetch user's token from database
$stmt = $db->prepare("SELECT google_token FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
$googleToken = $userData['google_token'] ?? null;

if ($googleToken) {
    $accessToken = json_decode($googleToken, true);
    $client->setAccessToken($accessToken);
}

// If there is no previous token or it's expired.
if ($client->isAccessTokenExpired()) {
    // Refresh the token if possible, else fetch a new one.
    if ($client->getRefreshToken()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        // Save the updated token to database
        $stmt = $db->prepare("UPDATE users SET google_token = ? WHERE id = ?");
        $stmt->execute([json_encode($client->getAccessToken()), $user_id]);
    } elseif (isset($_GET['code'])) {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $client->setAccessToken($token);

        if (array_key_exists('error', $token)) {
            throw new Exception(join(', ', $token));
        }

        // Save the token to database
        $stmt = $db->prepare("UPDATE users SET google_token = ? WHERE id = ?");
        $stmt->execute([json_encode($client->getAccessToken()), $user_id]);

        // Redirect back to clear the code from the URL
        header('Location: dasboard.php');
        exit;
    } else {
        $authUrl = $client->createAuthUrl();
        echo "<h1>Welcome, " . htmlspecialchars($_SESSION['username'] ?? $_SESSION['name'] ?? 'User') . "!</h1>";
        printf('<p>To view your emails, you need to authorize Gmail:</p>');
        printf('<a href="%s"><button>Authorize Gmail</button></a>', $authUrl);
        echo '<br><br><a href="logout.php"><button>Logout</button></a>';
        exit;
    }
}

// Gmail service
$service = new Google\Service\Gmail($client);

echo "<h1>Welcome, " . htmlspecialchars($_SESSION['username'] ?? $_SESSION['name'] ?? 'User') . "!</h1>";

if (isset($_SESSION['generated_password'])) {
    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border: 1px solid #c3e6cb; border-radius: 4px;'>";
    echo "<strong>Registration Successful!</strong><br>";
    echo "Your generated credentials for manual login are:<br>";
    echo "Username: <strong>" . htmlspecialchars($_SESSION['username']) . "</strong><br>";
    echo "Password: <strong>" . htmlspecialchars($_SESSION['generated_password']) . "</strong><br>";
    echo "<small><em>Please save this password as it will only be shown once.</em></small>";
    echo "</div>";
    
    // Unset so it only shows once
    unset($_SESSION['generated_password']);
}

echo '<a href="logout.php"><button>Logout / Switch Account</button></a><br><br>';

$optParams = [
    'q' => 'security@facebookmail.com',
    'maxResults' => 1
];

try {
    $messages = $service->users_messages->listUsersMessages('me', $optParams);
    if ($messages->getMessages()) {
        foreach ($messages->getMessages() as $message) {
            $msg = $service->users_messages->get('me', $message->getId());
            $fullBody = getFullMessageBody($msg);
            echo "Snippet: " . $msg->getSnippet() . "<br><br>";
            echo "Full Body: <br><div>" . $fullBody . "</div><br><hr>";
        }
    } else {
        echo "No messages found.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

function getFullMessageBody($message)
{
    $payload = $message->getPayload();

    // If no parts, body is directly available
    if ($payload->getBody() && $payload->getBody()->getData()) {
        return decodeBody($payload->getBody()->getData());
    }

    // If multipart, loop through parts
    $parts = $payload->getParts();
    if (!$parts) {
        return '';
    }

    $htmlBody = '';
    $plainBody = '';

    foreach ($parts as $part) {
        $mimeType = $part->getMimeType();
        $body = $part->getBody();

        if ($mimeType === 'text/html' && $body->getData()) {
            $htmlBody = decodeBody($body->getData());
        }

        if ($mimeType === 'text/plain' && $body->getData()) {
            $plainBody = decodeBody($body->getData());
        }

        // Handle nested parts (recursive)
        if ($part->getParts()) {
            $nested = getFullMessageBodyFromParts($part->getParts());
            if (!empty($nested['html'])) {
                $htmlBody = $nested['html'];
            }
            if (!empty($nested['plain'])) {
                $plainBody = $nested['plain'];
            }
        }
    }

    // Prefer HTML
    return $htmlBody ?: $plainBody;
}

function getFullMessageBodyFromParts($parts)
{
    $result = ['html' => '', 'plain' => ''];

    foreach ($parts as $part) {
        $mimeType = $part->getMimeType();
        $body = $part->getBody();

        if ($mimeType === 'text/html' && $body->getData()) {
            $result['html'] = decodeBody($body->getData());
        }

        if ($mimeType === 'text/plain' && $body->getData()) {
            $result['plain'] = decodeBody($body->getData());
        }

        if ($part->getParts()) {
            $nested = getFullMessageBodyFromParts($part->getParts());
            if (!empty($nested['html'])) {
                $result['html'] = $nested['html'];
            }
            if (!empty($nested['plain'])) {
                $result['plain'] = $nested['plain'];
            }
        }
    }

    return $result;
}

function decodeBody($data)
{
    $data = str_replace(['-', '_'], ['+', '/'], $data);
    return base64_decode($data);
}
