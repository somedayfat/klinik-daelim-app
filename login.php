<?php
// File: login.php
session_start();
include('config/koneksi.php'); // Pastikan path ini benar!

$error = '';

// Cek apakah user sudah login, jika ya, redirect ke index.php
if (isset($_SESSION['user_login'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password_input = $_POST['password'];

    // 1. Query mencari user berdasarkan username
    $query = "SELECT * FROM user WHERE username = '$username' AND is_active = 1";
    $result = mysqli_query($koneksi, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // 2. Verifikasi Password dengan HASH
        if (password_verify($password_input, $user['password'])) {
            // Login Berhasil! Set Session
            $_SESSION['user_login'] = true;
            $_SESSION['id_user'] = $user['id_user'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['level'] = $user['level'];
            
            // Redirect ke halaman utama (index.php)
            header("Location: index.php");
            exit();
        } else {
            // Password salah
            $error = "Username atau password salah. ❌";
        }
    } else {
        // Username tidak ditemukan atau akun tidak aktif
        $error = "Username atau password salah. ❌";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Klinik PT. Daelim</title>
    <link rel="stylesheet" href="assets/compiled/css/app.css">
    <link rel="stylesheet" href="assets/compiled/css/app-dark.css">
    <link rel="stylesheet" href="assets/compiled/css/auth.css">
    <link rel="shortcut icon" href="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2033%2034'%20fill-rule='evenodd'%20stroke-linejoin='round'%20stroke-miterlimit='2'%20xmlns:v='https://vecta.io/nano'%3e%3cpath%20d='M3%2027.472c0%204.409%206.18%205.552%2013.5%205.552%207.281%200%2013.5-1.103%2013.5-5.513s-6.179-5.552-13.5-5.552c-7.281%200-13.5%201.103-13.5%205.513z'%20fill='%23435ebe'%20fill-rule='nonzero'/%3e%3ccircle%20cx='16.5'%20cy='10.8'%20r='10.8'%20fill='%23435ebe'%20fill-rule='nonzero'/%3e%3c/svg%3e" type="image/x-icon">
</head>

<body>
    <script src="assets/static/js/initTheme.js"></script>
    <div id="auth">
        <div class="row h-100">
            <div class="col-lg-5 col-12">
                <div id="auth-left">
                    <h1 class="auth-title">Log in.</h1>
                    <p class="auth-subtitle mb-5">Selamat datang di Sistem Informasi Klinik PT. Daelim Indonesia.</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="login.php">
                        <div class="form-group position-relative has-icon-left mb-4">
                            <input type="text" class="form-control form-control-xl" placeholder="Username" name="username" required>
                            <div class="form-control-icon"><i class="bi bi-person"></i></div>
                        </div>
                        <div class="form-group position-relative has-icon-left mb-4">
                            <input type="password" class="form-control form-control-xl" placeholder="Password" name="password" required>
                            <div class="form-control-icon"><i class="bi bi-shield-lock"></i></div>
                        </div>
                        
                        <button class="btn btn-primary btn-block btn-lg shadow-lg mt-5" type="submit" name="login">Log in</button>
                    </form>
                </div>
            </div>
<div class="col-lg-7 d-none d-lg-block">
    <div id="auth-right" style="
        background: url('assets/static/images/banner.jpg');
        background-size: cover; 
        background-repeat: no-repeat;
        background-position: center center;
        height: 100vh; 
    ">
    </div>
</div>
        </div>
    </div>
</body>
</html>