<?php
require_once __DIR__ . '/../config/config.php';

if (isset($_GET['quick_login'])) {
    $role = $_GET['quick_login'];
    if ($role === 'admin') {
        $_SESSION['user_id'] = 1; $_SESSION['role'] = 'admin'; $_SESSION['fullname'] = 'Quản trị viên hệ thống';
        header("Location: /amin/admin.php"); exit();
    } elseif ($role === 'customer') {
        $_SESSION['user_id'] = 2; $_SESSION['role'] = 'customer'; $_SESSION['fullname'] = 'Nguyễn Văn A';
        header("Location: /index/?type=products"); exit();
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = md5(trim($_POST['password']));
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ? AND status = 1");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['fullname']= $user['fullname'];
        header($user['role'] === 'admin' ? "Location: /amin/admin.php" : "Location: /index/?type=products");
        exit();
    } else {
        $error = 'Tên đăng nhập hoặc mật khẩu không chính xác!';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng Nhập - GlowDrinks</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .login-card { max-width:460px;margin:60px auto;background:white;border-radius:var(--radius-lg);padding:40px;box-shadow:var(--shadow-lg);border:1px solid var(--border); }
        .quick-box  { background:var(--light);border-radius:var(--radius-md);padding:20px;margin-top:25px;border:1px dashed var(--primary);text-align:center; }
    </style>
</head>
<body style="background-color:#f8fafc;">
<div class="login-card">
    <div style="text-align:center;margin-bottom:25px;">
        <a href="/" class="logo" style="justify-content:center;margin-bottom:15px;display:inline-flex;">
            <svg viewBox="0 0 24 24" style="width:36px;height:36px;fill:var(--primary);">
                <path d="M2 21h18v-2H2v2M20 8h-2V5h2v3M4 19h12v-4H4v4m0-6h12V9H4v4m0-6h12V5H4v4m16-1c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-4v6h4z"/>
            </svg>
            <span>GlowDrinks</span>
        </a>
        <h2 style="font-size:1.6rem;color:var(--dark);font-weight:800;">Đăng Nhập Tài Khoản</h2>
        <p style="color:var(--text-muted);font-size:0.9rem;margin-top:4px;">Đăng nhập để đặt hàng & tích điểm thưởng</p>
    </div>

    <!-- Thông báo yêu cầu đăng nhập khi mua sản phẩm -->
    <div style="background:#fffbeb;color:#b45309;padding:12px 16px;border-radius:var(--radius-sm);margin-bottom:20px;font-weight:600;font-size:0.9rem;border:1px solid #fef3c7;display:flex;align-items:center;gap:8px;">
        🔒 Bạn cần đăng nhập tài khoản trước khi chọn mua món đồ uống!
    </div>

    <?php if (!empty($error)): ?>
        <div style="background:#fee2e2;color:#dc2626;padding:12px 16px;border-radius:var(--radius-sm);margin-bottom:20px;font-weight:600;">⚠️ <?php echo $error; ?></div>
    <?php endif; ?>

    <form action="/login/login_demo.php" method="POST">
        <div class="form-group">
            <label class="form-label">Tên đăng nhập *</label>
            <input type="text" name="username" class="form-control" placeholder="nguyenvana hoặc admin" required>
        </div>
        <div class="form-group" style="margin-bottom:25px;">
            <label class="form-label">Mật khẩu *</label>
            <input type="password" name="password" class="form-control" placeholder="123456" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;padding:14px;font-weight:700;font-size:1rem;">Đăng Nhập Để Mua Hàng</button>
    </form>

    <div class="quick-box">
        <div style="font-weight:700;margin-bottom:12px;color:var(--primary);font-size:0.9rem;text-transform:uppercase;">⚡ Đăng Nhập Nhanh (Dành Cho Dùng Thử)</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <a href="/login/login_demo.php?quick_login=customer" class="btn btn-primary" style="padding:10px;font-size:0.85rem;background:#0284c7;border:none;">👤 Tài Khoản Khách</a>
            <a href="/login/login_demo.php?quick_login=admin" class="btn btn-secondary" style="padding:10px;font-size:0.85rem;">👑 Admin Quản Trị</a>
        </div>
    </div>
    <div style="text-align:center;margin-top:25px;">
        <a href="/index/" style="font-size:0.9rem;color:var(--text-muted);text-decoration:none;">← Quay lại trang chủ</a>
    </div>
</div>
</body>
</html>
