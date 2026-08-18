<!-- ===== CATEGORIES COMPONENT ===== -->
<?php
// Expects $categories array and $category_id, $search variables
$cat_icons = ['☕', '🧋', '🍵', '🧊', '🍡', '🍽️'];
?>
<div class="cat-section">
    <div class="cat-title-row">
        <h2 class="sec-title" data-i18n="categories_title">Danh Mục <span>Thực Đơn</span></h2>
        <a href="/index/?type=products" class="view-all" data-i18n="categories_view_all">Xem tất cả →</a>
    </div>
    <div class="cat-grid">
        <a href="/index/" class="cat-card <?php echo ($category_id == 0 && empty($search)) ? 'active' : ''; ?>">
            <span class="cat-emoji">🍽️</span>
            <div class="cat-name" data-i18n="categories_all">Tất Cả</div>
            <div class="cat-count" data-i18n="categories_all_count">Toàn bộ món</div>
        </a>
        <?php foreach ($categories as $i => $cat): ?>
        <a href="/index/?category_id=<?php echo $cat['id']; ?>" class="cat-card <?php echo ($category_id == $cat['id']) ? 'active' : ''; ?>">
            <span class="cat-emoji"><?php echo $cat_icons[$i % count($cat_icons)]; ?></span>
            <div class="cat-name"><?php echo htmlspecialchars($cat['name']); ?></div>
            <div class="cat-count"><?php echo $cat['total_products']; ?> <span data-i18n="item_label">món</span></div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
