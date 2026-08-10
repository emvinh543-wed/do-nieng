<?php
require_once __DIR__ . '/../ThanhNgang/header.php';

$cart     = $_SESSION['cart'] ?? [];
$subtotal = array_sum(array_column($cart, 'total_item_amount'));
$shipping = ($subtotal > 100000 || $subtotal == 0) ? 0 : 15000;
$total    = $subtotal + $shipping;
?>
<section class="section">
    <h2 class="section-title" style="margin-bottom:30px;">Gio Hang <span>Cua Ban</span></h2>

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
            <h3 class="summary-title">Tom Tat Don Hang</h3>
            <div class="summary-row"><span>Tam tinh:</span><span id="summary-subtotal" style="font-weight:600;"><?php echo formatVND($subtotal); ?></span></div>
            <div class="summary-row"><span>Phi van chuyen:</span>
                <span id="summary-shipping" style="font-weight:600;color:<?php echo $shipping==0 ? 'var(--success)' : 'inherit'; ?>">
                    <?php echo $shipping==0 ? 'Mien phi' : formatVND($shipping); ?>
                </span>
            </div>
            <div style="font-size:0.82rem;color:var(--text-muted);margin-bottom:20px;">Mien phi ship cho don tu 100.000 ₫!</div>
            <div class="summary-row-total">
                <span>Tong tien:</span>
                <span id="summary-grandtotal" class="total-price"><?php echo formatVND($total); ?></span>
            </div>
            <a href="/cart/checkout.php" class="btn btn-primary" style="width:100%;margin-top:30px;padding:15px;">Tien Hanh Dat Hang</a>
            <a href="/index/?type=products" class="btn btn-outline" style="width:100%;margin-top:10px;font-size:0.9rem;">Tiep tuc chon nuoc</a>
        </div>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:80px 20px;background:white;border-radius:var(--radius-lg);border:1px solid var(--border);">
        <div style="font-size:60px;margin-bottom:20px;">🛒</div>
        <h2>Gio hang cua ban dang trong!</h2>
        <p style="color:var(--text-muted);margin:10px 0 30px;">Hay quay lai menu de lua chon mon uong yeu thich.</p>
        <a href="/index/?type=products" class="btn btn-primary">Kham Pha Menu Ngay</a>
    </div>
    <?php endif; ?>
</section>

<script>
function updateCartQty(key, amount) {
    const inp = document.getElementById('qty-' + key);
    let qty = parseInt(inp.value) + amount;
    if (qty <= 0) { if(confirm('Xoa mon nay khoi gio hang?')) deleteCartItem(key); return; }
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
