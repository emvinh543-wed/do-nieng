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
    <link rel="stylesheet" href="/css/home.css">
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
        .lang-switcher {
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.7);
            border: 1px solid #F0E8D0;
            border-radius: 999px;
            padding: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }
        .lang-pill {
            border: none;
            background: transparent;
            color: #5A4638;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 6px 10px;
            border-radius: 999px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .lang-pill.active {
            background: linear-gradient(135deg, #F5A623, #E8622A);
            color: white;
            box-shadow: 0 6px 18px rgba(245,166,35,0.25);
        }
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
            <li><a href="/" class="nav-link" data-i18n="nav_home">Trang Chu</a></li>
            <li><a href="/index/?type=products" class="nav-link" data-i18n="nav_menu">Thuc Don</a></li>
            <li><a href="/cart/my_orders.php" class="nav-link" data-i18n="nav_orders">Don Hang</a></li>
            <li><a href="/Game/duck_game.php" target="_blank" class="nav-link" style="color:#f5a623;font-weight:800;" data-i18n="nav_game">🎮 Game Vịt 3D</a></li>
            <li><a href="/contact.php" class="nav-link" data-i18n="nav_contact">Lien He</a></li>
            <?php if ($current_user && $current_user['role'] === 'admin'): ?>
                <li><a href="/amin/admin.php" class="nav-link" style="color:#E8622A;font-weight:700;" data-i18n="nav_admin">Quan Tri</a></li>
            <?php endif; ?>
        </ul>

        <div class="nav-actions">
            <div class="lang-switcher" aria-label="Language selector">
                <button type="button" class="lang-pill active" data-lang="vi">VI</button>
                <button type="button" class="lang-pill" data-lang="en">EN</button>
                <button type="button" class="lang-pill" data-lang="ja">JP</button>
                <button type="button" class="lang-pill" data-lang="zh">中</button>
            </div>

            <form action="/index/" method="GET" style="display:flex;gap:5px;">
                <input type="text" name="search" data-i18n-placeholder="search_placeholder" placeholder="Tim mon uong..."
                       style="padding:7px 13px;font-size:0.85rem;border:1.5px solid #F0E8D0;border-radius:20px;background:#fff;"
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button type="submit" class="btn btn-primary" style="padding:7px 14px;font-size:0.85rem;border-radius:20px;" data-i18n="search_btn">Tim</button>
            </form>

            <a href="/cart/cart.php" class="cart-icon" data-i18n-title="cart_title" title="Gio hang">
                <svg><path d="M9 22a1 1 0 100-2 1 1 0 000 2zM17 22a1 1 0 100-2 1 1 0 000 2z"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
                <span class="cart-count" id="cart-counter"><?php echo $cart_count; ?></span>
            </a>

            <?php if ($current_user): ?>
                <div style="font-size:0.88rem;font-weight:700;display:flex;align-items:center;gap:10px;color:#4A3728;">
                    <span data-i18n="greeting">Chao</span>, <span style="color:#F5A623;"><?php echo htmlspecialchars($current_user['fullname']); ?></span>
                    <a href="/login/logout.php" style="font-size:0.78rem;color:#E05252;" data-i18n="logout_btn">Thoat</a>
                </div>
            <?php else: ?>
                <a href="/login/login_demo.php" class="btn btn-primary" style="padding:8px 18px;font-size:0.85rem;border-radius:20px;" data-i18n="login_btn">Dang Nhap</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<script>
    const localeMap = {
        vi: {
            nav_home: 'Trang Chủ', nav_menu: 'Thực Đơn', nav_orders: 'Đơn Hàng', nav_game: '🎮 Game Vịt 3D', nav_contact: 'Liên Hệ', nav_admin: 'Quản Trị',
            search_btn: 'Tìm', login_btn: 'Đăng Nhập', logout_btn: 'Thoát', greeting: 'Chào',
            menu_title: 'Món Ngon <span>Gợi Ý</span>', back_home: '← Về trang chủ', nothing_found: 'Không tìm thấy sản phẩm!',
            empty_hint: 'Thử tìm kiếm với từ khóa khác hoặc chọn danh mục khác.', back_home_button: '↩ Về trang chủ'
            ,
            // Duck widget translations
            duck_bubble_title: '🦆 Vịt AI đang đi dạo! 👋',
            duck_bubble_text: 'Chào! Tớ là trợ lý Vịt AI. Tớ đi quanh trang để giúp bạn — hỏi mình bất kỳ điều gì nhé!',
            duck_header_title: 'Vịt AI',
            duck_header_status: '🟢 Trợ lý ảo GlowDrinks (Online)',
            duck_welcome_html: 'Xin chào! 👋 Chào mừng bạn đến với <strong>GlowDrinks</strong>!<br><br>Tớ là trợ lý <strong>Vịt AI</strong>, có thể giúp bạn tìm món, đặt hàng hoặc hướng dẫn thanh toán.<br><br><strong>Bạn cần hỗ trợ gì hôm nay?</strong>',
            duck_chip_play: '🎮 Chơi Game Vịt 3D (Nhận Mã Giảm Giá)',
            duck_chip_best: '🥤 Món nước bán chạy?',
            duck_chip_delivery: '🛵 Giao hàng bao lâu?',
            duck_chip_qr: '📱 Thanh toán QR?',
            duck_chip_order: '🛒 Hướng dẫn đặt hàng',
            duck_input_placeholder: 'Hỏi Vịt AI bất kỳ điều gì...'
            ,
            // Hero / Promo / Categories / Footer / Product strings
            hero_badge: '🔥 &nbsp;Giao hàng miễn phí trong 2km',
            hero_title_line1: 'Đồ Uống Sạch,',
            hero_title_highlight: 'Năng Lượng Sáng Tạo!',
            hero_sub: 'Chào đón ngày mới với ly cà phê đậm vị Việt Nam, hay nạp vitamin tươi mát với trà trái cây tự nhiên đặc trưng của GlowDrinks.',
            hero_btn_order: '🛒 Đặt món ngay',
            hero_btn_contact: '📞 Liên hệ hỗ trợ',
            promo_tag_big: '🔥 ƯU ĐÃI HÔM NAY',
            promo_title_big: 'Giảm 15% Toàn Bộ<br>Trà Sữa & Cà Phê',
            promo_sub_big: 'Áp dụng cho đơn hàng từ 50.000₫ trở lên',
            promo_btn_big: 'Xem ngay →',
            promo_tag_small: '🎮 MÃ VIP',
            promo_title_small: 'Chơi Game<br>Nhận Voucher',
            promo_sub_small: 'Vịt 3D – Top 10 nhận ưu đãi!',
            promo_btn_small: 'Chơi ngay 🦆',
            categories_title: 'Danh Mục <span>Thực Đơn</span>',
            categories_view_all: 'Xem tất cả →',
            categories_all: 'Tất Cả',
            categories_all_count: 'Toàn bộ món',
            product_badge_hot: '🔥 Bán chạy',
            add_to_cart: 'Thêm vào giỏ',
            footer_about_text: 'GlowDrinks mang đến trải nghiệm đồ uống tinh tế và phong cách với chất lượng nguyên liệu sạch tốt nhất.',
            footer_title_explore: 'Kham Pha',
            footer_links_home: 'Trang chu',
            footer_links_menu: 'Thuc don do uong',
            footer_links_contact: 'Lien he - Gop y',
            footer_title_hours: 'Gio Hoat Dong',
            footer_hours_weekdays: 'Thu 2 - Thu 6: 07:00 - 22:30',
            footer_hours_weekend: 'Thu 7 - Chu Nhat: 08:00 - 23:00',
            footer_hotline_label: 'Hotline giao hang nhanh:',
            footer_hotline: '1900 6868',
            footer_address: '123 Duong Ba Thang Hai, Quan 10, TP.HCM',
            footer_email: 'lienhe@glowdrinks.com',
            footer_payment_methods: 'MoMo, VNPay, Chuyen khoan, COD',
            footer_copyright: '© 2026 GlowDrinks Store. Tat ca quyen duoc bao luu.',
            footer_designer: 'Thiet ke boi Antigravity AI'
            ,
            // misc
            search_placeholder: 'Tìm món uống...'
            , cart_title: 'Giỏ hàng'
            , product_off_label: 'Off'
            , footer_title_contact: 'Địa Chỉ Liên Hệ'
            , item_label: 'món'
        },
        en: {
            nav_home: 'Home', nav_menu: 'Menu', nav_orders: 'Orders', nav_game: '🎮 3D Duck Game', nav_contact: 'Contact', nav_admin: 'Admin',
            search_btn: 'Search', login_btn: 'Login', logout_btn: 'Logout', greeting: 'Hi',
            menu_title: 'Featured <span>Drinks</span>', back_home: '← Back home', nothing_found: 'No products found!',
            empty_hint: 'Try another keyword or choose a different category.', back_home_button: '↩ Back home'
            ,
            // Duck widget translations
            duck_bubble_title: '🦆 Duck AI is strolling! 👋',
            duck_bubble_text: 'Hi! I am the Duck Assistant. I roam the site to help — ask me anything!',
            duck_header_title: 'Duck AI',
            duck_header_status: '🟢 GlowDrinks assistant (Online)',
            duck_welcome_html: 'Hello! 👋 Welcome to <strong>GlowDrinks</strong>!<br><br>I am the <strong>Duck Assistant</strong>, I can help with menu, ordering or payment guidance.<br><br><strong>How can I help you today?</strong>',
            duck_chip_play: '🎮 Play 3D Duck Game (Get Discount Code)',
            duck_chip_best: '🥤 Bestselling drinks?',
            duck_chip_delivery: '🛵 How long for delivery?',
            duck_chip_qr: '📱 How to pay with QR?',
            duck_chip_order: '🛒 How to place an order',
            duck_input_placeholder: 'Ask Duck AI anything...'
            ,
            // Hero / Promo / Categories / Footer / Product strings
            hero_badge: '🔥 Free delivery within 2km',
            hero_title_line1: 'Clean Drinks,',
            hero_title_highlight: 'Creative Energy!',
            hero_sub: 'Start your day with rich Vietnamese coffee or recharge with fresh fruit tea unique to GlowDrinks.',
            hero_btn_order: '🛒 Order now',
            hero_btn_contact: '📞 Contact support',
            promo_tag_big: '🔥 TODAY\'S DEAL',
            promo_title_big: '15% Off All<br>Milk Tea & Coffee',
            promo_sub_big: 'Applies to orders over 50,000₫',
            promo_btn_big: 'See now →',
            promo_tag_small: '🎮 VIP CODE',
            promo_title_small: 'Play Game<br>Get Voucher',
            promo_sub_small: 'Duck 3D – Top 10 get rewards!',
            promo_btn_small: 'Play now 🦆',
            categories_title: 'Categories <span>Menu</span>',
            categories_view_all: 'View all →',
            categories_all: 'All',
            categories_all_count: 'All items',
            product_badge_hot: '🔥 Bestseller',
            add_to_cart: 'Add to cart',
            footer_about_text: 'GlowDrinks brings refined drink experiences and style using the best clean ingredients.',
            footer_title_explore: 'Explore',
            footer_links_home: 'Home',
            footer_links_menu: 'Drinks Menu',
            footer_links_contact: 'Contact - Feedback',
            footer_title_hours: 'Opening Hours',
            footer_hours_weekdays: 'Mon - Fri: 07:00 - 22:30',
            footer_hours_weekend: 'Sat - Sun: 08:00 - 23:00',
            footer_hotline_label: 'Fast delivery hotline:',
            footer_hotline: '1900 6868',
            footer_address: '123 Ba Thang Hai St, Dist 10, HCMC',
            footer_email: 'lienhe@glowdrinks.com',
            footer_payment_methods: 'MoMo, VNPay, Bank transfer, COD',
            footer_copyright: '© 2026 GlowDrinks Store. All rights reserved.',
            footer_designer: 'Designed by Antigravity AI'
            ,
            // misc
            search_placeholder: 'Search drinks...'
            , cart_title: 'Cart'
            , product_off_label: 'Off'
            , footer_title_contact: 'Contact Address'
            , item_label: 'items'
        },
        ja: {
            nav_home: 'ホーム', nav_menu: 'メニュー', nav_orders: '注文', nav_game: '🎮 3Dアヒルゲーム', nav_contact: 'お問い合わせ', nav_admin: '管理',
            search_btn: '検索', login_btn: 'ログイン', logout_btn: 'ログアウト', greeting: 'こんにちは',
            menu_title: 'おすすめ <span>ドリンク</span>', back_home: '← ホームへ戻る', nothing_found: '商品が見つかりません！',
            empty_hint: '別のキーワードで検索するか、別のカテゴリを選んでください。', back_home_button: '↩ ホームへ戻る'
            ,
            duck_bubble_title: '🦆 アヒルAIが散歩中！ 👋',
            duck_bubble_text: 'こんにちは！アヒルアシスタントです。サイト内を回って手伝います—何でも聞いてください！',
            duck_header_title: 'アヒルAI',
            duck_header_status: '🟢 GlowDrinks アシスタント（オンライン）',
            duck_welcome_html: 'こんにちは！ 👋 <strong>GlowDrinks</strong>へようこそ！<br><br>私は <strong>アヒルアシスタント</strong> です。メニュー、注文、支払いの案内ができます。<br><br><strong>今日は何をお手伝いしましょうか？</strong>',
            duck_chip_play: '🎮 3Dアヒルゲームをプレイ（割引コード獲得）',
            duck_chip_best: '🥤 人気のドリンクは？',
            duck_chip_delivery: '🛵 配達はどのくらい？',
            duck_chip_qr: '📱 QR決済の方法？',
            duck_chip_order: '🛒 注文方法',
            duck_input_placeholder: 'アヒルAIに何でも聞いてください...'
            ,
            // hero/promo/categories/footer (JA)
            hero_badge: '🔥 半径2km以内配送料無料',
            hero_title_line1: 'クリーンドリンク、',
            hero_title_highlight: 'クリエイティブなエネルギー！',
            hero_sub: '深いベトナムコーヒーやフレッシュなフルーツティーで新しい一日を迎えましょう。',
            hero_btn_order: '🛒 今すぐ注文',
            hero_btn_contact: '📞 サポートに連絡',
            promo_tag_big: '🔥 本日の特価',
            promo_title_big: 'ミルクティー＆コーヒー 全品15%OFF',
            promo_sub_big: '50,000₫以上の注文に適用',
            promo_btn_big: '今すぐ見る →',
            promo_tag_small: '🎮 VIPコード',
            promo_title_small: 'ゲームで賞品ゲット',
            promo_sub_small: 'アヒル3D – トップ10は特典！',
            promo_btn_small: 'プレイする 🦆',
            categories_title: 'カテゴリ <span>メニュー</span>',
            categories_view_all: 'すべて表示 →',
            categories_all: 'すべて',
            categories_all_count: '全商品',
            product_badge_hot: '🔥 ベストセラー',
            add_to_cart: 'カートに追加',
            footer_about_text: 'GlowDrinksは、最高のクリーン素材で洗練されたドリンク体験を提供します。',
            footer_title_explore: '探索',
            footer_links_home: 'ホーム',
            footer_links_menu: 'ドリンクメニュー',
            footer_links_contact: 'お問い合わせ',
            footer_title_hours: '営業時間',
            footer_hours_weekdays: '月〜金: 07:00 - 22:30',
            footer_hours_weekend: '土日: 08:00 - 23:00',
            footer_hotline_label: '配達ホットライン:',
            footer_hotline: '1900 6868',
            footer_address: 'Ba Thang Hai通り123, 第10区, ホーチミン市',
            footer_email: 'lienhe@glowdrinks.com',
            footer_payment_methods: 'MoMo, VNPay, 銀行振込, 代金引換',
            footer_copyright: '© 2026 GlowDrinks Store. 全著作権所有。',
            footer_designer: 'Antigravity AI によるデザイン'
                ,
                // misc
                search_placeholder: 'ドリンクを検索...'
                , cart_title: 'カート'
                , product_off_label: 'オフ'
                , footer_title_contact: 'お問い合わせ'
                , item_label: '件'
        },
        zh: {
            nav_home: '首页', nav_menu: '菜单', nav_orders: '订单', nav_game: '🎮 3D鸭子游戏', nav_contact: '联系', nav_admin: '管理',
            search_btn: '搜索', login_btn: '登录', logout_btn: '退出', greeting: '你好',
            menu_title: '推荐 <span>饮品</span>', back_home: '← 返回首页', nothing_found: '未找到商品！',
            empty_hint: '请尝试其他关键词或选择其他分类。', back_home_button: '↩ 返回首页'
            ,
            duck_bubble_title: '🦆 鸭子AI在散步！ 👋',
            duck_bubble_text: '嗨！我是鸭子助手。我会在网站巡游来帮助你—随时问我任何问题！',
            duck_header_title: '鸭子AI',
            duck_header_status: '🟢 GlowDrinks 助手（在线）',
            duck_welcome_html: '你好！ 👋 欢迎来到 <strong>GlowDrinks</strong>！<br><br>我是 <strong>鸭子助手</strong>，可以帮助你查看菜单、下单或支付说明。<br><br><strong>我今天能为你做些什么？</strong>',
            duck_chip_play: '🎮 玩3D鸭子游戏（获得折扣码）',
            duck_chip_best: '🥤 畅销饮品？',
            duck_chip_delivery: '🛵 多久能送达？',
            duck_chip_qr: '📱 如何用QR付款？',
            duck_chip_order: '🛒 如何下单',
            duck_input_placeholder: '向鸭子AI提问任何问题...'
            ,
            // zh (simplified) hero/promo/categories/footer
            hero_badge: '🔥 半径2公里内免费配送',
            hero_title_line1: '干净饮品，',
            hero_title_highlight: '创意能量！',
            hero_sub: '用越南风味浓郁的咖啡或新鲜果茶迎接新的一天，GlowDrinks 为你带来独特风味。',
            hero_btn_order: '🛒 立即订购',
            hero_btn_contact: '📞 联系支持',
            promo_tag_big: '🔥 今日优惠',
            promo_title_big: '奶茶与咖啡 全场15%折扣',
            promo_sub_big: '适用于50,000₫以上订单',
            promo_btn_big: '查看 →',
            promo_tag_small: '🎮 VIP码',
            promo_title_small: '玩游戏<br>获取优惠券',
            promo_sub_small: '鸭子3D – 前10名获得奖励！',
            promo_btn_small: '立即玩 🦆',
            categories_title: '分类 <span>菜单</span>',
            categories_view_all: '查看全部 →',
            categories_all: '全部',
            categories_all_count: '全部商品',
            product_badge_hot: '🔥 畅销',
            add_to_cart: '加入购物车',
            footer_about_text: 'GlowDrinks 致力于使用优质健康食材，带来精致的饮品体验。',
            footer_title_explore: '探索',
            footer_links_home: '首页',
            footer_links_menu: '饮品菜单',
            footer_links_contact: '联系 - 反馈',
            footer_title_hours: '营业时间',
            footer_hours_weekdays: '周一 - 周五: 07:00 - 22:30',
            footer_hours_weekend: '周六 - 周日: 08:00 - 23:00',
            footer_hotline_label: '快速配送热线:',
            footer_hotline: '1900 6868',
            footer_address: '123 Ba Thang Hai 街, 第10区, 胡志明市',
            footer_email: 'lienhe@glowdrinks.com',
            footer_payment_methods: 'MoMo, VNPay, 银行转账, 货到付款',
            footer_copyright: '© 2026 GlowDrinks Store。版权所有。',
            footer_designer: 'Antigravity AI 设计'
                ,
                // misc
                search_placeholder: '搜索饮品...'
                , cart_title: '购物车'
                , product_off_label: '折'
                , footer_title_contact: '联系地址'
                , item_label: '件'
        }
    };

    function syncLanguageButtons(lang) {
        document.querySelectorAll('.lang-pill').forEach((button) => {
            button.classList.toggle('active', button.dataset.lang === lang);
        });
    }

    function applyLanguage(lang) {
        const dictionary = localeMap[lang] || localeMap.vi;
        document.querySelectorAll('[data-i18n]').forEach((element) => {
            const key = element.getAttribute('data-i18n');
            if (dictionary[key]) {
                element.innerHTML = dictionary[key];
            }
        });

        // set placeholders for inputs marked with data-i18n-placeholder
        document.querySelectorAll('[data-i18n-placeholder]').forEach((el) => {
            const key = el.getAttribute('data-i18n-placeholder');
            if (dictionary[key]) el.placeholder = dictionary[key];
        });

        // set title attributes for elements marked with data-i18n-title
        document.querySelectorAll('[data-i18n-title]').forEach((el) => {
            const key = el.getAttribute('data-i18n-title');
            if (dictionary[key]) el.setAttribute('title', dictionary[key]);
        });

        const menuTitle = document.querySelector('[data-page-title]');
        if (menuTitle && dictionary.menu_title) {
            menuTitle.innerHTML = dictionary.menu_title;
        }

        syncLanguageButtons(lang);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.lang-pill').forEach((button) => {
            button.addEventListener('click', function () {
                const lang = button.dataset.lang;
                applyLanguage(lang);
                try { localStorage.setItem('site_lang', lang); } catch(e){}
                // Persist selection server-side via cookie so PHP can render products in that language.
                try { document.cookie = 'site_lang=' + lang + '; path=/; max-age=' + (60*60*24*365); } catch(e) {}
                // reload to let server render localized product names/descriptions
                setTimeout(() => { location.reload(); }, 120);
            });
        });
        const saved = (function(){ try { return localStorage.getItem('site_lang'); } catch(e){ return null; } })();
        applyLanguage(saved || 'vi');
    });
</script>
