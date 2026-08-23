<?php
require_once __DIR__ . '/../ThanhNgang/header.php';

$cart     = $_SESSION['cart'] ?? [];
$site_lang = get_site_lang();
// Refresh product names in cart according to current site language (if product_id present)
if (!empty($cart)) {
    foreach ($cart as $k => $it) {
        if (!empty($it['product_id'])) {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 1");
            $stmt->execute([intval($it['product_id'])]);
            $prod = $stmt->fetch();
            if ($prod) {
                $prod = localize_row($prod, $site_lang, ['name','description']);
                $cart[$k]['name'] = $prod['name'];
                // update image if product has one
                if (!empty($prod['image'])) $cart[$k]['image'] = $prod['image'];
            }
        }
    }
}

$subtotal = array_sum(array_column($cart, 'total_item_amount'));
$voucher  = $_SESSION['voucher'] ?? null;
$discount = 0;
if ($voucher && !empty($voucher['discount_percent'])) {
    $discount = round($subtotal * ($voucher['discount_percent'] / 100));
}
$after_discount = max(0, $subtotal - $discount);
$shipping = ($after_discount > 100000 || $after_discount == 0) ? 0 : 15000;
$total    = $after_discount + $shipping;
?>
<section class="section">
    <h2 class="section-title" style="margin-bottom:30px;">Giỏ Hàng <span>Của Bạn</span></h2>

    <?php if (count($cart) > 0): ?>
    <div class="cart-layout" id="cart-container">
        <div class="cart-table-card">
            <?php foreach ($cart as $key => $item): ?>
            <div class="cart-item" id="cart-row-<?php echo $key; ?>">
                <img src="<?php echo getProductImage($item['image']); ?>" class="cart-item-img"
                     onerror="this.src='https://images.unsplash.com/photo-1544025162-d76694265947?w=500&auto=format&fit=crop&q=60'"
                     alt="<?php echo htmlspecialchars($item['name']); ?>">
                <div class="cart-item-details">
                    <h3 class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                    <div class="cart-item-meta">
                        <span>Size: <?php echo $item['size']; ?></span>
                        <?php if (!empty($item['toppings'])): ?>
                            <span>+ <?php echo htmlspecialchars($item['toppings']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <button class="qty-btn" style="width:30px;height:30px;" onclick="updateCartQty('<?php echo $key; ?>',-1)">-</button>
                    <input type="text" id="qty-<?php echo $key; ?>" class="qty-input" style="width:40px;padding:4px;" value="<?php echo $item['qty']; ?>" readonly>
                    <button class="qty-btn" style="width:30px;height:30px;" onclick="updateCartQty('<?php echo $key; ?>',1)">+</button>
                </div>
                <div style="display:flex;align-items:center;gap:15px;justify-content:flex-end;">
                    <div class="cart-item-price" id="price-<?php echo $key; ?>"><?php echo formatVND($item['total_item_amount']); ?></div>
                    <button class="cart-item-delete" onclick="deleteCartItem('<?php echo $key; ?>')">❌</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="summary-card">
            <h3 class="summary-title">Tóm Tắt Đơn Hàng</h3>
            <div class="summary-row"><span>Tạm tính:</span><span id="summary-subtotal" style="font-weight:600;"><?php echo formatVND($subtotal); ?></span></div>
            
            <!-- VOUCHER INPUT BOX -->
            <div style="margin:15px 0;padding:12px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:12px;">
                <label style="font-size:0.82rem;font-weight:700;color:var(--dark);display:block;margin-bottom:6px;">🎟️ Mã Giảm Giá / Voucher (GlowFarm/Duck3D):</label>
                <?php if ($voucher): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;background:#e0f2fe;border:1px solid #38bdf8;padding:8px 12px;border-radius:8px;font-size:0.85rem;color:#0369a1;font-weight:700;">
                        <span>Mã <?php echo htmlspecialchars($voucher['code']); ?> (-<?php echo $voucher['discount_percent']; ?>%)</span>
                        <button type="button" onclick="removeVoucher()" style="background:none;border:none;color:#ef4444;font-weight:bold;cursor:pointer;font-size:1.1rem;" title="Hủy mã">✕</button>
                    </div>
                <?php else: ?>
                    <div style="display:flex;gap:6px;">
                        <input type="text" id="voucher-code-input" placeholder="VD: GLOWFARM15" style="flex:1;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;font-weight:700;text-transform:uppercase;">
                        <button type="button" onclick="applyVoucher()" class="btn btn-primary" style="padding:8px 12px;font-size:0.82rem;border-radius:8px;">Áp Dụng</button>
                    </div>
                <?php endif; ?>
                <div id="voucher-msg" style="font-size:0.8rem;margin-top:6px;font-weight:600;"></div>
            </div>

            <?php if ($discount > 0): ?>
            <div class="summary-row" style="color:#2563eb;font-weight:700;">
                <span>Giảm giá Voucher:</span>
                <span>-<?php echo formatVND($discount); ?></span>
            </div>
            <?php endif; ?>

            <div class="summary-row"><span>Phí vận chuyển:</span>
                <span id="summary-shipping" style="font-weight:600;color:<?php echo $shipping==0 ? 'var(--success)' : 'inherit'; ?>">
                    <?php echo $shipping==0 ? 'Miễn phí' : formatVND($shipping); ?>
                </span>
            </div>
            <div style="font-size:0.82rem;color:var(--text-muted);margin-bottom:20px;">Miễn phí ship cho đơn từ 100.000 ₫!</div>
            <div class="summary-row-total">
                <span>Tổng tiền:</span>
                <span id="summary-grandtotal" class="total-price"><?php echo formatVND($total); ?></span>
            </div>
            <a href="/cart/checkout.php" class="btn btn-primary" style="width:100%;margin-top:20px;padding:15px;">Tiến Hành Đặt Hàng</a>
            <a href="/index/?type=products" class="btn btn-outline" style="width:100%;margin-top:10px;font-size:0.9rem;">Tiếp tục chọn nước</a>
        </div>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:80px 20px;background:white;border-radius:var(--radius-lg);border:1px solid var(--border);">
        <div style="font-size:60px;margin-bottom:20px;">🛒</div>
        <h2>Giỏ hàng của bạn đang trống!</h2>
        <p style="color:var(--text-muted);margin:10px 0 30px;">Hãy quay lại menu để lựa chọn món uống yêu thích.</p>
        <a href="/index/?type=products" class="btn btn-primary">Khám Phá Menu Ngay</a>
    </div>
    <?php endif; ?>
</section>

<script>
function applyVoucher() {
    const code = document.getElementById('voucher-code-input').value.trim();
    const msgEl = document.getElementById('voucher-msg');
    if (!code) { msgEl.style.color = '#ef4444'; msgEl.textContent = 'Vui lòng nhập mã giảm giá!'; return; }
    
    fetch('/cart/cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=apply_voucher&code=${encodeURIComponent(code)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            msgEl.style.color = '#16a34a';
            msgEl.textContent = data.message;
            setTimeout(() => window.location.reload(), 500);
        } else {
            msgEl.style.color = '#ef4444';
            msgEl.textContent = data.message;
        }
    });
}

function removeVoucher() {
    fetch('/cart/cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=remove_voucher'
    })
    .then(r => r.json())
    .then(() => window.location.reload());
}

function updateCartQty(key, amount) {
    const inp = document.getElementById('qty-' + key);
    let qty = parseInt(inp.value) + amount;
    if (qty <= 0) { if(confirm('Xóa món này khỏi giỏ hàng?')) deleteCartItem(key); return; }
    inp.value = qty;
    fetch('/cart/cart_action.php', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=update&cart_key=${key}&qty=${qty}`})
    .then(r=>r.json()).then(()=>window.location.reload());
}
function deleteCartItem(key) {
    fetch('/cart/cart_action.php', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=delete&cart_key=${key}`})
    .then(r=>r.json()).then(data=>{
        const row = document.getElementById('cart-row-' + key);
        row.style.transition='all 0.3s ease'; row.style.opacity='0';
        setTimeout(()=>window.location.reload(), 300);
    });
}
</script>

<?php require_once __DIR__ . '/../ThanhNgang/footer.php'; ?>
