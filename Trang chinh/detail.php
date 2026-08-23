<?php
require_once __DIR__ . '/ThanhNgang/header.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { header("Location: /index/"); exit(); }

$stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.status = 1");
$stmt->execute([$id]);
$product = $stmt->fetch();

$site_lang = get_site_lang();
if ($product) {
    $product = localize_row($product, $site_lang, ['name','description','content']);
}

if (!$product) {
    echo '<div class="section" style="text-align:center;padding:100px 20px;"><h2>San pham khong ton tai!</h2><a href="/index/" class="btn btn-primary" style="margin-top:20px;">Ve Trang Chu</a></div>';
    require_once __DIR__ . '/../ThanhNgang/footer.php';
    exit();
}

$toppings = $pdo->query("SELECT * FROM products WHERE category_id = 5 AND status = 1 ORDER BY price ASC")->fetchAll();
// localize topping names
if (!empty($toppings)) {
    foreach ($toppings as $i => $t) {
        $toppings[$i] = localize_row($t, $site_lang, ['name','description']);
    }
}
$reviews_stmt = $pdo->prepare("SELECT r.*, u.fullname FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? AND r.status = 1 ORDER BY r.created_at DESC");
$reviews_stmt->execute([$id]);
$reviews = $reviews_stmt->fetchAll();

$review_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!$current_user) { $review_msg = 'Bạn cần đăng nhập để gửi đánh giá!'; }
    else {
        $rating  = intval($_POST['rating']);
        $comment = trim($_POST['comment']);
        // ---- RÀNG BUỘC SERVER-SIDE ----
        if ($rating < 1 || $rating > 5) {
            $review_msg = '⚠ Số sao không hợp lệ (phải từ 1 đến 5).';
        } elseif (empty($comment)) {
            $review_msg = '⚠ Nội dung đánh giá không được để trống.';
        } elseif (mb_strlen($comment) < 5) {
            $review_msg = '⚠ Nội dung đánh giá phải có ít nhất 5 ký tự.';
        } elseif (mb_strlen($comment) > 1000) {
            $review_msg = '⚠ Nội dung đánh giá không được quá 1000 ký tự.';
        } else {
            $pdo->prepare("INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (?,?,?,?)")->execute([$current_user['id'], $id, $rating, $comment]);
            header("Location: /detail.php?id=$id"); exit();
        }
    }
}

$hasDiscount = ($product['discount_price'] > 0);
$basePrice   = $hasDiscount ? $product['discount_price'] : $product['price'];
?>

<section class="section">
    <div style="margin-bottom:20px;">
        <a href="/index/" style="color:var(--text-muted);">&larr; Thuc don</a> /
        <span style="color:var(--dark);font-weight:500;"><?php echo htmlspecialchars($product['name']); ?></span>
    </div>

    <div class="detail-container">
        <div class="detail-gallery">
            <img src="<?php echo getProductImage($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>"
                 onerror="this.src='https://images.unsplash.com/photo-1544025162-d76694265947?w=500&auto=format&fit=crop&q=60'">
        </div>

        <div class="detail-info">
            <span style="color:var(--primary);font-weight:700;text-transform:uppercase;font-size:0.85rem;letter-spacing:1px;margin-bottom:5px;">
                <?php echo htmlspecialchars($product['category_name']); ?>
            </span>
            <h1 class="detail-title"><?php echo htmlspecialchars($product['name']); ?></h1>
            <div class="detail-price-row">
                <?php if ($hasDiscount): ?>
                    <span class="detail-old-price"><?php echo formatVND($product['price']); ?></span>
                <?php endif; ?>
                <span class="detail-price" id="displayed-price"><?php echo formatVND($basePrice); ?></span>
            </div>
            <p class="detail-desc"><?php echo htmlspecialchars($product['description']); ?></p>

            <form id="product-custom-form">
                <div class="customization-section">
                    <h4 class="custom-title">Chon Size Ly</h4>
                    <div class="sizes-options">
                        <div class="size-option"><input type="radio" name="size" id="size-s" value="S" onclick="updateTotalPrice()">
                            <label for="size-s" class="size-label">Size S<span class="size-subtext">-5.000 ₫</span></label></div>
                        <div class="size-option"><input type="radio" name="size" id="size-m" value="M" checked onclick="updateTotalPrice()">
                            <label for="size-m" class="size-label">Size M<span class="size-subtext">Chuan</span></label></div>
                        <div class="size-option"><input type="radio" name="size" id="size-l" value="L" onclick="updateTotalPrice()">
                            <label for="size-l" class="size-label">Size L<span class="size-subtext">+5.000 ₫</span></label></div>
                    </div>
                </div>

                <?php if (count($toppings) > 0): ?>
                <div class="customization-section">
                    <h4 class="custom-title">Them Toppings</h4>
                    <div class="toppings-options">
                        <?php foreach ($toppings as $top): ?>
                        <div class="topping-option">
                            <input type="checkbox" name="toppings[]" id="top-<?php echo $top['id']; ?>"
                                   value="<?php echo $top['id']; ?>" data-price="<?php echo $top['price']; ?>" onclick="updateTotalPrice()">
                            <label for="top-<?php echo $top['id']; ?>" class="topping-label">
                                <span><?php echo htmlspecialchars($top['name']); ?></span>
                                <span class="topping-price">+<?php echo formatVND($top['price']); ?></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="quantity-control">
                    <button type="button" class="qty-btn" onclick="adjustQty(-1)">-</button>
                    <input type="number" id="qty-input" class="qty-input" value="1" min="1" readonly>
                    <button type="button" class="qty-btn" onclick="adjustQty(1)">+</button>
                    <span style="color:var(--text-muted);font-size:0.9rem;">(Con: <?php echo $product['quantity']; ?> coc)</span>
                </div>

                <div class="detail-actions">
                    <button type="button" onclick="submitToCart()" class="btn btn-primary" style="flex-grow:1;padding:15px 30px;">
                        Them Vao Gio Hang
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($product['content'])): ?>
    <div style="background:white;border-radius:var(--radius-lg);padding:40px;box-shadow:var(--shadow-sm);border:1px solid var(--border);margin-top:30px;">
        <h3 style="font-size:1.4rem;font-weight:800;color:var(--dark);margin-bottom:20px;border-left:4px solid var(--primary);padding-left:15px;">Thong Tin Chi Tiet</h3>
        <div style="line-height:1.8;color:var(--text-main);"><?php echo nl2br(htmlspecialchars($product['content'])); ?></div>
    </div>
    <?php endif; ?>

    <!-- Reviews -->
    <div style="background:white;border-radius:var(--radius-lg);padding:40px;box-shadow:var(--shadow-sm);border:1px solid var(--border);margin-top:40px;">
        <h3 style="font-size:1.4rem;font-weight:800;color:var(--dark);margin-bottom:20px;border-left:4px solid var(--primary);padding-left:15px;">Danh Gia Khach Hang (<?php echo count($reviews); ?>)</h3>
        <?php if (count($reviews) > 0): ?>
            <div style="display:flex;flex-direction:column;gap:20px;margin-bottom:45px;">
                <?php foreach ($reviews as $rev): ?>
                <div style="border-bottom:1px solid var(--border);padding-bottom:15px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                        <span style="font-weight:700;"><?php echo htmlspecialchars($rev['fullname']); ?></span>
                        <span style="color:var(--secondary);"><?php echo str_repeat('★',$rev['rating']).str_repeat('☆',5-$rev['rating']); ?></span>
                    </div>
                    <p><?php echo htmlspecialchars($rev['comment']); ?></p>
                    <span style="color:var(--text-muted);font-size:0.8rem;"><?php echo date('d/m/Y H:i',strtotime($rev['created_at'])); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color:var(--text-muted);margin-bottom:45px;">Chua co danh gia nao. Hay la nguoi dau tien!</p>
        <?php endif; ?>

        <div style="background:var(--light);padding:30px;border-radius:var(--radius-md);border:1px solid var(--border);">
            <h4 style="font-weight:700;margin-bottom:15px;">Viet Danh Gia Cua Ban</h4>
            <?php if (!empty($review_msg)): ?>
                <div style="padding:10px 15px;border-radius:var(--radius-sm);margin-bottom:15px;background:white;border:1px solid var(--border);"><?php echo $review_msg; ?></div>
            <?php endif; ?>
            <?php if ($current_user): ?>
            <form action="/detail.php?id=<?php echo $id; ?>" method="POST" id="reviewForm" novalidate>
                <div class="form-group">
                    <label class="form-label">Số sao *</label>
                    <!-- Star Rating UI -->
                    <div id="star-picker" style="display:flex;gap:6px;margin-bottom:8px;">
                        <?php for($s=5;$s>=1;$s--): ?>
                        <label style="cursor:pointer;font-size:1.8rem;color:#d1d5db;transition:color .15s;" for="star<?php echo $s; ?>" title="<?php echo $s; ?> sao">
                            <input type="radio" name="rating" id="star<?php echo $s; ?>" value="<?php echo $s; ?>" style="display:none;">
                            ★
                        </label>
                        <?php endfor; ?>
                    </div>
                    <div class="field-error" id="rv_err_rating" style="display:none;color:#dc2626;font-size:0.82rem;"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nội dung đánh giá * <span id="rv_counter" style="float:right;font-size:0.8rem;color:var(--text-muted);font-weight:400;">0/1000</span></label>
                    <textarea name="comment" id="rv_comment" rows="4" class="form-control" placeholder="Chia sẻ cảm nhận của bạn về sản phẩm... (tối thiểu 5 ký tự)" style="background:white;"></textarea>
                    <div class="field-error" id="rv_err_comment" style="display:none;color:#dc2626;font-size:0.82rem;"></div>
                </div>
                <button type="submit" name="submit_review" class="btn btn-primary">⭐ Gửi Đánh Giá</button>
            </form>
            <style>
            #star-picker label:hover, #star-picker label:hover ~ label { color:#f59e0b; }
            #star-picker input:checked ~ label { color:#d1d5db; }
            #star-picker input:checked + label { color:#f59e0b; }
            /* RTL trick for star rating */
            #star-picker { flex-direction:row-reverse; justify-content:flex-end; }
            #star-picker label:hover,
            #star-picker label:hover ~ label,
            #star-picker input:checked ~ label { color:#f59e0b!important; }
            #star-picker input:checked + label,
            #star-picker input:checked + label ~ label { color:#f59e0b!important; }
            </style>
            <script>
            const rvCommentEl = document.getElementById('rv_comment');
            const rvCntEl     = document.getElementById('rv_counter');
            rvCommentEl.addEventListener('input', function(){
                const len = this.value.length;
                rvCntEl.textContent = len+'/1000';
                rvCntEl.style.color = len>900?'#dc2626':len>700?'#f59e0b':'var(--text-muted)';
                if(this.classList.contains('is-invalid') && len>=5) {
                    this.classList.remove('is-invalid'); this.classList.add('is-valid');
                    document.getElementById('rv_err_comment').style.display='none';
                }
            });
            document.getElementById('reviewForm').addEventListener('submit', function(e){
                let ok = true;
                const rating = document.querySelector('input[name=rating]:checked');
                if (!rating) {
                    document.getElementById('rv_err_rating').textContent='⚠ Vui lòng chọn số sao đánh giá.';
                    document.getElementById('rv_err_rating').style.display='block';
                    ok = false;
                } else {
                    document.getElementById('rv_err_rating').style.display='none';
                }
                const v = rvCommentEl.value.trim();
                if (!v) {
                    rvCommentEl.classList.add('is-invalid');
                    document.getElementById('rv_err_comment').textContent='⚠ Nội dung không được để trống.';
                    document.getElementById('rv_err_comment').style.display='block'; ok=false;
                } else if(v.length<5) {
                    rvCommentEl.classList.add('is-invalid');
                    document.getElementById('rv_err_comment').textContent='⚠ Nội dung phải có ít nhất 5 ký tự.';
                    document.getElementById('rv_err_comment').style.display='block'; ok=false;
                } else if(v.length>1000) {
                    rvCommentEl.classList.add('is-invalid');
                    document.getElementById('rv_err_comment').textContent='⚠ Không được quá 1000 ký tự.';
                    document.getElementById('rv_err_comment').style.display='block'; ok=false;
                }
                if (!ok) e.preventDefault();
            });
            </script>
            <?php else: ?>
                <p style="color:var(--text-muted);">Vui long <a href="/login/login_demo.php" style="color:var(--primary);font-weight:600;text-decoration:underline;">Dang Nhap</a> de viet danh gia.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
const basePrice = <?php echo $basePrice; ?>;
function adjustQty(a) { const i=document.getElementById('qty-input'); let q=parseInt(i.value)+a; i.value=Math.max(1,q); updateTotalPrice(); }
function updateTotalPrice() {
    let t = basePrice;
    const s = document.querySelector('input[name="size"]:checked').value;
    if (s==='S') t-=5000; if (s==='L') t+=5000;
    document.querySelectorAll('input[name="toppings[]"]:checked').forEach(cb=>{ t+=parseInt(cb.getAttribute('data-price')); });
    t *= parseInt(document.getElementById('qty-input').value);
    document.getElementById('displayed-price').innerText = new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(t).replace('₫',' ₫');
}
function submitToCart() {
    const id=<?php echo $id; ?>, qty=parseInt(document.getElementById('qty-input').value);
    const size=document.querySelector('input[name="size"]:checked').value;
    const tops=Array.from(document.querySelectorAll('input[name="toppings[]"]:checked')).map(c=>c.value);
    fetch('/cart/cart_action.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=add&product_id=${id}&qty=${qty}&size=${size}&toppings=${tops.join(',')}`})
    .then(r=>r.json()).then(data=>{
        if(data.status==='require_login'){
            const t=document.createElement('div');
            t.style.cssText='position:fixed;bottom:30px;right:30px;background:#e11d48;color:white;padding:15px 25px;border-radius:var(--radius-md);box-shadow:var(--shadow-lg);font-weight:600;z-index:9999;transform:translateY(100px);opacity:0;transition:all 0.4s ease;';
            t.innerText='⚠️ ' + data.message; document.body.appendChild(t);
            setTimeout(()=>{t.style.transform='translateY(0)';t.style.opacity='1';},50);
            setTimeout(()=>{window.location.href=data.redirect;},1500);
            return;
        }
        if(data.status==='success'){
            document.getElementById('cart-counter').innerText=data.total_count;
            const t=document.createElement('div');
            t.style.cssText='position:fixed;bottom:30px;right:30px;background:var(--primary);color:white;padding:15px 25px;border-radius:var(--radius-md);box-shadow:var(--shadow-lg);font-weight:600;z-index:9999;transform:translateY(100px);opacity:0;transition:all 0.4s ease;';
            t.innerText='Da them vao gio hang!';document.body.appendChild(t);
            setTimeout(()=>{t.style.transform='translateY(0)';t.style.opacity='1';},50);
            setTimeout(()=>{t.style.transform='translateY(100px)';t.style.opacity='0';setTimeout(()=>{t.remove();window.location.href='/cart/cart.php';},400);},1500);
        }
    });
}
</script>

<?php require_once __DIR__ . '/ThanhNgang/footer.php'; ?>
