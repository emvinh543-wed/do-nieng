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
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chiến Long Bá 3D - MMORPG Thế Giới Mở Đỉnh Cao</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700;900&family=Orbitron:wght@500;600;700;800;900&family=Russo+One&family=Share+Tech+Mono&family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <!-- Three.js 3D Engine -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="/Game/lang.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; user-select: none; }
        body {
            background: #030712;
            color: #f8fafc;
            overflow: hidden;
            height: 100vh;
            width: 100vw;
            font-family: 'Orbitron', 'Inter', sans-serif;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
        }
        
        #game-canvas { width: 100vw; height: 100vh; display: block; }
        
        /* MMORPG HUD LAYER */
        .hud-layer {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 16px 20px;
            z-index: 10;
        }

        .hud-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            width: 100%;
            gap: 15px;
        }

        /* 1. CHARACTER FRAME (AVATAR, HP/MP, LEVEL) - PHONG CÁCH CHIẾN LONG BÁ */
        .player-frame {
            display: flex;
            align-items: center;
            gap: 14px;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.94), rgba(3, 7, 18, 0.96));
            padding: 10px 18px 10px 12px;
            border-radius: 18px;
            border: 2px solid #f59e0b;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.8), 0 0 20px rgba(245, 158, 11, 0.25);
            backdrop-filter: blur(14px);
            pointer-events: auto;
            position: relative;
        }

        .avatar-hex {
            position: relative;
            width: 62px;
            height: 62px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .avatar-hex img {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fbbf24;
            box-shadow: 0 0 14px rgba(251, 191, 36, 0.6);
        }
        .level-badge {
            position: absolute;
            bottom: -4px;
            right: -4px;
            background: linear-gradient(135deg, #ef4444, #991b1b);
            border: 1.5px solid #fbbf24;
            color: #fff;
            font-size: 0.68rem;
            font-weight: 900;
            padding: 2px 6px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.6);
            font-family: 'Share Tech Mono', monospace;
        }

        .player-bars {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 180px;
        }
        .player-name-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .player-name {
            font-family: 'Cinzel Decorative', cursive, sans-serif;
            font-size: 0.95rem;
            font-weight: 900;
            color: #fbbf24;
            text-shadow: 0 0 10px rgba(251, 191, 36, 0.6);
            letter-spacing: 0.5px;
        }
        .player-title {
            font-size: 0.62rem;
            color: #38bdf8;
            font-weight: 800;
            letter-spacing: 0.5px;
            background: rgba(56, 189, 248, 0.15);
            padding: 1px 6px;
            border-radius: 6px;
            border: 1px solid rgba(56, 189, 248, 0.4);
        }

        /* Thanh Máu HP & Nộ MP */
        .bar-wrap {
            position: relative;
            width: 100%;
            height: 14px;
            background: rgba(3, 7, 18, 0.8);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.8);
        }
        .bar-fill-hp {
            height: 100%;
            width: 100%;
            background: linear-gradient(90deg, #dc2626, #ef4444, #f87171);
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.8);
            transition: width 0.3s ease;
        }
        .bar-fill-mp {
            height: 100%;
            width: 100%;
            background: linear-gradient(90deg, #2563eb, #38bdf8, #818cf8);
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(56, 189, 248, 0.8);
            transition: width 0.3s ease;
        }
        .bar-text {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Share Tech Mono', monospace;
            font-size: 0.62rem;
            font-weight: 800;
            color: #fff;
            text-shadow: 0 1px 3px rgba(0,0,0,0.9);
        }

        /* 2. TACTICAL RADAR MINI-MAP (TOP RIGHT) */
        .radar-box {
            position: relative;
            width: 130px;
            height: 130px;
            background: rgba(3, 7, 18, 0.92);
            border: 2px solid #f59e0b;
            border-radius: 50%;
            backdrop-filter: blur(14px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.8), 0 0 16px rgba(245, 158, 11, 0.35);
            overflow: hidden;
            pointer-events: auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #radar-canvas {
            width: 100%;
            height: 100%;
            border-radius: 50%;
        }
        .radar-sweep {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: conic-gradient(from 0deg, rgba(245, 158, 11, 0.25) 0deg, transparent 60deg);
            animation: radarRotate 4s linear infinite;
            pointer-events: none;
        }
        @keyframes radarRotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .radar-coords {
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            font-family: 'Share Tech Mono', monospace;
            font-size: 0.65rem;
            color: #38bdf8;
            white-space: nowrap;
            background: rgba(15, 23, 42, 0.9);
            padding: 1px 6px;
            border-radius: 6px;
            border: 1px solid rgba(56, 189, 248, 0.4);
        }

        /* 3. FLOATING SCORE NUMBERS */
        .damage-container {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 50;
        }
        .damage-popup {
            position: absolute;
            font-family: 'Russo One', sans-serif;
            font-size: 1.4rem;
            font-weight: 900;
            color: #fde047;
            text-shadow: 0 0 10px rgba(234, 179, 8, 0.9), 2px 2px 0 #000;
            animation: damageFloat 0.9s cubic-bezier(0.18, 0.89, 0.32, 1.28) forwards;
            pointer-events: none;
        }
        .damage-popup.critical {
            font-size: 1.9rem;
            color: #ef4444;
            text-shadow: 0 0 16px rgba(239, 68, 68, 0.95), 2px 2px 0 #000;
        }
        @keyframes damageFloat {
            0% { opacity: 0; transform: translateY(0) scale(0.6); }
            30% { opacity: 1; transform: translateY(-24px) scale(1.15); }
            100% { opacity: 0; transform: translateY(-65px) scale(0.9); }
        }

        .score-board {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .hud-card {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.92), rgba(3, 7, 18, 0.96));
            border: 2px solid #f59e0b;
            padding: 8px 16px;
            border-radius: 16px;
            backdrop-filter: blur(12px);
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.6), 0 0 12px rgba(245, 158, 11, 0.2);
        }

        .hud-card label { font-size: 0.68rem; color: #94a3b8; text-transform: uppercase; font-weight: 800; display: block; font-family: 'Share Tech Mono', monospace; }
        .hud-card span { font-size: 1.35rem; font-weight: 900; color: #fbbf24; font-family: 'Russo One', sans-serif; }

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

        /* Interaction Prompt HUD (Tương tác Tàu Lửa & Ngôi Nhà) */
        .interaction-prompt {
            position: absolute;
            bottom: 82px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(3, 7, 18, 0.98));
            border: 2px solid #f59e0b;
            border-radius: 30px;
            padding: 8px 22px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.8), 0 0 20px rgba(245, 158, 11, 0.4);
            backdrop-filter: blur(14px);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 100;
            pointer-events: auto;
            animation: bounceIn 0.3s ease;
        }
        .prompt-text {
            font-family: 'Orbitron', sans-serif;
            font-size: 0.92rem;
            font-weight: 800;
            color: #fbbf24;
            letter-spacing: 0.5px;
            text-shadow: 0 0 10px rgba(251, 191, 36, 0.5);
        }
        .btn-prompt-act {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border: 1px solid #fde047;
            color: #000;
            font-weight: 900;
            font-size: 0.8rem;
            padding: 5px 14px;
            border-radius: 16px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.6);
            transition: all 0.2s ease;
        }
        .btn-prompt-act:hover {
            transform: scale(1.08);
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
        }
        @keyframes bounceIn {
            0% { transform: translateX(-50%) scale(0.8); opacity: 0; }
            100% { transform: translateX(-50%) scale(1); opacity: 1; }
        }

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

        /* Train Schedule HUD */
        .train-hud {
            position: fixed;
            top: 76px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(15, 23, 42, 0.88);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(245, 166, 35, 0.5);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            color: #f8fafc;
            padding: 6px 18px;
            border-radius: 30px;
            font-size: 0.86rem;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 100;
            pointer-events: auto;
        }
        .btn-skip-train {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border: none;
            color: white;
            font-weight: 700;
            font-size: 0.78rem;
            padding: 4px 12px;
            border-radius: 14px;
            cursor: pointer;
            transition: transform 0.2s, background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-skip-train:hover {
            transform: scale(1.05);
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
        }
        .btn-weather-toggle, .btn-daytime-toggle {
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid #38bdf8;
            color: #f8fafc;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-weather-toggle:hover {
            background: rgba(56, 189, 248, 0.25);
            border-color: #7dd3fc;
            box-shadow: 0 0 10px rgba(56, 189, 248, 0.4);
        }
        .btn-daytime-toggle:hover {
            background: rgba(245, 158, 11, 0.25);
            border-color: #fbbf24;
            box-shadow: 0 0 10px rgba(245, 158, 11, 0.4);
        }
            transform: scale(1.05);
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
        }

        @media (max-width: 768px) {
            .joystick-container { display: block; }
            .controls-hint { display: none; }
            .train-hud { top: auto; bottom: 85px; font-size: 0.75rem; padding: 4px 12px; }
        }
    </style>
</head>
<body>

    <!-- 3D Canvas -->
    <canvas id="game-canvas"></canvas>

    <!-- First-Person Crosshair -->
    <div id="crosshair"></div>

    <!-- Floating Damage Popup Container -->
    <div class="damage-container" id="damage-container"></div>

    <!-- HUD Layer Đồ Họa Cao Cấp Chiến Long Bá -->
    <div class="hud-layer">
        <div class="hud-top">
            <!-- 1. Player Info Card (Giao diện Chiến Long Bá) -->
            <div class="player-frame">
                <div class="avatar-hex">
                    <span style="font-size:2.2rem;">🐥</span>
                    <span class="player-level-badge">LV.99</span>
                </div>
                <div class="player-bars">
                    <div class="player-name-row">
                        <span class="player-name"><?php echo $logged_in_user ? htmlspecialchars($logged_in_user['fullname']) : 'Vịt Thần Hoàng Kim'; ?></span>
                        <span class="player-title">⭐ THỢ SĂN KHO BÁU</span>
                    </div>
                    <div class="bar-wrap">
                        <div class="bar-fill-hp" style="width: 100%; background: linear-gradient(90deg, #f59e0b, #fbbf24, #fef08a); box-shadow: 0 0 10px rgba(251, 191, 36, 0.8);"></div>
                        <div class="bar-text">CẤP ĐỘ KHÁM PHÁ: TỐI ĐA</div>
                    </div>
                    <div class="bar-wrap">
                        <div class="bar-fill-mp" style="width: 100%;"></div>
                        <div class="bar-text">THẾ GIỚI 3D VÔ TẬN</div>
                    </div>
                </div>
            </div>

            <!-- 2. Radar Minimap 360 Độ (Góc trên bên phải) -->
            <div class="radar-box">
                <div class="radar-header">
                    <span>📡 RADAR QUÉT 360°</span>
                    <span id="radar-coords" style="color:#38bdf8;">[0, 0]</span>
                </div>
                <div style="position:relative; width:130px; height:130px; margin:0 auto;">
                    <canvas id="radar-canvas" width="130" height="130"></canvas>
                    <div class="radar-sweep"></div>
                </div>
            </div>

            <!-- 3. Stats Scoreboard & Buttons -->
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
        
        <!-- Train Schedule HUD -->
        <div class="train-hud" id="train-hud">
            <span style="font-size:1.25rem;">🚂</span>
            <span id="train-status-text">Tàu đang đỗ tại <strong>Ga Trung Tâm</strong>: Khởi hành sau <strong id="train-countdown" style="color:#fbbf24;font-size:1rem;">05:00</strong></span>
            <button class="btn-skip-train" id="btn-train-action" onclick="departTrainNow()" title="Bỏ qua chờ 5 phút, khởi hành ngay">🔔 Bấm Còi Chạy Ngay</button>
        </div>

        <!-- Interaction Prompt HUD (Tương tác Tàu Lửa & Ngôi Nhà) -->
        <div class="interaction-prompt" id="interaction-prompt" style="display:none;">
            <span id="prompt-icon" style="font-size:1.4rem;">🏡</span>
            <span id="prompt-text" class="prompt-text">[E] Gõ cửa thăm nhà</span>
            <button class="btn-prompt-act" id="btn-prompt-act" onclick="triggerCurrentInteraction()">Tương Tác [E]</button>
        </div>

        <div class="controls-hint">
            <h4 data-i18n="controls_title">🐥 ĐIỀU KHIỂN & TƯƠNG TÁC THẾ GIỚI 3D:</h4>
            <div class="key-row">
                <span data-i18n="controls_text">Di chuyển:</span> <span class="key">W</span><span class="key">A</span><span class="key">S</span><span class="key">D</span> 
                <span>| Xoay camera: Rê chuột trái | Đổi góc nhìn:</span> <span class="key">V</span>
            </div>
            <div class="key-row" style="margin-top:4px;">
                <span>Tương tác:</span> <span class="key">E</span> Lên/Xuống Tàu & Gõ Cửa Nhà | <span class="key">F</span> Kéo Còi Tàu 🔔
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

    // 3rd Person Orbit Controls (Nhấn giữ & rê chuột để xoay camera và hướng quay quanh vịt)
    let orbitYaw = 0;        // Góc xoay ngang quanh vịt (radians)
    let orbitPitch = 0.66;   // Góc ngẩng nhìn xuống ~ 38 độ
    let orbitDistance = 35.6;// Khoảng cách camera đến vịt
    let isMouseDragging = false;
    let lastMouseX = 0;
    let lastMouseY = 0;

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

    function playTrainWhistleSound() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            [440, 554].forEach(freq => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(freq * 1.05, ctx.currentTime + 0.3);
                osc.frequency.exponentialRampToValueAtTime(freq * 0.98, ctx.currentTime + 1.2);
                gain.gain.setValueAtTime(0.01, ctx.currentTime);
                gain.gain.linearRampToValueAtTime(0.25, ctx.currentTime + 0.15);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 1.3);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 1.35);
            });
        } catch(e){}
    }

    function departTrainNow() {
        if (!trainState) return;
        playTrainWhistleSound();
        trainState.status = 'RUNNING';
        trainState.remainingSeconds = 0;
        const btn = document.getElementById('btn-train-action');
        if (btn) btn.style.display = 'none';
        const stText = document.getElementById('train-status-text');
        if (stText) {
            const nextSt = getStationByIndex(trainState.currentStationIdx + 1);
            stText.innerHTML = `🚂 Tàu đang chạy tới <strong>${nextSt.name}</strong>... [Tốc độ: 45 km/h]`;
        }
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

    // Mouse Drag to Orbit Camera & Change Direction in 3rd Person, or Mouse Look in 1st Person
    window.addEventListener('mousedown', e => {
        if (e.target.closest('.game-modal') || e.target.closest('button') || e.target.closest('a') || e.target.closest('.header-bar')) {
            return;
        }
        if (e.button === 0) {
            isMouseDragging = true;
            lastMouseX = e.clientX;
            lastMouseY = e.clientY;
        }
    });

    window.addEventListener('mouseup', () => {
        isMouseDragging = false;
    });

    window.addEventListener('mouseleave', () => {
        isMouseDragging = false;
    });

    window.addEventListener('mousemove', e => {
        if (!gameActive) return;

        if (isFirstPerson) {
            const movementX = e.movementX || e.mozMovementX || 0;
            const movementY = e.movementY || e.mozMovementY || 0;

            fpYaw -= movementX * 0.003;
            fpPitch -= movementY * 0.003;
            fpPitch = Math.max(-Math.PI / 3, Math.min(Math.PI / 3, fpPitch));
        } else if (isMouseDragging) {
            const movementX = e.clientX - lastMouseX;
            const movementY = e.clientY - lastMouseY;
            lastMouseX = e.clientX;
            lastMouseY = e.clientY;

            // Rê chuột sang trái (movementX < 0) -> xoay hướng camera/vịt sang trái
            orbitYaw -= movementX * 0.006;

            // Chỉnh độ cao góc nhìn ngẩng/cúi
            orbitPitch += movementY * 0.004;
            orbitPitch = Math.max(0.15, Math.min(Math.PI / 2.3, orbitPitch));
        }
    });

    // Cuộn chuột zoom gần/xa trong góc nhìn 3rd Person
    window.addEventListener('wheel', e => {
        if (!isFirstPerson && gameActive) {
            orbitDistance = Math.max(12, Math.min(65, orbitDistance + e.deltaY * 0.04));
        }
    }, { passive: true });

    // Tương tác Thế giới 3D: Tàu Hoả & Ngôi Nhà
    let isOnTrain = false;
    let activeHouses = [];
    let currentPromptAction = null;

    // Press 'V' to toggle View Mode, 'E' to Interact, 'F' for Train Horn
    window.addEventListener('keydown', e => {
        if (e.key === 'v' || e.key === 'V') {
            toggleFirstPersonView();
        } else if (e.key === 'e' || e.key === 'E') {
            if (currentPromptAction) {
                currentPromptAction();
            }
        } else if (e.key === 'f' || e.key === 'F') {
            if (globalSteamTrain && duckGroup && Math.hypot(duckGroup.position.x - (globalSteamTrain.position.x + 3.5), duckGroup.position.z - globalSteamTrain.position.z) < 5.2) {
                blowTrainHorn();
            } else {
                toggleFirstPersonView();
            }
        }
    });

    // 3D Objects & Dynamic Infinite Chunk System
    let scene, camera, renderer, dirLight, hemiLight;
    let duckGroup, duckBody, duckHead, duckBeak, duckWingL, duckWingR;
    let heroAuraRing = null; // Vòng hào quang Ma Pháp Trận dưới chân vịt phong cách Chiến Long Bá
    let foods = [];
    let activeAnimals = [];
    let activeShips = [];
    let activeParticles = null;
    let score = 0, timeLeft = 60, gameActive = false, timerInterval;

    // GLOBAL TRAIN & RAILWAY STATIONS CONFIG
    const TRAIN_STATIONS = [
        { name: "GA HOA RỪNG (Flower Station)", x: -480 },
        { name: "GA ĐỒI THÔNG (Pine Hill Station)", x: -240 },
        { name: "GA TRUNG TÂM GLOW (Central Station)", x: 0 },
        { name: "GA BÃI BIỂN VÀNG (Golden Beach Station)", x: 240 },
        { name: "GA ĐÔ THỊ ÁNH SÁNG (Metro City Station)", x: 480 },
        { name: "GA NÚI LỬA ĐỎ (Volcano Station)", x: 720 },
        { name: "GA BĂNG TUYẾT (Snowy Mountain Station)", x: 960 }
    ];

    function getStationByIndex(idx) {
        if (idx < 0) return TRAIN_STATIONS[0];
        if (idx >= TRAIN_STATIONS.length) return TRAIN_STATIONS[TRAIN_STATIONS.length - 1];
        return TRAIN_STATIONS[idx];
    }

    let globalSteamTrain = null;
    let trainState = {
        x: 0,
        z: 0,
        speed: 0,
        maxSpeed: 0.38,
        status: 'WAITING', // 'WAITING' (đang đỗ tại ga) hoặc 'RUNNING' (đang di chuyển)
        currentStationIdx: 2, // Xuất phát tại Ga Trung Tâm (x: 0)
        remainingSeconds: 300, // 5 phút (300 giây) theo đúng yêu cầu
        lastTimerTick: Date.now()
    };
    let trainSmokeList = [];
    let stationBoards = [];

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
        stationBoards = [];
        activeShips = [];
        activeHouses = [];
        isOnTrain = false;

        // Dọn dẹp hạt khói cũ
        trainSmokeList.forEach(p => scene.remove(p.mesh));
        trainSmokeList = [];

        // Khởi tạo Đoàn Tàu Hơi Nước Duy Nhất tại Ga Trung Tâm (x = 0, z = 0)
        if (globalSteamTrain) {
            scene.remove(globalSteamTrain);
            globalSteamTrain = null;
        }
        globalSteamTrain = createUltraDetailedTrain(0, 0, 1);
        globalSteamTrain.position.set(0, 0, 0);
        scene.add(globalSteamTrain);

        trainState.x = 0;
        trainState.speed = 0;
        trainState.status = 'WAITING';
        trainState.currentStationIdx = 2; // Bắt đầu ở Ga Trung Tâm
        trainState.remainingSeconds = 300; // 5 phút
        trainState.lastTimerTick = Date.now();

        const btnAct = document.getElementById('btn-train-action');
        if (btnAct) btnAct.style.display = 'inline-flex';

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
        houseGroup.add(mb, mbPole);
        houseGroup.userData = { door: door };
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
    // TEXTURE GENERATOR FOR 3D SIGNBOARDS & STATION BOARDS
    // -------------------------------------------------------------
    function createTextCanvasTexture(text, bgColor = '#0f172a', textColor = '#fbbf24', borderColor = '#f59e0b', fontSize = 34) {
        const canvas = document.createElement('canvas');
        canvas.width = 512;
        canvas.height = 128;
        const ctx = canvas.getContext('2d');

        ctx.fillStyle = bgColor;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        ctx.lineWidth = 8;
        ctx.strokeStyle = borderColor;
        ctx.strokeRect(4, 4, canvas.width - 8, canvas.height - 8);

        ctx.fillStyle = textColor;
        ctx.font = `bold ${fontSize}px sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(text, canvas.width / 2, canvas.height / 2);

        const texture = new THREE.CanvasTexture(canvas);
        texture.needsUpdate = true;
        return { texture, canvas, ctx };
    }

    // -------------------------------------------------------------
    // ULTRA-DETAILED 3D TRAIN STATION (Trạm Ga Tàu Lửa Hoành Tráng)
    // -------------------------------------------------------------
    function createTrainStation(stationX, stationZ, stationName) {
        const stationGroup = new THREE.Group();

        // 1. Sân Ga Platform (Dài 48m, Rộng 7.5m, Cao 0.85m)
        const platGeo = new THREE.BoxGeometry(48, 0.85, 7.5);
        const platMat = new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.85 });
        const platform = new THREE.Mesh(platGeo, platMat);
        platform.position.set(stationX, 0.42, stationZ + 5.2);
        platform.receiveShadow = true; platform.castShadow = true;
        stationGroup.add(platform);

        // Vạch sọc vàng mép sân ga cảnh báo an toàn
        const edgeGeo = new THREE.BoxGeometry(48, 0.05, 0.4);
        const edgeMat = new THREE.MeshStandardMaterial({ color: 0xfacc15, roughness: 0.5 });
        const yellowLine = new THREE.Mesh(edgeGeo, edgeMat);
        yellowLine.position.set(stationX, 0.86, stationZ + 1.6);
        stationGroup.add(yellowLine);

        // 2. Mái Vòm Che Sân Ga Hiện Đại (Canopy Roof)
        const colMat = new THREE.MeshStandardMaterial({ color: 0x0284c7, metalness: 0.8, roughness: 0.2 });
        const roofMat = new THREE.MeshStandardMaterial({ color: 0x38bdf8, transparent: true, opacity: 0.65, roughness: 0.1 });

        for (let cx = -18; cx <= 18; cx += 12) {
            // Cột thép chữ Y
            const col = new THREE.Mesh(new THREE.CylinderGeometry(0.25, 0.25, 4.8, 12), colMat);
            col.position.set(stationX + cx, 2.8, stationZ + 5.2);
            col.castShadow = true;
            stationGroup.add(col);

            // Xà ngang vòm
            const beam = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.35, 7.2), colMat);
            beam.position.set(stationX + cx, 5.2, stationZ + 5.2);
            stationGroup.add(beam);
        }

        // Tấm mái vòm kính cong
        const roofGeo = new THREE.BoxGeometry(46, 0.18, 7.4);
        const canopy = new THREE.Mesh(roofGeo, roofMat);
        canopy.position.set(stationX, 5.3, stationZ + 5.2);
        stationGroup.add(canopy);

        // 3. Tòa Nhà Ga Hành Khách (Station House / Ticket Office)
        const buildingGeo = new THREE.BoxGeometry(22, 5.2, 7.5);
        const buildingMat = new THREE.MeshStandardMaterial({ color: 0x991b1b, roughness: 0.7 });
        const building = new THREE.Mesh(buildingGeo, buildingMat);
        building.position.set(stationX, 2.6, stationZ + 12.5);
        building.castShadow = true; building.receiveShadow = true;
        stationGroup.add(building);

        // Mái nhà ga dốc ngói đen
        const houseRoof = new THREE.Mesh(new THREE.ConeGeometry(17, 2.4, 4), new THREE.MeshStandardMaterial({ color: 0x1e293b }));
        houseRoof.rotation.y = Math.PI / 4; houseRoof.position.set(stationX, 6.4, stationZ + 12.5);
        stationGroup.add(houseRoof);

        // Cửa kính phòng vé sáng đèn
        const doorMat = new THREE.MeshStandardMaterial({ color: 0xfde047, emissive: 0xfde047, emissiveIntensity: 0.85 });
        const door = new THREE.Mesh(new THREE.BoxGeometry(3.2, 3.2, 0.2), doorMat);
        door.position.set(stationX, 1.8, stationZ + 8.65);
        stationGroup.add(door);

        // 4. BIỂN HIỆU TÊN GA 3D (Station Signboard)
        const signTexObj = createTextCanvasTexture(`🚉 ${stationName}`, '#0f172a', '#fbbf24', '#f59e0b', 30);
        const signGeo = new THREE.BoxGeometry(16, 2.4, 0.4);
        const signMat = new THREE.MeshStandardMaterial({ map: signTexObj.texture, roughness: 0.3 });
        const stationSign = new THREE.Mesh(signGeo, signMat);
        stationSign.position.set(stationX, 6.6, stationZ + 5.2);
        stationGroup.add(stationSign);

        // Biển hiệu 2 đầu sân ga
        const smallSignObj = createTextCanvasTexture(stationName, '#0284c7', '#ffffff', '#38bdf8', 34);
        [-20, 20].forEach(sx => {
            const pole = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.12, 3.5, 8), colMat);
            pole.position.set(stationX + sx, 2.2, stationZ + 2.5);
            const smSign = new THREE.Mesh(new THREE.BoxGeometry(4.8, 1.4, 0.2), new THREE.MeshStandardMaterial({ map: smallSignObj.texture }));
            smSign.position.set(stationX + sx, 3.4, stationZ + 2.5);
            stationGroup.add(pole, smSign);
        });

        // 5. BẢNG ĐIỆN TỬ LED ĐẾM NGƯỢC 3D (Departure Countdown Board)
        const boardCanvas = document.createElement('canvas');
        boardCanvas.width = 512; boardCanvas.height = 128;
        const bCtx = boardCanvas.getContext('2d');
        const bTex = new THREE.CanvasTexture(boardCanvas);
        const boardMesh = new THREE.Mesh(
            new THREE.BoxGeometry(8.2, 1.8, 0.3),
            new THREE.MeshStandardMaterial({ map: bTex, roughness: 0.3, emissive: 0x000000 })
        );
        boardMesh.position.set(stationX, 4.4, stationZ + 3.8);
        stationGroup.add(boardMesh);

        // Lưu vào danh sách để cập nhật đồng hồ đếm ngược
        stationBoards.push({ canvas: boardCanvas, ctx: bCtx, texture: bTex, stationX: stationX });

        // 6. Băng ghế gỗ & Đèn sân ga (Benches & Platform Lamps)
        const benchMat = new THREE.MeshStandardMaterial({ color: 0x78350f, roughness: 0.8 });
        [-12, 0, 12].forEach(bx => {
            const bench = new THREE.Mesh(new THREE.BoxGeometry(3.4, 0.6, 1.0), benchMat);
            bench.position.set(stationX + bx, 1.15, stationZ + 6.8);
            stationGroup.add(bench);

            // Cột đèn
            const lampPole = new THREE.Mesh(new THREE.CylinderGeometry(0.1, 0.12, 4.2, 8), new THREE.MeshStandardMaterial({ color: 0x1e293b, metalness: 0.8 }));
            lampPole.position.set(stationX + bx, 2.6, stationZ + 3.5);
            const lampLight = new THREE.PointLight(0xfef08a, 1.8, 18);
            lampLight.position.set(stationX + bx, 4.6, stationZ + 3.5);
            stationGroup.add(lampPole, lampLight);
        });

        // 7. Biển báo an toàn đường ray (Crossbuck Railway Sign)
        const crossPole = new THREE.Mesh(new THREE.CylinderGeometry(0.1, 0.1, 3.2, 8), new THREE.MeshStandardMaterial({ color: 0x94a3b8 }));
        crossPole.position.set(stationX - 22, 1.6, stationZ - 2.5);
        const crossSignTex = createTextCanvasTexture('⚠️ CHÚ Ý ĐƯỜNG RAY', '#dc2626', '#ffffff', '#fef08a', 28);
        const crossSign = new THREE.Mesh(new THREE.BoxGeometry(4.5, 1.2, 0.15), new THREE.MeshStandardMaterial({ map: crossSignTex.texture }));
        crossSign.position.set(stationX - 22, 2.8, stationZ - 2.5);
        stationGroup.add(crossPole, crossSign);

        return stationGroup;
    }

    // -------------------------------------------------------------
    // ULTRA-DETAILED REALISTIC BEACH & OCEAN COASTLINE (Bãi Biển & Sóng Biển)
    // -------------------------------------------------------------
    function createBeachCoastline(originX, originZ, seed) {
        const beachGroup = new THREE.Group();

        // 1. Nền Cát Vàng Mịn (Golden Sand Platform)
        const sandGeo = new THREE.BoxGeometry(CHUNK_SIZE, 2, CHUNK_SIZE);
        const sandMat = new THREE.MeshStandardMaterial({ color: 0xfde047, roughness: 0.95 });
        const sand = new THREE.Mesh(sandGeo, sandMat);
        sand.position.set(originX, -0.95, originZ);
        sand.receiveShadow = true;
        beachGroup.add(sand);

        // 2. Dải Bọt Sóng Biển Trắng Mép Bờ (Frothy Shoreline Water)
        const waveGeo = new THREE.PlaneGeometry(CHUNK_SIZE, 28);
        const waveMat = new THREE.MeshStandardMaterial({
            color: 0x38bdf8, roughness: 0.1, metalness: 0.4, transparent: true, opacity: 0.88,
            emissive: 0x0284c7, emissiveIntensity: 0.25
        });
        const wave = new THREE.Mesh(waveGeo, waveMat);
        wave.rotation.x = -Math.PI / 2;
        wave.position.set(originX, 0.08, originZ - CHUNK_SIZE / 2 + 14);
        beachGroup.add(wave);

        // 3. Rặng Dừa Nhiệt Đới Nghiêng Bóng Ra Biển (Coconut Palms)
        for (let p = 0; p < 7; p++) {
            seed++;
            const px = originX + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 16);
            const pz = originZ + (seededRandom(seed + 1) - 0.5) * (CHUNK_SIZE - 20);

            const palm = new THREE.Group();
            const trunkMat = new THREE.MeshStandardMaterial({ color: 0x78350f, roughness: 0.85 });
            const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.24, 0.44, 5.5, 8), trunkMat);
            trunk.position.y = 2.75;
            trunk.rotation.z = (seededRandom(seed + 2) - 0.5) * 0.35; // Nghiêng tự nhiên
            trunk.rotation.x = 0.2;

            // Tán lá dừa xòe rộng
            const leafMat = new THREE.MeshStandardMaterial({ color: 0x16a34a, roughness: 0.4 });
            const crown = new THREE.Group();
            crown.position.set(0, 5.4, 0.5);
            for (let a = 0; a < 6; a++) {
                const frond = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.1, 4.2), leafMat);
                frond.rotation.y = (a * Math.PI) / 3;
                frond.rotation.x = 0.35;
                frond.position.z = 1.6;
                crown.add(frond);
            }

            // Chùm quả dừa nâu
            for (let c = 0; c < 3; c++) {
                const coco = new THREE.Mesh(new THREE.SphereGeometry(0.35, 6, 6), new THREE.MeshStandardMaterial({ color: 0x451a03 }));
                coco.position.set((c - 1) * 0.3, 5.1, 0.5);
                palm.add(coco);
            }

            palm.add(trunk, crown);
            palm.position.set(px, 0, pz);
            beachGroup.add(palm);
        }

        // 4. Ghế Tắm Nắng & Dù Che Biển (Sun Loungers & Beach Umbrellas)
        const umbrellaColors = [0xef4444, 0x3b82f6, 0x10b981, 0xf59e0b];
        for (let u = 0; u < 4; u++) {
            seed++;
            const ux = originX + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 24);
            const uz = originZ + (seededRandom(seed + 1) - 0.5) * (CHUNK_SIZE - 28);

            const spot = new THREE.Group();

            // Ghế tắm nắng màu trắng
            const lounger = new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.35, 3.2), new THREE.MeshStandardMaterial({ color: 0xf8fafc, roughness: 0.4 }));
            lounger.position.set(0, 0.2, 0); lounger.castShadow = true;

            // Dù che biển nhiều màu
            const pole = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.08, 3.4, 8), new THREE.MeshStandardMaterial({ color: 0x94a3b8 }));
            pole.position.set(1.5, 1.7, 0);

            const canopy = new THREE.Mesh(
                new THREE.ConeGeometry(2.4, 1.2, 8),
                new THREE.MeshStandardMaterial({ color: umbrellaColors[u % umbrellaColors.length], roughness: 0.3 })
            );
            canopy.position.set(1.5, 3.2, 0);
            canopy.castShadow = true;

            spot.add(lounger, pole, canopy);
            spot.position.set(ux, 0, uz);
            beachGroup.add(spot);
        }

        // 5. Phao Cứu Sinh Tròn & Lâu Đài Cát (Lifebuoys & Sandcastles)
        for (let b = 0; b < 3; b++) {
            seed++;
            const bx = originX + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 20);
            const bz = originZ + (seededRandom(seed + 1) - 0.5) * (CHUNK_SIZE - 22);

            const buoy = new THREE.Mesh(new THREE.TorusGeometry(0.7, 0.25, 8, 16), new THREE.MeshStandardMaterial({ color: 0xf97316 }));
            buoy.rotation.x = Math.PI / 2; buoy.position.set(bx, 0.2, bz);
            beachGroup.add(buoy);

            // Lâu đài cát
            const castle = new THREE.Mesh(new THREE.CylinderGeometry(0.6, 0.9, 1.2, 8), new THREE.MeshStandardMaterial({ color: 0xca8a04, roughness: 0.9 }));
            castle.position.set(bx + 2.5, 0.6, bz);
            beachGroup.add(castle);
        }

        // 6. BIỂN HIỆU CHÀO MỪNG BÃI BIỂN 3D (Beach Welcome Signboard)
        const signTex = createTextCanvasTexture('🏖️ BÃI BIỂN VỊT VÀNG - GLOW BEACH 🌊', '#0284c7', '#ffffff', '#38bdf8', 26);
        const signMesh = new THREE.Mesh(new THREE.BoxGeometry(10.5, 1.8, 0.2), new THREE.MeshStandardMaterial({ map: signTex.texture, roughness: 0.4 }));
        signMesh.position.set(originX, 2.6, originZ + 18);
        const signPoles = [
            new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.12, 2.8, 8), new THREE.MeshStandardMaterial({ color: 0x78350f })),
            new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.12, 2.8, 8), new THREE.MeshStandardMaterial({ color: 0x78350f }))
        ];
        signPoles[0].position.set(originX - 4.5, 1.4, originZ + 18);
        signPoles[1].position.set(originX + 4.5, 1.4, originZ + 18);
        beachGroup.add(signMesh, signPoles[0], signPoles[1]);

        return beachGroup;
    }

    // -------------------------------------------------------------
    // ULTRA-DETAILED REALISTIC 3D STEAM LOCOMOTIVE TRAIN (Tàu Hỏa Hơi Nước)
    // -------------------------------------------------------------
    function createUltraDetailedTrain(originX, originZ, seed = 1) {
        const trainGroup = new THREE.Group();
        const trainWheels = [];

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
            trainWheels.push(wL, wR);
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

        trainGroup.position.set(originX, 0, originZ);
        trainGroup.userData = {
            chimneyX: 3.2,
            chimneyY: 4.1,
            wheels: trainWheels
        };
        
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

        // 1. VÙNG ĐẠI DƯƠNG VÀ BÃI BIỂN (cz <= -1)
        if (cz === -1) {
            // Vùng Bãi Biển Cát Vàng Nắng Rực Rỡ (Bờ biển chuyển tiếp)
            const beachCoast = createBeachCoastline(originX, originZ, seed);
            chunkGroup.add(beachCoast);
            return chunkGroup;
        } else if (cz <= -2) {
            // Vùng Đại Dương Biển Cả Mênh Mông (Deep Infinite Ocean)
            const oceanGeo = new THREE.PlaneGeometry(CHUNK_SIZE, CHUNK_SIZE);
            const oceanMat = new THREE.MeshStandardMaterial({
                color: theme.water, roughness: 0.08, metalness: 0.6, transparent: true, opacity: 0.94,
                emissive: theme.emissiveWater, emissiveIntensity: theme.emissiveIntensity
            });
            const ocean = new THREE.Mesh(oceanGeo, oceanMat);
            ocean.rotation.x = -Math.PI / 2; ocean.position.set(originX, 0.05, originZ);
            chunkGroup.add(ocean);

            // Tàu Thuyền Buồm & Tàu Chở Hàng ngoài khơi xa
            seed++;
            if (seededRandom(seed) > 0.35) {
                const ship = createUltraDetailedShip(originX, originZ, seed);
                scene.add(ship);
                activeShips.push(ship);
            }
            return chunkGroup;
        }

        // 2. VÙNG ĐẤT LIỀN (cz >= 0): ĐƯỜNG SẮT VÀ TRẠM GA TÀU LỬA
        if (cz === 0) {
            const railGroup = new THREE.Group();
            // Nền sỏi đá rải đường ray (Ballast)
            const ballastGeo = new THREE.BoxGeometry(CHUNK_SIZE, 0.12, 4.2);
            const ballastMat = new THREE.MeshStandardMaterial({ color: 0x475569, roughness: 0.95 });
            const ballast = new THREE.Mesh(ballastGeo, ballastMat);
            ballast.position.set(originX, 0.04, originZ);
            railGroup.add(ballast);

            // Hai thanh ray thép song song
            const railMat = new THREE.MeshStandardMaterial({ color: 0x94a3b8, metalness: 0.9, roughness: 0.2 });
            const railL = new THREE.Mesh(new THREE.BoxGeometry(CHUNK_SIZE, 0.2, 0.22), railMat);
            const railR = railL.clone();
            railL.position.set(originX, 0.15, originZ - 1.35);
            railR.position.set(originX, 0.15, originZ + 1.35);
            railGroup.add(railL, railR);

            // Các thanh tà vẹt gỗ (Sleepers)
            const sleeperMat = new THREE.MeshStandardMaterial({ color: 0x451a03, roughness: 0.9 });
            for (let s = -CHUNK_SIZE/2 + 1.5; s < CHUNK_SIZE/2; s += 2.6) {
                const sleeper = new THREE.Mesh(new THREE.BoxGeometry(0.55, 0.14, 3.6), sleeperMat);
                sleeper.position.set(originX + s, 0.07, originZ);
                railGroup.add(sleeper);
            }
            chunkGroup.add(railGroup);

            // TỰ ĐỘNG SINH TRẠM GA NẾU CÓ TRẠM NẰM TRONG PHẠM VI CHUNK NÀY
            for (const st of TRAIN_STATIONS) {
                if (Math.abs(originX - st.x) <= CHUNK_SIZE / 2) {
                    const station = createTrainStation(st.x, originZ, st.name);
                    chunkGroup.add(station);
                }
            }
        }

        // 3. THÀNH PHỐ VÀ NHÀ CỬA CHÂN THẬT (Đảm bảo không đè lên hành lang đường sắt)
        seed++;
        if (seededRandom(seed) > 0.38) {
            const isCityChunk = (Math.abs(cx + cz) % 3 === 0);
            const buildingCount = isCityChunk ? 2 : 2;

            for (let b = 0; b < buildingCount; b++) {
                seed++;
                const bx = originX + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 28);
                seed++;
                let bz = originZ + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 28);

                // Tránh hành lang đường sắt và trạm ga (z = 0)
                if (Math.abs(bz) < 16) {
                    bz = (bz >= 0) ? bz + 18 : bz - 18;
                }

                let buildingGroup;
                if (isCityChunk) {
                    buildingGroup = createUltraDetailedSkyscraper(seed, theme);
                } else {
                    buildingGroup = createUltraDetailedHouse(seed, theme);
                }

                buildingGroup.position.set(bx, 0, bz);
                chunkGroup.add(buildingGroup);

                // Lưu danh sách ngôi nhà có cửa tương tác
                if (!isCityChunk && buildingGroup.userData && buildingGroup.userData.door) {
                    activeHouses.push({
                        x: bx,
                        z: bz,
                        doorX: bx,
                        doorZ: bz + 2.9,
                        door: buildingGroup.userData.door,
                        visited: false,
                        chunkKey: `${cx},${cz}`
                    });
                }
            }
        }

        // 4. HỒ NƯỚC VÀ HOA SÚNG (Chỉ ở đất liền xa ray)
        seed++;
        if (cz > 0 && seededRandom(seed) > 0.55) {
            const pondRadius = 6 + seededRandom(seed+1) * 7;
            const pondGeo = new THREE.CircleGeometry(pondRadius, 24);
            const pondMat = new THREE.MeshStandardMaterial({
                color: theme.water, roughness: 0.12, metalness: 0.3, transparent: true, opacity: 0.92,
                emissive: theme.emissiveWater, emissiveIntensity: theme.emissiveIntensity
            });
            const pond = new THREE.Mesh(pondGeo, pondMat);
            pond.rotation.x = -Math.PI / 2;
            const pondX = originX + (seededRandom(seed+2) - 0.5) * (CHUNK_SIZE - 24);
            let pondZ = originZ + (seededRandom(seed+3) - 0.5) * (CHUNK_SIZE - 24);
            if (Math.abs(pondZ) < 14) pondZ += 18;

            pond.position.set(pondX, 0.06, pondZ);
            chunkGroup.add(pond);
        }

        // 5. CÂY CỐI THIÊN NHIÊN
        for (let t = 0; t < 9; t++) {
            seed++;
            const tx = originX + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 14);
            seed++;
            let tz = originZ + (seededRandom(seed) - 0.5) * (CHUNK_SIZE - 14);

            // Không mọc trên đường sắt
            if (Math.abs(tz) < 8) continue;

            const treeGroup = new THREE.Group();
            const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.35, 0.48, 2.8, 8), new THREE.MeshStandardMaterial({ color: theme.treeTrunk, roughness: 0.9 }));
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
                activeHouses = activeHouses.filter(h => h.chunkKey !== key);
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

        // THIẾT KẾ VÒNG MA PHÁP TRẬN HOÀNG KIM (HERO MAGIC AURA) DƯỚI CHÂN VỊT
        const auraGeo = new THREE.RingGeometry(1.6, 2.3, 32);
        const auraCanvas = document.createElement('canvas');
        auraCanvas.width = 256; auraCanvas.height = 256;
        const actx = auraCanvas.getContext('2d');
        actx.strokeStyle = '#fbbf24';
        actx.lineWidth = 10;
        actx.beginPath(); actx.arc(128, 128, 115, 0, Math.PI * 2); actx.stroke();
        actx.strokeStyle = '#38bdf8';
        actx.lineWidth = 5;
        actx.beginPath(); actx.arc(128, 128, 92, 0, Math.PI * 2); actx.stroke();
        for(let k=0; k<6; k++){
            const ang = k * Math.PI / 3;
            actx.fillStyle = '#f59e0b';
            actx.beginPath();
            actx.arc(128 + Math.cos(ang)*103, 128 + Math.sin(ang)*103, 7, 0, Math.PI*2);
            actx.fill();
        }
        const auraTex = new THREE.CanvasTexture(auraCanvas);
        const auraMat = new THREE.MeshBasicMaterial({
            map: auraTex,
            transparent: true,
            opacity: 0.85,
            side: THREE.DoubleSide
        });
        heroAuraRing = new THREE.Mesh(auraGeo, auraMat);
        heroAuraRing.rotation.x = -Math.PI / 2;
        heroAuraRing.position.y = 0.08;
        duckGroup.add(heroAuraRing);

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

    // -------------------------------------------------------------
    // ĐỒ HỌA CHIẾN LONG BÁ: MINI-MAP RADAR & FLOATING SCORE POPUP
    // -------------------------------------------------------------
    function showFloatingScorePopup(text, worldPos) {
        const container = document.getElementById('damage-container');
        if (!container || !camera) return;

        const p = worldPos.clone();
        p.project(camera);

        const screenX = (p.x * 0.5 + 0.5) * window.innerWidth;
        const screenY = (-(p.y * 0.5) + 0.5) * window.innerHeight;

        const el = document.createElement('div');
        el.className = 'damage-popup';
        el.innerText = text;
        el.style.left = `${screenX + (Math.random() - 0.5) * 20}px`;
        el.style.top = `${screenY + (Math.random() - 0.5) * 15}px`;
        container.appendChild(el);

        setTimeout(() => {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 900);
    }

    // Mini-map Radar Canvas Drawing (Quét 360 độ phong cách Chiến Long Bá)
    function drawRadarMiniMap() {
        const rCanvas = document.getElementById('radar-canvas');
        if (!rCanvas || !duckGroup) return;

        const ctx = rCanvas.getContext('2d');
        const w = rCanvas.width;
        const h = rCanvas.height;
        const cx = w / 2;
        const cy = h / 2;
        const radius = w / 2 - 4;

        ctx.clearRect(0, 0, w, h);

        // Nền radar Dark Cyber
        ctx.fillStyle = '#030712';
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.fill();

        // Viền radar kim hoàng Chiến Long Bá
        ctx.strokeStyle = '#f59e0b';
        ctx.lineWidth = 2.5;
        ctx.stroke();

        // Vòng tròn cự ly
        ctx.strokeStyle = 'rgba(56, 189, 248, 0.25)';
        ctx.lineWidth = 1;
        [0.35, 0.7].forEach(ratio => {
            ctx.beginPath();
            ctx.arc(cx, cy, radius * ratio, 0, Math.PI * 2);
            ctx.stroke();
        });

        // Đường chữ thập radar
        ctx.beginPath();
        ctx.moveTo(cx, 4); ctx.lineTo(cx, h - 4);
        ctx.moveTo(4, cy); ctx.lineTo(w - 4, cy);
        ctx.stroke();

        const radarScale = 0.45; // 1 unit in-game = 0.45 px on radar

        // Vẽ các trạm tàu hỏa (chấm cam)
        TRAIN_STATIONS.forEach(st => {
            const dx = (st.x - duckGroup.position.x) * radarScale;
            const dz = (0 - duckGroup.position.z) * radarScale;
            if (Math.hypot(dx, dz) < radius - 3) {
                ctx.fillStyle = '#f97316';
                ctx.beginPath();
                ctx.arc(cx + dx, cy + dz, 3, 0, Math.PI * 2);
                ctx.fill();
            }
        });

        // Vẽ các quả thức ăn xung quanh (chấm xanh ngọc phát sáng)
        ctx.fillStyle = '#22c55e';
        foods.forEach(f => {
            const dx = (f.position.x - duckGroup.position.x) * radarScale;
            const dz = (f.position.z - duckGroup.position.z) * radarScale;
            if (Math.hypot(dx, dz) < radius - 3) {
                ctx.beginPath();
                ctx.arc(cx + dx, cy + dz, 2, 0, Math.PI * 2);
                ctx.fill();
            }
        });

        // Vẽ Người chơi (Tâm radar: Chấm vàng kim rực rỡ)
        ctx.fillStyle = '#fbbf24';
        ctx.beginPath();
        ctx.arc(cx, cy, 4, 0, Math.PI * 2);
        ctx.fill();

        // Cập nhật text toạ độ
        const coordsEl = document.getElementById('radar-coords');
        if (coordsEl) {
            coordsEl.innerText = `[${Math.round(duckGroup.position.x)}, ${Math.round(duckGroup.position.z)}]`;
        }
    }

    // Cập nhật hiệu ứng đồ họa Chiến Long Bá trong animation loop
    function updateVisualEffects(time) {
        // 1. Xoay Vòng Ma Pháp Trận Hoàng Kim dưới chân vịt
        if (heroAuraRing) {
            heroAuraRing.rotation.z += 0.02;
        }

        // 2. Vẽ Radar quét 360 độ
        drawRadarMiniMap();
    }

    // -------------------------------------------------------------
    // HỆ THỐNG TƯƠNG TÁC TÀU HỎA & NGÔI NHÀ 🏡🚂
    // -------------------------------------------------------------
    function playKnockDoorSound() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            [0, 0.12, 0.24].forEach(t => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(260, ctx.currentTime + t);
                gain.gain.setValueAtTime(0.35, ctx.currentTime + t);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + t + 0.08);
                osc.connect(gain); gain.connect(ctx.destination);
                osc.start(ctx.currentTime + t);
                osc.stop(ctx.currentTime + t + 0.08);
            });
            setTimeout(() => {
                try {
                    const ctx2 = new (window.AudioContext || window.webkitAudioContext)();
                    [659, 523].forEach((freq, idx) => {
                        const osc = ctx2.createOscillator();
                        const gain = ctx2.createGain();
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(freq, ctx2.currentTime + idx * 0.22);
                        gain.gain.setValueAtTime(0.25, ctx2.currentTime + idx * 0.22);
                        gain.gain.exponentialRampToValueAtTime(0.01, ctx2.currentTime + idx * 0.22 + 0.45);
                        osc.connect(gain); gain.connect(ctx2.destination);
                        osc.start(ctx2.currentTime + idx * 0.22);
                        osc.stop(ctx2.currentTime + idx * 0.22 + 0.5);
                    });
                }catch(e){}
            }, 360);
        }catch(e){}
    }

    function triggerCurrentInteraction() {
        if (currentPromptAction) {
            currentPromptAction();
        }
    }

    // Tương tác 1: Lên tàu đi nhờ / Bước xuống tàu
    function toggleRideTrain() {
        if (!duckGroup || !globalSteamTrain) return;

        isOnTrain = !isOnTrain;
        if (isOnTrain) {
            duckGroup.position.x = globalSteamTrain.position.x - 7.5;
            duckGroup.position.z = globalSteamTrain.position.z;
            duckGroup.position.y = 2.4;
            playTrainWhistleSound();
            showFloatingScorePopup('🚂 LÊN TÀU DU NGOẠN! +50 ĐIỂM', duckGroup.position);
            score += 50;
            document.getElementById('score-val').innerText = score;
        } else {
            duckGroup.position.z += 4.2;
            duckGroup.position.y = 0;
            playQuackSound();
            showFloatingScorePopup('🚶 ĐÃ BƯỚC XUỐNG TÀU AN TOÀN!', duckGroup.position);
        }
    }

    // Tương tác 2: Kéo còi tàu hơi nước
    function blowTrainHorn() {
        if (!globalSteamTrain) return;
        playTrainWhistleSound();
        spawnTrainSmoke(globalSteamTrain.position.x + 3.2, 4.8, globalSteamTrain.position.z, 0.12, 1.2);
        showFloatingScorePopup('🔔 TU TUU! +30 ĐIỂM CÒI TÀU', globalSteamTrain.position);
        score += 30;
        document.getElementById('score-val').innerText = score;
    }

    // Tương tác 3: Gõ cửa thăm nhà
    function knockHouseDoor(house) {
        if (!house || !house.door) return;

        playKnockDoorSound();

        // Mở cánh cửa 90 độ
        house.door.rotation.y = -Math.PI / 2.2;
        setTimeout(() => {
            if (house.door) house.door.rotation.y = 0;
        }, 3600);

        if (!house.visited) {
            house.visited = true;
            const gifts = [
                { text: '🎁 CHỦ NHÀ TẶNG BÁNH MÌ NÓNG! +80 ĐIỂM', pts: 80 },
                { text: '🍎 CHỦ NHÀ TẶNG GIỎ TÁO VÀNG! +100 ĐIỂM', pts: 100 },
                { text: '🍯 BẠN ĐƯỢC TẶNG HŨ MẬT ONG! +120 ĐIỂM', pts: 120 },
                { text: '⭐ NHẬN ĐƯỢC HUY HIỆU THÂN THIỆN! +150 ĐIỂM', pts: 150 }
            ];
            const g = gifts[Math.floor(Math.random() * gifts.length)];
            score += g.pts;
            document.getElementById('score-val').innerText = score;
            showFloatingScorePopup(g.text, duckGroup.position);
        } else {
            showFloatingScorePopup('🏡 CHỦ NHÀ: CHÚC BẠN CHUYẾN ĐI VUI VẺ!', duckGroup.position);
        }
    }

    // Kiểm tra các tương tác xung quanh người chơi
    function checkInteractionsAroundPlayer() {
        const promptEl = document.getElementById('interaction-prompt');
        const iconEl = document.getElementById('prompt-icon');
        const textEl = document.getElementById('prompt-text');
        const btnEl = document.getElementById('btn-prompt-act');
        if (!promptEl || !duckGroup) return;

        // 1. Kiểm tra tương tác với Tàu hoả
        if (globalSteamTrain) {
            const distTrain = Math.hypot(duckGroup.position.x - globalSteamTrain.position.x, duckGroup.position.z - globalSteamTrain.position.z);
            if (isOnTrain) {
                promptEl.style.display = 'flex';
                iconEl.innerText = '🚶';
                textEl.innerText = '[E] Bước Xuống Tàu';
                btnEl.innerText = 'Xuống Tàu [E]';
                currentPromptAction = toggleRideTrain;
                return;
            } else if (distTrain < 7.2) {
                const distHead = Math.hypot(duckGroup.position.x - (globalSteamTrain.position.x + 3.5), duckGroup.position.z - globalSteamTrain.position.z);
                promptEl.style.display = 'flex';
                if (distHead < 4.2) {
                    iconEl.innerText = '🔔';
                    textEl.innerText = '[F] Kéo Còi Tàu | [E] Lên Tàu';
                    btnEl.innerText = 'Kéo Còi [F]';
                    currentPromptAction = blowTrainHorn;
                } else {
                    iconEl.innerText = '🚂';
                    textEl.innerText = '[E] Lên Tàu Du Ngoạn';
                    btnEl.innerText = 'Lên Tàu [E]';
                    currentPromptAction = toggleRideTrain;
                }
                return;
            }
        }

        // 2. Kiểm tra tương tác với Ngôi Nhà
        let nearestHouse = null;
        let minDist = Infinity;
        activeHouses.forEach(h => {
            const d = Math.hypot(duckGroup.position.x - h.doorX, duckGroup.position.z - h.doorZ);
            if (d < minDist) {
                minDist = d;
                nearestHouse = h;
            }
        });

        if (nearestHouse && minDist < 4.8) {
            promptEl.style.display = 'flex';
            iconEl.innerText = '🏡';
            textEl.innerText = nearestHouse.visited ? '[E] Chào Chủ Nhà' : '[E] Gõ Cửa Thăm Nhà';
            btnEl.innerText = 'Gõ Cửa [E]';
            currentPromptAction = () => knockHouseDoor(nearestHouse);
            return;
        }

        // Không có đối tượng nào gần: Ẩn thanh prompt
        promptEl.style.display = 'none';
        currentPromptAction = null;
    }

    // MAIN ANIMATION LOOP (ĐÃ BỎ HOÀN TOÀN RUNG MÀN HÌNH - CAMERA MƯỢT MÀ 100%)
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

            // NẾU ĐANG TRÊN TÀU: VỊT TỰ ĐỘNG CHẠY THEO TOA TÀU MƯỢT MÀ
            if (isOnTrain && globalSteamTrain) {
                duckGroup.position.x = globalSteamTrain.position.x - 7.5;
                duckGroup.position.z = globalSteamTrain.position.z;
                duckGroup.position.y = 2.4;
                duckWingL.rotation.z = Math.sin(time * 3) * 0.15;
                duckWingR.rotation.z = -Math.sin(time * 3) * 0.15;
            } else if (dx !== 0 || dz !== 0) {
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
                    // 3rd Person: Di chuyển theo hướng nhìn của camera hiện tại
                    const forwardX = -Math.sin(orbitYaw);
                    const forwardZ = -Math.cos(orbitYaw);
                    const rightX = Math.cos(orbitYaw);
                    const rightZ = -Math.sin(orbitYaw);

                    const moveX = (forwardX * (-dz) + rightX * dx);
                    const moveZ = (forwardZ * (-dz) + rightZ * dx);

                    const moveLen = Math.hypot(moveX, moveZ);
                    if (moveLen > 0) {
                        const normX = moveX / moveLen;
                        const normZ = moveZ / moveLen;
                        duckGroup.position.x += normX * speed;
                        duckGroup.position.z += normZ * speed;

                        const targetAngle = Math.atan2(normX, normZ);
                        let diff = targetAngle - duckGroup.rotation.y;
                        while (diff < -Math.PI) diff += Math.PI * 2;
                        while (diff > Math.PI) diff -= Math.PI * 2;
                        duckGroup.rotation.y += diff * 0.25;
                    }
                }

                // BỎ RUNG MÀN HÌNH: Đặt vị trí Y phẳng cố định, không nhấp nhô gây rung giật camera!
                duckGroup.position.y = 0;
                duckWingL.rotation.z = Math.sin(time * 12) * 0.35;
                duckWingR.rotation.z = -Math.sin(time * 12) * 0.35;
            } else {
                duckGroup.position.y = 0;
                duckWingL.rotation.z = 0; duckWingR.rotation.z = 0;
            }

            // CAMERA CONTROLLER: FIRST PERSON VS THIRD PERSON (ỔN ĐỊNH HOÀN TOÀN, KHÔNG RUNG GIẬT)
            if (isFirstPerson) {
                const headPosY = duckGroup.position.y + 2.1;
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
                // 3rd Person Orbit Camera quanh con Vịt
                const rPlane = Math.cos(orbitPitch) * orbitDistance;
                const targetCamX = duckGroup.position.x + Math.sin(orbitYaw) * rPlane;
                const targetCamY = (isOnTrain ? 3.0 : 1.2) + Math.sin(orbitPitch) * orbitDistance;
                const targetCamZ = duckGroup.position.z + Math.cos(orbitYaw) * rPlane;

                camera.position.x = THREE.MathUtils.lerp(camera.position.x, targetCamX, 0.08);
                camera.position.y = THREE.MathUtils.lerp(camera.position.y, targetCamY, 0.08);
                camera.position.z = THREE.MathUtils.lerp(camera.position.z, targetCamZ, 0.08);
                camera.lookAt(duckGroup.position.x, (isOnTrain ? 2.6 : 1.2), duckGroup.position.z);
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

                    // Hiệu ứng nảy số điểm màu vàng kim phong cách Chiến Long Bá
                    showFloatingScorePopup(`+${food.userData.points} ĐIỂM`, food.position);

                    scene.remove(food);
                    foods.splice(i, 1);
                    maintainFruitsAroundPlayer(duckGroup.position.x, duckGroup.position.z);
                }
            }

            // CẬP NHẬT TÀU HỎA VÀ CÁC TRẠM GA (Chu kỳ đỗ 5 phút, còi tàu, khói hơi nước)
            updateTrainAndStations(time);

            // CẬP NHẬT HIỆU ỨNG ĐỒ HỌA CHIẾN LONG BÁ (Aura ma pháp trận, Radar quét 360 độ)
            updateVisualEffects(time);

            // KIỂM TRA TƯƠNG TÁC VỚI TÀU HỎA VÀ CÁC NGÔI NHÀ
            checkInteractionsAroundPlayer();

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

    // -------------------------------------------------------------
    // TRAIN SCHEDULE CONTROLLER (Dừng tại trạm 5 phút, khói, còi)
    // -------------------------------------------------------------
    function updateTrainAndStations(time) {
        if (!globalSteamTrain) return;

        const currentStation = getStationByIndex(trainState.currentStationIdx);
        const nextStation = getStationByIndex(trainState.currentStationIdx + 1);

        const now = Date.now();
        if (trainState.status === 'WAITING') {
            if (now - trainState.lastTimerTick >= 1000) {
                trainState.lastTimerTick = now;
                if (trainState.remainingSeconds > 0) {
                    trainState.remainingSeconds--;
                }
            }

            // Cập nhật text trên HUD
            const min = Math.floor(trainState.remainingSeconds / 60);
            const sec = trainState.remainingSeconds % 60;
            const timeStr = `${min < 10 ? '0' : ''}${min}:${sec < 10 ? '0' : ''}${sec}`;

            const countdownEl = document.getElementById('train-countdown');
            if (countdownEl) countdownEl.innerText = timeStr;

            const stText = document.getElementById('train-status-text');
            if (stText) {
                stText.innerHTML = `Tàu đang đỗ tại <strong>${currentStation.name}</strong>: Khởi hành sau <strong id="train-countdown" style="color:#fbbf24;font-size:1rem;">${timeStr}</strong>`;
            }

            const btnAct = document.getElementById('btn-train-action');
            if (btnAct) btnAct.style.display = 'inline-flex';

            // Cập nhật bảng đếm ngược 3D trên các trạm ga
            stationBoards.forEach(b => {
                b.ctx.fillStyle = '#020617';
                b.ctx.fillRect(0, 0, 512, 128);
                b.ctx.strokeStyle = '#f59e0b';
                b.ctx.lineWidth = 6;
                b.ctx.strokeRect(4, 4, 504, 120);

                b.ctx.fillStyle = '#ef4444';
                b.ctx.font = 'bold 24px monospace';
                b.ctx.fillText('● CHUYẾN TÀU KẾ TIẾP (DEPARTURE)', 25, 42);

                b.ctx.fillStyle = '#fde047';
                b.ctx.font = 'bold 44px monospace';
                b.ctx.fillText(`KHỞI HÀNH: ${timeStr}`, 25, 96);

                b.texture.needsUpdate = true;
            });

            // Khi hết 5 phút (300 giây): Tự động khởi hành sang trạm kế tiếp
            if (trainState.remainingSeconds <= 0) {
                departTrainNow();
            }

            // Nhả khói nhẹ khi đỗ ở ga
            if (Math.random() < 0.12) {
                spawnTrainSmoke(globalSteamTrain.position.x + 3.2, 4.2, globalSteamTrain.position.z, 0.04, 0.4);
            }
        } else if (trainState.status === 'RUNNING') {
            // Tàu đang di chuyển dọc đường ray đến trạm kế tiếp
            const targetX = nextStation.x;
            const distRemaining = targetX - globalSteamTrain.position.x;

            // Tăng tốc dần đến maxSpeed, hoặc hãm tốc khi cách trạm < 45m
            if (distRemaining > 45) {
                trainState.speed = Math.min(trainState.maxSpeed, trainState.speed + 0.005);
            } else {
                trainState.speed = Math.max(0.04, distRemaining * 0.012);
            }

            globalSteamTrain.position.x += trainState.speed;

            // Quay các bánh xe đầu máy hơi nước
            if (globalSteamTrain.userData && globalSteamTrain.userData.wheels) {
                globalSteamTrain.userData.wheels.forEach(w => {
                    w.rotation.z -= trainState.speed * 1.5;
                });
            }

            // Nhả các cụm khói hơi nước cuộn bay lên trời
            if (Math.random() < 0.45) {
                spawnTrainSmoke(
                    globalSteamTrain.position.x + 3.2,
                    4.2,
                    globalSteamTrain.position.z,
                    0.09,
                    0.8 + Math.random() * 0.4
                );
            }

            // Khi đã tới trạm kế tiếp: Dừng hẳn tại trạm
            if (globalSteamTrain.position.x >= targetX) {
                globalSteamTrain.position.x = targetX;
                trainState.speed = 0;
                trainState.status = 'WAITING';
                trainState.currentStationIdx++;
                if (trainState.currentStationIdx >= TRAIN_STATIONS.length - 1) {
                    trainState.currentStationIdx = 0;
                    globalSteamTrain.position.x = TRAIN_STATIONS[0].x;
                }
                trainState.remainingSeconds = 300; // 5 phút tiếp theo cho trạm mới!
                playQuackSound();
            }
        }

        // Cập nhật các hạt khói hơi nước bay lên trời
        for (let si = trainSmokeList.length - 1; si >= 0; si--) {
            const sm = trainSmokeList[si];
            sm.mesh.position.y += sm.vy;
            sm.mesh.position.x -= sm.vx;
            sm.scale += 0.035;
            sm.mesh.scale.set(sm.scale, sm.scale, sm.scale);
            sm.life -= 0.018;
            sm.mesh.material.opacity = sm.life * 0.6;

            if (sm.life <= 0) {
                scene.remove(sm.mesh);
                trainSmokeList.splice(si, 1);
            }
        }
    }

    function spawnTrainSmoke(sx, sy, sz, vy = 0.08, initScale = 0.5) {
        if (trainSmokeList.length > 60) return;
        const smokeGeo = new THREE.SphereGeometry(initScale, 7, 7);
        const smokeMat = new THREE.MeshStandardMaterial({
            color: 0xe2e8f0,
            transparent: true,
            opacity: 0.6,
            roughness: 0.95
        });
        const smokeMesh = new THREE.Mesh(smokeGeo, smokeMat);
        smokeMesh.position.set(sx + (Math.random() - 0.5) * 0.3, sy, sz + (Math.random() - 0.5) * 0.3);
        scene.add(smokeMesh);

        trainSmokeList.push({
            mesh: smokeMesh,
            vy: vy,
            vx: 0.04 + Math.random() * 0.04,
            scale: initScale,
            life: 1.0
        });
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
