<?php
session_start();
require 'config.php';

// Jika sudah ada session login, langsung dialihkan ke halaman kasir
if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password']; 

    $query = "SELECT id_karyawan, nama, posisi FROM KARYAWAN WHERE username = '$username' AND password = '$password'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['user'] = $user; 
        header("Location: index.php");
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Kafe</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="card" style="width: 100%; max-width: 400px;">
            <h2 style="text-align: center; border: none; padding: 0; margin-bottom: 20px;">☕ Login Sistem Kafe</h2>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><?= $error; ?></div>
            <?php endif; ?>
            
            <form action="" method="POST">
                <div class="form-group">
                    <label>Username:</label>
                    <input type="text" name="username" required placeholder="Masukkan username">
                </div>
                <div class="form-group">
                    <label>Password:</label>
                    <input type="password" name="password" required placeholder="Masukkan password">
                </div>
                <button type="submit" class="btn-primary">Masuk</button>
            </form>
        </div>
    </div>
</body>
</html>