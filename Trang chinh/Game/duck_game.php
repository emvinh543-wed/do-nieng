<?php
// Config inclusion with fallback
if (file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
} elseif (file_exists(__DIR__ . '/../Trang chinh/config/config.php')) {
    require_once __DIR__ . '/../Trang chinh/config/config.php';
}

// Get logged in user info if available
$logged_in_user = null;
if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT id, fullname, username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $logged_in_user = $stmt->fetch();
    } catch (Exception $e) {}
}

// AJAX API: Handle Submit Score
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'submit_score') {
    header('Content-Type: application/json');
    $score = isset($_POST['score']) ? (int)$_POST['score'] : 0;
    $playerName = isset($_POST['player_name']) ? trim($_POST['player_name']) : '';

    if (empty($playerName)) {
        $playerName = $logged_in_user ? $logged_in_user['fullname'] : 'Khách Vô Danh 🐥';
    }

    if ($score > 0 && isset($pdo)) {
        try {
            $userId = $logged_in_user ? $logged_in_user['id'] : null;
            $stmt = $pdo->prepare("INSERT INTO game_leaderboard (user_id, player_name, score) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $playerName, $score]);
            
            // Get Top 10 Leaderboard
            $topStmt = $pdo->query("SELECT player_name, score, created_at FROM game_leaderboard ORDER BY score DESC, created_at ASC LIMIT 10");
            $leaderboard = $topStmt->fetchAll();

            echo json_encode(['status' => 'success', 'leaderboard' => $leaderboard]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit();
        }
    }
    echo json_encode(['status' => 'ignored']);
    exit();
}

// Fetch Initial Leaderboard for rendering
$initial_leaderboard = [];
if (isset($pdo)) {
    try {
        $topStmt = $pdo->query("SELECT player_name, score FROM game_leaderboard ORDER BY score DESC, created_at ASC LIMIT 10");
        $initial_leaderboard = $topStmt->fetchAll();
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vịt Con 3D Háu Ăn - Đồ Họa 3D Siêu Chân Thật & Bản Đồ Chi Tiết</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <!-- Three.js 3D Engine -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="/Game/lang.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; user-select: none; }
        body { background: #070c18; color: white; overflow: hidden; height: 100vh; width: 100vw; }
        
        #game-canvas { width: 100vw; height: 100vh; display: block; }
        
        /* UI Overlay */
        .hud-layer {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 18px 22px;
            z-index: 10;
        }

        .hud-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(15, 23, 42, 0.88);
            padding: 10px 20px;
            border-radius: 30px;
            backdrop-filter: blur(14px);
            border: 2px solid rgba(245, 166, 35, 0.5);
            pointer-events: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .brand-logo img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid #f5a623; }
        .brand-logo h1 { font-size: 1.15rem; font-weight: 800; color: #f5a623; }
        
        .user-tag {
            font-size: 0.78rem;
            color: #38bdf8;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .score-board {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .hud-card {
            background: rgba(15, 23, 42, 0.88);
            border: 2px solid #f5a623;
            padding: 8px 16px;
            border-radius: 18px;
            backdrop-filter: blur(12px);
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        }

        .hud-card label { font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; display: block; }
        .hud-card span { font-size: 1.35rem; font-weight: 800; color: #fbbf24; }

        /* First Person / Third Person Toggle Button */
        .btn-view-toggle {
            pointer-events: auto;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: 2px solid #34d399;
            padding: 9px 16px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.88rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 18px rgba(16, 185, 129, 0.4);
            transition: all 0.25s ease;
        }
        .btn-view-toggle:hover { transform: scale(1.06); box-shadow: 0 6px 22px rgba(16, 185, 129, 0.6); }

        /* Leaderboard Button */
        .btn-leaderboard-toggle {
            pointer-events: auto;
            background: linear-gradient(135deg, #0284c7, #0369a1);
            color: white;
            border: 2px solid #38bdf8;
            padding: 9px 16px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.88rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(2, 132, 199, 0.4);
            transition: transform 0.2s;
        }
        .btn-leaderboard-toggle:hover { transform: scale(1.05); }

        /* Controls Hints Overlay */
        .controls-hint {
            position: absolute;
            bottom: 20px;
            left: 25px;
            background: rgba(15, 23, 42, 0.88);
            border: 1px solid rgba(255,255,255,0.18);
            padding: 12px 18px;
            border-radius: 18px;
            backdrop-filter: blur(14px);
            pointer-events: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .controls-hint h4 { font-size: 0.82rem; color: #f5a623; margin-bottom: 6px; }
        .key-row { display: flex; gap: 6px; align-items: center; font-size: 0.8rem; color: #cbd5e1; }
        .key { background: #334155; padding: 4px 9px; border-radius: 6px; font-weight: 800; color: white; border-bottom: 2px solid #1e293b; }

        /* First Person Crosshair */
        #crosshair {
            position: absolute;
            top: 50%; left: 50%;
            width: 16px; height: 16px;
            transform: translate(-50%, -50%);
            pointer-events: none;
            display: none;
            z-index: 20;
        }
        #crosshair::before, #crosshair::after {
            content: ''; position: absolute; background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 0 5px rgba(0,0,0,0.9);
        }
        #crosshair::before { top: 7px; left: 0; width: 16px; height: 2px; }
        #crosshair::after { top: 0; left: 7px; width: 2px; height: 16px; }

        /* Modals & Selection Drawer */
        .game-modal {
            position: absolute;
            inset: 0;
            background: rgba(7, 12, 24, 0.92);
            backdrop-filter: blur(16px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            pointer-events: auto;
            animation: fadeIn 0.4s ease;
        }

        .modal-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border: 3px solid #f5a623;
            border-radius: 28px;
            padding: 35px 40px;
            width: 760px;
            max-width: 95%;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0,0,0,0.8);
            max-height: 92vh;
            overflow-y: auto;
        }

        .modal-card h2 { font-size: 1.8rem; font-weight: 800; color: #f5a623; margin-bottom: 6px; }
        .modal-card p { font-size: 0.9rem; color: #94a3b8; margin-bottom: 20px; }

        /* Selector Grid for Ducks & Maps */
        .section-label {
            font-size: 0.95rem; font-weight: 800; color: #38bdf8; text-align: left; margin: 15px 0 10px 0;
            display: flex; align-items: center; gap: 8px;
        }

        .select-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .select-card {
            background: #334155;
            border: 2px solid #475569;
            border-radius: 18px;
            padding: 12px 10px;
            cursor: pointer;
            transition: all 0.25s ease;
            text-align: center;
        }

        .select-card:hover {
            transform: translateY(-4px);
            border-color: #f5a623;
            background: #475569;
        }

        .select-card.active {
            border-color: #f5a623;
            background: linear-gradient(135deg, rgba(245, 166, 35, 0.25), rgba(217, 119, 6, 0.25));
            box-shadow: 0 0 22px rgba(245, 166, 35, 0.5);
        }

        .select-card-icon { font-size: 2.2rem; display: block; margin-bottom: 6px; }
        .select-card-name { font-size: 0.85rem; font-weight: 800; color: white; display: block; }
        .select-card-desc { font-size: 0.72rem; color: #cbd5e1; margin-top: 3px; display: block; }

        .btn-play {
            background: linear-gradient(135deg, #f5a623, #d97706);
            color: white;
            border: none;
            padding: 14px 42px;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(245, 166, 35, 0.4);
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            margin-top: 10px;
        }

        .btn-play:hover { transform: scale(1.06); box-shadow: 0 14px 35px rgba(245, 166, 35, 0.6); }

        .voucher-box {
            background: rgba(34, 197, 94, 0.15);
            border: 2px dashed #22c55e;
            padding: 14px;
            border-radius: 16px;
            margin: 15px 0;
            color: #4ade80;
            font-size: 1.05rem;
            font-weight: 800;
        }

        /* Leaderboard Modal Table */
        .lb-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.88rem;
        }

        .lb-table th { background: #334155; color: #f5a623; padding: 10px; text-align: left; border-radius: 8px; }
        .lb-table td { padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: left; }
        .lb-table tr.highlight { background: rgba(245, 166, 35, 0.2); font-weight: 800; color: #fde047; }

        .rank-badge {
            width: 24px; height: 24px; border-radius: 50%; display: inline-flex;
            align-items: center; justify-content: center; font-weight: 800; font-size: 0.78rem;
        }
        .rank-1 { background: #eab308; color: #000; }
        .rank-2 { background: #94a3b8; color: #000; }
        .rank-3 { background: #b45309; color: #fff; }

        /* Virtual Joystick for Mobile */
        .joystick-container {
            position: absolute; bottom: 25px; right: 25px; width: 120px; height: 120px;
            background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%; pointer-events: auto; display: none; touch-action: none;
        }
        .joystick-stick {
            width: 50px; height: 50px; background: #f5a623; border-radius: 50%; position: absolute; top: 35px; left: 35px; box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }

        @media (max-width: 768px) {
            .joystick-container { display: block; }
            .controls-hint { display: none; }
        }
    </style>
</head>
<body>

    <!-- 3D Canvas -->
    <canvas id="game-canvas"></canvas>

    <!-- First-Person Crosshair -->
    <div id="crosshair"></div>

    <!-- HUD Layer -->
    <div class="hud-layer">
        <div class="hud-top">
            <div class="brand-logo">
                <img src="/anh/duck_3d.png" alt="Duck 3D" onerror="this.src='https://ui-avatars.com/api/?name=Duck'">
                <div>
                    <h1 data-i18n="game_title">Vịt Con 3D Háu Ăn 🐥</h1>
                    <div class="user-tag">
                        <?php if ($logged_in_user): ?>
                            <span data-i18n="player_label">🟢 Người chơi:</span> <strong><?php echo htmlspecialchars($logged_in_user['fullname']); ?></strong>
                        <?php else: ?>
                            <span data-i18n="guest_label">👤 Khách</span> (<a href="/login/login_demo.php" style="color:#f5a623;" target="_blank" data-i18n="login_link">Đăng nhập</a> <span data-i18n="guest_suffix">để lưu tên</span>)
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="score-board">
                <div class="hud-card">
                    <label data-i18n="score_label">Điểm Số</label>
                    <span id="score-val">0</span>
                </div>
                <div class="hud-card">
                    <label data-i18n="time_label">Thời Gian</label>
                    <span id="time-val">60s</span>
                </div>
                <div class="hud-card" style="border-color:#38bdf8;">
                    <label data-i18n="explore_label">Khám Phá</label>
                    <span id="dist-val" style="color:#38bdf8;">0m</span>
                </div>
                <div class="hud-card" style="border-color:#a855f7;">
                    <label data-i18n="chunk_label">Tọa Độ Chunk</label>
                    <span id="chunk-val" style="color:#c084fc;font-size:1.1rem;">[0, 0]</span>
                </div>
                <a href="/Game/farm_game.php" class="btn-leaderboard-toggle" style="background:linear-gradient(135deg, #15803d, #166534);border-color:#4ade80;text-decoration:none;">
                    🌱 Game Làm Vườn 2D
                </a>
                <button class="btn-view-toggle" id="btn-view-toggle" onclick="toggleFirstPersonView()" data-i18n="view_btn">
                    👀 Chế độ 1st Person
                </button>
                <button class="btn-leaderboard-toggle" onclick="toggleLeaderboardModal()" data-i18n="leaderboard_btn">
                    🏆 BẢNG XẾP HẠNG
                </button>
            </div>
        </div>

        <div class="controls-hint">
            <h4 data-i18n="controls_title">🗺️ Bản Đồ Vô Tận & Đồ Họa 3D Cao Cấp:</h4>
            <div class="key-row">
                <span data-i18n="controls_text">Dùng</span> <span class="key">W</span><span class="key">A</span><span class="key">S</span><span class="key">D</span> 
                <span data-i18n="controls_or">hoặc</span> <span class="key">↑</span><span class="key">←</span><span class="key">↓</span><span class="key">→</span> 
                <span data-i18n="controls_desc">điều khiển Vịt (Nhấn V đổi Góc Nhìn 1st/3rd Person)</span> 🌐
            </div>
        </div>

        <div class="joystick-container" id="joystick">
            <div class="joystick-stick" id="stick"></div>
        </div>
    </div>

    <!-- Start / Character & Map Selection Modal -->
    <div class="game-modal" id="start-modal">
        <div class="modal-card">
            <h2 data-i18n="start_title">🐥 CHỌN NHÂN VẬT VỊT & THẾ GIỚI 3D SỐNG ĐỘNG 🗺️</h2>
            <p data-i18n="start_subtitle">Khám phá thế giới 3D đa dạng: Thành phố, Nhà cửa chân thật, Đường rẫy Tàu hỏa đầu máy hơi nước, Tàu chở Container & Bãi biển!</p>

            <!-- Duck Skins Selection -->
            <div class="section-label" data-i18n="duck_section_label">🐥 1. CHỌN CHÚ VỊT 3D CỦA BẠN:</div>
            <div class="select-grid" id="duck-skin-grid">
                <div class="select-card active" onclick="selectDuckSkin('golden', this)">
                    <span class="select-card-icon">🐥</span>
                    <span class="select-card-name" data-i18n="duck_golden_name">Vịt Vàng Vui Vẻ</span>
                    <span class="select-card-desc" data-i18n="duck_golden_desc">Vịt truyền thống rực rỡ</span>
                </div>
                <div class="select-card" onclick="selectDuckSkin('cool', this)">
                    <span class="select-card-icon">🕶️</span>
                    <span class="select-card-name" data-i18n="duck_cool_name">Vịt Cool Boy</span>
                    <span class="select-card-desc" data-i18n="duck_cool_desc">Kính râm ngầu mạ vàng</span>
                </div>
                <div class="select-card" onclick="selectDuckSkin('king', this)">
                    <span class="select-card-icon">👑</span>
                    <span class="select-card-name" data-i18n="duck_king_name">Vịt Hoàng Gia</span>
                    <span class="select-card-desc" data-i18n="duck_king_desc">Vương miện quyền lực</span>
                </div>
                <div class="select-card" onclick="selectDuckSkin('fairy', this)">
                    <span class="select-card-icon">🌸</span>
                    <span class="select-card-name" data-i18n="duck_fairy_name">Vịt Hồng Kẹo Ngọt</span>
                    <span class="select-card-desc" data-i18n="duck_fairy_desc">Sắc hồng ngọt ngào</span>
                </div>
                <div class="select-card" onclick="selectDuckSkin('ninja', this)">
                    <span class="select-card-icon">🥷</span>
                    <span class="select-card-name" data-i18n="duck_ninja_name">Vịt Ninja Đen</span>
                    <span class="select-card-desc" data-i18n="duck_ninja_desc">Băng trán & thân huyền bí</span>
                </div>
            </div>

            <!-- Map Worlds Selection -->
            <div class="section-label" data-i18n="map_section_label">🗺️ 2. CHỌN THẾ GIỚI BẢN ĐỒ 3D (DYNAMIC CHUNK LOADING):</div>
            <div class="select-grid" id="map-world-grid">
                <div class="select-card active" onclick="selectMapWorld('park', this)">
                    <span class="select-card-icon">🏙️</span>
                    <span class="select-card-name" data-i18n="map_park_name">Đô Thị & Công Viên Xanh</span>
                    <span class="select-card-desc" data-i18n="map_park_desc">Thành phố, Tàu biển, Tàu hỏa & Hồ ao</span>
                </div>
                <div class="select-card" onclick="selectMapWorld('volcano', this)">
                    <span class="select-card-icon">🌋</span>
                    <span class="select-card-name" data-i18n="map_volcano_name">Đảo Núi Lửa & Dung Nham</span>
                    <span class="select-card-desc" data-i18n="map_volcano_desc">Dòng Dung Nham & Tàu chiến</span>
                </div>
                <div class="select-card" onclick="selectMapWorld('snow', this)">
                    <span class="select-card-icon">❄️</span>
                    <span class="select-card-name" data-i18n="map_snow_name">Vương Quốc Băng & Tuyết</span>
                    <span class="select-card-desc" data-i18n="map_snow_desc">Tuyết trắng, Sông băng & Tàu phá băng</span>
                </div>
                <div class="select-card" onclick="selectMapWorld('galaxy', this)">
                    <span class="select-card-icon">🌌</span>
                    <span class="select-card-name" data-i18n="map_galaxy_name">Đêm Ngân Hà Cyberpunk</span>
                    <span class="select-card-desc" data-i18n="map_galaxy_desc">Thành phố Cyber, Tàu vũ trụ & Pha lê</span>
                </div>
            </div>

            <!-- Time Selection -->
            <div class="section-label" data-i18n="time_section_label">⏱️ 3. CHỌN THỜI GIAN CHƠI:</div>
            <div class="select-grid" id="time-select-grid" style="grid-template-columns:repeat(4,1fr);">
                <div class="select-card" onclick="selectTime(60, this)">
                    <span class="select-card-icon">⚡</span>
                    <span class="select-card-name" data-i18n="time_60_name">60 Giây</span>
                    <span class="select-card-desc" data-i18n="time_60_desc">Chớp nhoáng nhanh</span>
                </div>
                <div class="select-card active" onclick="selectTime(180, this)">
                    <span class="select-card-icon">🕐</span>
                    <span class="select-card-name" data-i18n="time_180_name">3 Phút</span>
                    <span class="select-card-desc" data-i18n="time_180_desc">Vừa đủ khám phá</span>
                </div>
                <div class="select-card" onclick="selectTime(300, this)">
                    <span class="select-card-icon">🕔</span>
                    <span class="select-card-name" data-i18n="time_300_name">5 Phút</span>
                    <span class="select-card-desc" data-i18n="time_300_desc">Phiêu lưu cơ bản</span>
                </div>
                <div class="select-card" onclick="selectTime(5400, this)">
                    <span class="select-card-icon">🌍</span>
                    <span class="select-card-name" data-i18n="time_5400_name">90 Phút</span>
                    <span class="select-card-desc" data-i18n="time_5400_desc">Khám phá vô tận!</span>
                </div>
            </div>

            <button class="btn-play" onclick="startGame()" data-i18n="start_game_btn">🚀 BẮT ĐẦU VÀO GAME NGAY</button>
        </div>
    </div>

    <!-- Leaderboard Modal -->
    <div class="game-modal" id="lb-modal" style="display:none;">
        <div class="modal-card" style="width:560px;">
            <h2 style="color:#38bdf8;" data-i18n="leaderboard_title">🏆 BẢNG XẾP HẠNG TOP CAO THỦ</h2>
            <p data-i18n="leaderboard_desc">Danh sách 10 người chơi ghi điểm cao nhất trong Game Vịt 3D:</p>
            
            <table class="lb-table">
                <thead>
                    <tr>
                        <th style="width:50px;" data-i18n="table_rank">Hạng</th>
                        <th data-i18n="table_player">Tên Người Chơi</th>
                        <th style="text-align:right;" data-i18n="table_score">Điểm Số</th>
                    </tr>
                </thead>
                <tbody id="lb-table-body">
                    <?php if (!empty($initial_leaderboard)): ?>
                        <?php foreach ($initial_leaderboard as $idx => $row): ?>
                            <tr class="<?php echo ($logged_in_user && $row['player_name'] == $logged_in_user['fullname']) ? 'highlight' : ''; ?>">
                                <td>
                                    <?php if ($idx < 3): ?>
                                        <span class="rank-badge rank-<?php echo ($idx+1); ?>"><?php echo ($idx+1); ?></span>
                                    <?php else: ?>
                                        #<?php echo ($idx+1); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['player_name']); ?></td>
                                <td style="text-align:right;font-weight:800;color:#fbbf24;"><?php echo number_format($row['score']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="text-align:center;" data-i18n="no_rank_data">Chưa có dữ liệu xếp hạng</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top:20px;">
                <button class="btn-play" onclick="toggleLeaderboardModal()" style="background:#475569;" data-i18n="leaderboard_close">ĐÓNG BẢNG</button>
            </div>
        </div>
    </div>

    <!-- Game Over Modal -->
    <div class="game-modal" id="end-modal" style="display:none;">
        <div class="modal-card" style="width:500px;">
            <img src="/anh/duck_3d.png" alt="Duck 3D" style="width:85px;height:85px;border-radius:50%;border:3px solid #f5a623;">
            <h2 style="color:#22c55e;" data-i18n="end_title">🎉 HOÀN THÀNH CHUYẾN ĐI!</h2>
            <p><span data-i18n="display_name_label">Tên hiển thị:</span> <strong><?php echo $logged_in_user ? htmlspecialchars($logged_in_user['fullname']) : 'Khách Vô Danh'; ?></strong></p>
            <div style="font-size:2.5rem;font-weight:800;color:#fbbf24;margin-bottom:10px;" id="final-score">0 ĐIỂM</div>
            
            <div id="voucher-result" class="voucher-box" style="display:none;">
                <span data-i18n="voucher_label">🎟️ Mã Giảm Giá 20%:</span> <strong>GLOWDUCK20</strong>
            </div>

            <div style="display:flex;gap:12px;justify-content:center;margin-top:20px;">
                <button class="btn-play" onclick="showStartModal()" data-i18n="restart_btn">🔄 ĐỔI BẢN ĐỒ / VỊT KÍCH THÍCH</button>
                <button class="btn-play" onclick="toggleLeaderboardModal()" style="background:#0284c7;" data-i18n="view_leaderboard_btn">🏆 XEM BẢNG HẠNG</button>
            </div>
        </div>
    </div>

    <!-- 3D MULTI-MAP & FIRST-PERSON SYSTEM SCRIPT -->
    <script>
    const loggedInPlayerName = "<?php echo $logged_in_user ? addslashes($logged_in_user['fullname']) : 'Khách Vô Danh 🐥'; ?>";

    // Options
    let currentDuckSkin = 'golden';
    let currentMapWorld = 'park';
    let selectedTime = 180;

    // View Mode State: false = 3rd Person, true = 1st Person
    let isFirstPerson = false;
    let fpYaw = 0;   // Yaw angle (horizontal look)
    let fpPitch = 0; // Pitch angle (vertical look)

    function selectDuckSkin(skin, el) {
        currentDuckSkin = skin;
        document.querySelectorAll('#duck-skin-grid .select-card').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
        playQuackSound();
    }

    function selectMapWorld(mapKey, el) {
        currentMapWorld = mapKey;
        document.querySelectorAll('#map-world-grid .select-card').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
        playQuackSound();
    }

    function selectTime(seconds, el) {
        selectedTime = seconds;
        document.querySelectorAll('#time-select-grid .select-card').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
        playQuackSound();
    }

    function formatTime(secs) {
        if (secs < 60) return secs + 's';
        const m = Math.floor(secs / 60);
        const s = secs % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    // Sound Generator
    function playQuackSound() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator(); const gain = ctx.createGain();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(520, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(220, ctx.currentTime + 0.12);
            gain.gain.setValueAtTime(0.3, ctx.currentTime); gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.12);
            osc.connect(gain); gain.connect(ctx.destination);
            osc.start(); osc.stop(ctx.currentTime + 0.12);
        } catch(e){}
    }

    function playEatSound() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator(); const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(650, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(1300, ctx.currentTime + 0.1);
            gain.gain.setValueAtTime(0.3, ctx.currentTime); gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.1);
            osc.connect(gain); gain.connect(ctx.destination);
            osc.start(); osc.stop(ctx.currentTime + 0.1);
        } catch(e){}
    }

    // Toggle 1st Person / 3rd Person View
    function toggleFirstPersonView() {
        isFirstPerson = !isFirstPerson;
        const btn = document.getElementById('btn-view-toggle');
        const crosshair = document.getElementById('crosshair');

        if (isFirstPerson) {
            btn.innerHTML = '🔭 Chế độ 3rd Person';
            btn.style.background = 'linear-gradient(135deg, #8b5cf6, #6d28d9)';
            btn.style.borderColor = '#a78bfa';
            crosshair.style.display = 'block';
            if (duckBeak) duckBeak.visible = true;
            // Lock pointer for mouse look if game active
            if (gameActive) {
                document.body.requestPointerLock = document.body.requestPointerLock || document.body.mozRequestPointerLock;
                if (document.body.requestPointerLock) document.body.requestPointerLock();
            }
        } else {
            btn.innerHTML = '👀 Chế độ 1st Person';
            btn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
            btn.style.borderColor = '#34d399';
            crosshair.style.display = 'none';
            if (duckBeak) duckBeak.visible = true;
            if (document.exitPointerLock) document.exitPointerLock();
        }
        playQuackSound();
    }

    // Mouse look event listener for 1st person
    window.addEventListener('mousemove', e => {
        if (!gameActive || !isFirstPerson) return;
        const movementX = e.movementX || e.mozMovementX || 0;
        const movementY = e.movementY || e.mozMovementY || 0;

        fpYaw -= movementX * 0.003;
        fpPitch -= movementY * 0.003;
        fpPitch = Math.max(-Math.PI / 3, Math.min(Math.PI / 3, fpPitch));
    });

    // Press 'V' or 'F' to toggle View Mode
    window.addEventListener('keydown', e => {
        if (e.key === 'v' || e.key === 'V' || e.key === 'f' || e.key === 'F') {
            toggleFirstPersonView();
        }
    });

    // 3D Objects & Dynamic Infinite Chunk System
    let scene, camera, renderer, dirLight, hemiLight;
    let duckGroup, duckBody, duckHead, duckBeak, duckWingL, duckWingR;
    let foods = [];
    let activeAnimals = [];
    let activeTrains = [];
    let activeShips = [];
    let activeParticles = null;
    let activeSmokeParticles = [];
    let score = 0, timeLeft = 60, gameActive = false, timerInterval;

    const CHUNK_SIZE = 95;
    const LOAD_RADIUS = 2;
    const activeChunks = new Map();

    const MAP_THEMES = {
        park: {
            bg: 0x0b172a, ground: 0x15803d, water: 0x0284c7, treeLeaf: 0x16a34a, treeTrunk: 0x78350f, rock: 0x64748b, bridge: 0x92400e,
            emissiveWater: 0x0284c7, emissiveIntensity: 0.15, particleColor: 0x86efac
        },
        volcano: {
            bg: 0x1c0a0a, ground: 0x262626, water: 0xeab308, treeLeaf: 0xd97706, treeTrunk: 0x451a03, rock: 0x57534e, bridge: 0x44403c,
            emissiveWater: 0xd97706, emissiveIntensity: 0.75, particleColor: 0xf97316
        },
        snow: {
            bg: 0x0f172a, ground: 0xf1f5f9, water: 0x38bdf8, treeLeaf: 0x0284c7, treeTrunk: 0x334155, rock: 0x94a3b8, bridge: 0x475569,
            emissiveWater: 0x38bdf8, emissiveIntensity: 0.15, particleColor: 0xffffff
        },
        galaxy: {
            bg: 0x080414, ground: 0x3b0764, water: 0xc084fc, treeLeaf: 0xf43f5e, treeTrunk: 0x6b21a8, rock: 0xa855f7, bridge: 0x581c87,
            emissiveWater: 0xc084fc, emissiveIntensity: 0.85, particleColor: 0xec4899
        }
    };

    const keys = { w: false, a: false, s: false, d: false, ArrowUp: false, ArrowLeft: false, ArrowDown: false, ArrowRight: false };
    let moveVector = { x: 0, z: 0 };

    window.addEventListener('keydown', e => { if (keys.hasOwnProperty(e.key) || keys.hasOwnProperty(e.code)) keys[e.key] = true; });
    window.addEventListener('keyup', e => { if (keys.hasOwnProperty(e.key) || keys.hasOwnProperty(e.code)) keys[e.key] = false; });

    function init3D() {
        const canvas = document.getElementById('game-canvas');
        scene = new THREE.Scene();

        camera = new THREE.PerspectiveCamera(50, window.innerWidth / window.innerHeight, 0.1, 1000);
        camera.position.set(0, 22, 28);
        camera.lookAt(0, 0, 0);

        renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, alpha: false });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap;

        create3DDuck(currentDuckSkin);
        rebuildMapEnvironment();
        maintainFruitsAroundPlayer(0, 0);

        window.addEventListener('resize', onWindowResize);
        animate();
    }

    function rebuildMapEnvironment() {
        const theme = MAP_THEMES[currentMapWorld] || MAP_THEMES.park;
        scene.background = new THREE.Color(theme.bg);
        scene.fog = new THREE.FogExp2(theme.bg, 0.006);

        // Remove old lights
        scene.children.filter(c => c.isLight).forEach(l => scene.remove(l));

        hemiLight = new THREE.HemisphereLight(0xffffff, theme.ground, 0.65);
        scene.add(hemiLight);

        dirLight = new THREE.DirectionalLight((currentMapWorld === 'volcano') ? 0xff6622 : 0xfffae6, 1.4);
        dirLight.position.set(45, 65, 45);
        dirLight.castShadow = true;
        dirLight.shadow.mapSize.width = 2048;
        dirLight.shadow.mapSize.height = 2048;
        dirLight.shadow.bias = -0.0001;
        dirLight.shadow.camera.near = 0.5;
        dirLight.shadow.camera.far = 280;
        dirLight.shadow.camera.left = -75;
        dirLight.shadow.camera.right = 75;
        dirLight.shadow.camera.top = 75;
        dirLight.shadow.camera.bottom = -75;
        scene.add(dirLight);

        createWeatherParticles(theme.particleColor);

        for (const [key, group] of activeChunks.entries()) {
            scene.remove(group);
        }
        activeChunks.clear();
        activeTrains = [];
        activeShips = [];
        activeSmokeParticles = [];

        updateWorldChunksAroundPlayer(0, 0);
    }

    function createWeatherParticles(colorHex) {
        if (activeParticles) scene.remove(activeParticles);
        const count = 450;
        const geom = new THREE.BufferGeometry();
        const positions = new Float32Array(count * 3);

        for (let i = 0; i < count; i++) {
            positions[i*3] = (Math.random() - 0.5) * 380;
            positions[i*3+1] = Math.random() * 110;
            positions[i*3+2] = (Math.random() - 0.5) * 380;
        }

        geom.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        const mat = new THREE.PointsMaterial({
            color: colorHex,
            size: 1.5,
            transparent: true,
            opacity: 0.65
        });

        activeParticles = new THREE.Points(geom, mat);
        scene.add(activeParticles);
    }

    function seededRandom(seed) {
        let x = Math.sin(seed) * 10000;
        return x - Math.floor(x);
    }

    // -------------------------------------------------------------
    // ULTRA-DETAILED REALISTIC 3D HOUSE BUILDER (Nhà Cửa Chân Thật)
    // -------------------------------------------------------------
    function createUltraDetailedHouse(seed, theme) {
        const houseGroup = new THREE.Group();

        // 1. Raised Stone Base & Walkway
        const baseGeo = new THREE.BoxGeometry(7.2, 0.4, 6.2);
        const baseMat = new THREE.MeshStandardMaterial({ color: 0x475569, roughness: 0.9 });
        const base = new THREE.Mesh(baseGeo, baseMat);
        base.position.y = 0.2; base.receiveShadow = true; houseGroup.add(base);

        // 2. Main Wall Body (Wood / Plaster)
        const wallMat = new THREE.MeshStandardMaterial({ color: 0xf8fafc, roughness: 0.75 });
        const wallGeo = new THREE.BoxGeometry(6.6, 3.8, 5.6);
        const wall = new THREE.Mesh(wallGeo, wallMat);
        wall.position.y = 2.1; wall.castShadow = true; wall.receiveShadow = true;
        houseGroup.add(wall);

        // 3. Sloped Tiled Roof (Hip & Gable Roof)
        const roofMat = new THREE.MeshStandardMaterial({ color: 0xb91c1c, roughness: 0.5 });
        const roofGeo = new THREE.ConeGeometry(5.2, 2.6, 4);
        const roof = new THREE.Mesh(roofGeo, roofMat);
        roof.rotation.y = Math.PI / 4;
        roof.position.y = 5.3;
        roof.castShadow = true;
        houseGroup.add(roof);

        // Roof Trim / Eaves
        const eavesGeo = new THREE.BoxGeometry(7.0, 0.2, 6.0);
        const eavesMat = new THREE.MeshStandardMaterial({ color: 0x78350f, roughness: 0.8 });
        const eaves = new THREE.Mesh(eavesGeo, eavesMat);
        eaves.position.y = 4.0;
        houseGroup.add(eaves);

        // 4. Brick Chimney & Smoke
        const chimneyGeo = new THREE.BoxGeometry(0.8, 2.4, 0.8);
        const chimneyMat = new THREE.MeshStandardMaterial({ color: 0x991b1b, roughness: 0.9 });
        const chimney = new THREE.Mesh(chimneyGeo, chimneyMat);
        chimney.position.set(1.8, 5.2, 1.2);
        chimney.castShadow = true;
        houseGroup.add(chimney);

        // 5. Front Porch with White Pillars & Steps
        const porchMat = new THREE.MeshStandardMaterial({ color: 0x78350f, roughness: 0.7 });
        const porchRoof = new THREE.Mesh(new THREE.BoxGeometry(2.8, 0.2, 1.6), porchMat);
        porchRoof.position.set(0, 2.9, 3.2); porchRoof.castShadow = true;
        houseGroup.add(porchRoof);

        const pillarMat = new THREE.MeshStandardMaterial({ color: 0xffffff });
        const p1 = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.12, 2.5, 8), pillarMat); p1.position.set(-1.1, 1.65, 3.8);
        const p2 = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.12, 2.5, 8), pillarMat); p2.position.set(1.1, 1.65, 3.8);
        houseGroup.add(p1, p2);

        // Door with handle & frame
        const doorFrame = new THREE.Mesh(new THREE.BoxGeometry(1.3, 2.3, 0.15), new THREE.MeshStandardMaterial({ color: 0x334155 }));
        doorFrame.position.set(0, 1.35, 2.82);
        const door = new THREE.Mesh(new THREE.BoxGeometry(1.1, 2.1, 0.1), new THREE.MeshStandardMaterial({ color: 0x92400e }));
        door.position.set(0, 1.35, 2.88);
        const handle = new THREE.Mesh(new THREE.SphereGeometry(0.08, 8, 8), new THREE.MeshStandardMaterial({ color: 0xf5a623, metalness: 0.8 }));
        handle.position.set(0.4, 1.35, 2.95);
        houseGroup.add(doorFrame, door, handle);

        // 6. Windows with Frame & Glass
        const winFrameMat = new THREE.MeshStandardMaterial({ color: 0x1e293b });
        const winGlassMat = new THREE.MeshStandardMaterial({ color: 0xfde047, emissive: 0xfde047, emissiveIntensity: 0.7, roughness: 0.1 });

        const windowPositions = [
            [-1.9, 2.4, 2.82], [1.9, 2.4, 2.82], // Front
            [-3.32, 2.4, 0], [3.32, 2.4, 0]     // Sides
        ];

        windowPositions.forEach((pos, idx) => {
            const isSide = idx >= 2;
            const wf = new THREE.Mesh(new THREE.BoxGeometry(isSide ? 0.15 : 1.1, 1.3, isSide ? 1.1 : 0.15), winFrameMat);
            wf.position.set(pos[0], pos[1], pos[2]);
            const wg = new THREE.Mesh(new THREE.BoxGeometry(isSide ? 0.1 : 0.95, 1.15, isSide ? 0.95 : 0.1), winGlassMat);
            wg.position.set(pos[0], pos[1], pos[2] + (isSide ? 0 : 0.02));
            houseGroup.add(wf, wg);

            // Window Flower Box under front windows
            if (!isSide) {
                const fb = new THREE.Mesh(new THREE.BoxGeometry(1.1, 0.35, 0.4), new THREE.MeshStandardMaterial({ color: 0x78350f }));
                fb.position.set(pos[0], pos[1] - 0.8, pos[2] + 0.15);
                const flower = new THREE.Mesh(new THREE.SphereGeometry(0.22, 6, 6), new THREE.MeshStandardMaterial({ color: 0xec4899 }));
                flower.position.set(pos[0], pos[1] - 0.55, pos[2] + 0.15);
                houseGroup.add(fb, flower);
            }
        });

        // 7. Garden Fence & Mailbox
        const fenceMat = new THREE.MeshStandardMaterial({ color: 0xffffff });
        for (let fx = -3.2; fx <= 3.2; fx += 1.0) {
            const post = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.9, 0.12), fenceMat);
            post.position.set(fx, 0.45, 4.5); post.castShadow = true;
            houseGroup.add(post);
        }
        const railBar = new THREE.Mesh(new THREE.BoxGeometry(6.6, 0.1, 0.08), fenceMat);
        railBar.position.set(0, 0.5, 4.5); houseGroup.add(railBar);

        // Mailbox
        const mb = new THREE.Mesh(new THREE.BoxGeometry(0.4, 0.3, 0.5), new THREE.MeshStandardMaterial({ color: 0x0284c7 }));
        mb.position.set(2.8, 0.8, 4.5);
        const mbPole = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, 0.7, 6), new THREE.MeshStandardMaterial({ color: 0x475569 }));
        mbPole.position.set(2.8, 0.35, 4.5);
        houseGroup.add(mb, mbPole);

        return houseGroup;
    }

    // -------------------------------------------------------------
    // ULTRA-DETAILED REALISTIC 3D SKYSCRAPER BUILDER (Tòa Nhà Đô Thị)
    // -------------------------------------------------------------
    function createUltraDetailedSkyscraper(seed, theme) {
        const skyGroup = new THREE.Group();
        const height = 18 + seededRandom(seed) * 24;
        const w = 7 + seededRandom(seed+1) * 4;
        const d = 7 + seededRandom(seed+2) * 4;

        // Ground Floor Storefront
        const lobbyGeo = new THREE.BoxGeometry(w + 0.4, 3.8, d + 0.4);
        const lobbyMat = new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.2, metalness: 0.5 });
        const lobby = new THREE.Mesh(lobbyGeo, lobbyMat);
        lobby.position.y = 1.9; lobby.castShadow = true; skyGroup.add(lobby);

        // Storefront Fabric Awning
        const awningGeo = new THREE.BoxGeometry(w + 0.6, 0.3, 1.2);
        const awningMat = new THREE.MeshStandardMaterial({ color: 0x0284c7, roughness: 0.6 });
        const awning = new THREE.Mesh(awningGeo, awningMat);
        awning.position.set(0, 3.6, d/2 + 0.6); skyGroup.add(awning);

        // Main Skyscraper Body with Facade Grid
        const bodyGeo = new THREE.BoxGeometry(w, height, d);
        const bodyMat = new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.3, metalness: 0.4 });
        const body = new THREE.Mesh(bodyGeo, bodyMat);
        body.position.y = height / 2 + 3.8; body.castShadow = true; body.receiveShadow = true;
        skyGroup.add(body);

        // Glass Curtain Windows
        const winMat = new THREE.MeshStandardMaterial({ color: 0xfde047, emissive: 0xfde047, emissiveIntensity: 0.85, roughness: 0.1 });
        for (let wy = 5.5; wy < height + 3.5; wy += 3.2) {
            const glassRing = new THREE.Mesh(new THREE.BoxGeometry(w + 0.08, 1.3, d + 0.08), winMat);
            glassRing.position.y = wy;
            skyGroup.add(glassRing);
        }

        // Roof Helipad & Antenna Tower
        const heliGeo = new THREE.CylinderGeometry(2.4, 2.4, 0.2, 16);
        const heliMat = new THREE.MeshStandardMaterial({ color: 0x475569, roughness: 0.8 });
        const helipad = new THREE.Mesh(heliGeo, heliMat);
        helipad.position.y = height + 3.9; skyGroup.add(helipad);

        // Red Aviation Warning Beacon Light on Antenna Tower
        const towerGeo = new THREE.CylinderGeometry(0.1, 0.18, 4.5, 8);
        const towerMat = new THREE.MeshStandardMaterial({ color: 0x94a3b8, metalness: 0.8 });
        const tower = new THREE.Mesh(towerGeo, towerMat);
        tower.position.set(0, height + 6.1, 0); skyGroup.add(tower);

        const beacon = new THREE.PointLight(0xef4444, 2.5, 25);
        beacon.position.set(0, height + 8.4, 0); skyGroup.add(beacon);

        return skyGroup;
    }

    // -------------------------------------------------------------
    // ULTRA-DETAILED REALISTIC 3D STEAM LOCOMOTIVE TRAIN (Tàu Hỏa Hơi Nước)
    // -------------------------------------------------------------
    function createUltraDetailedTrain(originX, originZ, seed) {
        const trainGroup = new THREE.Group();

        // 1. Steam Locomotive Engine
        const engineGroup = new THREE.Group();

        // Boiler Barrel
        const boilerGeo = new THREE.CylinderGeometry(1.3, 1.3, 5.2, 16);
        const boilerMat = new THREE.MeshStandardMaterial({ color: 0x1e293b, metalness: 0.7, roughness: 0.3 });
        const boiler = new THREE.Mesh(boilerGeo, boilerMat);
        boiler.rotation.z = Math.PI / 2; boiler.position.set(1.5, 1.8, 0); boiler.castShadow = true;
        engineGroup.add(boiler);

        // Driver Cabin / Cab
        const cabGeo = new THREE.BoxGeometry(2.8, 3.2, 2.8);
        const cabMat = new THREE.MeshStandardMaterial({ color: 0x991b1b, metalness: 0.3, roughness: 0.5 });
        const cab = new THREE.Mesh(cabGeo, cabMat);
        cab.position.set(-1.8, 2.0, 0); cab.castShadow = true;
        engineGroup.add(cab);

        // Cab Roof
        const cabRoof = new THREE.Mesh(new THREE.BoxGeometry(3.2, 0.3, 3.1), new THREE.MeshStandardMaterial({ color: 0x1e293b }));
        cabRoof.position.set(-1.8, 3.7, 0); engineGroup.add(cabRoof);

        // Front Wedge Cowcatcher (Pilot)
        const pilotGeo = new THREE.ConeGeometry(1.4, 1.2, 4);
        const pilotMat = new THREE.MeshStandardMaterial({ color: 0xd97706, metalness: 0.6 });
        const pilot = new THREE.Mesh(pilotGeo, pilotMat);
        pilot.rotation.z = -Math.PI / 2; pilot.rotation.x = Math.PI / 4;
        pilot.position.set(4.3, 0.7, 0); engineGroup.add(pilot);

        // Smokestack (Chimney)
        const chimneyGeo = new THREE.CylinderGeometry(0.38, 0.28, 1.4, 12);
        const chimneyMat = new THREE.MeshStandardMaterial({ color: 0x0f172a, metalness: 0.8 });
        const chimney = new THREE.Mesh(chimneyGeo, chimneyMat);
        chimney.position.set(3.2, 3.4, 0); engineGroup.add(chimney);

        // Steam Domes on Boiler
        const dome1 = new THREE.Mesh(new THREE.SphereGeometry(0.42, 10, 10), new THREE.MeshStandardMaterial({ color: 0xf5a623, metalness: 0.9 }));
        dome1.position.set(1.4, 3.2, 0); engineGroup.add(dome1);

        // Heavy Drive Wheels
        const wheelMat = new THREE.MeshStandardMaterial({ color: 0xdc2626, metalness: 0.8 });
        for (let wx = -0.5; wx <= 3.2; wx += 1.8) {
            const wL = new THREE.Mesh(new THREE.CylinderGeometry(0.7, 0.7, 0.25, 16), wheelMat);
            wL.rotation.x = Math.PI / 2; wL.position.set(wx, 0.7, 1.35);
            const wR = wL.clone(); wR.position.set(wx, 0.7, -1.35);
            engineGroup.add(wL, wR);
        }

        // Headlight with Glowing Yellow Spotlight Cone
        const headlightBox = new THREE.Mesh(new THREE.BoxGeometry(0.6, 0.6, 0.6), new THREE.MeshStandardMaterial({ color: 0xf5a623, metalness: 0.8 }));
        headlightBox.position.set(4.2, 2.2, 0); engineGroup.add(headlightBox);

        const headlight = new THREE.PointLight(0xfde047, 3, 30);
        headlight.position.set(4.6, 2.2, 0); engineGroup.add(headlight);

        engineGroup.position.set(0, 0, 0);
        trainGroup.add(engineGroup);

        // 2. Coal Tender Car
        const tenderGroup = new THREE.Group();
        const tenderBody = new THREE.Mesh(new THREE.BoxGeometry(4.2, 1.8, 2.5), new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.8 }));
        tenderBody.position.set(-5.6, 1.3, 0); tenderBody.castShadow = true;
        const coalMound = new THREE.Mesh(new THREE.SphereGeometry(1.6, 8, 6), new THREE.MeshStandardMaterial({ color: 0x020617, roughness: 1 }));
        coalMound.scale.set(1.2, 0.4, 0.7); coalMound.position.set(-5.6, 2.2, 0);
        tenderGroup.add(tenderBody, coalMound);
        trainGroup.add(tenderGroup);

        // 3. Passenger Coaches
        for (let c = 1; c <= 3; c++) {
            const coachGroup = new THREE.Group();
            const coachBody = new THREE.Mesh(new THREE.BoxGeometry(6.4, 2.6, 2.6), new THREE.MeshStandardMaterial({ color: (c % 2 === 0) ? 0x0284c7 : 0x16a34a, roughness: 0.4 }));
            coachBody.position.set(-5.6 - c * 7.2, 1.7, 0); coachBody.castShadow = true;
            
            // Roof Vents
            const coachRoof = new THREE.Mesh(new THREE.BoxGeometry(6.6, 0.3, 2.7), new THREE.MeshStandardMaterial({ color: 0xf8fafc }));
            coachRoof.position.set(-5.6 - c * 7.2, 3.1, 0);
            
            // Windows
            const winMat = new THREE.MeshStandardMaterial({ color: 0xfde047, emissive: 0xfde047, emissiveIntensity: 0.8 });
            for (let wx = -2.2; wx <= 2.2; wx += 1.4) {
                const wL = new THREE.Mesh(new THREE.BoxGeometry(0.8, 0.9, 0.1), winMat);
                wL.position.set(-5.6 - c * 7.2 + wx, 2.0, 1.32);
                const wR = wL.clone(); wR.position.set(-5.6 - c * 7.2 + wx, 2.0, -1.32);
                coachGroup.add(wL, wR);
            }

            coachGroup.add(coachBody, coachRoof);
            trainGroup.add(coachGroup);
        }

        trainGroup.position.set(originX - CHUNK_SIZE/2 + seededRandom(seed+1)*CHUNK_SIZE, 0, originZ);
        trainGroup.userData = { speed: 0.24 + seededRandom(seed+2)*0.16, trackZ: originZ, originX: originX, chimneyX: 3.2, chimneyY: 3.4 };
        
        return trainGroup;
    }

    // -------------------------------------------------------------
    // ULTRA-DETAILED REALISTIC 3D CARGO SHIPS & SAILBOATS (Tàu Biển Dập Dềnh Sóng)
    // -------------------------------------------------------------
    function createUltraDetailedShip(originX, originZ, seed) {
        const shipGroup = new THREE.Group();
        const shipType = Math.floor(seededRandom(seed) * 2);

        if (shipType === 0) {
            // Container Freight Ship
            // Lower Red Underwater Hull
            const redHullGeo = new THREE.BoxGeometry(14, 1.4, 4.4);
            const redHullMat = new THREE.MeshStandardMaterial({ color: 0x991b1b, roughness: 0.5 });
            const redHull = new THREE.Mesh(redHullGeo, redHullMat);
            redHull.position.y = 0.5; redHull.castShadow = true; shipGroup.add(redHull);

            // Upper Navy Blue Hull & Pointed Bow
            const navyHullGeo = new THREE.BoxGeometry(13.8, 1.6, 4.2);
            const navyHullMat = new THREE.MeshStandardMaterial({ color: 0x0f172a, roughness: 0.3 });
            const navyHull = new THREE.Mesh(navyHullGeo, navyHullMat);
            navyHull.position.y = 1.9; navyHull.castShadow = true; shipGroup.add(navyHull);

            // Pointed Bow Nose
            const bowGeo = new THREE.ConeGeometry(2.1, 4.2, 4);
            const bow = new THREE.Mesh(bowGeo, navyHullMat);
            bow.rotation.z = -Math.PI / 2; bow.rotation.x = Math.PI / 4;
            bow.position.set(8.5, 1.9, 0); shipGroup.add(bow);

            // Multi-colored Container Stacks on Deck
            const containerColors = [0xdc2626, 0x0284c7, 0x16a34a, 0xca8a04, 0x7c3aed];
            for (let cx = -4; cx <= 3; cx += 2.2) {
                for (let cy = 0; cy < 2; cy++) {
                    const cMat = new THREE.MeshStandardMaterial({ color: containerColors[Math.floor(Math.abs(cx+cy)) % containerColors.length], roughness: 0.6 });
                    const container = new THREE.Mesh(new THREE.BoxGeometry(2.0, 1.2, 3.6), cMat);
                    container.position.set(cx, 3.2 + cy * 1.3, 0); container.castShadow = true;
                    shipGroup.add(container);
                }
            }

            // Rear Bridge Tower & Smokestack
            const bridgeGeo = new THREE.BoxGeometry(3.2, 4.2, 3.8);
            const bridgeMat = new THREE.MeshStandardMaterial({ color: 0xf8fafc, roughness: 0.3 });
            const bridge = new THREE.Mesh(bridgeGeo, bridgeMat);
            bridge.position.set(-5.2, 4.8, 0); bridge.castShadow = true; shipGroup.add(bridge);

            // Captain Wheelhouse Windows
            const winGeo = new THREE.BoxGeometry(3.3, 0.6, 3.9);
            const winMat = new THREE.MeshStandardMaterial({ color: 0x38bdf8, emissive: 0x38bdf8, emissiveIntensity: 0.6 });
            const windows = new THREE.Mesh(winGeo, winMat);
            windows.position.set(-5.2, 6.2, 0); shipGroup.add(windows);

            // Smokestack Funnel
            const funnel = new THREE.Mesh(new THREE.CylinderGeometry(0.6, 0.7, 2.2, 12), new THREE.MeshStandardMaterial({ color: 0xdc2626 }));
            funnel.position.set(-5.8, 7.8, 0); shipGroup.add(funnel);

        } else {
            // Classic Wooden Sailboat / Frigate
            const hullGeo = new THREE.BoxGeometry(10, 2.2, 3.4);
            const hullMat = new THREE.MeshStandardMaterial({ color: 0x78350f, roughness: 0.7 });
            const hull = new THREE.Mesh(hullGeo, hullMat);
            hull.position.y = 1.1; hull.castShadow = true; shipGroup.add(hull);

            // 2 Masts with Billowing White Cloth Sails
            const mastMat = new THREE.MeshStandardMaterial({ color: 0x451a03 });
            const sailMat = new THREE.MeshStandardMaterial({ color: 0xffffff, side: THREE.DoubleSide, roughness: 0.9 });

            [-1.8, 2.2].forEach(mx => {
                const mast = new THREE.Mesh(new THREE.CylinderGeometry(0.15, 0.22, 8.5, 8), mastMat);
                mast.position.set(mx, 5.2, 0); shipGroup.add(mast);

                const sail = new THREE.Mesh(new THREE.PlaneGeometry(3.6, 5.5), sailMat);
                sail.position.set(mx, 5.8, 0.8); sail.rotation.y = 0.2;
                shipGroup.add(sail);
            });
        }

        shipGroup.position.set(originX + (seededRandom(seed+1)-0.5)*(CHUNK_SIZE-30), 0.1, originZ + (seededRandom(seed+2)-0.5)*(CHUNK_SIZE-30));
        shipGroup.userData = { bobOffset: Math.random()*Math.PI*2, speedX: (Math.random()-0.5)*0.07 };
        return shipGroup;
    }

    // PROCEDURAL CHUNK BUILDER
    function createChunkGroup(cx, cz, mapKey) {
        const theme = MAP_THEMES[mapKey] || MAP_THEMES.park;
        const chunkGroup = new THREE.Group();
        const originX = cx * CHUNK_SIZE;
        const originZ = cz * CHUNK_SIZE;

        // Ground Tile
        const groundGeo = new THREE.BoxGeometry(CHUNK_SIZE, 2, CHUNK_SIZE);
        const groundMat = new THREE.MeshStandardMaterial({ color: theme.ground, roughness: 0.75 });
        const ground = new THREE.Mesh(groundGeo, groundMat);
        ground.position.set(originX, -1, originZ);
        ground.receiveShadow = true;
        chunkGroup.add(ground);

        let seed = (cx * 73856093) ^ (cz * 19349663);

        // 1. CITY & HOUSES GENERATION
        seed++;
        if (seededRandom(seed) > 0.35) {
            const isCityChunk = (Math.abs(cx + cz) % 4 === 0);
            const buildingCount = isCityChunk ? (3 + Math.floor(seededRandom(seed+1)*3)) : (2 + Math.floor(seededRandom(seed+1)*2));

            for (let b = 0; b < buildingCount; b++) {
                seed++;
                const bx = originX + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 28);
                seed++;
                const bz = originZ + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 28);

                let buildingGroup;
                if (isCityChunk) {
                    buildingGroup = createUltraDetailedSkyscraper(seed, theme);
                } else {
                    buildingGroup = createUltraDetailedHouse(seed, theme);
                }

                buildingGroup.position.set(bx, 0, bz);
                chunkGroup.add(buildingGroup);
            }
        }

        // 2. RAILWAY TRACKS & STEAM LOCOMOTIVE TRAIN
        if (Math.abs(cz) % 5 === 0) {
            const railGroup = new THREE.Group();
            const railMat = new THREE.MeshStandardMaterial({ color: 0x475569, metalness: 0.8, roughness: 0.2 });
            const sleeperMat = new THREE.MeshStandardMaterial({ color: 0x451a03, roughness: 0.9 });

            // Metallic rails across X axis
            const railL = new THREE.Mesh(new THREE.BoxGeometry(CHUNK_SIZE, 0.18, 0.22), railMat);
            const railR = railL.clone();
            railL.position.set(originX, 0.12, originZ - 1.35);
            railR.position.set(originX, 0.12, originZ + 1.35);
            railGroup.add(railL, railR);

            // Wooden sleepers
            for (let s = -CHUNK_SIZE/2 + 2; s < CHUNK_SIZE/2; s += 2.8) {
                const sleeper = new THREE.Mesh(new THREE.BoxGeometry(0.55, 0.12, 3.5), sleeperMat);
                sleeper.position.set(originX + s, 0.06, originZ);
                railGroup.add(sleeper);
            }
            chunkGroup.add(railGroup);

            // Spawn Moving 3D Steam Train
            seed++;
            if (seededRandom(seed) > 0.35) {
                const train = createUltraDetailedTrain(originX, originZ, seed);
                scene.add(train);
                activeTrains.push(train);
            }
        }

        // 3. OCEAN, BEACH & SEA SHIPS
        const isSeaChunk = (Math.abs(cx) % 4 === 0 && cz < -CHUNK_SIZE);
        if (isSeaChunk) {
            // Ocean Plane
            const seaGeo = new THREE.PlaneGeometry(CHUNK_SIZE, CHUNK_SIZE);
            const seaMat = new THREE.MeshStandardMaterial({
                color: theme.water, roughness: 0.1, metalness: 0.5, transparent: true, opacity: 0.92,
                emissive: theme.emissiveWater, emissiveIntensity: theme.emissiveIntensity
            });
            const sea = new THREE.Mesh(seaGeo, seaMat);
            sea.rotation.x = -Math.PI / 2; sea.position.set(originX, 0.05, originZ);
            chunkGroup.add(sea);

            // Sandy Beach
            const beachGeo = new THREE.PlaneGeometry(CHUNK_SIZE, 15);
            const beachMat = new THREE.MeshStandardMaterial({ color: 0xfde047, roughness: 0.9 });
            const beach = new THREE.Mesh(beachGeo, beachMat);
            beach.rotation.x = -Math.PI / 2; beach.position.set(originX, 0.08, originZ + CHUNK_SIZE/2 - 7.5);
            chunkGroup.add(beach);

            // Palm Trees
            for (let p = 0; p < 4; p++) {
                seed++;
                const px = originX + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 12);
                const pz = originZ + CHUNK_SIZE/2 - 7.5 + (seededRandom(seed+1) - 0.5) * 6;

                const palmGroup = new THREE.Group();
                const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 0.38, 3.8, 8), new THREE.MeshStandardMaterial({ color: 0x78350f }));
                trunk.position.y = 1.9;
                const leaves = new THREE.Mesh(new THREE.SphereGeometry(1.8, 8, 6), new THREE.MeshStandardMaterial({ color: 0x15803d }));
                leaves.scale.set(1.6, 0.45, 1.6); leaves.position.y = 3.8;
                palmGroup.add(trunk, leaves);
                palmGroup.position.set(px, 0, pz);
                chunkGroup.add(palmGroup);
            }

            // Spawn Animated Ship
            seed++;
            if (seededRandom(seed) > 0.25) {
                const ship = createUltraDetailedShip(originX, originZ, seed);
                scene.add(ship);
                activeShips.push(ship);
            }
        }

        // 4. LAKES & PONDS
        seed++;
        if (!isSeaChunk && seededRandom(seed) > 0.48) {
            const pondRadius = 7 + seededRandom(seed+1) * 9;
            const pondGeo = new THREE.CircleGeometry(pondRadius, 28);
            const pondMat = new THREE.MeshStandardMaterial({
                color: theme.water, roughness: 0.12, metalness: 0.3, transparent: true, opacity: 0.92,
                emissive: theme.emissiveWater, emissiveIntensity: theme.emissiveIntensity
            });
            const pond = new THREE.Mesh(pondGeo, pondMat);
            pond.rotation.x = -Math.PI / 2;
            const pondX = originX + (seededRandom(seed+2) - 0.5) * (CHUNK_SIZE - 22);
            const pondZ = originZ + (seededRandom(seed+3) - 0.5) * (CHUNK_SIZE - 22);
            pond.position.set(pondX, 0.06, pondZ);
            chunkGroup.add(pond);

            // Lilypads
            for (let l = 0; l < 4; l++) {
                seed++;
                const pad = new THREE.Mesh(new THREE.CircleGeometry(0.6 + seededRandom(seed)*0.4, 8), new THREE.MeshStandardMaterial({ color: 0x22c55e }));
                pad.rotation.x = -Math.PI / 2;
                pad.position.set(pondX + (seededRandom(seed+1)-0.5)*(pondRadius*0.8), 0.08, pondZ + (seededRandom(seed+2)-0.5)*(pondRadius*0.8));
                chunkGroup.add(pad);
            }
        }

        // 5. TREES GENERATION
        for (let t = 0; t < 12; t++) {
            seed++;
            const tx = originX + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 14);
            seed++;
            const tz = originZ + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 14);

            const treeGroup = new THREE.Group();
            const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.38, 0.52, 3.0, 8), new THREE.MeshStandardMaterial({ color: theme.treeTrunk, roughness: 0.9 }));
            trunk.position.y = 1.5; trunk.castShadow = true;

            const foliage = new THREE.Mesh(new THREE.ConeGeometry(2.2, 3.5, 8), new THREE.MeshStandardMaterial({ color: theme.treeLeaf, roughness: 0.6 }));
            foliage.position.y = 3.5; foliage.castShadow = true;

            treeGroup.add(trunk, foliage);
            treeGroup.position.set(tx, 0, tz);
            chunkGroup.add(treeGroup);
        }

        // Spawn Animals
        spawnAnimalsInChunk(cx, cz, chunkGroup, mapKey, seed);

        return chunkGroup;
    }

    function spawnAnimalsInChunk(cx, cz, chunkGroup, mapKey, seedBase) {
        const originX = cx * CHUNK_SIZE;
        const originZ = cz * CHUNK_SIZE;
        let seed = seedBase + 777;

        for (let i = 0; i < 3; i++) {
            seed++;
            const ax = originX + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 18);
            seed++;
            const az = originZ + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 18);

            let animalGroup = new THREE.Group();
            const matR = new THREE.MeshStandardMaterial({ color: 0xf8fafc, roughness: 0.6 });
            const body = new THREE.Mesh(new THREE.SphereGeometry(0.4, 10, 10), matR);
            body.position.y = 0.4; body.castShadow = true;
            animalGroup.add(body);

            animalGroup.position.set(ax, 0, az);
            animalGroup.userData = { wanderAngle: Math.random()*Math.PI*2, speed: 0.02 + Math.random()*0.02, chunkKey: `${cx},${cz}` };
            chunkGroup.add(animalGroup);
            activeAnimals.push(animalGroup);
        }
    }

    function updateWorldChunksAroundPlayer(px, pz) {
        const currentChunkX = Math.floor((px + CHUNK_SIZE / 2) / CHUNK_SIZE);
        const currentChunkZ = Math.floor((pz + CHUNK_SIZE / 2) / CHUNK_SIZE);

        const neededKeys = new Set();
        for (let dx = -LOAD_RADIUS; dx <= LOAD_RADIUS; dx++) {
            for (let dz = -LOAD_RADIUS; dz <= LOAD_RADIUS; dz++) {
                const cx = currentChunkX + dx;
                const cz = currentChunkZ + dz;
                const key = `${cx},${cz}`;
                neededKeys.add(key);

                if (!activeChunks.has(key)) {
                    const chunkGroup = createChunkGroup(cx, cz, currentMapWorld);
                    scene.add(chunkGroup);
                    activeChunks.set(key, chunkGroup);
                }
            }
        }

        for (const [key, group] of activeChunks.entries()) {
            if (!neededKeys.has(key)) {
                scene.remove(group);
                activeChunks.delete(key);
            }
        }

        const distExplored = Math.floor(Math.sqrt(px*px + pz*pz));
        document.getElementById('dist-val').innerText = distExplored + 'm';
        document.getElementById('chunk-val').innerText = `[${currentChunkX}, ${currentChunkZ}]`;
    }

    // DUCK CHARACTER BUILDER
    function create3DDuck(skinKey) {
        if (duckGroup) scene.remove(duckGroup);
        duckGroup = new THREE.Group();

        let bodyColor = 0xffd43b;
        let beakColor = 0xf97316;

        if (skinKey === 'cool') bodyColor = 0xf97316;
        else if (skinKey === 'king') bodyColor = 0x0284c7;
        else if (skinKey === 'fairy') bodyColor = 0xf472b6;
        else if (skinKey === 'ninja') { bodyColor = 0x334155; beakColor = 0xe2e8f0; }

        const bodyGeo = new THREE.SphereGeometry(1.2, 24, 24);
        const duckMat = new THREE.MeshStandardMaterial({ color: bodyColor, roughness: 0.3, metalness: 0.1 });
        duckBody = new THREE.Mesh(bodyGeo, duckMat); duckBody.position.y = 1.2; duckBody.castShadow = true;
        duckGroup.add(duckBody);

        const headGeo = new THREE.SphereGeometry(0.85, 20, 20);
        duckHead = new THREE.Mesh(headGeo, duckMat); duckHead.position.set(0, 2.2, 0.3); duckHead.castShadow = true;
        duckGroup.add(duckHead);

        const beakGeo = new THREE.BoxGeometry(0.5, 0.22, 0.5);
        const beakMat = new THREE.MeshStandardMaterial({ color: beakColor, roughness: 0.4 });
        duckBeak = new THREE.Mesh(beakGeo, beakMat); duckBeak.position.set(0, 2.1, 1.1);
        duckGroup.add(duckBeak);

        const eyeGeo = new THREE.SphereGeometry(0.12, 12, 12);
        const eyeMat = new THREE.MeshBasicMaterial({ color: 0x0f172a });
        const eyeL = new THREE.Mesh(eyeGeo, eyeMat); eyeL.position.set(-0.35, 2.4, 0.95);
        const eyeR = new THREE.Mesh(eyeGeo, eyeMat); eyeR.position.set(0.35, 2.4, 0.95);
        duckGroup.add(eyeL, eyeR);

        const wingGeo = new THREE.BoxGeometry(0.2, 0.7, 0.9);
        duckWingL = new THREE.Mesh(wingGeo, duckMat); duckWingL.position.set(-1.2, 1.3, 0);
        duckWingR = new THREE.Mesh(wingGeo, duckMat); duckWingR.position.set(1.2, 1.3, 0);
        duckGroup.add(duckWingL, duckWingR);

        duckGroup.position.set(0, 0, 0);
        scene.add(duckGroup);
    }

    // FRUITS SPANWER
    function maintainFruitsAroundPlayer(px, pz) {
        for (let i = foods.length - 1; i >= 0; i--) {
            const food = foods[i];
            const dist = Math.sqrt((food.position.x - px)**2 + (food.position.z - pz)**2);
            if (dist > 220) {
                scene.remove(food);
                foods.splice(i, 1);
            }
        }

        while (foods.length < 40) {
            let itemGroup = new THREE.Group();
            const appleGeo = new THREE.SphereGeometry(0.65, 16, 16);
            const appleMat = new THREE.MeshStandardMaterial({ color: 0xdc2626, roughness: 0.2 });
            const apple = new THREE.Mesh(appleGeo, appleMat); apple.position.y = 0.65; apple.castShadow = true;
            itemGroup.add(apple);

            const angle = Math.random() * Math.PI * 2;
            const dist = 12 + Math.random() * 110;
            const fx = px + Math.cos(angle) * dist;
            const fz = pz + Math.sin(angle) * dist;

            itemGroup.position.set(fx, 0, fz);
            itemGroup.userData = { points: 15, rotSpeed: 0.03, floatOffset: Math.random()*Math.PI*2 };
            scene.add(itemGroup);
            foods.push(itemGroup);
        }
    }

    // MAIN ANIMATION LOOP
    function animate() {
        requestAnimationFrame(animate);
        const time = Date.now() * 0.003;

        if (gameActive) {
            let dx = 0, dz = 0;
            if (keys.w || keys.W || keys.ArrowUp)    dz -= 1;
            if (keys.s || keys.S || keys.ArrowDown)  dz += 1;
            if (keys.a || keys.A || keys.ArrowLeft)  dx -= 1;
            if (keys.d || keys.D || keys.ArrowRight) dx += 1;

            if (moveVector.x !== 0 || moveVector.z !== 0) {
                dx = moveVector.x; dz = moveVector.z;
            }

            if (dx !== 0 || dz !== 0) {
                const speed = 0.35;
                
                if (isFirstPerson) {
                    const forwardX = -Math.sin(fpYaw);
                    const forwardZ = -Math.cos(fpYaw);
                    const rightX = Math.cos(fpYaw);
                    const rightZ = -Math.sin(fpYaw);

                    const moveX = (forwardX * (-dz) + rightX * dx);
                    const moveZ = (forwardZ * (-dz) + rightZ * dx);

                    duckGroup.position.x += moveX * speed;
                    duckGroup.position.z += moveZ * speed;
                    duckGroup.rotation.y = fpYaw;
                } else {
                    duckGroup.position.x += dx * speed;
                    duckGroup.position.z += dz * speed;

                    const targetAngle = Math.atan2(dx, dz);
                    duckGroup.rotation.y = targetAngle;
                }

                duckGroup.position.y = Math.abs(Math.sin(time * 14)) * 0.4;
                duckWingL.rotation.z = Math.sin(time * 16) * 0.45;
                duckWingR.rotation.z = -Math.sin(time * 16) * 0.45;
            } else {
                duckGroup.position.y = Math.sin(time * 3) * 0.08;
                duckWingL.rotation.z = 0; duckWingR.rotation.z = 0;
            }

            // CAMERA CONTROLLER: FIRST PERSON VS THIRD PERSON
            if (isFirstPerson) {
                const headPosY = duckGroup.position.y + 2.3;
                camera.position.set(duckGroup.position.x, headPosY, duckGroup.position.z + 0.3);

                const dirX = Math.sin(fpYaw) * Math.cos(fpPitch);
                const dirY = Math.sin(fpPitch);
                const dirZ = Math.cos(fpYaw) * Math.cos(fpPitch);

                camera.lookAt(
                    camera.position.x - dirX * 10,
                    camera.position.y + dirY * 10,
                    camera.position.z - dirZ * 10
                );
            } else {
                camera.position.x = THREE.MathUtils.lerp(camera.position.x, duckGroup.position.x, 0.08);
                camera.position.z = THREE.MathUtils.lerp(camera.position.z, duckGroup.position.z + 28, 0.08);
                camera.position.y = THREE.MathUtils.lerp(camera.position.y, duckGroup.position.y + 22, 0.08);
                camera.lookAt(duckGroup.position.x, 1, duckGroup.position.z);
            }

            if (dirLight) {
                dirLight.position.set(duckGroup.position.x + 45, 65, duckGroup.position.z + 45);
            }

            updateWorldChunksAroundPlayer(duckGroup.position.x, duckGroup.position.z);
            maintainFruitsAroundPlayer(duckGroup.position.x, duckGroup.position.z);

            // Food Collisions
            for (let i = foods.length - 1; i >= 0; i--) {
                const food = foods[i];
                food.rotation.y += food.userData.rotSpeed;
                food.position.y = 0.2 + Math.sin(time * 4 + food.userData.floatOffset) * 0.25;

                const dist = duckGroup.position.distanceTo(food.position);
                if (dist < 2.2) {
                    playEatSound();
                    score += food.userData.points;
                    document.getElementById('score-val').innerText = score;

                    scene.remove(food);
                    foods.splice(i, 1);
                    maintainFruitsAroundPlayer(duckGroup.position.x, duckGroup.position.z);
                }
            }

            // Animate Trains
            activeTrains.forEach(train => {
                train.position.x += train.userData.speed;
                if (train.position.x > train.userData.originX + CHUNK_SIZE) {
                    train.position.x = train.userData.originX - CHUNK_SIZE;
                }
            });

            // Animate Ships
            activeShips.forEach(ship => {
                ship.position.y = 0.1 + Math.sin(time * 2 + ship.userData.bobOffset) * 0.18;
                ship.position.x += ship.userData.speedX;
            });
        }

        if (activeParticles) {
            activeParticles.rotation.y += 0.0008;
        }

        renderer.render(scene, camera);
    }

    function onWindowResize() {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    }

    function showStartModal() {
        document.getElementById('start-modal').style.display = 'flex';
        document.getElementById('end-modal').style.display = 'none';
        document.getElementById('lb-modal').style.display = 'none';
    }

    function startGame() {
        rebuildMapEnvironment();
        create3DDuck(currentDuckSkin);

        document.getElementById('start-modal').style.display = 'none';
        document.getElementById('end-modal').style.display = 'none';
        document.getElementById('lb-modal').style.display = 'none';
        
        score = 0;
        timeLeft = selectedTime;
        gameActive = true;

        for (let fi = foods.length - 1; fi >= 0; fi--) {
            scene.remove(foods[fi]);
        }
        foods = [];
        maintainFruitsAroundPlayer(0, 0);
        
        document.getElementById('score-val').innerText = '0';
        document.getElementById('time-val').innerText = formatTime(timeLeft);

        if (duckGroup) duckGroup.position.set(0, 0, 0);

        playQuackSound();

        if (timerInterval) clearInterval(timerInterval);
        timerInterval = setInterval(function() {
            if (!gameActive) return;
            timeLeft--;
            document.getElementById('time-val').innerText = formatTime(timeLeft);
            
            if (timeLeft <= 0) {
                endGame();
            }
        }, 1000);
    }

    function endGame() {
        gameActive = false;
        clearInterval(timerInterval);
        playQuackSound();

        if (document.exitPointerLock) document.exitPointerLock();

        document.getElementById('final-score').innerText = score + ' ĐIỂM';
        const vResult = document.getElementById('voucher-result');
        if (score >= 100) {
            vResult.style.display = 'block';
        } else {
            vResult.style.display = 'none';
        }

        if (score > 0) {
            const formData = new FormData();
            formData.append('score', score);
            formData.append('player_name', loggedInPlayerName);

            fetch('/Game/duck_game.php?action=submit_score', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' && data.leaderboard) {
                    renderLeaderboardTable(data.leaderboard);
                }
            })
            .catch(err => console.log('Score submit error:', err));
        }

        document.getElementById('end-modal').style.display = 'flex';
    }

    function toggleLeaderboardModal() {
        const lb = document.getElementById('lb-modal');
        if (lb.style.display === 'flex') {
            lb.style.display = 'none';
        } else {
            lb.style.display = 'flex';
        }
    }

    function renderLeaderboardTable(list) {
        const tbody = document.getElementById('lb-table-body');
        if (!tbody) return;

        let html = '';
        list.forEach((row, idx) => {
            const isMe = (row.player_name === loggedInPlayerName);
            const rankBadge = (idx < 3) ? `<span class="rank-badge rank-${idx+1}">${idx+1}</span>` : `#${idx+1}`;
            html += `
                <tr class="${isMe ? 'highlight' : ''}">
                    <td>${rankBadge}</td>
                    <td>${escapeHtml(row.player_name)}</td>
                    <td style="text-align:right;font-weight:800;color:#fbbf24;">${Number(row.score).toLocaleString()}</td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    window.addEventListener('DOMContentLoaded', init3D);
    </script>
</body>
</html>
