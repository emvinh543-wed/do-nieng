<?php
require_once __DIR__ . '/../ThanhNgang/header.php';

if (!$current_user) {
    echo '<section class="section" style="max-width:500px;margin:60px auto;text-align:center;"><div style="font-size:60px;">🛒</div><h2>Khách Hàng Chưa Đăng Nhập</h2><p style="color:var(--text-muted);margin:10px 0 25px;">Vui lòng đăng nhập để xem lịch sử đơn hàng của bạn.</p><a href="/login/login_demo.php" class="btn btn-primary">Đăng Nhập Ngay</a></section>';
    require_once __DIR__ . '/../ThanhNgang/footer.php'; exit();
}

$user_id = $current_user['id'];
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$user_id]);
$my_orders = $stmt->fetchAll();

// Check if any order is in-progress (pending/processing/shipping)
$has_active = false;
foreach ($my_orders as $o) {
    if (in_array($o['order_status'], ['pending','processing','shipping'])) { $has_active = true; break; }
}
?>
<section class="section" style="max-width:1200px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 class="section-title" style="margin-bottom:5px;">Quản Lý <span>Đơn Hàng Của Tôi</span></h2>
            <p class="section-desc">Theo dõi tiến độ giao hàng và lịch sử mua hàng tại GlowDrinks</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;">
            <?php if ($has_active): ?>
            <button onclick="location.reload()" style="display:inline-flex;align-items:center;gap:6px;background:var(--light);border:1px solid var(--border);padding:8px 16px;border-radius:20px;font-size:0.85rem;font-weight:600;cursor:pointer;color:var(--dark);" title="Làm mới trạng thái">
                🔄 Cập nhật trạng thái
            </button>
            <?php endif; ?>
            <a href="/index/?type=products" class="btn btn-primary" style="padding:10px 20px;">+ Đặt Thêm Nước Mới</a>
        </div>
    </div>

    <?php if (count($my_orders) > 0): ?>
        <div style="display:flex;flex-direction:column;gap:18px;">
            <?php foreach ($my_orders as $ord):
                $d_stmt = $pdo->prepare("SELECT od.*, p.name, p.image FROM order_details od JOIN products p ON od.product_id = p.id WHERE od.order_id = ?");
                $d_stmt->execute([$ord['id']]);
                $items = $d_stmt->fetchAll();
                
                $created_ts = strtotime($ord['created_at']);
                $est_time = date('H:i', $created_ts + (25 * 60));
                $is_completed = $ord['order_status'] === 'completed';
                $is_paid      = $ord['payment_status'] === 'paid';

                // Border color by status
                $border_color = $is_completed ? '#22c55e' : ($ord['order_status']==='shipping' ? '#0284c7' : 'var(--border)');
                $card_bg      = $is_completed ? '#f0fdf4' : 'white';
            ?>
            <div style="background:<?php echo $card_bg; ?>;border-radius:var(--radius-lg);border:1.5px solid <?php echo $border_color; ?>;padding:22px 25px;box-shadow:var(--shadow-sm);position:relative;overflow:hidden;">
                
                <?php if ($is_completed): ?>
                <!-- Ribbon: Hoàn thành -->
                <div style="position:absolute;top:0;right:0;background:#22c55e;color:white;font-size:0.72rem;font-weight:800;padding:4px 14px;border-bottom-left-radius:10px;letter-spacing:0.5px;">✅ GIAO THÀNH CÔNG</div>
                <?php elseif ($ord['order_status']==='shipping'): ?>
                <div style="position:absolute;top:0;right:0;background:#0284c7;color:white;font-size:0.72rem;font-weight:800;padding:4px 14px;border-bottom-left-radius:10px;">🛵 ĐANG GIAO</div>
                <?php endif; ?>

                <!-- Header: ID + Ngày + Trạng thái -->
                <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid <?php echo $is_completed ? '#bbf7d0' : 'var(--border)'; ?>;padding-bottom:14px;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                    <div>
                        <strong style="font-size:1.1rem;color:var(--dark);">Đơn hàng #<?php echo $ord['id']; ?></strong>
                        <span style="font-size:0.85rem;color:var(--text-muted);margin-left:10px;">Ngày đặt: <?php echo date('d/m/Y H:i', $created_ts); ?></span>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <!-- Order Status Badge -->
                        <?php if ($ord['order_status']==='pending'): ?>
                            <span class="status-badge status-pending" style="font-size:0.8rem;">⏳ Chờ duyệt đơn</span>
                        <?php elseif ($ord['order_status']==='processing'): ?>
                            <span class="status-badge" style="background:#e0f2fe;color:#0284c7;font-size:0.8rem;">🍵 Đang pha chế</span>
                        <?php elseif ($ord['order_status']==='shipping'): ?>
                            <span class="status-badge" style="background:#dbeafe;color:#0284c7;font-size:0.8rem;">🛵 Đang giao hàng</span>
                        <?php elseif ($ord['order_status']==='completed'): ?>
                            <span class="status-badge status-completed" style="font-size:0.8rem;background:#dcfce7;color:#15803d;">✅ Đã giao xong</span>
                        <?php else: ?>
                            <span class="status-badge status-cancelled" style="font-size:0.8rem;">❌ Đã hủy</span>
                        <?php endif; ?>

                        <!-- Payment Badge -->
                        <?php if ($is_paid): ?>
                            <span style="background:#d1fae5;color:#059669;padding:4px 10px;border-radius:20px;font-size:0.8rem;font-weight:700;border:1px solid #86efac;">💳 Đã Thanh Toán</span>
                        <?php else: ?>
                            <span style="background:#fee2e2;color:#dc2626;padding:4px 10px;border-radius:20px;font-size:0.8rem;font-weight:700;border:1px solid #fca5a5;">⚠️ Chưa Thanh Toán</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Nội dung: Sản phẩm + Thông tin giao hàng -->
                <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:center;">
                    <!-- Danh sách sản phẩm -->
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <?php foreach ($items as $it): ?>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <img src="<?php echo getProductImage($it['image']); ?>" 
                                 style="width:44px;height:44px;border-radius:8px;object-fit:cover;border:1px solid var(--border);" 
                                 onerror="this.src='/anh/trasuatranchau.png'">
                            <div>
                                <strong style="font-size:0.92rem;"><?php echo $it['quantity']; ?>x <?php echo htmlspecialchars($it['name']); ?></strong>
                                <span style="font-size:0.8rem;color:var(--text-muted);margin-left:8px;">Size: <?php echo $it['size']; ?></span>
                                <?php if (!empty($it['toppings'])): ?>
                                    <span style="font-size:0.78rem;color:var(--text-muted);">| +<?php echo htmlspecialchars($it['toppings']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Thông tin thanh toán & giao hàng -->
                    <div style="border-left:1px solid <?php echo $is_completed ? '#bbf7d0' : 'var(--border)'; ?>;padding-left:20px;display:flex;flex-direction:column;gap:8px;">
                        
                        <?php if ($is_completed): ?>
                        <!-- Đã hoàn thành: hiện banner xanh -->
                        <div style="background:#dcfce7;border:1px solid #86efac;padding:8px 12px;border-radius:8px;text-align:center;margin-bottom:4px;">
                            <div style="font-size:1.3rem;">✅</div>
                            <div style="font-size:0.82rem;font-weight:800;color:#15803d;">Đã Giao Hàng & Thanh Toán Thành Công!</div>
                            <div style="font-size:0.75rem;color:#16a34a;margin-top:2px;">Đơn hàng hoàn thành lúc <?php echo $est_time; ?></div>
                        </div>
                        <?php else: ?>
                        <div style="font-size:0.85rem;color:var(--text-muted);">
                            Dự kiến giao: <strong style="color:var(--primary);font-size:1rem;"><?php echo $est_time; ?></strong>
                        </div>
                        <?php endif; ?>

                        <div style="font-size:0.85rem;color:var(--text-muted);">
                            Phương thức: <strong style="color:var(--dark);"><?php echo $ord['payment_method']; ?></strong>
                        </div>

                        <div style="font-size:1.2rem;font-weight:800;color:<?php echo $is_completed ? '#15803d' : 'var(--primary)'; ?>;">
                            <?php echo formatVND($ord['total_amount']); ?>
                        </div>

                        <a href="/cart/order_success.php?id=<?php echo $ord['id']; ?>" 
                           class="btn <?php echo $is_completed ? 'btn-primary' : 'btn-outline'; ?>" 
                           style="padding:7px 14px;font-size:0.82rem;text-align:center;margin-top:4px;<?php echo $is_completed ? 'background:#22c55e;border-color:#22c55e;' : ''; ?>">
                            <?php echo $is_completed ? '📋 Xem Chi Tiết Đơn' : '📱 Theo Dõi & Mã QR'; ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align:center;padding:80px 20px;background:white;border-radius:var(--radius-lg);border:1px solid var(--border);">
            <div style="font-size:60px;margin-bottom:20px;">📦</div>
            <h2>Bạn chưa có đơn hàng nào!</h2>
            <p style="color:var(--text-muted);margin:10px 0 30px;">Khám phá menu nước tuyệt ngon tại GlowDrinks ngay hôm nay.</p>
            <a href="/index/?type=products" class="btn btn-primary">Khám Phá Menu Ngay</a>
        </div>
    <?php endif; ?>
</section>

<!-- Tự động cập nhật trạng thái nếu có đơn đang giao -->
<?php if ($has_active): ?>
<script>
// Tự reload sau 30 giây nếu có đơn đang xử lý/giao
setTimeout(function() {
    location.reload();
}, 30000);
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../ThanhNgang/footer.php'; ?>
