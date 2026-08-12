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
    <title>Vịt Con 3D Háu Ăn - Thế Giới Đa Bản Đồ Vô Tận (Infinite Dynamic Map)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <!-- Three.js 3D Engine -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; user-select: none; }
        body { background: #0b1329; color: white; overflow: hidden; height: 100vh; width: 100vw; }
        
        #game-canvas { width: 100vw; height: 100vh; display: block; }
        
        /* UI Overlay */
        .hud-layer {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 20px 25px;
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
            background: rgba(15, 23, 42, 0.85);
            padding: 10px 20px;
            border-radius: 30px;
            backdrop-filter: blur(12px);
            border: 2px solid rgba(245, 166, 35, 0.4);
            pointer-events: auto;
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        }

        .brand-logo img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid #f5a623; }
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
            gap: 12px;
        }

        .hud-card {
            background: rgba(15, 23, 42, 0.85);
            border: 2px solid #f5a623;
            padding: 8px 18px;
            border-radius: 20px;
            backdrop-filter: blur(12px);
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        }

        .hud-card label { font-size: 0.72rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; display: block; }
        .hud-card span { font-size: 1.4rem; font-weight: 800; color: #fbbf24; }

        /* Leaderboard Button */
        .btn-leaderboard-toggle {
            pointer-events: auto;
            background: linear-gradient(135deg, #0284c7, #0369a1);
            color: white;
            border: 2px solid #38bdf8;
            padding: 8px 18px;
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
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(255,255,255,0.15);
            padding: 12px 18px;
            border-radius: 18px;
            backdrop-filter: blur(12px);
            pointer-events: auto;
        }

        .controls-hint h4 { font-size: 0.82rem; color: #f5a623; margin-bottom: 6px; }
        .key-row { display: flex; gap: 6px; align-items: center; font-size: 0.8rem; color: #cbd5e1; }
        .key { background: #334155; padding: 4px 9px; border-radius: 6px; font-weight: 800; color: white; border-bottom: 2px solid #1e293b; }

        /* Modals & Selection Drawer */
        .game-modal {
            position: absolute;
            inset: 0;
            background: rgba(11, 19, 41, 0.9);
            backdrop-filter: blur(14px);
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
            width: 720px;
            max-width: 95%;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0,0,0,0.7);
            max-height: 90vh;
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
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
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
            box-shadow: 0 0 20px rgba(245, 166, 35, 0.4);
        }

        .select-card-icon { font-size: 2.2rem; display: block; margin-bottom: 6px; }
        .select-card-name { font-size: 0.85rem; font-weight: 800; color: white; display: block; }
        .select-card-desc { font-size: 0.72rem; color: #cbd5e1; margin-top: 3px; display: block; }

        .btn-play {
            background: linear-gradient(135deg, #f5a623, #d97706);
            color: white;
            border: none;
            padding: 14px 40px;
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

    <!-- HUD Layer -->
    <div class="hud-layer">
        <div class="hud-top">
            <div class="brand-logo">
                <img src="/anh/duck_3d.png" alt="Duck 3D" onerror="this.src='https://ui-avatars.com/api/?name=Duck'">
                <div>
                    <h1>Vịt Con 3D Háu Ăn 🐥</h1>
                    <div class="user-tag">
                        <?php if ($logged_in_user): ?>
                            🟢 Người chơi: <strong><?php echo htmlspecialchars($logged_in_user['fullname']); ?></strong>
                        <?php else: ?>
                            👤 Khách (<a href="/login/login_demo.php" style="color:#f5a623;" target="_blank">Đăng nhập</a> để lưu tên)
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="score-board">
                <div class="hud-card">
                    <label>Điểm Số</label>
                    <span id="score-val">0</span>
                </div>
                <div class="hud-card">
                    <label>Thời Gian</label>
                    <span id="time-val">60s</span>
                </div>
                <div class="hud-card" style="border-color:#38bdf8;">
                    <label>Khám Phá</label>
                    <span id="dist-val" style="color:#38bdf8;">0m</span>
                </div>
                <div class="hud-card" style="border-color:#a855f7;">
                    <label>Tọa Độ Chunk</label>
                    <span id="chunk-val" style="color:#c084fc;font-size:1.1rem;">[0, 0]</span>
                </div>
                <button class="btn-leaderboard-toggle" onclick="toggleLeaderboardModal()">
                    🏆 BẢNG XẾP HẠNG
                </button>
            </div>
        </div>

        <div class="controls-hint">
            <h4>🗺️ Bản Đồ Vô Tận & Dynamic Loading:</h4>
            <div class="key-row">
                Dùng <span class="key">W</span><span class="key">A</span><span class="key">S</span><span class="key">D</span> hoặc 
                <span class="key">↑</span><span class="key">←</span><span class="key">↓</span><span class="key">→</span> điều khiển Vịt tự do di chuyển khám phá thế giới 🌐
            </div>
        </div>

        <div class="joystick-container" id="joystick">
            <div class="joystick-stick" id="stick"></div>
        </div>
    </div>

    <!-- Start / Character & Map Selection Modal -->
    <div class="game-modal" id="start-modal">
        <div class="modal-card">
            <h2>🐥 CHỌN NHÂN VẬT VỊT & BẢN ĐỒ VÔ TẬN 🗺️</h2>
            <p>Khám phá thế giới 3D tự động sinh theo bước chân di chuyển của bạn ("Đi tới đâu load tới đó")!</p>

            <!-- Duck Skins Selection -->
            <div class="section-label">🐥 1. CHỌN CHÚ VỊT 3D CỦA BẠN:</div>
            <div class="select-grid" id="duck-skin-grid">
                <div class="select-card active" onclick="selectDuckSkin('golden', this)">
                    <span class="select-card-icon">🐥</span>
                    <span class="select-card-name">Vịt Vàng Vui Vẻ</span>
                    <span class="select-card-desc">Vịt truyền thống rực rỡ</span>
                </div>
                <div class="select-card" onclick="selectDuckSkin('cool', this)">
                    <span class="select-card-icon">🕶️</span>
                    <span class="select-card-name">Vịt Cool Boy</span>
                    <span class="select-card-desc">Kính râm ngầu mạ vàng</span>
                </div>
                <div class="select-card" onclick="selectDuckSkin('king', this)">
                    <span class="select-card-icon">👑</span>
                    <span class="select-card-name">Vịt Hoàng Gia</span>
                    <span class="select-card-desc">Vương miện quyền lực</span>
                </div>
                <div class="select-card" onclick="selectDuckSkin('fairy', this)">
                    <span class="select-card-icon">🌸</span>
                    <span class="select-card-name">Vịt Hồng Kẹo Ngọt</span>
                    <span class="select-card-desc">Sắc hồng ngọt ngào</span>
                </div>
                <div class="select-card" onclick="selectDuckSkin('ninja', this)">
                    <span class="select-card-icon">🥷</span>
                    <span class="select-card-name">Vịt Ninja Đen</span>
                    <span class="select-card-desc">Băng trán & thân huyền bí</span>
                </div>
            </div>

            <!-- Map Worlds Selection -->
            <div class="section-label">🗺️ 2. CHỌN THẾ GIỚI BẢN ĐỒ 3D (DYNAMIC CHUNK LOADING):</div>
            <div class="select-grid" id="map-world-grid">
                <div class="select-card active" onclick="selectMapWorld('park', this)">
                    <span class="select-card-icon">🏞️</span>
                    <span class="select-card-name">Công Viên Xanh</span>
                    <span class="select-card-desc">Sông thơ mộng & cây xanh</span>
                </div>
                <div class="select-card" onclick="selectMapWorld('volcano', this)">
                    <span class="select-card-icon">🌋</span>
                    <span class="select-card-name">Đảo Núi Lửa</span>
                    <span class="select-card-desc">Dòng Dung Nham đỏ rực</span>
                </div>
                <div class="select-card" onclick="selectMapWorld('snow', this)">
                    <span class="select-card-icon">❄️</span>
                    <span class="select-card-name">Vương Quốc Băng</span>
                    <span class="select-card-desc">Tuyết trắng & sông băng</span>
                </div>
                <div class="select-card" onclick="selectMapWorld('galaxy', this)">
                    <span class="select-card-icon">🌌</span>
                    <span class="select-card-name">Đêm Ngân Hà</span>
                    <span class="select-card-desc">Cyberpunk & cây pha lê</span>
                </div>
            </div>

            <!-- Time Selection -->
            <div class="section-label">⏱️ 3. CHỌN THỜI GIAN CHƠI:</div>
            <div class="select-grid" id="time-select-grid" style="grid-template-columns:repeat(4,1fr);">
                <div class="select-card" onclick="selectTime(60, this)">
                    <span class="select-card-icon">⚡</span>
                    <span class="select-card-name">60 Giây</span>
                    <span class="select-card-desc">Chớp nhoáng nhanh</span>
                </div>
                <div class="select-card active" onclick="selectTime(180, this)">
                    <span class="select-card-icon">🕐</span>
                    <span class="select-card-name">3 Phút</span>
                    <span class="select-card-desc">Vừa đủ khám phá</span>
                </div>
                <div class="select-card" onclick="selectTime(300, this)">
                    <span class="select-card-icon">🕔</span>
                    <span class="select-card-name">5 Phút</span>
                    <span class="select-card-desc">Phiêu lưu cơ bản</span>
                </div>
                <div class="select-card" onclick="selectTime(5400, this)">
                    <span class="select-card-icon">🌍</span>
                    <span class="select-card-name">90 Phút</span>
                    <span class="select-card-desc">Khám phá vô tận!</span>
                </div>
            </div>

            <button class="btn-play" onclick="startGame()">🚀 BẮT ĐẦU VÀO GAME NGAY</button>
        </div>
    </div>

    <!-- Leaderboard Modal -->
    <div class="game-modal" id="lb-modal" style="display:none;">
        <div class="modal-card" style="width:550px;">
            <h2 style="color:#38bdf8;">🏆 BẢNG XẾP HẠNG TOP CAO THỦ</h2>
            <p>Danh sách 10 người chơi ghi điểm cao nhất trong Game Vịt 3D:</p>
            
            <table class="lb-table">
                <thead>
                    <tr>
                        <th style="width:50px;">Hạng</th>
                        <th>Tên Người Chơi</th>
                        <th style="text-align:right;">Điểm Số</th>
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
                        <tr><td colspan="3" style="text-align:center;">Chưa có dữ liệu xếp hạng</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top:20px;">
                <button class="btn-play" onclick="toggleLeaderboardModal()" style="background:#475569;">ĐÓNG BẢNG</button>
            </div>
        </div>
    </div>

    <!-- Game Over Modal -->
    <div class="game-modal" id="end-modal" style="display:none;">
        <div class="modal-card" style="width:500px;">
            <img src="/anh/duck_3d.png" alt="Duck 3D" style="width:85px;height:85px;border-radius:50%;border:3px solid #f5a623;">
            <h2 style="color:#22c55e;">🎉 HOÀN THÀNH CHUYẾN ĐI!</h2>
            <p>Tên hiển thị: <strong><?php echo $logged_in_user ? htmlspecialchars($logged_in_user['fullname']) : 'Khách Vô Danh'; ?></strong></p>
            <div style="font-size:2.5rem;font-weight:800;color:#fbbf24;margin-bottom:10px;" id="final-score">0 ĐIỂM</div>
            
            <div id="voucher-result" class="voucher-box" style="display:none;">
                🎟️ Mã Giảm Giá 20%: <strong>GLOWDUCK20</strong>
            </div>

            <div style="display:flex;gap:12px;justify-content:center;margin-top:20px;">
                <button class="btn-play" onclick="showStartModal()">🔄 ĐỔI BẢN ĐỒ / VỊT KÍCH THÍCH</button>
                <button class="btn-play" onclick="toggleLeaderboardModal()" style="background:#0284c7;">🏆 XEM BẢNG HẠNG</button>
            </div>
        </div>
    </div>

    <!-- 3D MULTI-MAP & DYNAMIC DYNAMIC CHUNK SYSTEM SCRIPT -->
    <script>
    const loggedInPlayerName = "<?php echo $logged_in_user ? addslashes($logged_in_user['fullname']) : 'Khách Vô Danh 🐥'; ?>";

    // Selected Options
    let currentDuckSkin = 'golden';
    let currentMapWorld = 'park';
    let selectedTime = 180; // default 3 phút

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

    // Audio FX Generator
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

    // 3D Engine Objects & Dynamic Infinite Chunk System
    let scene, camera, renderer, dirLight;
    let duckGroup, duckBody, duckHead, duckBeak, duckWingL, duckWingR;
    let foods = [];
    let activeAnimals = []; // {mesh, chunkKey, type, wanderAngle, wanderTimer, speed, bobOffset}
    let score = 0, timeLeft = 60, gameActive = false, timerInterval;

    // INFINITE DYNAMIC CHUNK MAP CONFIGURATION ("Đi tới đâu load tới đó")
    const CHUNK_SIZE = 90; // Each chunk tile is 90x90 units
    const LOAD_RADIUS = 2; // Loads 5x5 grid of chunks (450m x 450m active rendering)
    const activeChunks = new Map(); // key: "cx,cz" => THREE.Group

    const MAP_THEMES = {
        park: {
            bg: 0x0b1329, ground: 0x16a34a, water: 0x0284c7, treeLeaf: 0x15803d, treeTrunk: 0x78350f, rock: 0x64748b, bridge: 0x854d0e,
            emissiveWater: 0x000000, emissiveIntensity: 0
        },
        volcano: {
            bg: 0x1a0505, ground: 0x262626, water: 0xd97706, treeLeaf: 0xb91c1c, treeTrunk: 0x451a03, rock: 0x44403c, bridge: 0x44403c,
            emissiveWater: 0xd97706, emissiveIntensity: 0.6
        },
        snow: {
            bg: 0x0f172a, ground: 0xe2e8f0, water: 0x38bdf8, treeLeaf: 0x0284c7, treeTrunk: 0x334155, rock: 0x94a3b8, bridge: 0x475569,
            emissiveWater: 0x000000, emissiveIntensity: 0
        },
        galaxy: {
            bg: 0x090514, ground: 0x4c1d95, water: 0xc084fc, treeLeaf: 0xf43f5e, treeTrunk: 0x7e22ce, rock: 0xa855f7, bridge: 0x581c87,
            emissiveWater: 0xc084fc, emissiveIntensity: 0.6
        }
    };

    // Movement Controls
    const keys = { w: false, a: false, s: false, d: false, ArrowUp: false, ArrowLeft: false, ArrowDown: false, ArrowRight: false };
    let moveVector = { x: 0, z: 0 };

    window.addEventListener('keydown', e => { if (keys.hasOwnProperty(e.key) || keys.hasOwnProperty(e.code)) keys[e.key] = true; });
    window.addEventListener('keyup', e => { if (keys.hasOwnProperty(e.key) || keys.hasOwnProperty(e.code)) keys[e.key] = false; });

    function init3D() {
        const canvas = document.getElementById('game-canvas');
        scene = new THREE.Scene();

        camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);
        camera.position.set(0, 22, 28);
        camera.lookAt(0, 0, 0);

        renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.shadowMap.enabled = true;

        // Create Duck & Dynamic Map
        create3DDuck(currentDuckSkin);
        rebuildMapEnvironment();

        // Spawn Initial Fruits Around Duck
        maintainFruitsAroundPlayer(0, 0);

        // Window resize
        window.addEventListener('resize', onWindowResize);

        // Start render loop
        animate();
    }

    function rebuildMapEnvironment() {
        const theme = MAP_THEMES[currentMapWorld] || MAP_THEMES.park;
        scene.background = new THREE.Color(theme.bg);
        scene.fog = new THREE.FogExp2(theme.bg, 0.008);

        // Remove old lights
        scene.children.filter(c => c.isLight).forEach(l => scene.remove(l));

        const ambientLight = new THREE.AmbientLight(0xffffff, (currentMapWorld === 'galaxy' || currentMapWorld === 'volcano') ? 0.95 : 0.8);
        scene.add(ambientLight);

        dirLight = new THREE.DirectionalLight((currentMapWorld === 'volcano') ? 0xff7733 : 0xfff5cc, 1.3);
        dirLight.position.set(30, 45, 30);
        dirLight.castShadow = true;
        dirLight.shadow.mapSize.width = 2048;
        dirLight.shadow.mapSize.height = 2048;
        dirLight.shadow.camera.near = 0.5;
        dirLight.shadow.camera.far = 200;
        dirLight.shadow.camera.left = -60;
        dirLight.shadow.camera.right = 60;
        dirLight.shadow.camera.top = 60;
        dirLight.shadow.camera.bottom = -60;
        scene.add(dirLight);

        // Clear active chunks
        for (const [key, group] of activeChunks.entries()) {
            scene.remove(group);
        }
        activeChunks.clear();

        // Load initial chunks around origin (0, 0)
        updateWorldChunksAroundPlayer(0, 0);
    }

    // Pseudo-random generator for deterministic chunk generation
    function seededRandom(seed) {
        let x = Math.sin(seed) * 10000;
        return x - Math.floor(x);
    }

    // CREATE PROCEDURAL CHUNK AT (cx, cz)
    function createChunkGroup(cx, cz, mapKey) {
        const theme = MAP_THEMES[mapKey] || MAP_THEMES.park;
        const chunkGroup = new THREE.Group();
        const originX = cx * CHUNK_SIZE;
        const originZ = cz * CHUNK_SIZE;

        // Ground Platform
        const groundGeo = new THREE.BoxGeometry(CHUNK_SIZE, 2, CHUNK_SIZE);
        const groundMat = new THREE.MeshStandardMaterial({ color: theme.ground, roughness: 0.7 });
        const ground = new THREE.Mesh(groundGeo, groundMat);
        ground.position.set(originX, -1, originZ);
        ground.receiveShadow = true;
        chunkGroup.add(ground);

        // River Channel (Every 2nd horizontal or vertical chunk line)
        const hasRiver = (Math.abs(cz) % 3 === 0);
        if (hasRiver) {
            const waterGeo = new THREE.PlaneGeometry(CHUNK_SIZE, 16);
            const waterMat = new THREE.MeshStandardMaterial({
                color: theme.water,
                roughness: 0.1,
                metalness: 0.6,
                transparent: true,
                opacity: 0.9,
                emissive: theme.emissiveWater,
                emissiveIntensity: theme.emissiveIntensity
            });
            const waterMesh = new THREE.Mesh(waterGeo, waterMat);
            waterMesh.rotation.x = -Math.PI / 2;
            waterMesh.position.set(originX, 0.08, originZ);
            chunkGroup.add(waterMesh);

            // Wooden/Stone Bridge across river
            const bridgeGroup = new THREE.Group();
            const plankMat = new THREE.MeshStandardMaterial({ color: theme.bridge, roughness: 0.8 });
            for (let p = -8; p <= 8; p += 1.2) {
                const plankGeo = new THREE.BoxGeometry(7, 0.25, 1);
                const plank = new THREE.Mesh(plankGeo, plankMat);
                plank.position.set(0, 0.2, p);
                plank.castShadow = true;
                plank.receiveShadow = true;
                bridgeGroup.add(plank);
            }
            bridgeGroup.position.set(originX, 0, originZ);
            chunkGroup.add(bridgeGroup);
        }

        // Trees Generation (12-16 per chunk)
        let seed = (cx * 73856093) ^ (cz * 19349663);
        for (let i = 0; i < 14; i++) {
            seed++;
            const rx = (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 12);
            seed++;
            let rz = (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 12);
            if (hasRiver && Math.abs(rz) < 10) rz += (rz > 0 ? 12 : -12);

            const tx = originX + rx;
            const tz = originZ + rz;

            const treeGroup = new THREE.Group();
            const trunkGeo = new THREE.CylinderGeometry(0.4, 0.6, 3, 8);
            const trunkMat = new THREE.MeshStandardMaterial({ color: theme.treeTrunk, roughness: 0.9 });
            const trunk = new THREE.Mesh(trunkGeo, trunkMat);
            trunk.position.y = 1.5;
            trunk.castShadow = true;
            treeGroup.add(trunk);

            const foliageMat = new THREE.MeshStandardMaterial({ color: theme.treeLeaf, roughness: 0.5 });
            const f1Geo = new THREE.ConeGeometry(2.2, 3.2, 8);
            const f1 = new THREE.Mesh(f1Geo, foliageMat);
            f1.position.y = 3.5;
            f1.castShadow = true;
            const f2 = new THREE.Mesh(f1Geo, foliageMat);
            f2.position.y = 4.8;
            f2.scale.set(0.78, 0.78, 0.78);
            f2.castShadow = true;
            treeGroup.add(f1); treeGroup.add(f2);
            treeGroup.position.set(tx, 0, tz);
            chunkGroup.add(treeGroup);
        }

        // Rocks / Crystals Generation (8-12 per chunk)
        for (let i = 0; i < 10; i++) {
            seed++;
            const rx = (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 10);
            seed++;
            const rz = (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 10);

            const rockGeo = new THREE.DodecahedronGeometry(0.8 + seededRandom(seed)*0.6, 1);
            const rockMat = new THREE.MeshStandardMaterial({ color: theme.rock, roughness: 0.8 });
            const rock = new THREE.Mesh(rockGeo, rockMat);
            rock.rotation.set(seededRandom(seed*2), seededRandom(seed*3), seededRandom(seed*4));
            rock.position.set(originX + rx, 0.4, originZ + rz);
            rock.castShadow = true;
            rock.receiveShadow = true;
            chunkGroup.add(rock);
        }

        // Spawn Wildlife Animals
        spawnAnimalsInChunk(cx, cz, chunkGroup, mapKey, seed);

        return chunkGroup;
    }

    // SPAWN WILDLIFE ANIMALS PER MAP THEME
    function spawnAnimalsInChunk(cx, cz, chunkGroup, mapKey, seedBase) {
        const originX = cx * CHUNK_SIZE;
        const originZ = cz * CHUNK_SIZE;
        const chunkKey = `${cx},${cz}`;
        let seed = seedBase + 9999;
        const count = 4;

        for (let i = 0; i < count; i++) {
            seed++;
            const ax = originX + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 16);
            seed++;
            const az = originZ + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 16);
            seed++;
            const animalType = Math.floor(seededRandom(seed) * 3);

            let animalGroup = new THREE.Group();
            let animalSpeed = 0.018 + seededRandom(seed + 1) * 0.022;
            let bobOffset = seededRandom(seed + 2) * Math.PI * 2;

            if (mapKey === 'park') {
                if (animalType === 0) { // 🐇 Rabbit
                    const matW = new THREE.MeshStandardMaterial({ color: 0xf0f0f0, roughness: 0.6 });
                    const body = new THREE.Mesh(new THREE.SphereGeometry(0.38, 10, 10), matW);
                    body.position.y = 0.38; body.castShadow = true;
                    const head = new THREE.Mesh(new THREE.SphereGeometry(0.24, 10, 10), matW);
                    head.position.set(0, 0.72, 0.2); head.castShadow = true;
                    const earGeo = new THREE.CylinderGeometry(0.06, 0.04, 0.42, 6);
                    const earL = new THREE.Mesh(earGeo, new THREE.MeshStandardMaterial({ color: 0xf9c0c0 }));
                    earL.position.set(-0.1, 1.05, 0.18); earL.rotation.z = 0.12;
                    const earR = new THREE.Mesh(earGeo, new THREE.MeshStandardMaterial({ color: 0xf9c0c0 }));
                    earR.position.set(0.1, 1.05, 0.18); earR.rotation.z = -0.12;
                    const eyeM = new THREE.MeshBasicMaterial({ color: 0x220000 });
                    const eyeL2 = new THREE.Mesh(new THREE.SphereGeometry(0.05, 6, 6), eyeM);
                    eyeL2.position.set(-0.12, 0.76, 0.4);
                    const eyeR2 = new THREE.Mesh(new THREE.SphereGeometry(0.05, 6, 6), eyeM);
                    eyeR2.position.set(0.12, 0.76, 0.4);
                    animalGroup.add(body, head, earL, earR, eyeL2, eyeR2);
                    animalGroup.userData.animalName = 'rabbit';
                } else if (animalType === 1) { // 🐸 Frog
                    const matG = new THREE.MeshStandardMaterial({ color: 0x22c55e, roughness: 0.5 });
                    const body = new THREE.Mesh(new THREE.SphereGeometry(0.42, 12, 8), matG);
                    body.position.y = 0.28; body.scale.y = 0.7;
                    const head = new THREE.Mesh(new THREE.SphereGeometry(0.32, 10, 8), matG);
                    head.position.set(0, 0.62, 0.22);
                    const eyeGeo = new THREE.SphereGeometry(0.12, 8, 8);
                    const eyeM = new THREE.MeshStandardMaterial({ color: 0xfbbf24 });
                    const eyeL = new THREE.Mesh(eyeGeo, eyeM); eyeL.position.set(-0.2, 0.88, 0.28);
                    const eyeR = new THREE.Mesh(eyeGeo, eyeM); eyeR.position.set(0.2, 0.88, 0.28);
                    const pupilM = new THREE.MeshBasicMaterial({ color: 0x000000 });
                    const pupL = new THREE.Mesh(new THREE.SphereGeometry(0.06, 6, 6), pupilM); pupL.position.set(-0.2, 0.88, 0.38);
                    const pupR = new THREE.Mesh(new THREE.SphereGeometry(0.06, 6, 6), pupilM); pupR.position.set(0.2, 0.88, 0.38);
                    animalGroup.add(body, head, eyeL, eyeR, pupL, pupR);
                    animalGroup.userData.animalName = 'frog';
                    animalSpeed *= 0.6;
                } else { // 🦋 Butterfly
                    const wingM = new THREE.MeshStandardMaterial({ color: 0xf97316, side: THREE.DoubleSide, transparent: true, opacity: 0.85 });
                    const wingGeo = new THREE.SphereGeometry(0.38, 8, 6);
                    const wL = new THREE.Mesh(wingGeo, wingM); wL.scale.set(1, 0.08, 1.4); wL.position.set(-0.35, 0, 0);
                    const wR = new THREE.Mesh(wingGeo, wingM); wR.scale.set(1, 0.08, 1.4); wR.position.set(0.35, 0, 0);
                    const body2 = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, 0.5, 6), new THREE.MeshStandardMaterial({ color: 0x1e293b }));
                    animalGroup.add(wL, wR, body2);
                    animalGroup.userData.animalName = 'butterfly';
                    animalGroup.userData.flying = true;
                    animalSpeed *= 1.5;
                }

            } else if (mapKey === 'volcano') {
                if (animalType === 0) { // 🦎 Fire Lizard
                    const matR = new THREE.MeshStandardMaterial({ color: 0xdc2626, roughness: 0.4 });
                    const body = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.28, 0.9, 8), matR); body.rotation.z = Math.PI / 2; body.position.y = 0.22;
                    const head = new THREE.Mesh(new THREE.BoxGeometry(0.28, 0.22, 0.36), matR); head.position.set(0.58, 0.22, 0);
                    const tail = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.02, 0.7, 6), matR); tail.rotation.z = Math.PI / 2; tail.position.set(-0.75, 0.22, 0);
                    const eyeM = new THREE.MeshBasicMaterial({ color: 0xfbbf24 });
                    const eyeL = new THREE.Mesh(new THREE.SphereGeometry(0.06, 6, 6), eyeM); eyeL.position.set(0.62, 0.36, 0.14);
                    const eyeR = new THREE.Mesh(new THREE.SphereGeometry(0.06, 6, 6), eyeM); eyeR.position.set(0.62, 0.36, -0.14);
                    animalGroup.add(body, head, tail, eyeL, eyeR);
                    animalGroup.userData.animalName = 'lizard';
                } else if (animalType === 1) { // 🦀 Lava Crab
                    const matC = new THREE.MeshStandardMaterial({ color: 0x991b1b, roughness: 0.6 });
                    const shell = new THREE.Mesh(new THREE.SphereGeometry(0.38, 10, 8), matC); shell.scale.y = 0.55; shell.position.y = 0.22;
                    const eyeM = new THREE.MeshBasicMaterial({ color: 0xfbbf24 });
                    for (let li = -1; li <= 1; li += 2) {
                        const leg1 = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.04, 0.5, 5), matC);
                        leg1.rotation.z = Math.PI / 2 + 0.4 * li; leg1.position.set(0.5 * li, 0.18, 0.12);
                        const leg2 = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.04, 0.5, 5), matC);
                        leg2.rotation.z = Math.PI / 2 + 0.4 * li; leg2.position.set(0.5 * li, 0.18, -0.12);
                        animalGroup.add(leg1, leg2);
                    }
                    const eyeL = new THREE.Mesh(new THREE.SphereGeometry(0.07, 6, 6), eyeM); eyeL.position.set(-0.18, 0.44, 0.3);
                    const eyeR = new THREE.Mesh(new THREE.SphereGeometry(0.07, 6, 6), eyeM); eyeR.position.set(0.18, 0.44, 0.3);
                    animalGroup.add(shell, eyeL, eyeR);
                    animalGroup.userData.animalName = 'crab';
                    animalSpeed *= 0.7;
                } else { // 🦇 Bat
                    const matD = new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.7 });
                    const body = new THREE.Mesh(new THREE.SphereGeometry(0.22, 8, 8), matD); body.position.y = 0;
                    const wingL = new THREE.Mesh(new THREE.SphereGeometry(0.35, 6, 5), new THREE.MeshStandardMaterial({ color: 0x374151, transparent: true, opacity: 0.8, side: THREE.DoubleSide }));
                    wingL.scale.set(1, 0.06, 1.2); wingL.position.set(-0.42, 0, 0);
                    const wingR = wingL.clone(); wingR.position.set(0.42, 0, 0);
                    const earL = new THREE.Mesh(new THREE.ConeGeometry(0.08, 0.18, 5), matD); earL.position.set(-0.12, 0.28, 0);
                    const earR = new THREE.Mesh(new THREE.ConeGeometry(0.08, 0.18, 5), matD); earR.position.set(0.12, 0.28, 0);
                    animalGroup.add(body, wingL, wingR, earL, earR);
                    animalGroup.userData.animalName = 'bat';
                    animalGroup.userData.flying = true;
                    animalSpeed *= 2.2;
                }

            } else if (mapKey === 'snow') {
                if (animalType === 0) { // 🐧 Penguin
                    const matB = new THREE.MeshStandardMaterial({ color: 0x0f172a, roughness: 0.5 });
                    const matW = new THREE.MeshStandardMaterial({ color: 0xf8fafc, roughness: 0.5 });
                    const body = new THREE.Mesh(new THREE.SphereGeometry(0.42, 10, 10), matB); body.scale.y = 1.3; body.position.y = 0.5;
                    const belly = new THREE.Mesh(new THREE.SphereGeometry(0.28, 8, 8), matW); belly.scale.y = 1.2; belly.position.set(0, 0.5, 0.3);
                    const head = new THREE.Mesh(new THREE.SphereGeometry(0.28, 10, 10), matB); head.position.set(0, 1.08, 0);
                    const beak = new THREE.Mesh(new THREE.ConeGeometry(0.06, 0.18, 5), new THREE.MeshStandardMaterial({ color: 0xf97316 })); beak.rotation.x = Math.PI / 2; beak.position.set(0, 1.06, 0.3);
                    const eyeM = new THREE.MeshBasicMaterial({ color: 0xffffff });
                    const eyeL = new THREE.Mesh(new THREE.SphereGeometry(0.07, 6, 6), eyeM); eyeL.position.set(-0.14, 1.14, 0.24);
                    const eyeR = new THREE.Mesh(new THREE.SphereGeometry(0.07, 6, 6), eyeM); eyeR.position.set(0.14, 1.14, 0.24);
                    animalGroup.add(body, belly, head, beak, eyeL, eyeR);
                    animalGroup.userData.animalName = 'penguin';
                    animalSpeed *= 0.7;
                } else if (animalType === 1) { // 🐻‍❄️ Polar Bear
                    const matW = new THREE.MeshStandardMaterial({ color: 0xe2e8f0, roughness: 0.8 });
                    const body = new THREE.Mesh(new THREE.SphereGeometry(0.65, 10, 10), matW); body.position.y = 0.62;
                    const head = new THREE.Mesh(new THREE.SphereGeometry(0.4, 10, 10), matW); head.position.set(0, 1.3, 0.45);
                    const earL = new THREE.Mesh(new THREE.SphereGeometry(0.12, 8, 8), matW); earL.position.set(-0.28, 1.68, 0.42);
                    const earR = new THREE.Mesh(new THREE.SphereGeometry(0.12, 8, 8), matW); earR.position.set(0.28, 1.68, 0.42);
                    const nose = new THREE.Mesh(new THREE.SphereGeometry(0.1, 6, 6), new THREE.MeshStandardMaterial({ color: 0x1e293b })); nose.position.set(0, 1.22, 0.83);
                    animalGroup.add(body, head, earL, earR, nose);
                    animalGroup.userData.animalName = 'polarbear';
                    animalSpeed *= 0.5;
                } else { // 🦉 Snow Owl
                    const matW = new THREE.MeshStandardMaterial({ color: 0xf1f5f9, roughness: 0.6 });
                    const body = new THREE.Mesh(new THREE.SphereGeometry(0.38, 8, 10), matW); body.scale.y = 1.3; body.position.y = 0.48;
                    const head = new THREE.Mesh(new THREE.SphereGeometry(0.3, 8, 8), matW); head.position.set(0, 1.02, 0);
                    const beak = new THREE.Mesh(new THREE.ConeGeometry(0.06, 0.14, 4), new THREE.MeshStandardMaterial({ color: 0xf97316 })); beak.rotation.x = Math.PI / 2; beak.position.set(0, 0.96, 0.3);
                    const eyeM = new THREE.MeshBasicMaterial({ color: 0xfbbf24 });
                    const eyeL = new THREE.Mesh(new THREE.SphereGeometry(0.1, 6, 6), eyeM); eyeL.position.set(-0.15, 1.08, 0.24);
                    const eyeR = new THREE.Mesh(new THREE.SphereGeometry(0.1, 6, 6), eyeM); eyeR.position.set(0.15, 1.08, 0.24);
                    animalGroup.add(body, head, beak, eyeL, eyeR);
                    animalGroup.userData.animalName = 'owl';
                    animalGroup.userData.flying = true;
                    animalSpeed *= 1.2;
                }

            } else if (mapKey === 'galaxy') {
                if (animalType === 0) { // 👾 Alien Jellyfish
                    const matJ = new THREE.MeshStandardMaterial({ color: 0xc084fc, roughness: 0.1, metalness: 0.4, transparent: true, opacity: 0.8, emissive: 0xc084fc, emissiveIntensity: 0.5 });
                    const dome = new THREE.Mesh(new THREE.SphereGeometry(0.44, 12, 8, 0, Math.PI * 2, 0, Math.PI / 2), matJ); dome.position.y = 0.22;
                    for (let ti = 0; ti < 6; ti++) {
                        const tentGeo = new THREE.CylinderGeometry(0.03, 0.01, 0.55 + Math.random() * 0.3, 5);
                        const tent = new THREE.Mesh(tentGeo, matJ);
                        const ang = (ti / 6) * Math.PI * 2;
                        tent.position.set(Math.cos(ang) * 0.28, -0.12, Math.sin(ang) * 0.28);
                        animalGroup.add(tent);
                    }
                    animalGroup.add(dome);
                    animalGroup.userData.animalName = 'jellyfish';
                    animalGroup.userData.flying = true;
                    animalSpeed *= 0.8;
                } else if (animalType === 1) { // 🦌 Crystal Deer
                    const matC = new THREE.MeshStandardMaterial({ color: 0x818cf8, roughness: 0.1, metalness: 0.8, emissive: 0x818cf8, emissiveIntensity: 0.3 });
                    const body = new THREE.Mesh(new THREE.SphereGeometry(0.4, 8, 8), matC); body.scale.set(1.4, 0.9, 0.9); body.position.y = 0.65;
                    const neck = new THREE.Mesh(new THREE.CylinderGeometry(0.15, 0.18, 0.4, 6), matC); neck.position.set(0, 1.05, 0.3); neck.rotation.x = -0.4;
                    const head = new THREE.Mesh(new THREE.SphereGeometry(0.22, 8, 8), matC); head.position.set(0, 1.36, 0.52);
                    const antlerGeo = new THREE.CylinderGeometry(0.03, 0.05, 0.38, 4);
                    const antL = new THREE.Mesh(antlerGeo, matC); antL.position.set(-0.14, 1.64, 0.5); antL.rotation.z = 0.3;
                    const antR = new THREE.Mesh(antlerGeo, matC); antR.position.set(0.14, 1.64, 0.5); antR.rotation.z = -0.3;
                    const leg1 = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.05, 0.55, 5), matC); leg1.position.set(-0.22, 0.26, 0.18);
                    const leg2 = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.05, 0.55, 5), matC); leg2.position.set(0.22, 0.26, 0.18);
                    const leg3 = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.05, 0.55, 5), matC); leg3.position.set(-0.22, 0.26, -0.18);
                    const leg4 = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.05, 0.55, 5), matC); leg4.position.set(0.22, 0.26, -0.18);
                    animalGroup.add(body, neck, head, antL, antR, leg1, leg2, leg3, leg4);
                    animalGroup.userData.animalName = 'crystal_deer';
                } else { // ✨ Space Firefly
                    const matF = new THREE.MeshStandardMaterial({ color: 0xf43f5e, emissive: 0xf43f5e, emissiveIntensity: 1.2, roughness: 0.1 });
                    const orb = new THREE.Mesh(new THREE.SphereGeometry(0.14, 8, 8), matF);
                    const trail1 = new THREE.Mesh(new THREE.SphereGeometry(0.08, 6, 6), new THREE.MeshStandardMaterial({ color: 0xf43f5e, emissive: 0xf43f5e, emissiveIntensity: 0.6, transparent: true, opacity: 0.6 })); trail1.position.z = -0.24;
                    const trail2 = new THREE.Mesh(new THREE.SphereGeometry(0.05, 5, 5), new THREE.MeshStandardMaterial({ color: 0xf43f5e, emissive: 0xf43f5e, emissiveIntensity: 0.3, transparent: true, opacity: 0.35 })); trail2.position.z = -0.42;
                    animalGroup.add(orb, trail1, trail2);
                    animalGroup.userData.animalName = 'firefly';
                    animalGroup.userData.flying = true;
                    animalSpeed *= 2.5;
                }
            }

            animalGroup.position.set(ax, animalGroup.userData.flying ? (1.2 + seededRandom(seed) * 2.5) : 0, az);
            animalGroup.userData.chunkKey = chunkKey;
            animalGroup.userData.wanderAngle = seededRandom(seed + 5) * Math.PI * 2;
            animalGroup.userData.wanderTimer = seededRandom(seed + 6) * 120;
            animalGroup.userData.speed = animalSpeed;
            animalGroup.userData.bobOffset = seededRandom(seed + 7) * Math.PI * 2;
            animalGroup.castShadow = true;
            chunkGroup.add(animalGroup);
            activeAnimals.push(animalGroup);
        }
    }

    // DYNAMIC MAP CHUNK UPDATER ("Đi tới đâu load tới đó")
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

        // Remove distant chunks out of render radius + cleanup their animals
        for (const [key, group] of activeChunks.entries()) {
            if (!neededKeys.has(key)) {
                scene.remove(group);
                activeChunks.delete(key);
                // Remove animals belonging to this chunk
                for (let ai = activeAnimals.length - 1; ai >= 0; ai--) {
                    if (activeAnimals[ai].userData.chunkKey === key) {
                        activeAnimals.splice(ai, 1);
                    }
                }
            }
        }

        // Update HUD Chunk Coordinate & Distance
        const distExplored = Math.floor(Math.sqrt(px*px + pz*pz));
        document.getElementById('dist-val').innerText = distExplored + 'm';
        document.getElementById('chunk-val').innerText = `[${currentChunkX}, ${currentChunkZ}]`;
    }

    // DUCK CHARACTER BUILDER (Golden, Cool, King, Fairy, Ninja)
    function create3DDuck(skinKey) {
        if (duckGroup) scene.remove(duckGroup);
        duckGroup = new THREE.Group();

        let bodyColor = 0xffd43b;
        let beakColor = 0xf97316;

        if (skinKey === 'cool') {
            bodyColor = 0xf97316; // Neon Orange Duck
        } else if (skinKey === 'king') {
            bodyColor = 0x0284c7; // Royal Blue Duck
        } else if (skinKey === 'fairy') {
            bodyColor = 0xf472b6; // Sweet Pink Duck
        } else if (skinKey === 'ninja') {
            bodyColor = 0x334155; // Shadow Ninja Duck
            beakColor = 0xe2e8f0;
        }

        // Body
        const bodyGeo = new THREE.SphereGeometry(1.2, 24, 24);
        const duckMat = new THREE.MeshStandardMaterial({ color: bodyColor, roughness: 0.3, metalness: 0.1 });
        duckBody = new THREE.Mesh(bodyGeo, duckMat); duckBody.position.y = 1.2; duckBody.castShadow = true;
        duckGroup.add(duckBody);

        // Head
        const headGeo = new THREE.SphereGeometry(0.85, 20, 20);
        duckHead = new THREE.Mesh(headGeo, duckMat); duckHead.position.set(0, 2.2, 0.3); duckHead.castShadow = true;
        duckGroup.add(duckHead);

        // Beak
        const beakGeo = new THREE.BoxGeometry(0.5, 0.22, 0.5);
        const beakMat = new THREE.MeshStandardMaterial({ color: beakColor, roughness: 0.4 });
        duckBeak = new THREE.Mesh(beakGeo, beakMat); duckBeak.position.set(0, 2.1, 1.1);
        duckGroup.add(duckBeak);

        // Eyes
        const eyeGeo = new THREE.SphereGeometry(0.12, 12, 12);
        const eyeMat = new THREE.MeshBasicMaterial({ color: 0x0f172a });
        const eyeL = new THREE.Mesh(eyeGeo, eyeMat); eyeL.position.set(-0.35, 2.4, 0.95);
        const eyeR = new THREE.Mesh(eyeGeo, eyeMat); eyeR.position.set(0.35, 2.4, 0.95);
        duckGroup.add(eyeL); duckGroup.add(eyeR);

        // Wings
        const wingGeo = new THREE.BoxGeometry(0.2, 0.7, 0.9);
        duckWingL = new THREE.Mesh(wingGeo, duckMat); duckWingL.position.set(-1.2, 1.3, 0);
        duckWingR = new THREE.Mesh(wingGeo, duckMat); duckWingR.position.set(1.2, 1.3, 0);
        duckGroup.add(duckWingL); duckGroup.add(duckWingR);

        // UNIQUE ACCESORIES FOR SKINS
        if (skinKey === 'cool') { // 🕶️ Sunglasses
            const glassGeo = new THREE.BoxGeometry(0.9, 0.25, 0.15);
            const glassMat = new THREE.MeshStandardMaterial({ color: 0x0f172a, roughness: 0.1, metalness: 0.9 });
            const glasses = new THREE.Mesh(glassGeo, glassMat); glasses.position.set(0, 2.4, 1.05);
            duckGroup.add(glasses);
        } else if (skinKey === 'king') { // 👑 Golden Crown
            const crownGeo = new THREE.CylinderGeometry(0.45, 0.35, 0.4, 8);
            const crownMat = new THREE.MeshStandardMaterial({ color: 0xeab308, metalness: 0.8, roughness: 0.2 });
            const crown = new THREE.Mesh(crownGeo, crownMat); crown.position.set(0, 3.15, 0.3);
            duckGroup.add(crown);
        } else if (skinKey === 'fairy') { // 🌸 Flower Crown
            const flowerGeo = new THREE.TorusGeometry(0.5, 0.1, 8, 16);
            const flowerMat = new THREE.MeshStandardMaterial({ color: 0xf43f5e });
            const flower = new THREE.Mesh(flowerGeo, flowerMat); flower.rotation.x = Math.PI/2; flower.position.set(0, 2.9, 0.3);
            duckGroup.add(flower);
        } else if (skinKey === 'ninja') { // 🥷 Red Headband
            const bandGeo = new THREE.CylinderGeometry(0.86, 0.86, 0.2, 16);
            const bandMat = new THREE.MeshStandardMaterial({ color: 0xdc2626 });
            const band = new THREE.Mesh(bandGeo, bandMat); band.position.set(0, 2.45, 0.3);
            duckGroup.add(band);
        } else { // 🐥 Red Bowtie
            const bowGeo = new THREE.ConeGeometry(0.25, 0.4, 4);
            const bowMat = new THREE.MeshStandardMaterial({ color: 0xe11d48 });
            const bowL = new THREE.Mesh(bowGeo, bowMat); bowL.rotation.z = Math.PI / 2; bowL.position.set(-0.25, 1.7, 0.95);
            const bowR = new THREE.Mesh(bowGeo, bowMat); bowR.rotation.z = -Math.PI / 2; bowR.position.set(0.25, 1.7, 0.95);
            duckGroup.add(bowL); duckGroup.add(bowR);
        }

        duckGroup.position.set(0, 0, 0);
        scene.add(duckGroup);
    }

    // SPAWN & MAINTAIN DYNAMIC FRUITS & DRINKS AROUND PLAYER
    function maintainFruitsAroundPlayer(px, pz) {
        // Remove foods that are too far (> 220m from player)
        for (let i = foods.length - 1; i >= 0; i--) {
            const food = foods[i];
            const dist = Math.sqrt((food.position.x - px)**2 + (food.position.z - pz)**2);
            if (dist > 230) {
                scene.remove(food);
                foods.splice(i, 1);
            }
        }

        // Spawn items to maintain 40 active items near player
        while (foods.length < 40) {
            const type = Math.floor(Math.random() * 6);
            let itemGroup = new THREE.Group();
            let points = 15;

            if (type === 0) { // 🍎 Red Apple
                const appleGeo = new THREE.SphereGeometry(0.6, 16, 16);
                const appleMat = new THREE.MeshStandardMaterial({ color: 0xdc2626, roughness: 0.2 });
                const apple = new THREE.Mesh(appleGeo, appleMat); apple.position.y = 0.6; apple.castShadow = true;
                itemGroup.add(apple);
                const leafGeo = new THREE.BoxGeometry(0.3, 0.05, 0.15);
                const leafMat = new THREE.MeshBasicMaterial({ color: 0x16a34a });
                const leaf = new THREE.Mesh(leafGeo, leafMat); leaf.position.set(0.15, 1.15, 0);
                itemGroup.add(leaf);
                points = 15;
            } else if (type === 1) { // 🍊 Orange
                const orangeGeo = new THREE.SphereGeometry(0.55, 16, 16);
                const orangeMat = new THREE.MeshStandardMaterial({ color: 0xf97316, roughness: 0.4 });
                const orange = new THREE.Mesh(orangeGeo, orangeMat); orange.position.y = 0.55; orange.castShadow = true;
                itemGroup.add(orange);
                points = 15;
            } else if (type === 2) { // 🍉 Watermelon Slice
                const melonGeo = new THREE.CylinderGeometry(0.7, 0.7, 0.3, 12, 1, false, 0, Math.PI);
                const melonMat = new THREE.MeshStandardMaterial({ color: 0x16a34a, roughness: 0.5 });
                const melon = new THREE.Mesh(melonGeo, melonMat); melon.rotation.z = Math.PI/2; melon.position.y = 0.7; melon.castShadow = true;
                itemGroup.add(melon);
                const fleshGeo = new THREE.CylinderGeometry(0.55, 0.55, 0.31, 12, 1, false, 0, Math.PI);
                const fleshMat = new THREE.MeshStandardMaterial({ color: 0xef4444, roughness: 0.3 });
                const flesh = new THREE.Mesh(fleshGeo, fleshMat); flesh.rotation.z = Math.PI/2; flesh.position.y = 0.7;
                itemGroup.add(flesh);
                points = 25;
            } else if (type === 3) { // 🍓 Strawberry
                const strawGeo = new THREE.ConeGeometry(0.5, 0.8, 12);
                const strawMat = new THREE.MeshStandardMaterial({ color: 0xe11d48, roughness: 0.3 });
                const straw = new THREE.Mesh(strawGeo, strawMat); straw.rotation.x = Math.PI; straw.position.y = 0.7; straw.castShadow = true;
                itemGroup.add(straw);
                points = 20;
            } else if (type === 4) { // 🍌 Banana
                const bananaGeo = new THREE.TorusGeometry(0.5, 0.14, 8, 16, Math.PI * 0.8);
                const bananaMat = new THREE.MeshStandardMaterial({ color: 0xeab308, roughness: 0.3 });
                const banana = new THREE.Mesh(bananaGeo, bananaMat); banana.position.y = 0.6; banana.castShadow = true;
                itemGroup.add(banana);
                points = 15;
            } else { // 🧋 Boba Milk Tea
                const cupGeo = new THREE.CylinderGeometry(0.5, 0.38, 1.2, 16);
                const cupMat = new THREE.MeshStandardMaterial({ color: 0xf5a623, roughness: 0.2, transparent: true, opacity: 0.9 });
                const cup = new THREE.Mesh(cupGeo, cupMat); cup.position.y = 0.6; cup.castShadow = true;
                itemGroup.add(cup);
                points = 20;
            }

            const angle = Math.random() * Math.PI * 2;
            const dist = 12 + Math.random() * 110;
            const fx = px + Math.cos(angle) * dist;
            const fz = pz + Math.sin(angle) * dist;

            itemGroup.position.set(fx, 0, fz);
            itemGroup.userData = { points: points, rotSpeed: 0.02 + Math.random()*0.03, floatOffset: Math.random()*Math.PI*2 };
            scene.add(itemGroup);
            foods.push(itemGroup);
        }
    }

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
                const speed = 0.32;
                duckGroup.position.x += dx * speed;
                duckGroup.position.z += dz * speed;

                const targetAngle = Math.atan2(dx, dz);
                duckGroup.rotation.y = targetAngle;

                duckGroup.position.y = Math.abs(Math.sin(time * 14)) * 0.4;
                duckWingL.rotation.z = Math.sin(time * 16) * 0.45;
                duckWingR.rotation.z = -Math.sin(time * 16) * 0.45;
            } else {
                duckGroup.position.y = Math.sin(time * 3) * 0.08;
                duckWingL.rotation.z = 0; duckWingR.rotation.z = 0;
            }

            // Smooth 3D Camera Follow Player Infinitely
            camera.position.x = THREE.MathUtils.lerp(camera.position.x, duckGroup.position.x, 0.08);
            camera.position.z = THREE.MathUtils.lerp(camera.position.z, duckGroup.position.z + 28, 0.08);
            camera.lookAt(duckGroup.position.x, 1, duckGroup.position.z);

            // Light Follows Player for dynamic shadows
            if (dirLight) {
                dirLight.position.set(duckGroup.position.x + 30, 45, duckGroup.position.z + 30);
            }

            // Update Chunks & Food around Player position dynamically ("Đi tới đâu load tới đó")
            updateWorldChunksAroundPlayer(duckGroup.position.x, duckGroup.position.z);
            maintainFruitsAroundPlayer(duckGroup.position.x, duckGroup.position.z);

            // Food collision check
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

            // Animate Wildlife Animals
            for (let ai = 0; ai < activeAnimals.length; ai++) {
                const a = activeAnimals[ai];
                const ud = a.userData;
                ud.wanderTimer--;
                if (ud.wanderTimer <= 0) {
                    ud.wanderAngle += (Math.random() - 0.5) * 1.2;
                    ud.wanderTimer = 80 + Math.random() * 120;
                }
                const spd = ud.speed;
                a.position.x += Math.cos(ud.wanderAngle) * spd;
                a.position.z += Math.sin(ud.wanderAngle) * spd;
                a.rotation.y = ud.wanderAngle + Math.PI / 2;

                const name = ud.animalName;
                if (ud.flying) {
                    a.position.y = (ud.baseY || 1.8) + Math.sin(time * 2.2 + ud.bobOffset) * 0.5;
                    if (!ud.baseY) ud.baseY = a.position.y;
                    // Butterfly/bat wing flap
                    if (name === 'butterfly' && a.children[0]) {
                        a.children[0].rotation.y = Math.sin(time * 12 + ud.bobOffset) * 0.7;
                        a.children[1].rotation.y = -Math.sin(time * 12 + ud.bobOffset) * 0.7;
                    }
                    if (name === 'bat' && a.children[1]) {
                        a.children[1].rotation.z = Math.sin(time * 10) * 0.55;
                        a.children[2].rotation.z = -Math.sin(time * 10) * 0.55;
                    }
                } else {
                    // Ground bobbing / hop
                    if (name === 'rabbit') a.position.y = Math.abs(Math.sin(time * 7 + ud.bobOffset)) * 0.28;
                    else if (name === 'penguin') a.position.y = 0; // waddle rotation
                    else a.position.y = 0;
                }
            }
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
        // Rebuild 3D world & duck skin if changed
        rebuildMapEnvironment();
        create3DDuck(currentDuckSkin);

        document.getElementById('start-modal').style.display = 'none';
        document.getElementById('end-modal').style.display = 'none';
        document.getElementById('lb-modal').style.display = 'none';
        
        score = 0;
        timeLeft = selectedTime;
        gameActive = true;

        // Clear & respawn foods around start
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
