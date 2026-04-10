<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$code = $_POST['code'] ?? '';

if (empty($code)) {
    echo json_encode(['error' => 'Authorization code is missing']);
    exit;
}

try {
    $client = new Google\Client();
    $client->setAuthConfig(__DIR__ . '/../credentials.json');
    $client->setRedirectUri('postmessage');

    $token = $client->fetchAccessTokenWithAuthCode($code);

    if (array_key_exists('error', $token)) {
        throw new Exception(implode(', ', $token));
    }
    
    if (!isset($token['id_token'])) {
        throw new Exception('No id_token found in response');
    }

    $payload = $client->verifyIdToken($token['id_token']);
    if ($payload) {
        $google_id = $payload['sub'];
        $email = $payload['email'];
        $name = $payload['name'];
        $picture = $payload['picture'];

        // Connect to database
        $db = new PDO('sqlite:' . __DIR__ . '/../users.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if user already exists
        $stmt = $db->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
        $stmt->execute([$google_id, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Register new user
            $username = explode('@', $email)[0];
            
            // Check if username is taken, if so, append something
            $base_username = $username;
            $counter = 1;
            while (true) {
                $check_stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $check_stmt->execute([$username]);
                if (!$check_stmt->fetch()) {
                    break;
                }
                $username = $base_username . $counter;
                $counter++;
            }

            // Generate a random 8-character password and hash it
            $random_password = bin2hex(random_bytes(4));
            $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);

            $stmt = $db->prepare("INSERT INTO users (google_id, email, username, password, name, picture, google_token) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$google_id, $email, $username, $hashed_password, $name, $picture, json_encode($token)]);
            
            $user_id = $db->lastInsertId();
            $user = [
                'id' => $user_id,
                'email' => $email,
                'username' => $username,
                'name' => $name,
                'generated_password' => $random_password
            ];
        } else {
            // Update existing user if they didn't have a google_id but matched email
            if (empty($user['google_id'])) {
                $stmt = $db->prepare("UPDATE users SET google_id = ?, picture = ?, google_token = ? WHERE id = ?");
                $stmt->execute([$google_id, $picture, json_encode($token), $user['id']]);
            } else {
                $stmt = $db->prepare("UPDATE users SET picture = ?, google_token = ? WHERE id = ?");
                $stmt->execute([$picture, json_encode($token), $user['id']]);
            }
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['username'] = $user['username'];
        if (isset($user['generated_password'])) {
            $_SESSION['generated_password'] = $user['generated_password'];
        }

        echo json_encode(['redirect' => 'dashboard.php']);
    } else {
        echo json_encode(['error' => 'Invalid ID token']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Authentication error: ' . $e->getMessage()]);
}
