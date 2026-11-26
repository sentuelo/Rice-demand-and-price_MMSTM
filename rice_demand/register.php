<?php
include "conn.php";
session_start();

$message = "";

// REGISTER
if (isset($_POST['register'])) {
    $f_name       = trim($_POST['f_name'] ?? '');
    $l_name       = trim($_POST['l_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $contact      = trim($_POST['contact'] ?? '');
    $stall_name   = trim($_POST['stall_name'] ?? '');
    $stall_number = trim($_POST['stall_number'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $password     = $_POST['password'] ?? '';
    $confirm      = $_POST['confirm_password'] ?? '';

    if ($f_name === '' || $l_name === '' || $email === '' || $contact === '' ||
        $stall_name === '' || $stall_number === '' || $username === '' ||
        $password === '' || $confirm === '') {
        $message = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } elseif (!ctype_digit((string)$stall_number) || (int)$stall_number <= 0) {
        $message = "Stall number must be a positive whole number.";
    } elseif ($password !== $confirm) {
        $message = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters long.";
    } else {
        // Check duplicate username
        $stmt = $conn->prepare("SELECT 1 FROM tb_user WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $message = "Username is already taken.";
        } else {
            // Check duplicate stall number
            $stmt = $conn->prepare("SELECT 1 FROM tb_stall WHERE stall_number = ? LIMIT 1");
            $stmt->execute([(int)$stall_number]);
            if ($stmt->fetch()) {
                $message = "Stall number #{$stall_number} is already taken. Please choose another.";
            } else {
                try {
                    $conn->beginTransaction();

                    // Hash the password before storing
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert vendor
                    $stmt = $conn->prepare("INSERT INTO tb_vendors (f_name, l_name, email, contact) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$f_name, $l_name, $email, $contact]);
                    $new_id = $conn->lastInsertId();

                    // Insert user (hashed password)
                    $stmt = $conn->prepare("INSERT INTO tb_user (user_id, username, password, role) VALUES (?, ?, ?, 'vendor')");
                    $stmt->execute([$new_id, $username, $hashed_password]);

                    // Insert stall
                    $stmt = $conn->prepare("INSERT INTO tb_stall (vendors_id, stall_name, stall_number) VALUES (?, ?, ?)");
                    $stmt->execute([$new_id, $stall_name, (int)$stall_number]);

                    $conn->commit();

                    header("Location: login.php?registered=1");
                    exit;

                } catch (PDOException $e) {
                    $conn->rollBack();
                    // Friendly message for DB-level unique constraint (if any)
                    if ($e->getCode() === '23000') {
                        $message = "Stall number #{$stall_number} is already taken. Please choose another.";
                    } else {
                        $message = "Registration failed: " . $e->getMessage();
                    }
                }
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
    <title>RiceWatch - Register</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="auth-card">

    <div class="auth-header">
        <div class="logo-mark">🌾</div>
        <h1 class="auth-title">RiceWatch</h1>
        <p class="tagline">Create your vendor account</p>
    </div>

    <?php if ($message != ""): ?>
        <p class="form-message"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="POST" action="">

        <div class="row">
            <div class="field">
                <label>First name</label>
                <input type="text" name="f_name" value="<?= htmlspecialchars($_POST['f_name'] ?? '') ?>" placeholder="First name" required>
            </div>
            <div class="field">
                <label>Last name</label>
                <input type="text" name="l_name" value="<?= htmlspecialchars($_POST['l_name'] ?? '') ?>" placeholder="Last name" required>
            </div>
        </div>

        <div class="field">
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="@gmail.com" required>
        </div>

        <div class="field">
            <label>Contact number</label>
            <input type="text" name="contact" value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>" placeholder="09XX XX XXXX" required>
        </div>

        <div class="row">
            <div class="field">
                <label>Stall name</label>
                <input type="text" name="stall_name" value="<?= htmlspecialchars($_POST['stall_name'] ?? '') ?>" placeholder="Enter a stall name" required>
            </div>
            <div class="field">
                <label>Stall number</label>
                <input type="number" name="stall_number" min="1" step="1"
                value="<?= htmlspecialchars($_POST['stall_number'] ?? '') ?>"
                placeholder="Enter a stall number" required>
            </div>
        </div>

        <div class="field">
            <label>Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="Enter Username" required>
        </div>

        <div class="field">
            <label>Password</label>
            <input type="password" name="password" minlength="8" placeholder="Enter a Password (min. 8 characters)" required>
        </div>

        <div class="field">
            <label>Confirm password</label>
            <input type="password" name="confirm_password" minlength="8" placeholder="Re-enter a Password" required>
        </div>

        <button type="submit" name="register" class="btn-primary">Create account</button>
    </form>

    <div class="auth-switch">
        Already have an account?
        <a href="login.php" class="link">Sign in here</a>
    </div>

</div>

</body>
</html>