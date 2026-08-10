<?php
require_once __DIR__ . '/../config/config.php';

$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['qty'];
    }
}

$current_user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="DuckyDuck Drink Shop - Tra sua, ca phe, tra trai cay ngon. Giao hang nhanh 20 phut!">
    <title>DuckyDuck Drink Shop</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        /* Extra DuckyDuck overrides */
        body { background-color: #FDFBF0; }
        .logo img { height: 48px; width: auto; object-fit: contain; }
        .logo { gap: 8px; font-size: 1.4rem; font-weight: 800; color: #E8622A; letter-spacing: -0.5px; }
        .nav-link { color: #4A3728; }
        .nav-link:hover, .nav-link.active { color: #F5A623; }
        .nav-link::after { background-color: #F5A623; }
        header { background-color: rgba(253,251,240,0.92); border-bottom: 1px solid #F0E8D0; }
        .btn-primary { background: linear-gradient(135deg, #F5A623, #E8622A); border: none; }
        .btn-primary:hover { background: linear-gradient(135deg, #E09010, #CF5220); }
        .cart-count { background-color: #E8622A; }
        .category-icon-box { background-color: #FFF8E8; color: #F5A623; }
        .category-card:hover .category-icon-box { background-color: #F5A623; color: white; }
        .product-badge { background-color: #E8622A; }
        .section-title span { color: #F5A623; }
        .hero-title span { color: #F5A623; }
        .detail-price { color: #F5A623; }
        .price { color: #F5A623; }
        .admin-sidebar-link:hover, .admin-sidebar-link.active { background-color: #FFF8E8; color: #F5A623; }
        .footer-link:hover { color: #F5A623; }
        .footer-about .logo { color: #F5A623; }
        .summary-row-total .total-price { color: #F5A623; }
    </style>
</head>
<body>

<header>
    <div class="nav-container">
        <!-- Logo DuckyDuck -->
        <a href="/" class="logo">
            <img src="/anh/1.png" alt="DuckyDuck Logo" onerror="this.style.display='none'">
            <span>DuckyDuck</span>
        </a>

        <ul class="nav-menu">
            <li><a href="/" class="nav-link">Trang Chu</a></li>
            <li><a href="/index/?type=products" class="nav-link">Thuc Don</a></li>
            <li><a href="/cart/my_orders.php" class="nav-link">Don Hang</a></li>
            <li><a href="/Game/duck_game.php" target="_blank" class="nav-link" style="color:#f5a623;font-weight:800;">🎮 Game Vịt 3D</a></li>
            <li><a href="/contact.php" class="nav-link">Lien He</a></li>
            <?php if ($current_user && $current_user['role'] === 'admin'): ?>
                <li><a href="/amin/admin.php" class="nav-link" style="color:#E8622A;font-weight:700;">Quan Tri</a></li>
            <?php endif; ?>
        </ul>

        <div class="nav-actions">
            <form action="/index/" method="GET" style="display:flex;gap:5px;">
                <input type="text" name="search" placeholder="Tim mon uong..."
                       style="padding:7px 13px;font-size:0.85rem;border:1.5px solid #F0E8D0;border-radius:20px;background:#fff;"
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button type="submit" class="btn btn-primary" style="padding:7px 14px;font-size:0.85rem;border-radius:20px;">Tim</button>
            </form>

            <a href="/cart/cart.php" class="cart-icon" title="Gio hang">
                <svg><path d="M9 22a1 1 0 100-2 1 1 0 000 2zM17 22a1 1 0 100-2 1 1 0 000 2z"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
                <span class="cart-count" id="cart-counter"><?php echo $cart_count; ?></span>
            </a>

            <?php if ($current_user): ?>
                <div style="font-size:0.88rem;font-weight:700;display:flex;align-items:center;gap:10px;color:#4A3728;">
                    Chao, <span style="color:#F5A623;"><?php echo htmlspecialchars($current_user['fullname']); ?></span>
                    <a href="/login/logout.php" style="font-size:0.78rem;color:#E05252;">Thoat</a>
                </div>
            <?php else: ?>
                <a href="/login/login_demo.php" class="btn btn-primary" style="padding:8px 18px;font-size:0.85rem;border-radius:20px;">Dang Nhap</a>
            <?php endif; ?>
        </div>
    </div>
</header>
