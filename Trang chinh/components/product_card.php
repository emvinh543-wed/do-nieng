<!-- ===== PRODUCT CARD COMPONENT ===== -->
<?php
// Expects $prod array
$hasDiscount  = ($prod['discount_price'] > 0);
$displayPrice = $hasDiscount ? $prod['discount_price'] : $prod['price'];
$pct = $hasDiscount ? round((($prod['price'] - $prod['discount_price']) / $prod['price']) * 100) : 0;
?>
<div class="product-card" data-product-id="<?php echo $prod['id']; ?>">
    <?php if ($hasDiscount): ?>
        <span class="product-badge">-<?php echo $pct; ?>% <span data-i18n="product_off_label">Off</span></span>
    <?php elseif (!empty($prod['is_featured'])): ?>
        <span class="product-badge badge-hot" data-i18n="product_badge_hot">🔥 Bán chạy</span>
    <?php endif; ?>

    <div class="product-image-box">
        <a href="/detail.php?id=<?php echo $prod['id']; ?>" style="width:100%;height:100%;display:block;">
            <img src="<?php echo getProductImage($prod['image']); ?>"
                 alt="<?php echo htmlspecialchars($prod['name']); ?>"
                 loading="lazy"
                 onerror="this.src='https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=500&auto=format&fit=crop&q=60'">
            <div class="img-overlay"></div>
        </a>
    </div>

    <div class="product-info">
        <span class="product-cat"><?php echo htmlspecialchars($prod['category_name']); ?></span>
        <h3 class="product-title" data-product-name>
            <a href="/detail.php?id=<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['name']); ?></a>
        </h3>
        <p class="product-desc" data-product-desc><?php echo htmlspecialchars($prod['description']); ?></p>
        <div class="product-footer">
            <div class="price-box">
                <?php if ($hasDiscount): ?>
                    <span class="old-price"><?php echo formatVND($prod['price']); ?></span>
                <?php endif; ?>
                <span class="price"><?php echo formatVND($displayPrice); ?></span>
            </div>
            <button onclick="addToCart(<?php echo $prod['id']; ?>)" class="btn-add-cart" title="Thêm vào giỏ" data-i18n="add_to_cart">
                <svg viewBox="0 0 24 24"><path d="M9 22a1 1 0 100-2 1 1 0 000 2zM17 22a1 1 0 100-2 1 1 0 000 2z"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
            </button>
        </div>
    </div>
</div>
