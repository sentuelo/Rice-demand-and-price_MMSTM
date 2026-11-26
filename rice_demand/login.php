<?php
include "conn.php";
session_start();

$message = "";

if (isset($_GET['registered'])) {
    $message = "Account created successfully! You can now sign in.";
}

// LOGIN
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $message = "Please fill in all fields.";
    } else {
        $sql = "SELECT user_id, username, password, role FROM tb_user WHERE username = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $message = "No account found with that username.";
        } else {
            // Verify the hashed password
            if (password_verify($password, $user['password'])) {
                // Login success
                $_SESSION['user_id']  = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];

                if ($user['role'] === "admin") {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: vendors/dashboard.php");
                }
                exit;
            } else {
                $message = "Incorrect password.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RiceWatch - Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="auth-card">

    <div class="auth-header">
        <div class="logo-mark">🌾</div>
        <h1 class="auth-title">RiceWatch</h1>
        <p class="tagline">Sign in to manage your stalls</p>
    </div>

    <?php if ($message != ""): ?>
        <p class="form-message"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="POST" action="">

        <div class="field">
            <label>Username</label>
            <input type="text" name="username" placeholder="Enter a Username"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
        </div>

        <div class="field">
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter a Password" required>
        </div>

        <button type="submit" name="login" class="btn-primary">Sign in</button>
    </form>

    <div class="auth-switch">
        Don't have an account?
        <a href="register.php" class="link">Register here</a>
    </div>

</div>

</body>
</html>