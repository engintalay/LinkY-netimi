<?php
// login.php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Hatalı kullanıcı adı veya şifre.";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - Link Yöneticisi</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body style="justify-content: center; align-items: center;">

    <div class="glass-card" style="width: 100%; max-width: 400px;">
        <h2 style="text-align: center;">Giriş Yap</h2>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Kullanıcı Adı</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Şifre</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" style="width: 100%;">Giriş Yap</button>
        </form>
    </div>

</body>

</html>