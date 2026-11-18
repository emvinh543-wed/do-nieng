<?php
// Config inclusion with fallback
if (file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
} elseif (file_exists(__DIR__ . '/../Trang chinh/config/config.php')) {
    require_once __DIR__ . '/../Trang chinh/config/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get logged in user info
$logged_in_user = null;
if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT id, fullname, username, email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $logged_in_user = $stmt->fetch();
    } catch (Exception $e) {}
}

// Handle AJAX Save/Load/Voucher API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['api'])) {
    header('Content-Type: application/json');
    $api = $_GET['api'];

    if ($api === 'save_game') {
        $saveData = file_get_contents('php://input');
        if ($logged_in_user && isset($pdo)) {
            try {
                // Table auto creation if needed
                $pdo->exec("CREATE TABLE IF NOT EXISTS farm_saves (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNIQUE,
                    save_data JSON,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )");
                $stmt = $pdo->prepare("INSERT INTO farm_saves (user_id, save_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE save_data = VALUES(save_data)");
                $stmt->execute([$logged_in_user['id'], $saveData]);
                echo json_encode(['status' => 'success', 'message' => 'Đã lưu game lên đám mây!']);
                exit();
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                exit();
            }
        }
        echo json_encode(['status' => 'local_only', 'message' => 'Lưu game vào LocalStorage thành công!']);
        exit();
    }

    if ($api === 'redeem_voucher') {
        $data = json_decode(file_get_contents('php://input'), true);
        $cost = intval($data['cost'] ?? 0);
        $code = strtoupper(trim($data['code'] ?? 'GLOWFARM10'));

        if ($cost > 0) {
            if (isset($_SESSION['user_id']) && isset($pdo)) {
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        code VARCHAR(50) UNIQUE,
                        discount_percent INT,
                        min_order_amount DECIMAL(10,2) DEFAULT 0,
                        expiry_date DATE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                    $stmt = $pdo->prepare("INSERT IGNORE INTO coupons (code, discount_percent, min_order_amount, expiry_date) VALUES (?, ?, 0, '2026-12-31')");
                    $discount = ($code === 'GLOWFARM25') ? 25 : 15;
                    $stmt->execute([$code, $discount]);
                } catch (Exception $e) {}
            }
            echo json_encode(['status' => 'success', 'code' => $code, 'message' => 'Đổi voucher thành công! Nhập mã này tại trang checkout.']);
            exit();
        }
        echo json_encode(['status' => 'error', 'message' => 'Số Gold không đủ!']);
        exit();
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GlowFarm 🌿 — Nông Trại Pixel 2D | GlowDrinks</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=VT323&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --pixel-font: 'VT323', monospace;
            --sans-font: 'Plus Jakarta Sans', sans-serif;
            --farm-green: #2e7d32;
            --farm-gold: #ffc107;
            --farm-bg: #1b261e;
            --panel-bg: rgba(27, 38, 30, 0.92);
            --border-pixel: #8d6e63;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; user-select: none; }
        body {
            background-color: var(--farm-bg);
            color: #f1f8e9;
            font-family: var(--sans-font);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow-x: hidden;
        }

        /* Top Header Bar */
        .top-nav {
            width: 100%;
            background: linear-gradient(180deg, #2d3e31 0%, #1c271e 100%);
            border-bottom: 3px solid #4d6652;
            padding: 10px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
            z-index: 100;
        }

        .brand-title {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: white;
        }
        .brand-title span.logo-icon { font-size: 2rem; }
        .brand-title h1 { font-family: var(--pixel-font); font-size: 2.2rem; color: #a5d6a7; text-shadow: 2px 2px #1b5e20; letter-spacing: 1px; }
        .brand-subtitle { font-size: 0.8rem; color: #81c784; font-weight: 600; }

        .nav-links { display: flex; align-items: center; gap: 15px; }
        .nav-btn {
            background: #384d3d;
            border: 2px solid #5b7a62;
            color: #e8f5e9;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .nav-btn:hover { background: #4d6853; border-color: #81c784; transform: translateY(-2px); }

        /* Main Game Layout */
        .game-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px;
            width: 100%;
            max-width: 1100px;
            gap: 12px;
        }

        /* Top HUD Stats */
        .hud-bar {
            width: 100%;
            background: var(--panel-bg);
            border: 3px solid #5d4037;
            border-radius: 16px;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
            box-shadow: inset 0 0 10px rgba(0,0,0,0.5), 0 8px 25px rgba(0,0,0,0.4);
            flex-wrap: wrap;
            gap: 10px;
        }

        .hud-stat {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(0,0,0,0.3);
            padding: 6px 14px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .hud-stat .icon { font-size: 1.4rem; }
        .hud-stat .label { font-size: 0.75rem; color: #a5d6a7; text-transform: uppercase; font-weight: 700; }
        .hud-stat .val { font-family: var(--pixel-font); font-size: 1.6rem; color: #fff59d; font-weight: bold; }

        /* Canvas Screen Frame */
        .canvas-frame {
            position: relative;
            border: 6px solid #4e342e;
            border-radius: 12px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.7), inset 0 0 15px rgba(0,0,0,0.8);
            background: #000;
            overflow: hidden;
        }
        #farmCanvas {
            display: block;
            image-rendering: pixelated;
            image-rendering: crisp-edges;
            cursor: pointer;
        }

        /* Canvas Floating Action Buttons */
        .canvas-overlay-btns {
            position: absolute;
            top: 12px;
            right: 12px;
            display: flex;
            gap: 8px;
            z-index: 10;
        }
        .overlay-btn {
            background: rgba(46, 125, 50, 0.85);
            border: 2px solid #81c784;
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-family: var(--pixel-font);
            font-size: 1.2rem;
            cursor: pointer;
            backdrop-filter: blur(6px);
            transition: all 0.15s;
        }
        .overlay-btn:hover { background: #388e3c; transform: scale(1.05); }

        /* Hotbar / Toolbar */
        .hotbar-container {
            width: 100%;
            background: var(--panel-bg);
            border: 3px solid #5d4037;
            border-radius: 16px;
            padding: 12px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        }

        .tools-list {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .tool-slot {
            width: 64px;
            height: 64px;
            background: rgba(0,0,0,0.4);
            border: 3px solid #795548;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            position: relative;
            transition: all 0.2s;
        }
        .tool-slot:hover { border-color: #ffb74d; background: rgba(255,255,255,0.08); transform: translateY(-3px); }
        .tool-slot.active { border-color: #ffd54f; background: rgba(255, 193, 7, 0.25); box-shadow: 0 0 15px rgba(255, 213, 79, 0.4); }
        .tool-slot .tool-icon { font-size: 1.8rem; }
        .tool-slot .tool-name { font-size: 0.68rem; font-weight: 700; color: #d7ccc8; margin-top: 2px; text-align: center; }
        .tool-slot .seed-qty {
            position: absolute;
            top: 2px;
            right: 4px;
            font-family: var(--pixel-font);
            font-size: 1.1rem;
            color: #a5d6a7;
            font-weight: bold;
        }

        .action-btns { display: flex; gap: 10px; }
        .act-btn {
            padding: 10px 20px;
            border-radius: 12px;
            font-family: var(--pixel-font);
            font-size: 1.4rem;
            border: 2px solid transparent;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            transition: all 0.2s;
        }
        .btn-shop { background: linear-gradient(135deg, #f57c00, #e65100); color: white; border-color: #ffb74d; }
        .btn-shop:hover { transform: scale(1.05); box-shadow: 0 6px 20px rgba(245, 124, 0, 0.5); }
        .btn-sleep { background: linear-gradient(135deg, #1976d2, #0d47a1); color: white; border-color: #64b5f6; }
        .btn-sleep:hover { transform: scale(1.05); box-shadow: 0 6px 20px rgba(25, 118, 210, 0.5); }
        .btn-save { background: linear-gradient(135deg, #388e3c, #1b5e20); color: white; border-color: #81c784; }
        .btn-save:hover { transform: scale(1.05); box-shadow: 0 6px 20px rgba(56, 142, 60, 0.5); }

        /* Game Modals */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(8px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            animation: fadeIn 0.25s ease;
        }
        .modal-box {
            background: #263238;
            border: 4px solid #8d6e63;
            border-radius: 20px;
            width: 720px;
            max-width: 94%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.8);
            position: relative;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #455a64;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .modal-header h2 { font-family: var(--pixel-font); font-size: 2rem; color: #ffb74d; }
        .close-modal { font-size: 1.8rem; color: #b0bec5; cursor: pointer; background: none; border: none; }
        .close-modal:hover { color: #ff5252; }

        /* Shop Grid */
        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px;
        }
        .shop-card {
            background: #37474f;
            border: 2px solid #546e7a;
            border-radius: 14px;
            padding: 14px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .shop-card:hover { border-color: #ffb74d; transform: translateY(-3px); }
        .shop-card .icon { font-size: 2.8rem; }
        .shop-card .title { font-weight: 800; font-size: 1.05rem; color: #fff; }
        .shop-card .desc { font-size: 0.78rem; color: #b0bec5; min-height: 32px; }
        .shop-card .price { font-family: var(--pixel-font); font-size: 1.4rem; color: #ffd54f; font-weight: bold; }
        .buy-btn {
            width: 100%;
            background: #2e7d32;
            border: 1px solid #81c784;
            color: white;
            padding: 8px;
            border-radius: 8px;
            font-family: var(--pixel-font);
            font-size: 1.3rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .buy-btn:hover { background: #388e3c; }

        /* Voucher Box */
        .voucher-card {
            background: linear-gradient(135deg, #1b5e20, #2e7d32);
            border: 2px dashed #a5d6a7;
            border-radius: 16px;
            padding: 16px;
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .voucher-info h4 { color: #fff59d; font-size: 1.1rem; }
        .voucher-info p { color: #c8e6c9; font-size: 0.82rem; }

        /* Toast Notifications */
        #gameToast {
            position: fixed;
            bottom: 25px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(33, 33, 33, 0.94);
            border: 2px solid #ffb74d;
            color: white;
            padding: 10px 24px;
            border-radius: 30px;
            font-family: var(--pixel-font);
            font-size: 1.4rem;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s, transform 0.3s;
            z-index: 2000;
            box-shadow: 0 10px 30px rgba(0,0,0,0.6);
        }
        #gameToast.show { opacity: 1; transform: translateX(-50%) translateY(-10px); }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* Mobile controls hint */
        .controls-note {
            font-size: 0.82rem;
            color: #90a4ae;
            text-align: center;
            margin-top: 6px;
        }
        .key-badge { background: #455a64; color: #fff; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
    </style>
</head>
<body>

    <!-- Top Navigation -->
    <header class="top-nav">
        <a href="/index/?type=home" class="brand-title">
            <span class="logo-icon">🌿</span>
            <div>
                <h1>GlowFarm Pixel 2D</h1>
                <div class="brand-subtitle">Nông Trại Trồng Cây Đổi Voucher GlowDrinks</div>
            </div>
        </a>
        <div class="nav-links">
            <a href="/Game/duck_game.php" class="nav-btn">🐥 Game Vịt 3D</a>
            <a href="/index/?type=products" class="nav-btn">🍹 Thực Đơn</a>
            <button class="nav-btn" onclick="toggleSound()"><span id="soundIcon">🔊</span> Âm Thanh</button>
            <?php if ($logged_in_user): ?>
                <div style="font-size:0.85rem;color:#a5d6a7;font-weight:700;">🟢 <?php echo htmlspecialchars($logged_in_user['fullname']); ?></div>
            <?php else: ?>
                <a href="/login/login_demo.php" class="nav-btn" style="border-color:#ffb74d;color:#ffe082;">🔑 Đăng Nhập</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Game Section -->
    <div class="game-wrapper">

        <!-- Top HUD Bar -->
        <div class="hud-bar">
            <div class="hud-stat">
                <span class="icon">💰</span>
                <div>
                    <div class="label">Vàng (Gold)</div>
                    <div class="val" id="goldVal">150G</div>
                </div>
            </div>
            <div class="hud-stat">
                <span class="icon">☀️</span>
                <div>
                    <div class="label">Ngày</div>
                    <div class="val" id="dayVal">Ngày 1</div>
                </div>
            </div>
            <div class="hud-stat">
                <span class="icon">⏰</span>
                <div>
                    <div class="label">Thời Gian</div>
                    <div class="val" id="timeVal">08:00</div>
                </div>
            </div>
            <div class="hud-stat">
                <span class="icon">⚡</span>
                <div>
                    <div class="label">Thể Lực</div>
                    <div class="val" id="staminaVal">100/100</div>
                </div>
            </div>
            <div class="hud-stat">
                <span class="icon">🌾</span>
                <div>
                    <div class="label">Nông Sản Đã Thu</div>
                    <div class="val" id="harvestVal">0</div>
                </div>
            </div>
        </div>

        <!-- Canvas Game Screen -->
        <div class="canvas-frame">
            <div class="canvas-overlay-btns">
                <button class="overlay-btn" onclick="autoWaterCrops()" title="Tưới toàn bộ ô đất">💦 Tưới Tự Động</button>
                <button class="overlay-btn" onclick="toggleGridLines()" title="Bật/Tắt đường lưới">🌐 Lưới</button>
            </div>
            <canvas id="farmCanvas" width="800" height="480"></canvas>
        </div>

        <!-- Hotbar / Tools -->
        <div class="hotbar-container">
            <div class="tools-list" id="toolsList">
                <!-- Tool slots generated by JS -->
            </div>

            <div class="action-btns">
                <button class="act-btn btn-shop" onclick="openShopModal()">🛒 Cửa Hàng</button>
                <button class="act-btn btn-sleep" onclick="sleepNextDay()">🌙 Qua Ngày Mới</button>
                <button class="act-btn btn-save" onclick="saveGameCloud()">💾 Lưu Game</button>
            </div>
        </div>

        <div class="controls-note">
            💡 Điều khiển: Dùng phím <span class="key-badge">W</span><span class="key-badge">A</span><span class="key-badge">S</span><span class="key-badge">D</span> hoặc phím Mũi Tên để di chuyển nhân vật • Click chuột vào ô đất để cày, tưới, gieo hạt hoặc thu hoạch!
        </div>
    </div>

    <!-- Shop Modal -->
    <div class="modal-backdrop" id="shopModal">
        <div class="modal-box">
            <div class="modal-header">
                <h2>🛒 CỬA HÀNG NÔNG DÂN & VOUCHER</h2>
                <button class="close-modal" onclick="closeShopModal()">✕</button>
            </div>

            <h3 style="color:#a5d6a7;font-family:var(--pixel-font);font-size:1.5rem;margin-bottom:10px;">🌾 MUA HẠT GIỐNG</h3>
            <div class="shop-grid" id="seedShopGrid">
                <!-- Seed items -->
            </div>

            <h3 style="color:#a5d6a7;font-family:var(--pixel-font);font-size:1.5rem;margin-top:24px;margin-bottom:10px;">🧺 BÁN NÔNG SẢN ĐÃ THU HOẠCH</h3>
            <div class="shop-grid" id="sellShopGrid">
                <!-- Sell items -->
            </div>

            <h3 style="color:#a5d6a7;font-family:var(--pixel-font);font-size:1.5rem;margin-top:24px;margin-bottom:10px;">🎁 ĐỔI GOLD LẤY VOUCHER GLOWDRINKS</h3>
            <div class="voucher-card">
                <div class="voucher-info">
                    <h4>🎟️ Voucher Giảm 15% Toàn Đơn Hàng</h4>
                    <p>Yêu cầu: 300 Gold | Áp dụng khi mua nước trên GlowDrinks</p>
                </div>
                <button class="buy-btn" style="width:auto;padding:8px 16px;" onclick="redeemVoucher(300, 'GLOWFARM15')">300G ➔ Đổi Mã</button>
            </div>
            <div class="voucher-card" style="background:linear-gradient(135deg, #e65100, #f57c00);">
                <div class="voucher-info">
                    <h4>🎟️ Voucher Giảm 25% Đặc Biệt</h4>
                    <p>Yêu cầu: 500 Gold | Hạn dùng 2026</p>
                </div>
                <button class="buy-btn" style="width:auto;padding:8px 16px;background:#b71c1c;" onclick="redeemVoucher(500, 'GLOWFARM25')">500G ➔ Đổi Mã</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="gameToast">Đã tưới nước thành công!</div>

    <!-- Game Engine Logic Script -->
    <script>
    /* ============================================================
       GLOWFARM 2D PIXEL GAME ENGINE
       HTML5 Canvas pixel-art renderer with full farming logic
    ============================================================ */

    const CANVAS_W = 800;
    const CANVAS_H = 480;
    const TILE_SIZE = 32;
    const GRID_COLS = 25; // 25 * 32 = 800
    const GRID_ROWS = 15; // 15 * 32 = 480

    // Crop Catalog Definition
    const CROPS = {
        strawberry: { name: 'Dâu Tây 🍓',     cost: 10, sellPrice: 25, growDays: 2, icon: '🍓', color: '#e53935' },
        carrot:     { name: 'Cà Rốt 🥕',      cost: 8,  sellPrice: 18, growDays: 2, icon: '🥕', color: '#fb8c00' },
        tomato:     { name: 'Cà Chua 🍅',     cost: 15, sellPrice: 35, growDays: 3, icon: '🍅', color: '#d32f2f' },
        corn:       { name: 'Ngô Ngọt 🌽',    cost: 20, sellPrice: 50, growDays: 3, icon: '🌽', color: '#fbc02d' },
        bamboo:     { name: 'Măng Tre 🎋',    cost: 30, sellPrice: 75, growDays: 4, icon: '🎋', color: '#43a047' },
        coffee:     { name: 'Cà Phê Glow ☕', cost: 45, sellPrice: 120,growDays: 4, icon: '☕', color: '#6d4c41' },
        tea:        { name: 'Trà Xanh 🍵',    cost: 40, sellPrice: 110,growDays: 4, icon: '🍵', color: '#558b2f' }
    };

    // Tools Definition
    const TOOLS = [
        { id: 'hoe',     name: 'Cuốc Cày',   icon: '🧹' },
        { id: 'water',   name: 'Bình Tưới',  icon: '💧' },
        { id: 'seed_strawberry', name: 'Hạt Dâu', icon: '🍓', cropKey: 'strawberry' },
        { id: 'seed_carrot',     name: 'Hạt Cà Rốt', icon: '🥕', cropKey: 'carrot' },
        { id: 'seed_tomato',     name: 'Hạt Cà Chua', icon: '🍅', cropKey: 'tomato' },
        { id: 'seed_corn',       name: 'Hạt Ngô',   icon: '🌽', cropKey: 'corn' },
        { id: 'seed_bamboo',     name: 'Hạt Măng',  icon: '🎋', cropKey: 'bamboo' },
        { id: 'seed_coffee',     name: 'Hạt Cà Phê', icon: '☕', cropKey: 'coffee' },
        { id: 'seed_tea',        name: 'Hạt Trà',   icon: '🍵', cropKey: 'tea' },
        { id: 'hand',    name: 'Thu Hoạch',  icon: '✂️' }
    ];

    // Game State
    let gameState = {
        gold: 150,
        day: 1,
        timeHour: 8,
        timeMin: 0,
        stamina: 100,
        maxStamina: 100,
        totalHarvested: 0,
        selectedTool: 'hoe',
        seeds: { strawberry: 3, carrot: 3, tomato: 1, corn: 0, bamboo: 0, coffee: 0, tea: 0 },
        inventory: { strawberry: 0, carrot: 0, tomato: 0, corn: 0, bamboo: 0, coffee: 0, tea: 0 },
        tiles: [] // GRID_COLS x GRID_ROWS
    };

    // Player State
    let player = {
        x: 12 * TILE_SIZE,
        y: 8 * TILE_SIZE,
        speed: 2.5,
        dir: 'down', // 'down', 'up', 'left', 'right'
        isMoving: false,
        animFrame: 0,
        animTimer: 0
    };

    // Global System Controls
    let canvas, ctx;
    let keys = {};
    let soundEnabled = true;
    let showGrid = false;
    let audioCtx = null;

    // Web Audio Synthesizer for Pixel Sound Effects
    function playPixelSound(type) {
        if (!soundEnabled) return;
        try {
            if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') audioCtx.resume();

            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);

            const now = audioCtx.currentTime;
            if (type === 'hoe') {
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(150, now);
                osc.frequency.exponentialRampToValueAtTime(40, now + 0.1);
                gain.gain.setValueAtTime(0.3, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.1);
                osc.start(now); osc.stop(now + 0.1);
            } else if (type === 'water') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(400, now);
                osc.frequency.exponentialRampToValueAtTime(800, now + 0.15);
                gain.gain.setValueAtTime(0.25, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.15);
                osc.start(now); osc.stop(now + 0.15);
            } else if (type === 'plant') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(300, now);
                osc.frequency.exponentialRampToValueAtTime(500, now + 0.08);
                gain.gain.setValueAtTime(0.2, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.08);
                osc.start(now); osc.stop(now + 0.08);
            } else if (type === 'harvest') {
                osc.type = 'square';
                osc.frequency.setValueAtTime(523, now); // C5
                osc.frequency.setValueAtTime(659, now + 0.08); // E5
                osc.frequency.setValueAtTime(783, now + 0.16); // G5
                gain.gain.setValueAtTime(0.2, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.25);
                osc.start(now); osc.stop(now + 0.25);
            } else if (type === 'coin') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(987, now);
                osc.frequency.setValueAtTime(1318, now + 0.08);
                gain.gain.setValueAtTime(0.25, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.2);
                osc.start(now); osc.stop(now + 0.2);
            }
        } catch(e) {}
    }

    function toggleSound() {
        soundEnabled = !soundEnabled;
        document.getElementById('soundIcon').textContent = soundEnabled ? '🔊' : '🔇';
        showToast(soundEnabled ? 'Bật âm thanh sfx 🔊' : 'Tắt âm thanh 🔇');
    }

    // Initialize Map Grid
    function initMapGrid() {
        gameState.tiles = [];
        for (let r = 0; r < GRID_ROWS; r++) {
            let row = [];
            for (let c = 0; c < GRID_COLS; c++) {
                let type = 'grass';
                // Create natural pond in top right
                if (c >= 19 && c <= 23 && r >= 1 && r <= 4) {
                    type = 'water';
                } else if ((c === 18 && r >= 1 && r <= 4) || (r === 5 && c >= 19 && c <= 23)) {
                    type = 'water_edge';
                } else if (c >= 2 && c <= 16 && r >= 3 && r <= 12) {
                    type = 'soil_area'; // Main farming area
                }
                row.push({
                    type: type, // 'grass', 'soil_area', 'tilled', 'water'
                    watered: false,
                    crop: null // { type: 'strawberry', stage: 0..3, daysGrown: 0 }
                });
            }
            gameState.tiles.push(row);
        }
    }

    // --- GAME INITIALIZATION ---
    window.addEventListener('DOMContentLoaded', () => {
        canvas = document.getElementById('farmCanvas');
        ctx = canvas.getContext('2d');

        initMapGrid();
        loadLocalSave();
        renderHotbar();
        updateHUD();

        // Keyboard Event Listeners
        window.addEventListener('keydown', e => {
            keys[e.key.toLowerCase()] = true;
            if (['1','2','3','4','5','6','7','8','9','0'].includes(e.key)) {
                let idx = parseInt(e.key) - 1;
                if (idx === -1) idx = 9;
                if (TOOLS[idx]) selectTool(TOOLS[idx].id);
            }
        });
        window.addEventListener('keyup', e => { keys[e.key.toLowerCase()] = false; });

        // Mouse Click Interaction
        canvas.addEventListener('click', handleCanvasClick);

        // Start Game Loop
        requestAnimationFrame(gameLoop);

        // In-game Clock Interval (Every 5 seconds = 10 game minutes)
        setInterval(() => {
            gameState.timeMin += 10;
            if (gameState.timeMin >= 60) {
                gameState.timeMin = 0;
                gameState.timeHour++;
                if (gameState.timeHour >= 24) {
                    gameState.timeHour = 6;
                    sleepNextDay(true);
                }
            }
            updateHUD();
        }, 5000);
    });

    // Handle Clicking Canvas Tiles
    function handleCanvasClick(e) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;

        const clickX = (e.clientX - rect.left) * scaleX;
        const clickY = (e.clientY - rect.top) * scaleY;

        const col = Math.floor(clickX / TILE_SIZE);
        const row = Math.floor(clickY / TILE_SIZE);

        if (col < 0 || col >= GRID_COLS || row < 0 || row >= GRID_ROWS) return;

        const tile = gameState.tiles[row][col];
        const tool = gameState.selectedTool;

        // Tile Interaction Logic
        if (tool === 'hoe') {
            if (tile.type === 'soil_area' || tile.type === 'grass') {
                if (gameState.stamina >= 2) {
                    tile.type = 'tilled';
                    gameState.stamina -= 2;
                    playPixelSound('hoe');
                    showToast('Đã cày xới ô đất! 🧹');
                } else showToast('Bạn đã kiệt sức! Hãy ngủ qua ngày mới 🌙');
            }
        } else if (tool === 'water') {
            if (tile.type === 'tilled') {
                if (gameState.stamina >= 1) {
                    tile.watered = true;
                    gameState.stamina -= 1;
                    playPixelSound('water');
                    showToast('Đã tưới nước! 💧');
                } else showToast('Bạn đã kiệt sức! 🌙');
            }
        } else if (tool.startsWith('seed_')) {
            const cropKey = TOOLS.find(t => t.id === tool).cropKey;
            if (tile.type === 'tilled' && !tile.crop) {
                if (gameState.seeds[cropKey] > 0) {
                    gameState.seeds[cropKey]--;
                    tile.crop = { type: cropKey, stage: 0, daysGrown: 0 };
                    playPixelSound('plant');
                    renderHotbar();
                    showToast(`Đã gieo hạt ${CROPS[cropKey].name}! 🌱`);
                } else {
                    showToast(`Hết hạt giống ${CROPS[cropKey].name}! Hãy mua tại Cửa Hàng 🛒`);
                }
            }
        } else if (tool === 'hand') {
            if (tile.crop && tile.crop.stage >= 3) {
                const cKey = tile.crop.type;
                gameState.inventory[cKey] = (gameState.inventory[cKey] || 0) + 1;
                gameState.totalHarvested++;
                tile.crop = null;
                playPixelSound('harvest');
                showToast(`Thu hoạch thành công +1 ${CROPS[cKey].name}! 🧺`);
            }
        }

        updateHUD();
    }

    // --- GAME LOOP ---
    function gameLoop() {
        updatePlayer();
        renderScene();
        requestAnimationFrame(gameLoop);
    }

    // Player Movement Update
    function updatePlayer() {
        let dx = 0, dy = 0;
        if (keys['w'] || keys['arrowup'])    { dy -= 1; player.dir = 'up'; }
        if (keys['s'] || keys['arrowdown'])  { dy += 1; player.dir = 'down'; }
        if (keys['a'] || keys['arrowleft'])  { dx -= 1; player.dir = 'left'; }
        if (keys['d'] || keys['arrowright']) { dx += 1; player.dir = 'right'; }

        if (dx !== 0 && dy !== 0) {
            dx *= 0.7071;
            dy *= 0.7071;
        }

        if (dx !== 0 || dy !== 0) {
            player.isMoving = true;
            let nextX = player.x + dx * player.speed;
            let nextY = player.y + dy * player.speed;

            // Bounds check
            if (nextX >= 16 && nextX <= CANVAS_W - 32) player.x = nextX;
            if (nextY >= 16 && nextY <= CANVAS_H - 32) player.y = nextY;

            player.animTimer++;
            if (player.animTimer % 8 === 0) {
                player.animFrame = (player.animFrame + 1) % 4;
            }
        } else {
            player.isMoving = false;
            player.animFrame = 0;
        }
    }

    // --- PROCEDURAL CANVAS PIXEL ART RENDERING ---
    function renderScene() {
        ctx.clearRect(0, 0, CANVAS_W, CANVAS_H);

        // 1. Draw Map Tiles
        for (let r = 0; r < GRID_ROWS; r++) {
            for (let c = 0; c < GRID_COLS; c++) {
                const tile = gameState.tiles[r][c];
                const x = c * TILE_SIZE;
                const y = r * TILE_SIZE;

                // Base Tile Rendering
                if (tile.type === 'grass') {
                    ctx.fillStyle = (c + r) % 2 === 0 ? '#4caf50' : '#43a047';
                    ctx.fillRect(x, y, TILE_SIZE, TILE_SIZE);
                    // Add pixel grass blade detail
                    ctx.fillStyle = '#66bb6a';
                    ctx.fillRect(x + 6, y + 8, 2, 6);
                    ctx.fillRect(x + 20, y + 18, 2, 5);
                } else if (tile.type === 'soil_area') {
                    ctx.fillStyle = '#66bb6a';
                    ctx.fillRect(x, y, TILE_SIZE, TILE_SIZE);
                    // Light border outline for soil region
                    ctx.strokeStyle = 'rgba(0,0,0,0.05)';
                    ctx.strokeRect(x, y, TILE_SIZE, TILE_SIZE);
                } else if (tile.type === 'tilled') {
                    ctx.fillStyle = tile.watered ? '#4e342e' : '#795548'; // Darker if watered
                    ctx.fillRect(x, y, TILE_SIZE, TILE_SIZE);
                    // Tilled ridges
                    ctx.fillStyle = tile.watered ? '#3e2723' : '#5d4037';
                    ctx.fillRect(x + 4, y + 6, 24, 4);
                    ctx.fillRect(x + 4, y + 14, 24, 4);
                    ctx.fillRect(x + 4, y + 22, 24, 4);
                } else if (tile.type === 'water') {
                    ctx.fillStyle = '#29b6f6';
                    ctx.fillRect(x, y, TILE_SIZE, TILE_SIZE);
                    // Water animation shine
                    ctx.fillStyle = '#81d4fa';
                    let offset = Math.floor(Date.now() / 300) % 8;
                    ctx.fillRect(x + 4 + offset, y + 10, 10, 3);
                    ctx.fillRect(x + 14 - offset, y + 20, 8, 2);
                } else if (tile.type === 'water_edge') {
                    ctx.fillStyle = '#81c784';
                    ctx.fillRect(x, y, TILE_SIZE, TILE_SIZE);
                }

                // Render Crop Sprites
                if (tile.crop) {
                    drawCropSprite(ctx, x, y, tile.crop);
                }

                // Grid lines toggle
                if (showGrid) {
                    ctx.strokeStyle = 'rgba(255,255,255,0.15)';
                    ctx.strokeRect(x, y, TILE_SIZE, TILE_SIZE);
                }
            }
        }

        // 2. Draw Farm Decorative Structures
        drawBarnStructure(ctx, 0, 0); // Barn in top left

        // 3. Draw Player Sprite
        drawPlayerSprite(ctx, player.x, player.y);

        // 4. Day / Night Lighting Overlay
        let hour = gameState.timeHour;
        if (hour >= 19 || hour < 5) {
            // Night
            ctx.fillStyle = 'rgba(10, 15, 45, 0.45)';
            ctx.fillRect(0, 0, CANVAS_W, CANVAS_H);
        } else if (hour >= 17 && hour < 19) {
            // Sunset
            ctx.fillStyle = 'rgba(230, 81, 0, 0.15)';
            ctx.fillRect(0, 0, CANVAS_W, CANVAS_H);
        }
    }

    // Procedural Barn Sprite
    function drawBarnStructure(ctx, x, y) {
        // Red Barn Wall
        ctx.fillStyle = '#c62828';
        ctx.fillRect(x + 8, y + 16, 80, 50);
        // Roof
        ctx.fillStyle = '#424242';
        ctx.beginPath();
        ctx.moveTo(x, y + 16);
        ctx.lineTo(x + 48, y);
        ctx.lineTo(x + 96, y + 16);
        ctx.closePath();
        ctx.fill();
        // Door
        ctx.fillStyle = '#d7ccc8';
        ctx.fillRect(x + 36, y + 36, 24, 30);
        ctx.fillStyle = '#5d4037';
        ctx.fillRect(x + 46, y + 36, 4, 30);
    }

    // Procedural Crop Sprite Renderer
    function drawCropSprite(ctx, x, y, crop) {
        const cData = CROPS[crop.type];
        const stage = crop.stage;

        if (stage === 0) { // Seed / Sprout
            ctx.fillStyle = '#8d6e63';
            ctx.fillRect(x + 14, y + 20, 4, 4);
            ctx.fillStyle = '#66bb6a';
            ctx.fillRect(x + 15, y + 14, 2, 6);
        } else if (stage === 1) { // Small Plant
            ctx.fillStyle = '#43a047';
            ctx.fillRect(x + 12, y + 12, 8, 12);
            ctx.fillRect(x + 8, y + 16, 16, 4);
        } else if (stage === 2) { // Growing Plant
            ctx.fillStyle = '#2e7d32';
            ctx.fillRect(x + 10, y + 8, 12, 18);
            ctx.fillRect(x + 6, y + 12, 20, 6);
        } else if (stage >= 3) { // Harvestable Fruit!
            ctx.fillStyle = '#2e7d32';
            ctx.fillRect(x + 8, y + 6, 16, 20);
            // Fruit Color Pixel
            ctx.fillStyle = cData.color;
            ctx.fillRect(x + 6, y + 10, 8, 8);
            ctx.fillRect(x + 18, y + 12, 8, 8);
            ctx.fillRect(x + 12, y + 18, 8, 8);
        }
    }

    // Procedural Player Sprite Renderer
    function drawPlayerSprite(ctx, x, y) {
        // Shadow
        ctx.fillStyle = 'rgba(0,0,0,0.3)';
        ctx.beginPath();
        ctx.ellipse(x + 16, y + 30, 10, 4, 0, 0, Math.PI * 2);
        ctx.fill();

        // Pants (Blue)
        ctx.fillStyle = '#1565c0';
        let legOffset = player.isMoving ? (player.animFrame % 2 === 0 ? 2 : -2) : 0;
        ctx.fillRect(x + 10 + legOffset, y + 22, 5, 8);
        ctx.fillRect(x + 17 - legOffset, y + 22, 5, 8);

        // Body / Shirt (Red Overalls)
        ctx.fillStyle = '#e53935';
        ctx.fillRect(x + 9, y + 12, 14, 11);

        // Head / Skin
        ctx.fillStyle = '#ffcc80';
        ctx.fillRect(x + 10, y + 4, 12, 9);

        // Farmer Straw Hat (Yellow)
        ctx.fillStyle = '#fbc02d';
        ctx.fillRect(x + 4, y + 2, 24, 4);
        ctx.fillRect(x + 8, y - 3, 16, 5);

        // Eyes based on Direction
        ctx.fillStyle = '#212121';
        if (player.dir === 'down') {
            ctx.fillRect(x + 12, y + 7, 2, 2);
            ctx.fillRect(x + 18, y + 7, 2, 2);
        } else if (player.dir === 'left') {
            ctx.fillRect(x + 11, y + 7, 2, 2);
        } else if (player.dir === 'right') {
            ctx.fillRect(x + 19, y + 7, 2, 2);
        }
    }

    // --- HUD & TOOLBAR UPDATES ---
    function updateHUD() {
        document.getElementById('goldVal').textContent = gameState.gold + 'G';
        document.getElementById('dayVal').textContent = 'Ngày ' + gameState.day;
        document.getElementById('timeVal').textContent =
            (gameState.timeHour < 10 ? '0' : '') + gameState.timeHour + ':' +
            (gameState.timeMin < 10 ? '0' : '') + gameState.timeMin;
        document.getElementById('staminaVal').textContent = gameState.stamina + '/' + gameState.maxStamina;
        document.getElementById('harvestVal').textContent = gameState.totalHarvested;
    }

    function renderHotbar() {
        const container = document.getElementById('toolsList');
        container.innerHTML = '';

        TOOLS.forEach(tool => {
            const slot = document.createElement('div');
            slot.className = 'tool-slot' + (gameState.selectedTool === tool.id ? ' active' : '');
            slot.onclick = () => selectTool(tool.id);

            let qtyBadge = '';
            if (tool.cropKey) {
                const count = gameState.seeds[tool.cropKey] || 0;
                qtyBadge = `<span class="seed-qty">${count}</span>`;
            }

            slot.innerHTML = `
                ${qtyBadge}
                <span class="tool-icon">${tool.icon}</span>
                <span class="tool-name">${tool.name}</span>
            `;
            container.appendChild(slot);
        });
    }

    function selectTool(id) {
        gameState.selectedTool = id;
        renderHotbar();
    }

    // Auto Water Helper
    function autoWaterCrops() {
        let count = 0;
        for (let r = 0; r < GRID_ROWS; r++) {
            for (let c = 0; c < GRID_COLS; c++) {
                if (gameState.tiles[r][c].type === 'tilled' && !gameState.tiles[r][c].watered) {
                    gameState.tiles[r][c].watered = true;
                    count++;
                }
            }
        }
        playPixelSound('water');
        showToast(`Đã tưới nước cho ${count} ô đất! 💦`);
    }

    function toggleGridLines() {
        showGrid = !showGrid;
        showToast(showGrid ? 'Đã bật đường lưới 🌐' : 'Tắt đường lưới');
    }

    // Advance Day (Sleep)
    function sleepNextDay(auto = false) {
        gameState.day++;
        gameState.timeHour = 6;
        gameState.timeMin = 0;
        gameState.stamina = gameState.maxStamina;

        // Grow Crops
        for (let r = 0; r < GRID_ROWS; r++) {
            for (let c = 0; c < GRID_COLS; c++) {
                const tile = gameState.tiles[r][c];
                if (tile.crop && tile.watered) {
                    tile.crop.daysGrown++;
                    const maxDays = CROPS[tile.crop.type].growDays;
                    if (tile.crop.daysGrown >= maxDays) {
                        tile.crop.stage = 3;
                    } else if (tile.crop.daysGrown >= Math.floor(maxDays / 2)) {
                        tile.crop.stage = 2;
                    } else {
                        tile.crop.stage = 1;
                    }
                }
                // Reset water status
                tile.watered = false;
            }
        }

        saveLocalSave();
        updateHUD();
        showToast(auto ? 'Trời đã tối! Bạn thiếp đi và thức dậy lúc 6:00 sáng ☀️' : `Chào ngày mới! Đã sang Ngày ${gameState.day} ☀️`);
    }

    // --- SHOP & VOUCHER MODAL ---
    function openShopModal() {
        renderShopModal();
        document.getElementById('shopModal').style.display = 'flex';
    }

    function closeShopModal() {
        document.getElementById('shopModal').style.display = 'none';
    }

    function renderShopModal() {
        // Render Seed Shop
        const seedGrid = document.getElementById('seedShopGrid');
        seedGrid.innerHTML = '';
        Object.keys(CROPS).forEach(k => {
            const crop = CROPS[k];
            const card = document.createElement('div');
            card.className = 'shop-card';
            card.innerHTML = `
                <div class="icon">${crop.icon}</div>
                <div class="title">Hạt ${crop.name}</div>
                <div class="desc">Thời gian lớn: ${crop.growDays} ngày. Bán: ${crop.sellPrice}G</div>
                <div class="price">${crop.cost}G</div>
                <button class="buy-btn" onclick="buySeed('${k}')">Mua Hạt</button>
            `;
            seedGrid.appendChild(card);
        });

        // Render Inventory Sell Shop
        const sellGrid = document.getElementById('sellShopGrid');
        sellGrid.innerHTML = '';
        Object.keys(CROPS).forEach(k => {
            const crop = CROPS[k];
            const owned = gameState.inventory[k] || 0;
            const card = document.createElement('div');
            card.className = 'shop-card';
            card.innerHTML = `
                <div class="icon">${crop.icon}</div>
                <div class="title">${crop.name}</div>
                <div class="desc">Đang có: <strong>${owned}</strong> cái</div>
                <div class="price">+${crop.sellPrice}G/cái</div>
                <button class="buy-btn" style="background:#f57c00;" ${owned <= 0 ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : ''} onclick="sellCrop('${k}')">Bán Hết (${owned * crop.sellPrice}G)</button>
            `;
            sellGrid.appendChild(card);
        });
    }

    function buySeed(cropKey) {
        const crop = CROPS[cropKey];
        if (gameState.gold >= crop.cost) {
            gameState.gold -= crop.cost;
            gameState.seeds[cropKey] = (gameState.seeds[cropKey] || 0) + 1;
            playPixelSound('coin');
            updateHUD();
            renderHotbar();
            renderShopModal();
            showToast(`Đã mua 1 Hạt ${crop.name}! 🛒`);
        } else {
            showToast('Không đủ Gold! Hãy bán nông sản để kiếm thêm Gold 💰');
        }
    }

    function sellCrop(cropKey) {
        const crop = CROPS[cropKey];
        const owned = gameState.inventory[cropKey] || 0;
        if (owned > 0) {
            const earned = owned * crop.sellPrice;
            gameState.gold += earned;
            gameState.inventory[cropKey] = 0;
            playPixelSound('coin');
            updateHUD();
            renderShopModal();
            showToast(`Đã bán ${owned} ${crop.name} nhận +${earned}G! 💰`);
        }
    }

    function redeemVoucher(cost, code) {
        if (gameState.gold < cost) {
            showToast('Không đủ Gold để đổi Voucher! 💰');
            return;
        }

        fetch('/Game/farm_game.php?api=redeem_voucher', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cost: cost, code: code })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                gameState.gold -= cost;
                playPixelSound('coin');
                updateHUD();
                renderShopModal();
                alert(`🎉 ĐỔI VOUCHER THÀNH CÔNG!\n\nMã Voucher của bạn: ${data.code}\n\nMã này đã được ghi nhận vào hệ thống. Nhập mã ${data.code} tại trang Thanh Toán để nhận ưu đãi!`);
            } else {
                showToast(data.message);
            }
        });
    }

    // --- LOCALSTORAGE & CLOUD SAVE ---
    function saveLocalSave() {
        localStorage.setItem('glowfarm_save', JSON.stringify(gameState));
    }

    function loadLocalSave() {
        const saved = localStorage.getItem('glowfarm_save');
        if (saved) {
            try {
                const parsed = JSON.parse(saved);
                if (parsed && parsed.tiles) {
                    gameState = parsed;
                }
            } catch(e) {}
        }
    }

    function saveGameCloud() {
        saveLocalSave();
        fetch('/Game/farm_game.php?api=save_game', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(gameState)
        })
        .then(res => res.json())
        .then(data => {
            playPixelSound('coin');
            showToast(data.message);
        })
        .catch(() => {
            showToast('Đã lưu vào bộ nhớ trình duyệt (LocalStorage)!');
        });
    }

    // Toast Utility
    function showToast(msg) {
        const toast = document.getElementById('gameToast');
        toast.textContent = msg;
        toast.classList.add('show');
        setTimeout(() => { toast.classList.remove('show'); }, 2600);
    }
    </script>
</body>
</html>
