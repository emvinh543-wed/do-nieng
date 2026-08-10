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
    <title>Vịt Con 3D Háu Ăn - GlowDrinks Mini Game World</title>
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
            gap: 15px;
        }

        .hud-card {
            background: rgba(15, 23, 42, 0.85);
            border: 2px solid #f5a623;
            padding: 8px 20px;
            border-radius: 20px;
            backdrop-filter: blur(12px);
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        }

        .hud-card label { font-size: 0.72rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; display: block; }
        .hud-card span { font-size: 1.5rem; font-weight: 800; color: #fbbf24; }

        /* Leaderboard Drawer Button */
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

        /* Modals & Leaderboard Drawer */
        .game-modal {
            position: absolute;
            inset: 0;
            background: rgba(11, 19, 41, 0.88);
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
            width: 500px;
            max-width: 92%;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0,0,0,0.7);
        }

        .modal-card img { width: 95px; height: 95px; border-radius: 50%; border: 3px solid #f5a623; margin-bottom: 12px; }
        .modal-card h2 { font-size: 1.9rem; font-weight: 800; color: #f5a623; margin-bottom: 8px; }
        .modal-card p { font-size: 0.92rem; color: #94a3b8; margin-bottom: 20px; line-height: 1.5; }

        .btn-play {
            background: linear-gradient(135deg, #f5a623, #d97706);
            color: white;
            border: none;
            padding: 13px 34px;
            border-radius: 30px;
            font-size: 1.05rem;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(245, 166, 35, 0.4);
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
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

        .lb-table th {
            background: #334155;
            color: #f5a623;
            padding: 10px;
            text-align: left;
            border-radius: 8px;
        }

        .lb-table td {
            padding: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            text-align: left;
        }

        .lb-table tr.highlight {
            background: rgba(245, 166, 35, 0.2);
            font-weight: 800;
            color: #fde047;
        }

        .rank-badge {
            width: 24px; height: 24px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.78rem;
        }
        .rank-1 { background: #eab308; color: #000; }
        .rank-2 { background: #94a3b8; color: #000; }
        .rank-3 { background: #b45309; color: #fff; }

        /* Virtual Joystick for Mobile */
        .joystick-container {
            position: absolute;
            bottom: 25px;
            right: 25px;
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            pointer-events: auto;
            display: none;
            touch-action: none;
        }

        .joystick-stick {
            width: 50px;
            height: 50px;
            background: #f5a623;
            border-radius: 50%;
            position: absolute;
            top: 35px; left: 35px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
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
                <button class="btn-leaderboard-toggle" onclick="toggleLeaderboardModal()">
                    🏆 BẢNG XẾP HẠNG
                </button>
            </div>
        </div>

        <div class="controls-hint">
            <h4>🎮 Hướng Dẫn:</h4>
            <div class="key-row">
                Dùng <span class="key">W</span><span class="key">A</span><span class="key">S</span><span class="key">D</span> hoặc 
                <span class="key">↑</span><span class="key">←</span><span class="key">↓</span><span class="key">→</span> điều khiển Vịt 3D qua cầu ăn 🍎🍊🍉🧋
            </div>
        </div>

        <div class="joystick-container" id="joystick">
            <div class="joystick-stick" id="stick"></div>
        </div>
    </div>

    <!-- Start Modal -->
    <div class="game-modal" id="start-modal">
        <div class="modal-card">
            <img src="/anh/duck_3d.png" alt="Duck 3D" onerror="this.src='https://ui-avatars.com/api/?name=Duck'">
            <h2>Vịt Con 3D Háu Ăn! 🐥</h2>
            <p>Điều khiển chú Vịt Con 3D băng qua **Cầu Gỗ & Dòng Sông 3D** rực rỡ để thu gom thật nhiều **Táo, Cam, Dưa Hấu, Dâu Tây & Trà Sữa**!<br><br>Đạt điểm cao để ghi tên vào **Bảng Xếp Hạng TOP 10** & nhận Voucher 20%!</p>
            <button class="btn-play" onclick="startGame()">🚀 BẮT ĐẦU CHƠI NGAY</button>
        </div>
    </div>

    <!-- Leaderboard Modal -->
    <div class="game-modal" id="lb-modal" style="display:none;">
        <div class="modal-card" style="width:550px;">
            <h2 style="color:#38bdf8;">🏆 BẢNG XẾP HẠNG TOP cao thủ</h2>
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
        <div class="modal-card">
            <img src="/anh/duck_3d.png" alt="Duck 3D">
            <h2 style="color:#22c55e;">🎉 HOÀN THÀNH CHUYẾN ĐI!</h2>
            <p>Tên hiển thị: <strong><?php echo $logged_in_user ? htmlspecialchars($logged_in_user['fullname']) : 'Khách Vô Danh'; ?></strong></p>
            <div style="font-size:2.5rem;font-weight:800;color:#fbbf24;margin-bottom:10px;" id="final-score">0 ĐIỂM</div>
            
            <div id="voucher-result" class="voucher-box" style="display:none;">
                🎟️ Mã Giảm Giá 20%: <strong>GLOWDUCK20</strong>
            </div>

            <div style="display:flex;gap:12px;justify-content:center;margin-top:20px;">
                <button class="btn-play" onclick="startGame()">🔄 CHƠI LẠI</button>
                <button class="btn-play" onclick="toggleLeaderboardModal()" style="background:#0284c7;">🏆 XEM BẢNG HẠNG</button>
            </div>
        </div>
    </div>

    <!-- 3D EXPANDED MAP & GAME ENGINE SCRIPT -->
    <script>
    const loggedInPlayerName = "<?php echo $logged_in_user ? addslashes($logged_in_user['fullname']) : 'Khách Vô Danh 🐥'; ?>";

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

    // 3D Scene Setup
    let scene, camera, renderer;
    let duckGroup, duckBody, duckHead, duckBeak, duckWingL, duckWingR;
    let waterMesh;
    let foods = [];
    let score = 0, timeLeft = 60, gameActive = false, timerInterval;

    // Movement Controls
    const keys = { w: false, a: false, s: false, d: false, ArrowUp: false, ArrowLeft: false, ArrowDown: false, ArrowRight: false };
    let moveVector = { x: 0, z: 0 };

    window.addEventListener('keydown', e => { if (keys.hasOwnProperty(e.key) || keys.hasOwnProperty(e.code)) keys[e.key] = true; });
    window.addEventListener('keyup', e => { if (keys.hasOwnProperty(e.key) || keys.hasOwnProperty(e.code)) keys[e.key] = false; });

    function init3D() {
        const canvas = document.getElementById('game-canvas');
        scene = new THREE.Scene();
        scene.background = new THREE.Color(0x0b1329);
        scene.fog = new THREE.FogExp2(0x0b1329, 0.012);

        camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);
        camera.position.set(0, 22, 28);
        camera.lookAt(0, 0, 0);

        renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.shadowMap.enabled = true;

        // Lights
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.75);
        scene.add(ambientLight);

        const dirLight = new THREE.DirectionalLight(0xfff5cc, 1.3);
        dirLight.position.set(30, 45, 30);
        dirLight.castShadow = true;
        dirLight.shadow.mapSize.width = 2048;
        dirLight.shadow.mapSize.height = 2048;
        dirLight.shadow.camera.near = 0.5;
        dirLight.shadow.camera.far = 150;
        dirLight.shadow.camera.left = -60;
        dirLight.shadow.camera.right = 60;
        dirLight.shadow.camera.top = 60;
        dirLight.shadow.camera.bottom = -60;
        scene.add(dirLight);

        // EXPANDED 3D WORLD (North Island, South Island, River, Bridge, Trees, Rocks)
        create3DWorld();

        // Build Procedural 3D Duck Mesh
        create3DDuck();

        // Spawn Initial Beautiful Fruit & Drink Items (20 Items)
        spawnFruitAndDrinkItems(22);

        // Resize Listener
        window.addEventListener('resize', onWindowResize);

        // Start Render Loop
        animate();
    }

    function create3DWorld() {
        // North & South Grass Ground Islands (Large radius 55)
        const groundGeo = new THREE.BoxGeometry(110, 2, 110);
        const groundMat = new THREE.MeshStandardMaterial({ color: 0x16a34a, roughness: 0.7 });
        const ground = new THREE.Mesh(groundGeo, groundMat);
        ground.position.y = -1;
        ground.receiveShadow = true;
        scene.add(ground);

        // Animated 3D River running East-West through middle (z = 0)
        const waterGeo = new THREE.PlaneGeometry(120, 16);
        const waterMat = new THREE.MeshStandardMaterial({ color: 0x0284c7, roughness: 0.1, metalness: 0.6, transparent: true, opacity: 0.85 });
        waterMesh = new THREE.Mesh(waterGeo, waterMat);
        waterMesh.rotation.x = -Math.PI / 2;
        waterMesh.position.set(0, 0.08, 0);
        scene.add(waterMesh);

        // 3D Wooden Bridge across River (spanning north-south at x = 0)
        createWoodenBridge(0, 0);

        // Spawn 3D Trees (30 Trees around island)
        for (let i = 0; i < 35; i++) {
            let tx = (Math.random() - 0.5) * 100;
            let tz = (Math.random() - 0.5) * 100;
            // Avoid middle river area
            if (Math.abs(tz) < 10) tz += (tz > 0 ? 12 : -12);
            create3DTree(tx, tz);
        }

        // Spawn 3D Rocks (25 Boulders around riversides and hills)
        for (let i = 0; i < 25; i++) {
            let rx = (Math.random() - 0.5) * 100;
            let rz = (Math.random() - 0.5) * 100;
            create3DRock(rx, rz);
        }
    }

    function createWoodenBridge(x, z) {
        const bridgeGroup = new THREE.Group();
        
        // Wooden Planks
        const plankMat = new THREE.MeshStandardMaterial({ color: 0x854d0e, roughness: 0.8 });
        for (let p = -8; p <= 8; p += 1.2) {
            const plankGeo = new THREE.BoxGeometry(6, 0.25, 1);
            const plank = new THREE.Mesh(plankGeo, plankMat);
            plank.position.set(0, 0.2, p);
            plank.castShadow = true; plank.receiveShadow = true;
            bridgeGroup.add(plank);
        }

        // Bridge Side Rails
        const railMat = new THREE.MeshStandardMaterial({ color: 0x713f12 });
        const railLGeo = new THREE.BoxGeometry(0.3, 0.4, 17);
        const railL = new THREE.Mesh(railLGeo, railMat); railL.position.set(-2.8, 0.8, 0);
        const railR = new THREE.Mesh(railLGeo, railMat); railR.position.set(2.8, 0.8, 0);
        bridgeGroup.add(railL); bridgeGroup.add(railR);

        // Posts
        for (let postZ = -7.5; postZ <= 7.5; postZ += 5) {
            const postGeo = new THREE.CylinderGeometry(0.18, 0.18, 1.2, 8);
            const post1 = new THREE.Mesh(postGeo, railMat); post1.position.set(-2.8, 0.6, postZ);
            const post2 = new THREE.Mesh(postGeo, railMat); post2.position.set(2.8, 0.6, postZ);
            bridgeGroup.add(post1); bridgeGroup.add(post2);
        }

        bridgeGroup.position.set(x, 0, z);
        scene.add(bridgeGroup);
    }

    function create3DTree(x, z) {
        const treeGroup = new THREE.Group();
        
        // Trunk
        const trunkGeo = new THREE.CylinderGeometry(0.4, 0.6, 3, 8);
        const trunkMat = new THREE.MeshStandardMaterial({ color: 0x78350f, roughness: 0.9 });
        const trunk = new THREE.Mesh(trunkGeo, trunkMat);
        trunk.position.y = 1.5;
        trunk.castShadow = true;
        treeGroup.add(trunk);

        // Foliage (Layered Cones/Spheres)
        const foliageMat = new THREE.MeshStandardMaterial({ color: 0x15803d, roughness: 0.5 });
        const f1Geo = new THREE.ConeGeometry(2.2, 3, 8);
        const f1 = new THREE.Mesh(f1Geo, foliageMat); f1.position.y = 3.5; f1.castShadow = true;
        const f2 = new THREE.Mesh(f1Geo, foliageMat); f2.position.y = 4.8; f2.scale.set(0.8, 0.8, 0.8); f2.castShadow = true;
        treeGroup.add(f1); treeGroup.add(f2);

        treeGroup.position.set(x, 0, z);
        scene.add(treeGroup);
    }

    function create3DRock(x, z) {
        const rockGeo = new THREE.DodecahedronGeometry(0.8 + Math.random()*0.7, 1);
        const rockMat = new THREE.MeshStandardMaterial({ color: 0x64748b, roughness: 0.8 });
        const rock = new THREE.Mesh(rockGeo, rockMat);
        rock.rotation.set(Math.random(), Math.random(), Math.random());
        rock.position.set(x, 0.4, z);
        rock.castShadow = true; rock.receiveShadow = true;
        scene.add(rock);
    }

    function create3DDuck() {
        duckGroup = new THREE.Group();

        // Duck Body (Sphere)
        const bodyGeo = new THREE.SphereGeometry(1.2, 24, 24);
        const duckMat = new THREE.MeshStandardMaterial({ color: 0xffd43b, roughness: 0.3, metalness: 0.1 });
        duckBody = new THREE.Mesh(bodyGeo, duckMat);
        duckBody.position.y = 1.2;
        duckBody.castShadow = true;
        duckGroup.add(duckBody);

        // Duck Head (Sphere)
        const headGeo = new THREE.SphereGeometry(0.85, 20, 20);
        duckHead = new THREE.Mesh(headGeo, duckMat);
        duckHead.position.set(0, 2.2, 0.3);
        duckHead.castShadow = true;
        duckGroup.add(duckHead);

        // Duck Beak
        const beakGeo = new THREE.BoxGeometry(0.5, 0.22, 0.5);
        const beakMat = new THREE.MeshStandardMaterial({ color: 0xf97316, roughness: 0.4 });
        duckBeak = new THREE.Mesh(beakGeo, beakMat);
        duckBeak.position.set(0, 2.1, 1.1);
        duckGroup.add(duckBeak);

        // Duck Eyes
        const eyeGeo = new THREE.SphereGeometry(0.12, 12, 12);
        const eyeMat = new THREE.MeshBasicMaterial({ color: 0x0f172a });
        const eyeL = new THREE.Mesh(eyeGeo, eyeMat); eyeL.position.set(-0.35, 2.4, 0.95);
        const eyeR = new THREE.Mesh(eyeGeo, eyeMat); eyeR.position.set(0.35, 2.4, 0.95);
        duckGroup.add(eyeL); duckGroup.add(eyeR);

        // Duck Bowtie
        const bowGeo = new THREE.ConeGeometry(0.25, 0.4, 4);
        const bowMat = new THREE.MeshStandardMaterial({ color: 0xe11d48 });
        const bowL = new THREE.Mesh(bowGeo, bowMat); bowL.rotation.z = Math.PI / 2; bowL.position.set(-0.25, 1.7, 0.95);
        const bowR = new THREE.Mesh(bowGeo, bowMat); bowR.rotation.z = -Math.PI / 2; bowR.position.set(0.25, 1.7, 0.95);
        duckGroup.add(bowL); duckGroup.add(bowR);

        // Duck Wings
        const wingGeo = new THREE.BoxGeometry(0.2, 0.7, 0.9);
        duckWingL = new THREE.Mesh(wingGeo, duckMat); duckWingL.position.set(-1.2, 1.3, 0);
        duckWingR = new THREE.Mesh(wingGeo, duckMat); duckWingR.position.set(1.2, 1.3, 0);
        duckGroup.add(duckWingL); duckGroup.add(duckWingR);

        duckGroup.position.set(0, 0, 15); // Start on South bank
        scene.add(duckGroup);
    }

    // SPAWN HIGH QUALITY 3D FRUITS (Apples 🍎, Oranges 🍊, Watermelon 🍉, Strawberries 🍓, Bananas 🍌) & Drinks 🧋
    function spawnFruitAndDrinkItems(count) {
        for (let i = 0; i < count; i++) {
            const type = Math.floor(Math.random() * 6);
            let itemGroup = new THREE.Group();
            let points = 15;

            if (type === 0) { // 🍎 Red Apple
                const appleGeo = new THREE.SphereGeometry(0.6, 16, 16);
                const appleMat = new THREE.MeshStandardMaterial({ color: 0xdc2626, roughness: 0.2 });
                const apple = new THREE.Mesh(appleGeo, appleMat); apple.position.y = 0.6; apple.castShadow = true;
                itemGroup.add(apple);

                // Leaf
                const leafGeo = new THREE.BoxGeometry(0.3, 0.05, 0.15);
                const leafMat = new THREE.MeshBasicMaterial({ color: 0x16a34a });
                const leaf = new THREE.Mesh(leafGeo, leafMat); leaf.position.set(0.15, 1.15, 0);
                itemGroup.add(leaf);
                points = 15;
            }
            else if (type === 1) { // 🍊 Orange
                const orangeGeo = new THREE.SphereGeometry(0.55, 16, 16);
                const orangeMat = new THREE.MeshStandardMaterial({ color: 0xf97316, roughness: 0.4 });
                const orange = new THREE.Mesh(orangeGeo, orangeMat); orange.position.y = 0.55; orange.castShadow = true;
                itemGroup.add(orange);
                points = 15;
            }
            else if (type === 2) { // 🍉 Watermelon Slice
                const melonGeo = new THREE.CylinderGeometry(0.7, 0.7, 0.3, 12, 1, false, 0, Math.PI);
                const melonMat = new THREE.MeshStandardMaterial({ color: 0x16a34a, roughness: 0.5 });
                const melon = new THREE.Mesh(melonGeo, melonMat); melon.rotation.z = Math.PI/2; melon.position.y = 0.7; melon.castShadow = true;
                itemGroup.add(melon);

                const fleshGeo = new THREE.CylinderGeometry(0.55, 0.55, 0.31, 12, 1, false, 0, Math.PI);
                const fleshMat = new THREE.MeshStandardMaterial({ color: 0xef4444, roughness: 0.3 });
                const flesh = new THREE.Mesh(fleshGeo, fleshMat); flesh.rotation.z = Math.PI/2; flesh.position.y = 0.7;
                itemGroup.add(flesh);
                points = 25;
            }
            else if (type === 3) { // 🍓 Strawberry
                const strawGeo = new THREE.ConeGeometry(0.5, 0.8, 12);
                const strawMat = new THREE.MeshStandardMaterial({ color: 0xe11d48, roughness: 0.3 });
                const straw = new THREE.Mesh(strawGeo, strawMat); straw.rotation.x = Math.PI; straw.position.y = 0.7; straw.castShadow = true;
                itemGroup.add(straw);
                points = 20;
            }
            else if (type === 4) { // 🍌 Banana
                const bananaGeo = new THREE.TorusGeometry(0.5, 0.14, 8, 16, Math.PI * 0.8);
                const bananaMat = new THREE.MeshStandardMaterial({ color: 0xeab308, roughness: 0.3 });
                const banana = new THREE.Mesh(bananaGeo, bananaMat); banana.position.y = 0.6; banana.castShadow = true;
                itemGroup.add(banana);
                points = 15;
            }
            else { // 🧋 Boba Milk Tea
                const cupGeo = new THREE.CylinderGeometry(0.5, 0.38, 1.2, 16);
                const cupMat = new THREE.MeshStandardMaterial({ color: 0xf5a623, roughness: 0.2, transparent: true, opacity: 0.9 });
                const cup = new THREE.Mesh(cupGeo, cupMat); cup.position.y = 0.6; cup.castShadow = true;
                itemGroup.add(cup);
                points = 20;
            }

            // Random Position across 3D World (Avoid direct river drowning unless on bridge)
            let angle = Math.random() * Math.PI * 2;
            let dist  = 4 + Math.random() * 45;
            let fx = Math.cos(angle) * dist;
            let fz = Math.sin(angle) * dist;

            // Adjust if in river without bridge
            if (Math.abs(fz) < 7 && Math.abs(fx) > 4) {
                fz += (fz > 0 ? 10 : -10);
            }

            itemGroup.position.set(fx, 0, fz);
            itemGroup.userData = { points: points, rotSpeed: 0.02 + Math.random()*0.03, floatOffset: Math.random()*Math.PI*2 };
            scene.add(itemGroup);
            foods.push(itemGroup);
        }
    }

    function animate() {
        requestAnimationFrame(animate);

        const time = Date.now() * 0.003;

        // Animate River Water Movement
        if (waterMesh) {
            waterMesh.position.x = Math.sin(time * 0.5) * 1.5;
        }

        if (gameActive) {
            // Movement logic
            let dx = 0, dz = 0;
            if (keys.w || keys.W || keys.ArrowUp)    dz -= 1;
            if (keys.s || keys.S || keys.ArrowDown)  dz += 1;
            if (keys.a || keys.A || keys.ArrowLeft)  dx -= 1;
            if (keys.d || keys.D || keys.ArrowRight) dx += 1;

            if (moveVector.x !== 0 || moveVector.z !== 0) {
                dx = moveVector.x; dz = moveVector.z;
            }

            if (dx !== 0 || dz !== 0) {
                const speed = 0.28;
                duckGroup.position.x += dx * speed;
                duckGroup.position.z += dz * speed;

                // River Crossing Constraint (Can only cross river if x is near bridge: -4 <= x <= 4)
                if (Math.abs(duckGroup.position.z) < 7.5 && Math.abs(duckGroup.position.x) > 3.8) {
                    // Push back from water
                    duckGroup.position.z += (duckGroup.position.z > 0 ? 0.3 : -0.3);
                }

                // Map Outer Boundary (Radius 48)
                const distFromCenter = Math.sqrt(duckGroup.position.x**2 + duckGroup.position.z**2);
                if (distFromCenter > 48) {
                    duckGroup.position.x = (duckGroup.position.x / distFromCenter) * 48;
                    duckGroup.position.z = (duckGroup.position.z / distFromCenter) * 48;
                }

                // Face direction
                const targetAngle = Math.atan2(dx, dz);
                duckGroup.rotation.y = targetAngle;

                // Waddling animation
                duckGroup.position.y = Math.abs(Math.sin(time * 14)) * 0.4;
                duckWingL.rotation.z = Math.sin(time * 16) * 0.45;
                duckWingR.rotation.z = -Math.sin(time * 16) * 0.45;
            } else {
                duckGroup.position.y = Math.sin(time * 3) * 0.08;
                duckWingL.rotation.z = 0;
                duckWingR.rotation.z = 0;
            }

            // Smooth Camera Follow
            camera.position.x = duckGroup.position.x * 0.45;
            camera.position.z = duckGroup.position.z * 0.45 + 26;
            camera.lookAt(duckGroup.position.x * 0.6, 1, duckGroup.position.z * 0.6);

            // Food Animation & Collision
            for (let i = foods.length - 1; i >= 0; i--) {
                const food = foods[i];
                food.rotation.y += food.userData.rotSpeed;
                food.position.y = 0.2 + Math.sin(time * 4 + food.userData.floatOffset) * 0.25;

                // Collision Check
                const dist = duckGroup.position.distanceTo(food.position);
                if (dist < 2.0) {
                    playEatSound();
                    score += food.userData.points;
                    document.getElementById('score-val').innerText = score;

                    scene.remove(food);
                    foods.splice(i, 1);
                    spawnFruitAndDrinkItems(1);
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

    function startGame() {
        document.getElementById('start-modal').style.display = 'none';
        document.getElementById('end-modal').style.display = 'none';
        document.getElementById('lb-modal').style.display = 'none';
        
        score = 0;
        timeLeft = 60;
        gameActive = true;
        
        document.getElementById('score-val').innerText = '0';
        document.getElementById('time-val').innerText = '60s';

        // Reset Duck Position (South bank)
        if (duckGroup) duckGroup.position.set(0, 0, 15);

        playQuackSound();

        // Start Countdown Timer
        if (timerInterval) clearInterval(timerInterval);
        timerInterval = setInterval(function() {
            if (!gameActive) return;
            timeLeft--;
            document.getElementById('time-val').innerText = timeLeft + 's';
            
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

        // Submit Score via AJAX to Database Leaderboard
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

    // Initialize 3D Engine on page load
    window.addEventListener('DOMContentLoaded', init3D);
    </script>
</body>
</html>
