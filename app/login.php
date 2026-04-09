<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    die("Please provide both username and password.");
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../users.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['username'] = $user['username'];

        header('Location: ../dashboard.php');
        exit;
    } else {
        header('Location: ../index.php?error=Invalid credentials');
        exit;
    }
} catch (PDOException $e) {
    header('Location: ../index.php?error=Database error');
    exit;
}
