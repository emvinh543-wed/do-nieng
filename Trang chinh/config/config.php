<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'drink_shop');

// Đường dẫn gốc tuyệt đối của thư mục "Trang chinh"
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
// URL gốc để tạo link (dùng đường dẫn tương đối từ gốc server)
define('BASE_URL', '/');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <title>Loi ket noi Database</title>
        <style>
            body { font-family: sans-serif; background: #f1f5f9; }
            .card { max-width:600px; margin:100px auto; padding:40px; background:white;
                    border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,0.08);
                    border:1px solid #fee2e2; text-align:center; }
            code { display:block; background:#f1f5f9; padding:15px; border-radius:8px;
                   text-align:left; margin:20px 0; font-size:0.88rem; word-break:break-all; }
            a { display:inline-block; margin-top:15px; padding:10px 24px;
                background:#0d9488; color:white; border-radius:8px; text-decoration:none; }
        </style>
    </head>
    <body>
        <div class="card">
            <div style="font-size:48px">⚠️</div>
            <h2>Khong the ket noi Co so Du lieu!</h2>
            <p style="color:#64748b; margin-top:10px;">
                Vui long dam bao MySQL da khoi dong (XAMPP/Laragon)<br>
                va ban da import file <strong>drink_shop.sql</strong> vao phpMyAdmin.
            </p>
            <code>Chi tiet loi: <?php echo htmlspecialchars($e->getMessage()); ?></code>
            <a href="/">Thu tai lai</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

function formatVND($amount) {
    return number_format($amount, 0, ',', '.') . ' ₫';
}

function getProductImage($path) {
    if (!empty($path)) {
        // 1. Nếu là URL http/https → dùng thẳng
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        // 2. Thử đường dẫn tuyệt đối từ ROOT_PATH
        $full = ROOT_PATH . $path;
        if (file_exists($full)) {
            return '/' . ltrim(str_replace('\\', '/', $path), '/');
        }
        $basename    = basename($path);
        $name_no_ext = pathinfo($basename, PATHINFO_FILENAME);

        // 3. Thử trong anh/ với tên gốc
        foreach (['.png', '.jpg', '.jpeg', '.webp'] as $ext) {
            $try = ROOT_PATH . 'anh/' . $name_no_ext . $ext;
            if (file_exists($try)) return '/anh/' . $name_no_ext . $ext;
        }
        // 4. Thử trong anh/products/
        foreach (['.png', '.jpg', '.jpeg', '.webp'] as $ext) {
            $try = ROOT_PATH . 'anh/products/' . $name_no_ext . $ext;
            if (file_exists($try)) return '/anh/products/' . $name_no_ext . $ext;
        }
    }
    // Ảnh mặc định đẹp
    return 'https://images.unsplash.com/photo-1461023058943-07fcbe16d735?w=500&auto=format&fit=crop&q=70';
}

// Current site language (read from cookie), default to 'vi'
function get_site_lang() {
    if (isset($_COOKIE['site_lang']) && in_array($_COOKIE['site_lang'], ['vi','en','ja','zh'])) {
        return $_COOKIE['site_lang'];
    }
    return 'vi';
}

// Localize a DB row by preferring language-specific columns like name_en, description_ja, etc.
function localize_row(array $row, string $lang, array $fields = ['name','description','content']) : array {
    foreach ($fields as $f) {
        $lang_key = $f . '_' . $lang;
        if (isset($row[$lang_key]) && $row[$lang_key] !== null && $row[$lang_key] !== '') {
            $row[$f] = $row[$lang_key];
        }
    }
    return $row;
}
?>
