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
    <title>Vịt Con 3D Háu Ăn - Đồ Họa 3D Cao Cấp & Bản Đồ Sống Động</title>
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
            width: 14px; height: 14px;
            transform: translate(-50%, -50%);
            pointer-events: none;
            display: none;
            z-index: 20;
        }
        #crosshair::before, #crosshair::after {
            content: ''; position: absolute; background: rgba(255, 255, 255, 0.85);
            box-shadow: 0 0 4px rgba(0,0,0,0.8);
        }
        #crosshair::before { top: 6px; left: 0; width: 14px; height: 2px; }
        #crosshair::after { top: 0; left: 6px; width: 2px; height: 14px; }

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
            <p data-i18n="start_subtitle">Khám phá thế giới 3D đa dạng: Thành phố, Nhà cửa, Đường rẫy tàu hỏa, Bãi biển, Tàu biển, Thác nước & Hồ ao!</p>

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
        // Limit pitch looking up/down
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
    let trainSteamParticles = [];
    let score = 0, timeLeft = 60, gameActive = false, timerInterval;

    const CHUNK_SIZE = 90;
    const LOAD_RADIUS = 2;
    const activeChunks = new Map();

    const MAP_THEMES = {
        park: {
            bg: 0x0b172a, ground: 0x15803d, water: 0x0284c7, treeLeaf: 0x16a34a, treeTrunk: 0x78350f, rock: 0x64748b, bridge: 0x92400e,
            emissiveWater: 0x0284c7, emissiveIntensity: 0.1, particleColor: 0x86efac
        },
        volcano: {
            bg: 0x1c0a0a, ground: 0x262626, water: 0xeab308, treeLeaf: 0xd97706, treeTrunk: 0x451a03, rock: 0x57534e, bridge: 0x44403c,
            emissiveWater: 0xd97706, emissiveIntensity: 0.7, particleColor: 0xf97316
        },
        snow: {
            bg: 0x0f172a, ground: 0xf1f5f9, water: 0x38bdf8, treeLeaf: 0x0284c7, treeTrunk: 0x334155, rock: 0x94a3b8, bridge: 0x475569,
            emissiveWater: 0x38bdf8, emissiveIntensity: 0.1, particleColor: 0xffffff
        },
        galaxy: {
            bg: 0x080414, ground: 0x3b0764, water: 0xc084fc, treeLeaf: 0xf43f5e, treeTrunk: 0x6b21a8, rock: 0xa855f7, bridge: 0x581c87,
            emissiveWater: 0xc084fc, emissiveIntensity: 0.8, particleColor: 0xec4899
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
        scene.fog = new THREE.FogExp2(theme.bg, 0.007);

        // Remove old lights
        scene.children.filter(c => c.isLight).forEach(l => scene.remove(l));

        hemiLight = new THREE.HemisphereLight(0xffffff, theme.ground, 0.65);
        scene.add(hemiLight);

        dirLight = new THREE.DirectionalLight((currentMapWorld === 'volcano') ? 0xff6622 : 0xfffae6, 1.35);
        dirLight.position.set(40, 60, 40);
        dirLight.castShadow = true;
        dirLight.shadow.mapSize.width = 2048;
        dirLight.shadow.mapSize.height = 2048;
        dirLight.shadow.bias = -0.0001;
        dirLight.shadow.camera.near = 0.5;
        dirLight.shadow.camera.far = 250;
        dirLight.shadow.camera.left = -70;
        dirLight.shadow.camera.right = 70;
        dirLight.shadow.camera.top = 70;
        dirLight.shadow.camera.bottom = -70;
        scene.add(dirLight);

        // Particle atmosphere
        createWeatherParticles(theme.particleColor);

        // Clear active chunks
        for (const [key, group] of activeChunks.entries()) {
            scene.remove(group);
        }
        activeChunks.clear();
        activeTrains = [];
        activeShips = [];

        updateWorldChunksAroundPlayer(0, 0);
    }

    // Atmosphere Particle System
    function createWeatherParticles(colorHex) {
        if (activeParticles) scene.remove(activeParticles);
        const count = 400;
        const geom = new THREE.BufferGeometry();
        const positions = new Float32Array(count * 3);

        for (let i = 0; i < count; i++) {
            positions[i*3] = (Math.random() - 0.5) * 350;
            positions[i*3+1] = Math.random() * 100;
            positions[i*3+2] = (Math.random() - 0.5) * 350;
        }

        geom.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        const mat = new THREE.PointsMaterial({
            color: colorHex,
            size: 1.4,
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

    // PROCEDURAL CHUNK BUILDER (Houses, City, Railways, Trains, Lakes, Ocean, Ships, Docks, Beaches)
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

        // 1. CITY & HOUSES (Thành phố, Nhà cửa, Đèn đường)
        seed++;
        if (seededRandom(seed) > 0.4) {
            const isCityChunk = (Math.abs(cx + cz) % 4 === 0);
            const buildingCount = isCityChunk ? (3 + Math.floor(seededRandom(seed+1)*3)) : (2 + Math.floor(seededRandom(seed+1)*2));

            for (let b = 0; b < buildingCount; b++) {
                seed++;
                const bx = originX + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 24);
                seed++;
                const bz = originZ + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 24);

                const buildingGroup = new THREE.Group();
                if (isCityChunk) {
                    // Modern City Skyscrapers
                    const height = 14 + seededRandom(seed+2) * 22;
                    const w = 6 + seededRandom(seed+3) * 5;
                    const d = 6 + seededRandom(seed+4) * 5;

                    const bodyGeo = new THREE.BoxGeometry(w, height, d);
                    const bodyMat = new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.3, metalness: 0.4 });
                    const body = new THREE.Mesh(bodyGeo, bodyMat);
                    body.position.y = height / 2;
                    body.castShadow = true; body.receiveShadow = true;
                    buildingGroup.add(body);

                    // Glowing Windows
                    const winMat = new THREE.MeshStandardMaterial({ color: 0xfde047, emissive: 0xfde047, emissiveIntensity: 0.8 });
                    for (let wy = 3; wy < height - 2; wy += 3.5) {
                        const win = new THREE.Mesh(new THREE.BoxGeometry(w + 0.1, 1.2, d + 0.1), winMat);
                        win.position.y = wy;
                        buildingGroup.add(win);
                    }
                } else {
                    // Suburban Houses with tiled roofs & doors
                    const houseBodyGeo = new THREE.BoxGeometry(5.5, 3.2, 4.5);
                    const houseBodyMat = new THREE.MeshStandardMaterial({ color: 0xe2e8f0, roughness: 0.8 });
                    const houseBody = new THREE.Mesh(houseBodyGeo, houseBodyMat);
                    houseBody.position.y = 1.6;
                    houseBody.castShadow = true;

                    const roofGeo = new THREE.ConeGeometry(4.2, 2.2, 4);
                    const roofMat = new THREE.MeshStandardMaterial({ color: 0xb91c1c, roughness: 0.6 });
                    const roof = new THREE.Mesh(roofGeo, roofMat);
                    roof.rotation.y = Math.PI / 4;
                    roof.position.y = 4.2;
                    roof.castShadow = true;

                    const door = new THREE.Mesh(new THREE.BoxGeometry(1.0, 1.8, 0.2), new THREE.MeshStandardMaterial({ color: 0x78350f }));
                    door.position.set(0, 0.9, 2.3);

                    buildingGroup.add(houseBody, roof, door);
                }

                buildingGroup.position.set(bx, 0, bz);
                chunkGroup.add(buildingGroup);
            }
        }

        // 2. RAILWAY TRACKS & MOVING TRAIN (Đường rẫy & Tàu hỏa)
        if (Math.abs(cz) % 5 === 0) {
            const railGroup = new THREE.Group();
            const railMat = new THREE.MeshStandardMaterial({ color: 0x475569, metalness: 0.8, roughness: 0.2 });
            const sleeperMat = new THREE.MeshStandardMaterial({ color: 0x451a03, roughness: 0.9 });

            // Metallic rails across X axis
            const railL = new THREE.Mesh(new THREE.BoxGeometry(CHUNK_SIZE, 0.15, 0.2), railMat);
            const railR = railL.clone();
            railL.position.set(originX, 0.12, originZ - 1.2);
            railR.position.set(originX, 0.12, originZ + 1.2);
            railGroup.add(railL, railR);

            // Wooden sleepers
            for (let s = -CHUNK_SIZE/2 + 2; s < CHUNK_SIZE/2; s += 3) {
                const sleeper = new THREE.Mesh(new THREE.BoxGeometry(0.5, 0.1, 3.2), sleeperMat);
                sleeper.position.set(originX + s, 0.06, originZ);
                railGroup.add(sleeper);
            }
            chunkGroup.add(railGroup);

            // Spawn Moving 3D Train along tracks
            seed++;
            if (seededRandom(seed) > 0.4) {
                const trainGroup = new THREE.Group();
                const engineGeo = new THREE.BoxGeometry(6, 2.8, 2.6);
                const engineMat = new THREE.MeshStandardMaterial({ color: 0xd97706, roughness: 0.3, metalness: 0.5 });
                const engine = new THREE.Mesh(engineGeo, engineMat);
                engine.position.y = 1.5; engine.castShadow = true;
                trainGroup.add(engine);

                // Chimney
                const chimney = new THREE.Mesh(new THREE.CylinderGeometry(0.35, 0.35, 1.2, 8), new THREE.MeshStandardMaterial({ color: 0x1e293b }));
                chimney.position.set(2, 3.2, 0); trainGroup.add(chimney);

                // Headlight
                const headlight = new THREE.PointLight(0xfde047, 2, 20);
                headlight.position.set(3.2, 1.6, 0); trainGroup.add(headlight);

                // Passenger Cars
                for (let c = 1; c <= 3; c++) {
                    const carGeo = new THREE.BoxGeometry(5.2, 2.4, 2.4);
                    const carMat = new THREE.MeshStandardMaterial({ color: (c % 2 === 0) ? 0x0284c7 : 0x16a34a });
                    const car = new THREE.Mesh(carGeo, carMat);
                    car.position.set(-c * 6.2, 1.3, 0); car.castShadow = true;
                    trainGroup.add(car);
                }

                trainGroup.position.set(originX - CHUNK_SIZE/2 + seededRandom(seed+1)*CHUNK_SIZE, 0, originZ);
                trainGroup.userData = { speed: 0.22 + seededRandom(seed+2)*0.18, trackZ: originZ, originX: originX };
                scene.add(trainGroup);
                activeTrains.push(trainGroup);
            }
        }

        // 3. OCEAN, BEACH & SEA SHIPS (Tàu biển, Biển & Bãi biển)
        const isSeaChunk = (Math.abs(cx) % 4 === 0 && cz < -CHUNK_SIZE);
        if (isSeaChunk) {
            // Large Sea Plane
            const seaGeo = new THREE.PlaneGeometry(CHUNK_SIZE, CHUNK_SIZE);
            const seaMat = new THREE.MeshStandardMaterial({
                color: theme.water, roughness: 0.1, metalness: 0.5, transparent: true, opacity: 0.92,
                emissive: theme.emissiveWater, emissiveIntensity: theme.emissiveIntensity
            });
            const sea = new THREE.Mesh(seaGeo, seaMat);
            sea.rotation.x = -Math.PI / 2; sea.position.set(originX, 0.05, originZ);
            chunkGroup.add(sea);

            // Sandy Beach
            const beachGeo = new THREE.PlaneGeometry(CHUNK_SIZE, 14);
            const beachMat = new THREE.MeshStandardMaterial({ color: 0xfde047, roughness: 0.9 });
            const beach = new THREE.Mesh(beachGeo, beachMat);
            beach.rotation.x = -Math.PI / 2; beach.position.set(originX, 0.08, originZ + CHUNK_SIZE/2 - 7);
            chunkGroup.add(beach);

            // Palm Trees on Beach
            for (let p = 0; p < 4; p++) {
                seed++;
                const px = originX + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 10);
                const pz = originZ + CHUNK_SIZE/2 - 7 + (seededRandom(seed+1) - 0.5) * 6;

                const palmGroup = new THREE.Group();
                const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 0.35, 3.5, 8), new THREE.MeshStandardMaterial({ color: 0x78350f }));
                trunk.position.y = 1.75;
                const leaves = new THREE.Mesh(new THREE.SphereGeometry(1.6, 8, 6), new THREE.MeshStandardMaterial({ color: 0x15803d }));
                leaves.scale.set(1.5, 0.4, 1.5); leaves.position.y = 3.5;
                palmGroup.add(trunk, leaves);
                palmGroup.position.set(px, 0, pz);
                chunkGroup.add(palmGroup);
            }

            // Animated Sea Ship / Boat
            seed++;
            if (seededRandom(seed) > 0.3) {
                const shipGroup = new THREE.Group();
                const hullGeo = new THREE.BoxGeometry(8, 2.2, 3.2);
                const hullMat = new THREE.MeshStandardMaterial({ color: 0xd97706, roughness: 0.4 });
                const hull = new THREE.Mesh(hullGeo, hullMat);
                hull.position.y = 1.1; hull.castShadow = true;
                shipGroup.add(hull);

                const cabin = new THREE.Mesh(new THREE.BoxGeometry(3.5, 2.0, 2.4), new THREE.MeshStandardMaterial({ color: 0xf8fafc }));
                cabin.position.set(-1, 2.8, 0); shipGroup.add(cabin);

                shipGroup.position.set(originX + (seededRandom(seed+1)-0.5)*(CHUNK_SIZE-20), 0.1, originZ + (seededRandom(seed+2)-0.5)*(CHUNK_SIZE-20));
                shipGroup.userData = { bobOffset: Math.random()*Math.PI*2, speedX: (Math.random()-0.5)*0.08 };
                scene.add(shipGroup);
                activeShips.push(shipGroup);
            }
        }

        // 4. LAKES, PONDS & WATERFALLS (Hồ ao & Cây xanh)
        seed++;
        if (!isSeaChunk && seededRandom(seed) > 0.5) {
            const pondRadius = 6 + seededRandom(seed+1) * 8;
            const pondGeo = new THREE.CircleGeometry(pondRadius, 24);
            const pondMat = new THREE.MeshStandardMaterial({
                color: theme.water, roughness: 0.15, metalness: 0.3, transparent: true, opacity: 0.9,
                emissive: theme.emissiveWater, emissiveIntensity: theme.emissiveIntensity
            });
            const pond = new THREE.Mesh(pondGeo, pondMat);
            pond.rotation.x = -Math.PI / 2;
            const pondX = originX + (seededRandom(seed+2) - 0.5) * (CHUNK_SIZE - 20);
            const pondZ = originZ + (seededRandom(seed+3) - 0.5) * (CHUNK_SIZE - 20);
            pond.position.set(pondX, 0.06, pondZ);
            chunkGroup.add(pond);

            // Lilypads on pond
            for (let l = 0; l < 3; l++) {
                seed++;
                const pad = new THREE.Mesh(new THREE.CircleGeometry(0.6 + seededRandom(seed)*0.4, 8), new THREE.MeshStandardMaterial({ color: 0x22c55e }));
                pad.rotation.x = -Math.PI / 2;
                pad.position.set(pondX + (seededRandom(seed+1)-0.5)*(pondRadius*0.8), 0.08, pondZ + (seededRandom(seed+2)-0.5)*(pondRadius*0.8));
                chunkGroup.add(pad);
            }
        }

        // 5. TREES & ROCKS GENERATION
        for (let t = 0; t < 10; t++) {
            seed++;
            const tx = originX + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 12);
            seed++;
            const tz = originZ + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 12);

            const treeGroup = new THREE.Group();
            const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.35, 0.5, 2.8, 8), new THREE.MeshStandardMaterial({ color: theme.treeTrunk, roughness: 0.9 }));
            trunk.position.y = 1.4; trunk.castShadow = true;

            const foliage = new THREE.Mesh(new THREE.ConeGeometry(2.0, 3.2, 8), new THREE.MeshStandardMaterial({ color: theme.treeLeaf, roughness: 0.6 }));
            foliage.position.y = 3.2; foliage.castShadow = true;

            treeGroup.add(trunk, foliage);
            treeGroup.position.set(tx, 0, tz);
            chunkGroup.add(treeGroup);
        }

        // Spawn Animals
        spawnAnimalsInChunk(cx, cz, chunkGroup, mapKey, seed);

        return chunkGroup;
    }

    // SPAWN WILDLIFE ANIMALS
    function spawnAnimalsInChunk(cx, cz, chunkGroup, mapKey, seedBase) {
        const originX = cx * CHUNK_SIZE;
        const originZ = cz * CHUNK_SIZE;
        let seed = seedBase + 555;

        for (let i = 0; i < 3; i++) {
            seed++;
            const ax = originX + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 16);
            seed++;
            const az = originZ + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 16);

            let animalGroup = new THREE.Group();
            const matR = new THREE.MeshStandardMaterial({ color: 0xf8fafc, roughness: 0.6 });
            const body = new THREE.Mesh(new THREE.SphereGeometry(0.38, 10, 10), matR);
            body.position.y = 0.38; body.castShadow = true;
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
            const appleGeo = new THREE.SphereGeometry(0.6, 16, 16);
            const appleMat = new THREE.MeshStandardMaterial({ color: 0xdc2626, roughness: 0.2 });
            const apple = new THREE.Mesh(appleGeo, appleMat); apple.position.y = 0.6; apple.castShadow = true;
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
                const speed = 0.34;
                
                if (isFirstPerson) {
                    // WASD Movement relative to First Person Yaw Angle
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
                // Position Camera inside Duck's Head / Eyes
                const headPosY = duckGroup.position.y + 2.3;
                camera.position.set(duckGroup.position.x, headPosY, duckGroup.position.z + 0.3);

                // Calculate Look At Direction vector from fpYaw & fpPitch
                const dirX = Math.sin(fpYaw) * Math.cos(fpPitch);
                const dirY = Math.sin(fpPitch);
                const dirZ = Math.cos(fpYaw) * Math.cos(fpPitch);

                camera.lookAt(
                    camera.position.x - dirX * 10,
                    camera.position.y + dirY * 10,
                    camera.position.z - dirZ * 10
                );
            } else {
                // Smooth 3D Orbit Camera Follow
                camera.position.x = THREE.MathUtils.lerp(camera.position.x, duckGroup.position.x, 0.08);
                camera.position.z = THREE.MathUtils.lerp(camera.position.z, duckGroup.position.z + 28, 0.08);
                camera.position.y = THREE.MathUtils.lerp(camera.position.y, duckGroup.position.y + 22, 0.08);
                camera.lookAt(duckGroup.position.x, 1, duckGroup.position.z);
            }

            // Sun light follows player position for continuous dynamic shadows
            if (dirLight) {
                dirLight.position.set(duckGroup.position.x + 40, 60, duckGroup.position.z + 40);
            }

            // Update Chunks & Foods dynamically
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

        // Rotate Weather Particles
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
