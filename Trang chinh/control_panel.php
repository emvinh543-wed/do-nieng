<?php
/**
 * GLOWDRINKS - FRONTEND CONTROL CENTER & DASHBOARD
 * Bảng điều khiển quản trị và tự động chẩn đoán hệ thống GlowDrinks
 */

// Chỉ cho phép truy cập từ Localhost để bảo vệ an toàn
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
$allowedIps = ['127.0.0.1', '::1', 'localhost'];
if (!in_array($clientIp, $allowedIps)) {
    http_response_code(403);
    die('<div style="font-family:sans-serif;padding:50px;text-align:center;"><h2>403 Forbidden</h2><p>Bảng điều khiển này chỉ được phép truy cập từ máy chủ Localhost.</p></div>');
}

$rootDir = dirname(__DIR__);
$toolsScript = $rootDir . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'system_diagnose.php';
$configFile = __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
$currentPort = (int)($_SERVER['SERVER_PORT'] ?? 8000);

// Xử lý API AJAX
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['api'];

    // Lấy đường dẫn thực thi của PHP
    $phpBin = defined('PHP_BINARY') && file_exists(PHP_BINARY) ? PHP_BINARY : 'php';

    $cmd = '';
    switch ($action) {
        case 'diagnose':
            $cmd = "\"$phpBin\" \"$toolsScript\" check $currentPort";
            break;
        case 'reset_db':
            $cmd = "\"$phpBin\" \"$toolsScript\" reset-db";
            break;
        case 'free_port':
            $cmd = "\"$phpBin\" \"$toolsScript\" free-port $currentPort";
            break;
        case 'lint':
            $cmd = "\"$phpBin\" \"$toolsScript\" lint";
            break;
        default:
            echo json_encode(['success' => false, 'output' => 'Lệnh API không hợp lệ!'], JSON_UNESCAPED_UNICODE);
            exit;
    }

    $output = [];
    $returnVar = 0;
    exec("$cmd 2>&1", $output, $returnVar);

    echo json_encode([
        'success'   => ($returnVar === 0),
        'code'      => $returnVar,
        'action'    => $action,
        'output'    => implode("\n", $output),
        'timestamp' => date('H:i:s d/m/Y')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Kiểm tra PHP
$phpVersion = PHP_VERSION;
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'session', 'json'];
$extStatus = [];
$missingExts = [];
foreach ($requiredExtensions as $ext) {
    $isLoaded = extension_loaded($ext);
    $extStatus[$ext] = $isLoaded;
    if (!$isLoaded) $missingExts[] = $ext;
}

// 2. Kiểm tra Database
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'drink_shop';
if (file_exists($configFile)) {
    $cfgContent = file_get_contents($configFile);
    if (preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $cfgContent, $m)) $dbHost = $m[1];
    if (preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/i", $cfgContent, $m)) $dbUser = $m[1];
    if (preg_match("/define\s*\(\s*['\"]DB_PASS['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/i", $cfgContent, $m)) $dbPass = $m[1];
    if (preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $cfgContent, $m)) $dbName = $m[1];
}

$dbConnected = false;
$dbError = '';
$tableCount = 0;
$productCount = 0;
$userCount = 0;

try {
    $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 2
    ]);
    // Kiểm tra database có tồn tại không
    $stmt = $pdo->query("SHOW DATABASES LIKE '$dbName'");
    if ($stmt->fetch()) {
        $pdoDb = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $tables = $pdoDb->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $tableCount = count($tables);

        if (in_array('products', $tables)) {
            $productCount = (int)$pdoDb->query("SELECT COUNT(*) FROM products")->fetchColumn();
        }
        if (in_array('users', $tables)) {
            $userCount = (int)$pdoDb->query("SELECT COUNT(*) FROM users")->fetchColumn();
        }
        $dbConnected = true;
    } else {
        $dbError = "Database '$dbName' chưa được tạo.";
    }
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GlowDrinks - Frontend Control Center</title>
    <style>
        :root {
            --bg-body: #0b0f19;
            --bg-card: rgba(23, 32, 53, 0.75);
            --bg-card-hover: rgba(30, 41, 69, 0.9);
            --border-card: rgba(99, 102, 241, 0.18);
            --border-card-hover: rgba(99, 102, 241, 0.45);
            --primary: #6366f1;
            --primary-light: #818cf8;
            --cyan: #06b6d4;
            --emerald: #10b981;
            --amber: #f59e0b;
            --rose: #f43f5e;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --font-mono: "Fira Code", Consolas, "Courier New", monospace;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-body);
            background-image: 
                radial-gradient(at 10% 20%, rgba(99, 102, 241, 0.12) 0px, transparent 50%),
                radial-gradient(at 90% 10%, rgba(6, 182, 212, 0.1) 0px, transparent 50%),
                radial-gradient(at 50% 90%, rgba(16, 185, 129, 0.08) 0px, transparent 50%);
            background-attachment: fixed;
            color: var(--text-main);
            font-family: var(--font-family);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 28px 20px;
            width: 100%;
        }

        /* Top Header */
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .brand-box {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary), var(--cyan));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.35);
            font-size: 24px;
        }

        .brand-title h1 {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            background: linear-gradient(to right, #ffffff, #c7d2fe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-title p {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-primary {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: #ffffff;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #4338ca, #4f46e5);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
        }

        .btn-outline {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(148, 163, 184, 0.25);
            color: var(--text-main);
        }

        .btn-outline:hover {
            background: rgba(51, 65, 85, 0.8);
            border-color: rgba(148, 163, 184, 0.4);
            transform: translateY(-2px);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-card);
            border-radius: 16px;
            padding: 18px 20px;
            position: relative;
            overflow: hidden;
            transition: all 0.25s ease;
        }

        .stat-card:hover {
            border-color: var(--border-card-hover);
            transform: translateY(-3px);
            background: var(--bg-card-hover);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .stat-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .stat-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-danger {
            background: rgba(244, 63, 94, 0.15);
            color: #fb7185;
            border: 1px solid rgba(244, 63, 94, 0.3);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
        }

        .stat-desc {
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        /* Section Title */
        .section-heading {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            font-size: 1.15rem;
            font-weight: 700;
            color: #ffffff;
        }

        .section-heading span {
            color: var(--cyan);
        }

        /* Actions Grid */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .action-card {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-card);
            border-radius: 16px;
            padding: 22px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.25s ease;
            position: relative;
        }

        .action-card:hover {
            border-color: var(--primary-light);
            background: var(--bg-card-hover);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .action-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 14px;
        }

        .icon-blue { background: rgba(59, 130, 246, 0.18); color: #60a5fa; }
        .icon-cyan { background: rgba(6, 182, 212, 0.18); color: #22d3ee; }
        .icon-green { background: rgba(16, 185, 129, 0.18); color: #34d399; }
        .icon-amber { background: rgba(245, 158, 11, 0.18); color: #fbbf24; }
        .icon-rose { background: rgba(244, 63, 94, 0.18); color: #f87171; }
        .icon-purple { background: rgba(168, 85, 247, 0.18); color: #c084fc; }

        .action-title {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 6px;
            color: #ffffff;
        }

        .action-desc {
            font-size: 0.84rem;
            color: var(--text-muted);
            margin-bottom: 18px;
            flex-grow: 1;
        }

        .action-btn {
            width: 100%;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-cyan {
            background: rgba(6, 182, 212, 0.15);
            color: #22d3ee;
            border: 1px solid rgba(6, 182, 212, 0.3);
        }
        .btn-cyan:hover {
            background: #0891b2;
            color: #ffffff;
        }

        .btn-green {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .btn-green:hover {
            background: #059669;
            color: #ffffff;
        }

        .btn-amber {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .btn-amber:hover {
            background: #d97706;
            color: #ffffff;
        }

        .btn-rose {
            background: rgba(244, 63, 94, 0.15);
            color: #f87171;
            border: 1px solid rgba(244, 63, 94, 0.3);
        }
        .btn-rose:hover {
            background: #e11d48;
            color: #ffffff;
        }

        /* Console Output Card */
        .console-card {
            background: #070a12;
            border: 1px solid rgba(99, 102, 241, 0.25);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5);
            margin-bottom: 28px;
        }

        .console-header {
            background: #111827;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .console-dots {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .dot-red { background: #ef4444; }
        .dot-yellow { background: #f59e0b; }
        .dot-green { background: #10b981; }

        .console-title {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-family: var(--font-mono);
            font-weight: 500;
        }

        .console-tools {
            display: flex;
            gap: 8px;
        }

        .tool-btn {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-muted);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .tool-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
        }

        .console-body {
            padding: 18px 20px;
            font-family: var(--font-mono);
            font-size: 0.86rem;
            color: #38bdf8;
            background: #090d16;
            min-height: 200px;
            max-height: 380px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
            line-height: 1.6;
        }

        .console-body::-webkit-scrollbar {
            width: 8px;
        }
        .console-body::-webkit-scrollbar-track {
            background: #070a12;
        }
        .console-body::-webkit-scrollbar-thumb {
            background: #1e293b;
            border-radius: 4px;
        }

        /* Footer */
        footer {
            margin-top: auto;
            text-align: center;
            padding: 20px 0;
            color: var(--text-muted);
            font-size: 0.82rem;
            border-top: 1px solid rgba(148, 163, 184, 0.1);
        }

        /* Spinner */
        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-box {
            background: #1e293b;
            border: 1px solid rgba(244, 63, 94, 0.4);
            border-radius: 16px;
            max-width: 480px;
            width: 90%;
            padding: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
            animation: popIn 0.2s ease-out;
        }
        @keyframes popIn {
            from { transform: scale(0.92); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #f87171;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-desc {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 24px;
            line-height: 1.6;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Toast */
        #toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 600;
            display: none;
            z-index: 1001;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Header -->
        <header>
            <div class="brand-box">
                <div class="brand-icon">🍹</div>
                <div class="brand-title">
                    <h1>GLOWDRINKS CONTROL CENTER</h1>
                    <p>Frontend Dashboard quản trị, chẩn đoán và tự động khắc phục lỗi hệ thống</p>
                </div>
            </div>
            <div class="header-actions">
                <a href="/index/" target="_blank" class="btn btn-primary">
                    <span>🚀 Mở Trang Chủ GlowDrinks</span>
                </a>
                <a href="/amin/admin.php" target="_blank" class="btn btn-outline">
                    <span>🛡️ Quản Trị Admin</span>
                </a>
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <!-- PHP Card -->
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Môi Trường PHP</span>
                    <span class="stat-badge badge-success">✓ Sẵn sàng</span>
                </div>
                <div class="stat-value">v<?= htmlspecialchars($phpVersion) ?></div>
                <div class="stat-desc">
                    <?= empty($missingExts) ? 'Đầy đủ Extension (PDO, MySQL, JSON)' : '<span style="color:#f87171">Thiếu: ' . implode(', ', $missingExts) . '</span>' ?>
                </div>
            </div>

            <!-- MySQL Card -->
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">MySQL Database</span>
                    <?php if ($dbConnected): ?>
                        <span class="stat-badge badge-success">✓ Đã Kết Nối</span>
                    <?php else: ?>
                        <span class="stat-badge badge-danger">✕ Chưa Kết Nối</span>
                    <?php endif; ?>
                </div>
                <div class="stat-value" style="font-size: 1.25rem; font-family: var(--font-mono);">
                    <?= htmlspecialchars($dbName) ?>
                </div>
                <div class="stat-desc">
                    <?php if ($dbConnected): ?>
                        <?= $tableCount ?> bảng • <?= $productCount ?> món đồ uống
                    <?php else: ?>
                        <span style="color:#f87171" title="<?= htmlspecialchars($dbError) ?>">Bật MySQL trong Laragon/XAMPP/WAMP</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Server Port -->
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Cổng Server (Port)</span>
                    <span class="stat-badge badge-success">✓ Đang chạy</span>
                </div>
                <div class="stat-value">Port <?= htmlspecialchars($currentPort) ?></div>
                <div class="stat-desc">PHP Built-in Server (127.0.0.1:<?= $currentPort ?>)</div>
            </div>

            <!-- Dữ liệu / Tài khoản -->
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Dữ Liệu Người Dùng</span>
                    <span class="stat-badge badge-success"><?= $userCount ?> Users</span>
                </div>
                <div class="stat-value">Admin: admin</div>
                <div class="stat-desc">Mật khẩu mặc định: <code>123456</code></div>
            </div>
        </div>

        <!-- Section: System Actions -->
        <div class="section-heading">
            <span>⚡</span> BẢNG ĐIỀU KHIỂN & CÔNG CỤ TỰ ĐỘNG
        </div>

        <div class="actions-grid">
            <!-- 1. Chẩn đoán -->
            <div class="action-card">
                <div>
                    <div class="action-icon icon-cyan">🩺</div>
                    <div class="action-title">Chẩn Đoán & Tự Sửa Lỗi</div>
                    <div class="action-desc">
                        Tự động kiểm tra PHP extensions, kết nối MySQL, cấu trúc Database và giải phóng xung đột cổng.
                    </div>
                </div>
                <button class="action-btn btn-cyan" onclick="runTask('diagnose', this)">
                    <span>Chạy Kiểm Tra Ngay</span>
                </button>
            </div>

            <!-- 2. Khôi phục Database -->
            <div class="action-card">
                <div>
                    <div class="action-icon icon-rose">🔄</div>
                    <div class="action-title">Khôi Phục Database Gốc</div>
                    <div class="action-desc">
                        Xóa và nạp lại toàn bộ dữ liệu gốc (SQL), cập nhật ảnh sản phẩm và đồ uống snack mẫu.
                    </div>
                </div>
                <button class="action-btn btn-rose" onclick="openResetModal()">
                    <span>Khôi Phục Dữ Liệu</span>
                </button>
            </div>

            <!-- 3. Giải phóng Port -->
            <div class="action-card">
                <div>
                    <div class="action-icon icon-amber">🧹</div>
                    <div class="action-title">Giải Phóng Port <?= $currentPort ?></div>
                    <div class="action-desc">
                        Tự động tìm kiếm và đóng các tiến trình PHP cũ đang bị treo hoặc chiếm cổng mạng.
                    </div>
                </div>
                <button class="action-btn btn-amber" onclick="runTask('free_port', this)">
                    <span>Dọn Dẹp Port</span>
                </button>
            </div>

            <!-- 4. Lint Check PHP -->
            <div class="action-card">
                <div>
                    <div class="action-icon icon-green">📝</div>
                    <div class="action-title">Quét Lỗi Cú Pháp (Lint)</div>
                    <div class="action-desc">
                        Quét toàn bộ các file mã nguồn PHP trong thư mục "Trang chinh" để phát hiện cú pháp sai.
                    </div>
                </div>
                <button class="action-btn btn-green" onclick="runTask('lint', this)">
                    <span>Bắt Đầu Quét Mã</span>
                </button>
            </div>
        </div>

        <!-- Section: Interactive Console Terminal -->
        <div class="section-heading">
            <span>💻</span> TERMINAL LOG THỜI GIAN THỰC
        </div>

        <div class="console-card">
            <div class="console-header">
                <div class="console-dots">
                    <span class="dot dot-red"></span>
                    <span class="dot dot-yellow"></span>
                    <span class="dot dot-green"></span>
                    <span class="console-title">system_diagnose.php output</span>
                </div>
                <div class="console-tools">
                    <button class="tool-btn" onclick="copyConsoleLog()">Sao Chép</button>
                    <button class="tool-btn" onclick="clearConsoleLog()">Xóa Màn Hình</button>
                </div>
            </div>
            <div class="console-body" id="consoleOutput">Sẵn sàng! Hãy bấm một trong các công cụ phía trên để xem kết quả xử lý tại đây...</div>
        </div>

        <!-- Quick Links -->
        <div class="section-heading">
            <span>🔗</span> ĐIỀU HƯỚNG NHANH CÁC CHỨC NĂNG
        </div>
        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:28px;">
            <a href="/index/" class="btn btn-outline">🏠 Trang Chủ Bán Hàng</a>
            <a href="/detail.php" class="btn btn-outline">🔍 Trang Chi Tiết Sản Phẩm</a>
            <a href="/cart/cart.php" class="btn btn-outline">🛒 Giỏ Hàng & Thanh Toán</a>
            <a href="/contact.php" class="btn btn-outline">📞 Liên Hệ & Góp Ý</a>
            <a href="/amin/admin.php" class="btn btn-outline">⚙️ Trang Quản Trị Hệ Thống</a>
            <a href="/Game/duck_game.php" class="btn btn-outline">🎮 Vịt Vàng Mini Game</a>
        </div>

        <!-- Footer -->
        <footer>
            GlowDrinks Management System &bull; Frontend Control Panel &bull; Cổng kết nối HTTP: 127.0.0.1:<?= $currentPort ?>
        </footer>
    </div>

    <!-- Modal Xác Nhận Reset DB -->
    <div class="modal-overlay" id="resetModal">
        <div class="modal-box">
            <div class="modal-title">
                <span>⚠️</span> XÁC NHẬN KHÔI PHỤC DATABASE?
            </div>
            <div class="modal-desc">
                Thao tác này sẽ <strong style="color:#fb7185">XÓA TOÀN BỘ</strong> dữ liệu hiện tại trong Database <code><?= htmlspecialchars($dbName) ?></code> và nhập lại dữ liệu mẫu gốc từ file <code>drink_shop.sql</code>.<br><br>
                Bạn có chắc chắn muốn thực hiện không?
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="closeResetModal()">Hủy Bỏ</button>
                <button class="btn btn-rose" id="confirmResetBtn" onclick="executeResetDb()">
                    <span>Đồng Ý Khôi Phục</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast"></div>

    <script>
        const consoleEl = document.getElementById('consoleOutput');
        const resetModal = document.getElementById('resetModal');
        const toast = document.getElementById('toast');

        function showToast(msg, isSuccess = true) {
            toast.textContent = msg;
            toast.style.background = isSuccess ? '#10b981' : '#f43f5e';
            toast.style.color = '#ffffff';
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3500);
        }

        function appendLog(text) {
            const time = new Date().toLocaleTimeString();
            consoleEl.textContent += `\n[${time}] ${text}\n`;
            consoleEl.scrollTop = consoleEl.scrollHeight;
        }

        function clearConsoleLog() {
            consoleEl.textContent = 'Màn hình đã được dọn sạch.\n';
        }

        function copyConsoleLog() {
            navigator.clipboard.writeText(consoleEl.textContent).then(() => {
                showToast('Đã sao chép nội dung log vào Clipboard!');
            });
        }

        function openResetModal() {
            resetModal.style.display = 'flex';
        }

        function closeResetModal() {
            resetModal.style.display = 'none';
        }

        async function runTask(actionName, btnElement) {
            const originalContent = btnElement.innerHTML;
            btnElement.disabled = true;
            btnElement.innerHTML = `<span class="spinner"></span> Đang xử lý...`;
            
            appendLog(`--- BẮT ĐẦU TÁC VỤ: ${actionName.toUpperCase()} ---`);

            try {
                const response = await fetch(`control_panel.php?api=${encodeURIComponent(actionName)}`);
                const data = await response.json();

                appendLog(data.output || 'Không có phản hồi từ máy chủ.');
                
                if (data.success) {
                    showToast(`Thực thi ${actionName} thành công!`, true);
                    appendLog(`✓ Hoàn thành thành công (${data.timestamp})`);
                } else {
                    showToast(`Tác vụ ${actionName} có thông báo lưu ý.`, false);
                    appendLog(`! Kết thúc với mã: ${data.code} (${data.timestamp})`);
                }
            } catch (err) {
                appendLog(`[LỖI KẾT NỐI] ${err.message}`);
                showToast('Lỗi khi gửi yêu cầu tới máy chủ!', false);
            } finally {
                btnElement.disabled = false;
                btnElement.innerHTML = originalContent;
                appendLog('--------------------------------------------------');
            }
        }

        async function executeResetDb() {
            closeResetModal();
            const dummyBtn = document.createElement('button');
            appendLog('--- BẮT ĐẦU RESET VÀ IMPORT LẠI DATABASE GỐC ---');
            
            try {
                const response = await fetch('control_panel.php?api=reset_db');
                const data = await response.json();
                appendLog(data.output || 'Đã hoàn tất thao tác.');
                if (data.success) {
                    showToast('Đã khôi phục Database thành công!', true);
                    appendLog('✓ Khôi phục thành công! Trang sẽ tự động tải lại sau 3 giây để cập nhật số liệu...');
                    setTimeout(() => window.location.reload(), 3000);
                } else {
                    showToast('Có lỗi xảy ra khi khôi phục database!', false);
                }
            } catch (err) {
                appendLog(`[LỖI] ${err.message}`);
                showToast('Lỗi kết nối máy chủ!', false);
            }
        }
    </script>
</body>
</html>

