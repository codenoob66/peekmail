<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <title>Peek Mail</title>
</head>
<body>
    <div class="form-container">
        <h1 class="main-title">Welcome to Peek Mail</h1>
        <?php if (isset($_GET['error'])): ?>
            <p style="color: red; text-align: center;"><?php echo htmlspecialchars($_GET['error']); ?></p>
        <?php endif; ?>
        <form class="login-form" action="app/login.php" method="POST">
            <div class="input-container">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <div class="btn-container">
                <input type="submit" value="Login" class="login-btn">
            </div>
            <span class="divider">or sign in with</span>
        </form>

        <div class="btn-container" style="margin-top: 10px;">
            <button id="google-login-btn" type="button" class="login-btn" style="background-color: #4285F4; border-color: #4285F4;">Sign in with Google</button>
        </div>
    </div>
    <script src="https://accounts.google.com/gsi/client" async></script>
    <script src="script.js"></script>
</body>
</html>