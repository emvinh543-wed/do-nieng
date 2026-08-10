<?php
require_once __DIR__ . '/../ThanhNgang/header.php';

$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$search      = isset($_GET['search'])      ? trim($_GET['search'])          : '';
$type        = isset($_GET['type'])        ? trim($_GET['type'])            : '';

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

$stmt     = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories_stmt = $pdo->query("SELECT c.*, COUNT(p.id) as total_products FROM categories c LEFT JOIN products p ON c.id = p.category_id WHERE c.status = 1 GROUP BY c.id");
$categories      = $categories_stmt->fetchAll();
?>

<?php if (empty($search) && $category_id == 0 && $type !== 'products'): ?>
<section class="hero">
    <div class="hero-container">
        <div>
            <h1 class="hero-title">Do Uong Sach,<br><span>Nang Luong Sang Tao!</span></h1>
            <p class="hero-subtitle">
                Chao don ngay moi ngap tran hung khoi cung ly ca phe dam vi Viet Nam,
                hay nap vitamin tuoi mat voi cac dong tra trai cay tu nhien dac trung cua GlowDrinks.
            </p>
            <div class="hero-buttons">
                <a href="#menu-section" class="btn btn-primary">Dat mon ngay</a>
                <a href="/contact.php" class="btn btn-outline">Lien he ho tro</a>
            </div>
        </div>
        <div class="hero-image-container">
            <div class="hero-blob"></div>
            <svg class="hero-img" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="cupGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#0d9488"/>
                        <stop offset="100%" stop-color="#0f766e"/>
                    </linearGradient>
                </defs>
                <circle cx="100" cy="100" r="80" fill="rgba(13,148,136,0.05)"/>
                <path d="M70,50 L130,50 L120,150 L80,150 Z" fill="url(#cupGrad)" opacity="0.9"/>
                <path d="M72,55 L128,55 L124,90 L76,90 Z" fill="#ffffff" opacity="0.3"/>
                <rect x="75" y="45" width="50" height="8" rx="4" fill="#1e293b"/>
                <path d="M125,30 L110,60" stroke="#f59e0b" stroke-width="8" stroke-linecap="round"/>
                <circle cx="88" cy="140" r="6" fill="#1e293b"/>
                <circle cx="100" cy="142" r="6" fill="#1e293b"/>
                <circle cx="112" cy="138" r="6" fill="#1e293b"/>
                <circle cx="94"  cy="128" r="6" fill="#1e293b"/>
                <circle cx="106" cy="130" r="6" fill="#1e293b"/>
            </svg>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- DANH SACH SAN PHAM -->
<section class="section" id="menu-section" style="padding-top:20px;">
    <div class="section-header">
        <div>
            <h2 class="section-title">
                <?php
                if (!empty($search)) {
                    echo 'Ket qua tim: <span>"' . htmlspecialchars($search) . '"</span>';
                } elseif ($category_id > 0) {
                    $cn = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                    $cn->execute([$category_id]);
                    echo 'Menu: <span>' . htmlspecialchars($cn->fetchColumn()) . '</span>';
                } else {
                    echo 'Mon Ngon <span>Goi Y Cho Ban</span>';
                }
                ?>
            </h2>
            <p class="section-desc">Giao hang cuc nhanh trong vong 20 phut</p>
        </div>
    </div>

    <?php if (count($products) > 0): ?>
    <div class="products-grid">
        <?php foreach ($products as $prod):
            $hasDiscount  = ($prod['discount_price'] > 0);
            $displayPrice = $hasDiscount ? $prod['discount_price'] : $prod['price'];
        ?>
        <div class="product-card">
            <?php if ($hasDiscount):
                $pct = round((($prod['price'] - $prod['discount_price']) / $prod['price']) * 100);
            ?>
                <span class="product-badge">-<?php echo $pct; ?>% Off</span>
            <?php elseif ($prod['is_featured']): ?>
                <span class="product-badge" style="background-color:var(--primary);">Ban chay</span>
            <?php endif; ?>

            <div class="product-image-box">
                <a href="/detail.php?id=<?php echo $prod['id']; ?>" style="width:100%;height:100%;">
                    <img src="<?php echo getProductImage($prod['image']); ?>"
                         alt="<?php echo htmlspecialchars($prod['name']); ?>"
                         onerror="this.src='https://images.unsplash.com/photo-1544025162-d76694265947?w=500&auto=format&fit=crop&q=60'">
                </a>
            </div>

            <div class="product-info">
                <span class="product-cat"><?php echo htmlspecialchars($prod['category_name']); ?></span>
                <h3 class="product-title">
                    <a href="/detail.php?id=<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['name']); ?></a>
                </h3>
                <p class="product-desc"><?php echo htmlspecialchars($prod['description']); ?></p>

                <div class="product-footer">
                    <div class="price-box">
                        <?php if ($hasDiscount): ?>
                            <span class="old-price"><?php echo formatVND($prod['price']); ?></span>
                            <span class="price"><?php echo formatVND($prod['discount_price']); ?></span>
                        <?php else: ?>
                            <span class="price"><?php echo formatVND($prod['price']); ?></span>
                        <?php endif; ?>
                    </div>
                    <button onclick="addToCart(<?php echo $prod['id']; ?>)" class="btn-add-cart" title="Them vao gio">
                        <svg><path d="M9 22a1 1 0 100-2 1 1 0 000 2zM17 22a1 1 0 100-2 1 1 0 000 2z"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:50px 20px;background:white;border-radius:var(--radius-lg);border:1px solid var(--border);">
        <div style="font-size:40px;margin-bottom:15px;">🔍</div>
        <h3>Khong tim thay san pham nao!</h3>
        <p style="color:var(--text-muted);margin-top:5px;">Thu tim kiem voi tu khoa khac hoac chon danh muc khac.</p>
        <a href="/index/" class="btn btn-primary" style="margin-top:20px;">Tai lai trang chu</a>
    </div>
    <?php endif; ?>
</section>

<script>
function addToCart(productId) {
    fetch('/cart/cart_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=add&product_id=' + productId + '&qty=1'
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'require_login') {
            const t = document.createElement('div');
            t.style.cssText = 'position:fixed;bottom:30px;right:30px;background:#e11d48;color:white;padding:15px 25px;border-radius:var(--radius-md);box-shadow:var(--shadow-lg);font-weight:600;z-index:9999;transform:translateY(100px);opacity:0;transition:all 0.4s ease;';
            t.innerText = '⚠️ ' + data.message;
            document.body.appendChild(t);
            setTimeout(() => { t.style.transform='translateY(0)'; t.style.opacity='1'; }, 50);
            setTimeout(() => { window.location.href = data.redirect; }, 1500);
            return;
        }
        if (data.status === 'success') {
            document.getElementById('cart-counter').innerText = data.total_count;
            const t = document.createElement('div');
            t.style.cssText = 'position:fixed;bottom:30px;right:30px;background:var(--primary);color:white;padding:15px 25px;border-radius:var(--radius-md);box-shadow:var(--shadow-lg);font-weight:600;z-index:9999;transform:translateY(100px);opacity:0;transition:all 0.4s ease;';
            t.innerText = 'Da them ' + data.product_name + ' vao gio hang!';
            document.body.appendChild(t);
            setTimeout(() => { t.style.transform='translateY(0)'; t.style.opacity='1'; }, 50);
            setTimeout(() => { t.style.transform='translateY(100px)'; t.style.opacity='0'; setTimeout(()=>t.remove(),400); }, 2500);
        }
    });
}
</script>

<?php require_once __DIR__ . '/../ThanhNgang/footer.php'; ?>
