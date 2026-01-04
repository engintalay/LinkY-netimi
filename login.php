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


    // 1. IP Blocking Check
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blocked_ips WHERE ip_address = ?");
    $stmt->execute([$ip_address]);
    if ($stmt->fetchColumn() > 0) {
        $pdo->prepare("INSERT INTO login_logs (username, ip_address, status, message) VALUES (?, ?, 'blocked', 'IP Blocked')")->execute([$username, $ip_address]);
        die("Erişiminiz engellendi. (IP Blocked)");
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. Permanent Lock Check
        if ($user['is_permanently_locked']) {
            $pdo->prepare("INSERT INTO login_logs (username, ip_address, status, message) VALUES (?, ?, 'failure', 'Account Permanently Locked')")->execute([$username, $ip_address]);
            $error = "Hesabınız kalıcı olarak kilitlendi. Yöneticinize başvurun.";
        } 
        // 3. Temporary Lock Check
        elseif ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
            $pdo->prepare("INSERT INTO login_logs (username, ip_address, status, message) VALUES (?, ?, 'failure', 'Account Temporarily Locked')")->execute([$username, $ip_address]);
            $error = "Hesabınız kilitli. Lütfen $remaining dakika sonra tekrar deneyin.";
        }
        else {
            // Check Password
            if (password_verify($password, $user['password'])) {
                // Success: Reset counters
                $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$user['id']]);

                // Log Success
                $pdo->prepare("INSERT INTO login_logs (username, ip_address, status, message) VALUES (?, ?, 'success', 'Login Successful')")->execute([$user['username'], $ip_address]);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header("Location: dashboard.php");
                exit;
            } else {
                // Failure
                $failed_attempts = $user['failed_attempts'] + 1;
                $daily_lock_count = $user['daily_lock_count'];
                $today = date('Y-m-d');

                // Reset daily count if new day
                if ($user['last_lock_date'] != $today) {
                    $daily_lock_count = 0;
                }

                if ($failed_attempts >= 3) {
                    // Lock Account
                    $locked_until = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    $daily_lock_count++;
                    $failed_attempts = 0; // Reset for next cycle

                    // Logic for Daily Limits
                    if ($daily_lock_count >= 6) {
                        // Permanent Lock
                        $pdo->prepare("UPDATE users SET is_permanently_locked = 1, daily_lock_count = ?, last_lock_date = ? WHERE id = ?")
                            ->execute([$daily_lock_count, $today, $user['id']]);
                        $pdo->prepare("INSERT INTO login_logs (username, ip_address, status, message) VALUES (?, ?, 'failure', 'Account Locked (Permanent)')")->execute([$username, $ip_address]);
                        $error = "Hesabınız çok fazla hatalı giriş denemesi nedeniyle kalıcı olarak kilitlendi.";
                    } elseif ($daily_lock_count >= 3) {
                        // Block IP (Wait... requirement says "same day 3 times... ip block")
                        // "aynı gün içinde 3 defa böyle durum oluşursa ip adresini blokla" -> 3 lockouts = IP block
                        // "6 defa olursa kullanıcıyı kilitle" -> 6 lockouts = User Perm Lock.
                        // Wait, if IP is blocked at 3, how can we reach 6? 
                        // Maybe different IPs? Or maybe the requirement implies sequential handling?
                        // Let's assume strict following:
                        // 3rd lockout -> Block IP.
                        // 6th lockout -> Lock User (maybe from another IP?)
                        
                        $pdo->prepare("INSERT INTO blocked_ips (ip_address) VALUES (?)")->execute([$ip_address]);
                        $pdo->prepare("UPDATE users SET locked_until = ?, failed_attempts = 0, daily_lock_count = ?, last_lock_date = ? WHERE id = ?")
                             ->execute([$locked_until, $daily_lock_count, $today, $user['id']]);
                        
                        $pdo->prepare("INSERT INTO login_logs (username, ip_address, status, message) VALUES (?, ?, 'blocked', 'IP Blocked Triggered')")->execute([$username, $ip_address]);

                        $error = "Çok fazla hatalı deneme. IP adresiniz engellendi.";
                    } else {
                        // Just 10 min lock
                        $pdo->prepare("UPDATE users SET locked_until = ?, failed_attempts = 0, daily_lock_count = ?, last_lock_date = ? WHERE id = ?")
                             ->execute([$locked_until, $daily_lock_count, $today, $user['id']]);
                        $pdo->prepare("INSERT INTO login_logs (username, ip_address, status, message) VALUES (?, ?, 'failure', 'Account Locked (10m)')")->execute([$username, $ip_address]);
                        $error = "3 hatalı deneme yaptınız. Hesabınız 10 dakika kilitlendi.";
                    }

                } else {
                    // Just increment failed attempts
                    $pdo->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?")->execute([$failed_attempts, $user['id']]);
                    $pdo->prepare("INSERT INTO login_logs (username, ip_address, status, message) VALUES (?, ?, 'failure', 'Wrong Password')")->execute([$username, $ip_address]);
                    $error = "Hatalı kullanıcı adı veya şifre.";
                }
            }
        }
    } else {
        // User not found, generic error to prevent enumeration (or just stick to "Invalid user/pass")
        $pdo->prepare("INSERT INTO login_logs (username, ip_address, status, message) VALUES (?, ?, 'failure', 'User Not Found')")->execute([$username, $ip_address]);
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