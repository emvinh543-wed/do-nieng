<?php
require_once __DIR__ . '/../ThanhNgang/header.php';

$site_lang = get_site_lang();

// Parse query parameters
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$search      = isset($_GET['search'])      ? trim($_GET['search'])          : '';
$type        = isset($_GET['type'])        ? trim($_GET['type'])            : '';

// Build database query
$query  = "SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.status = 1";
$params = [];

if ($category_id > 0) {
    $query  .= " AND p.category_id = ?";
    $params[] = $category_id;
}
if (!empty($search)) {
    $query  .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= ($type === 'products') ? " ORDER BY p.id DESC" : " ORDER BY p.is_featured DESC, p.id ASC";
$stmt   = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Localize product fields if language-specific columns exist (e.g., name_en, description_ja)
if (!empty($products)) {
    foreach ($products as $i => $p) {
        $products[$i] = localize_row($p, $site_lang, ['name','description','content']);
    }
}

// Fetch categories with total product counts
$categories_stmt = $pdo->query("SELECT c.*, COUNT(p.id) as total_products FROM categories c LEFT JOIN products p ON c.id = p.category_id WHERE c.status = 1 GROUP BY c.id");
$categories      = $categories_stmt->fetchAll();
// Localize category names
if (!empty($categories)) {
    foreach ($categories as $i => $c) {
        $categories[$i] = localize_row($c, $site_lang, ['name']);
    }
}
?>

<?php if (empty($search) && $category_id == 0 && $type !== 'products'): ?>
    <!-- Component 1: Hero Banner -->
    <?php include __DIR__ . '/../components/hero.php'; ?>

    <!-- Component 2: Quick Statistics Bar -->
    <?php include __DIR__ . '/../components/stats.php'; ?>

    <!-- Component 3: Categories Carousel/Grid -->
    <?php include __DIR__ . '/../components/categories.php'; ?>

    <!-- Component 4: Promotional Banners -->
    <?php include __DIR__ . '/../components/promo.php'; ?>
<?php endif; ?>

<!-- Component 5: Products Grid Section -->
<div class="prod-section" id="menu-section">
    <div class="cat-title-row" style="margin-bottom:24px;">
        <h2 class="sec-title" data-page-title>
            <?php
            if (!empty($search)) {
                echo 'Kết quả: <span>"' . htmlspecialchars($search) . '"</span>';
            } elseif ($category_id > 0) {
                $cn = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
                $cn->execute([$category_id]);
                $cat = $cn->fetch();
                if ($cat) {
                    $cat = localize_row($cat, $site_lang, ['name']);
                    echo 'Menu: <span>' . htmlspecialchars($cat['name']) . '</span>';
                } else {
                    echo 'Menu';
                }
            } else {
                echo '<span data-i18n="menu_title">Món Ngon <span>Gợi Ý</span></span>';
            }
            ?>
        </h2>
        <?php if (!empty($search) || $category_id > 0): ?>
            <a href="/index/" class="view-all" data-i18n="back_home">← Về trang chủ</a>
        <?php endif; ?>
    </div>

    <?php if (count($products) > 0): ?>
        <div class="products-grid">
            <?php foreach ($products as $prod): ?>
                <?php include __DIR__ . '/../components/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align:center;padding:70px 20px;background:#fff;border-radius:20px;border:1px solid #F0E8D0;">
            <div style="font-size:50px;margin-bottom:15px;">🔍</div>
            <h3 style="color:#3A2A1A;margin-bottom:8px;" data-i18n="nothing_found">Không tìm thấy sản phẩm!</h3>
            <p style="color:#8B7355;" data-i18n="empty_hint">Thử tìm kiếm với từ khóa khác hoặc chọn danh mục khác.</p>
            <a href="/index/" class="hero-btn-main" style="display:inline-block;margin-top:20px;text-decoration:none;" data-i18n="back_home_button">↩ Về trang chủ</a>
        </div>
    <?php endif; ?>
</div>

<!-- AJAX Add to Cart Script -->
<script>
function addToCart(productId) {
    fetch('/cart/cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=add&product_id=' + productId + '&qty=1'
    })
    .then(r => r.json())
    .then(data => {
        const toast = document.createElement('div');
        const isErr = data.status === 'require_login';
        toast.style.cssText = 'position:fixed;bottom:30px;right:30px;padding:16px 26px;border-radius:16px;font-weight:700;font-size:0.95rem;z-index:9999;transform:translateY(80px);opacity:0;transition:all 0.4s cubic-bezier(0.4,0,0.2,1);box-shadow:0 10px 40px rgba(0,0,0,0.15);max-width:320px;';
        toast.style.background = isErr ? 'linear-gradient(135deg,#e11d48,#be185d)' : 'linear-gradient(135deg,#F5A623,#E8622A)';
        toast.style.color = '#fff';
        toast.innerText = isErr ? '⚠️ ' + data.message : '🛒 Đã thêm: ' + data.product_name;
        document.body.appendChild(toast);
        setTimeout(() => { toast.style.transform = 'translateY(0)'; toast.style.opacity = '1'; }, 30);
        
        if (isErr) {
            setTimeout(() => { window.location.href = data.redirect; }, 1500);
        } else {
            const el = document.getElementById('cart-counter');
            if (el) el.innerText = data.total_count;
            setTimeout(() => {
                toast.style.transform = 'translateY(80px)';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 400);
            }, 2800);
        }
    });
}
</script>

<?php require_once __DIR__ . '/../ThanhNgang/footer.php'; ?>
