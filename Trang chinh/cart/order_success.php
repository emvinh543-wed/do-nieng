<?php
require_once __DIR__ . '/../config/config.php';

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($order_id <= 0) { 
    header("Location: /index/"); 
    exit(); 
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) { 
    header("Location: /index/"); 
    exit(); 
}

require_once __DIR__ . '/../ThanhNgang/header.php';

// Confirmed payment trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_qr_paid'])) {
    $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$order_id]);
    $order['payment_status'] = 'paid';
    $confirm_msg = "Cảm ơn bạn! Đã xác nhận thông tin thanh toán chuyển khoản cho đơn hàng #$order_id.";
}

// Load order details
$details_stmt = $pdo->prepare("SELECT od.*, p.name, p.image FROM order_details od JOIN products p ON od.product_id = p.id WHERE od.order_id = ?");
$details_stmt->execute([$order_id]);
$items = $details_stmt->fetchAll();

// Calculate delivery time (Current time + 25 mins)
$created_timestamp = strtotime($order['created_at']);
$delivery_from = date('H:i', $created_timestamp + (20 * 60));
$delivery_to   = date('H:i', $created_timestamp + (30 * 60));
$target_end_time = $created_timestamp + (25 * 60);

// Default coordinates (Store: 123 Ba Thang Hai, Q10, HCM)
$store_lat = 10.7719;
$store_lng = 106.6710;

// Estimate customer location coordinates based on order ID
$cust_lat = $store_lat + (0.005 + ($order_id % 7) * 0.003);
$cust_lng = $store_lng + (0.008 + ($order_id % 5) * 0.004);

// Pool of shippers – mỗi đơn hàng được gán người ship khác nhau
$shippers = [
    [
        'name'    => 'Nguyễn Văn Tài',
        'phone'   => '0912.345.678',
        'tel'     => '0912345678',
        'plate'   => '59-P1 123.45',
        'vehicle' => 'Honda Wave Alpha',
        'rating'  => '4.9',
        'orders'  => '238',
        'avatar'  => '/anh/shipper.png',
        'fallback'=> 'Nguyen+Van+Tai',
    ],
    [
        'name'    => 'Trần Thị Lan',
        'phone'   => '0908.765.432',
        'tel'     => '0908765432',
        'plate'   => '59-G2 456.78',
        'vehicle' => 'Honda Air Blade',
        'rating'  => '4.8',
        'orders'  => '184',
        'avatar'  => '/anh/shipper2.png',
        'fallback'=> 'Tran+Thi+Lan',
    ],
    [
        'name'    => 'Phạm Văn Hùng',
        'phone'   => '0934.111.222',
        'tel'     => '0934111222',
        'plate'   => '51-AA 789.01',
        'vehicle' => 'Yamaha Exciter',
        'rating'  => '4.7',
        'orders'  => '310',
        'avatar'  => '/anh/shipper3.png',
        'fallback'=> 'Pham+Van+Hung',
    ],
    [
        'name'    => 'Lê Minh Khôi',
        'phone'   => '0978.333.555',
        'tel'     => '0978333555',
        'plate'   => '59-B3 321.99',
        'vehicle' => 'Suzuki Raider',
        'rating'  => '4.9',
        'orders'  => '421',
        'avatar'  => '/anh/shipper4.png',
        'fallback'=> 'Le+Minh+Khoi',
    ],
];
$shipper = $shippers[$order_id % count($shippers)];
?>
<!-- Leaflet CSS & JS for Live Map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
@keyframes popIn {
    0%   { transform: scale(0.5); opacity: 0; }
    80%  { transform: scale(1.05); }
    100% { transform: scale(1); opacity: 1; }
}
@keyframes fadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}
@keyframes confettiFall {
    0%   { transform: translateY(0) rotate(0deg); opacity:1; }
    100% { transform: translateY(60vh) rotate(720deg); opacity:0; }
}
@keyframes bounce {
    from { transform: translateY(0); }
    to   { transform: translateY(-8px); }
}
.live-dot { animation: livePulse 1.4s ease-in-out infinite; }
@keyframes livePulse {
    0%,100% { opacity:1; transform:scale(1); }
    50%     { opacity:0.4; transform:scale(1.5); }
}

/* KHU VỰC BẢN ĐỒ & SHIPPER PRO */
#delivery-map {
    width: 100%;
    height: 460px;
    border-radius: 18px;
    border: 1px solid #cbd5e1;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    position: relative;
    z-index: 1;
    background: #e2e8f0;
}

/* Shipper marker live radar pulse */
.shipper-marker-container {
    position: relative;
    width: 68px;
    height: 68px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.shipper-radar-ring {
    position: absolute;
    width: 76px;
    height: 76px;
    border-radius: 50%;
    background: rgba(2, 132, 199, 0.28);
    border: 2px solid #0284c7;
    animation: shipperRadarPulse 2s cubic-bezier(0.2, 0.6, 0.35, 1) infinite;
    pointer-events: none;
}
.shipper-radar-ring-inner {
    position: absolute;
    width: 76px;
    height: 76px;
    border-radius: 50%;
    background: rgba(16, 185, 129, 0.22);
    border: 1.5px dashed #10b981;
    animation: shipperRadarPulse 2s cubic-bezier(0.2, 0.6, 0.35, 1) infinite 0.8s;
    pointer-events: none;
}
@keyframes shipperRadarPulse {
    0%   { transform: scale(0.6); opacity: 1; }
    100% { transform: scale(1.85); opacity: 0; }
}
.shipper-avatar-wrapper {
    position: relative;
    width: 52px;
    height: 52px;
    border-radius: 50%;
    border: 3.5px solid #0284c7;
    background: #ffffff;
    box-shadow: 0 6px 20px rgba(2, 132, 199, 0.45);
    z-index: 2;
    overflow: visible;
    transition: transform 0.25s ease;
}
.shipper-marker-container:hover .shipper-avatar-wrapper {
    transform: scale(1.12);
}
.shipper-avatar-pic {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    display: block;
}
.shipper-vehicle-badge {
    position: absolute;
    bottom: -4px;
    right: -4px;
    width: 24px;
    height: 24px;
    background: #0284c7;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.25);
}
.shipper-floating-label {
    position: absolute;
    top: -38px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(15, 23, 42, 0.94);
    backdrop-filter: blur(6px);
    color: #ffffff;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.76rem;
    font-weight: 700;
    white-space: nowrap;
    box-shadow: 0 4px 14px rgba(0,0,0,0.3);
    display: flex;
    align-items: center;
    gap: 6px;
    pointer-events: none;
    z-index: 3;
    border: 1px solid rgba(255,255,255,0.25);
}
.shipper-floating-label::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 50%;
    transform: translateX(-50%);
    border-width: 5px 5px 0 5px;
    border-style: solid;
    border-color: rgba(15, 23, 42, 0.94) transparent transparent transparent;
}
.shipper-eta-pill {
    background: #0284c7;
    color: #ffffff;
    padding: 2px 7px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 800;
}

/* Map overlay toolbar */
.map-control-overlay {
    position: absolute;
    top: 14px;
    right: 14px;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.map-tool-btn {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 8px 14px;
    font-size: 0.82rem;
    font-weight: 700;
    color: #1e293b;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    display: flex;
    align-items: center;
    gap: 7px;
    transition: all 0.2s ease;
}
.map-tool-btn:hover {
    background: #0284c7;
    color: #ffffff;
    border-color: #0284c7;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(2,132,199,0.35);
}
.map-tool-btn-demo {
    background: #fffbeb;
    border-color: #f59e0b;
    color: #b45309;
}
.map-tool-btn-demo:hover {
    background: #f59e0b;
    color: #ffffff;
    border-color: #f59e0b;
}

/* Store and Customer Pins */
.custom-pin-store, .custom-pin-cust {
    position: relative;
    text-align: center;
}
.pin-label-tag {
    background: rgba(255,255,255,0.96);
    border: 1.5px solid #cbd5e1;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.74rem;
    font-weight: 800;
    white-space: nowrap;
    box-shadow: 0 3px 10px rgba(0,0,0,0.18);
    margin-bottom: 2px;
    display: inline-block;
}
</style>

<section class="section" style="max-width:1100px;">
    <?php if (isset($confirm_msg)): ?>
        <div style="background:#d1fae5;color:#059669;padding:15px 20px;border-radius:var(--radius-md);margin-bottom:25px;font-weight:700;display:flex;align-items:center;gap:10px;">
            ✅ <?php echo $confirm_msg; ?>
        </div>
    <?php endif; ?>

    <!-- Banner Đặt hàng thành công -->
    <div style="background:white;padding:30px;border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);border:1px solid var(--border);text-align:center;margin-bottom:25px;">
        <div style="font-size:60px;line-height:1;margin-bottom:12px;">🎉</div>
        <h2 style="font-size:1.8rem;color:var(--primary);font-weight:800;margin-bottom:6px;">Đặt Hàng Thành Công!</h2>
        <p style="color:var(--text-muted);font-size:1.05rem;">Mã đơn hàng của bạn: <strong style="color:var(--dark);">#<?php echo $order['id']; ?></strong></p>
    </div>

    <!-- KHU VỰC 1: THỜI GIAN GIAO HÀNG DỰ KIẾN (DELIVERY TRACKING TIME) -->
    <div style="background:white;padding:30px;border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);border:1px solid var(--border);margin-bottom:25px;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:15px;">
            <div>
                <h3 style="font-size:1.25rem;font-weight:800;color:var(--dark);display:flex;align-items:center;gap:8px;">
                    <span>🛵</span> Thời Gian Giao Hàng Dự Kiến
                </h3>
                <p style="color:var(--text-muted);font-size:0.9rem;margin-top:4px;">GlowDrinks giao nước siêu tốc trong vòng 20 - 30 phút</p>
            </div>
            <div style="background:var(--primary-light);color:var(--primary);padding:10px 20px;border-radius:var(--radius-md);text-align:right;">
                <div style="font-size:0.8rem;font-weight:700;text-transform:uppercase;">Dự kiến giao lúc</div>
                <div style="font-size:1.4rem;font-weight:800;"><?php echo $delivery_from; ?> - <?php echo $delivery_to; ?></div>
            </div>
        </div>

        <!-- Live Countdown Clock -->
        <div style="background:#fff8e8;border:1px dashed var(--primary);padding:18px;border-radius:var(--radius-md);text-align:center;margin-bottom:25px;">
            <div style="font-size:0.9rem;font-weight:700;color:var(--dark);margin-bottom:4px;">⏱️ Thời Gian Đếm Ngược Giao Hàng</div>
            <div id="countdown-timer" style="font-size:2.2rem;font-weight:800;color:var(--primary);letter-spacing:1px;">24:59</div>
            <div style="font-size:0.82rem;color:var(--text-muted);margin-top:4px;">Đội ngũ shipper đang di chuyển mang đồ uống tới địa chỉ của bạn</div>
        </div>

        <!-- Progress Tracker Bar -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;text-align:center;position:relative;">
            <div style="display:flex;flex-direction:column;align-items:center;">
                <div style="width:44px;height:44px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:800;margin-bottom:8px;box-shadow:0 4px 10px rgba(245,166,35,0.4);">📝</div>
                <span style="font-weight:700;font-size:0.88rem;color:var(--dark);">Đã nhận đơn</span>
                <span style="font-size:0.75rem;color:var(--success);font-weight:600;margin-top:2px;">Hoàn thành</span>
            </div>
            <div style="display:flex;flex-direction:column;align-items:center;">
                <div style="width:44px;height:44px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:800;margin-bottom:8px;box-shadow:0 4px 10px rgba(245,166,35,0.4);">🍵</div>
                <span style="font-weight:700;font-size:0.88rem;color:var(--dark);">Đang pha chế</span>
                <span style="font-size:0.75rem;color:var(--success);font-weight:600;margin-top:2px;">Hoàn thành</span>
            </div>
            <div id="step-shipping" style="display:flex;flex-direction:column;align-items:center;">
                <div style="width:44px;height:44px;border-radius:50%;background:#0284c7;color:white;display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:800;margin-bottom:8px;box-shadow:0 4px 10px rgba(2,132,199,0.4);">🛵</div>
                <span style="font-weight:700;font-size:0.88rem;color:#0284c7;">Đang giao hàng</span>
                <span id="step-shipping-label" style="font-size:0.75rem;color:#0284c7;font-weight:700;margin-top:2px;">Đang di chuyển</span>
            </div>
            <div id="step-done" style="display:flex;flex-direction:column;align-items:center;">
                <div id="step-done-icon" style="width:44px;height:44px;border-radius:50%;background:#e2e8f0;color:#64748b;display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:800;margin-bottom:8px;">✨</div>
                <span id="step-done-label" style="font-weight:600;font-size:0.88rem;color:var(--text-muted);">Đã giao nước</span>
                <span id="step-done-sublabel" style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">Chờ đến nơi</span>
            </div>
        </div>
    </div>

    <!-- MODAL: ĐÃ GIAO HÀNG THÀNH CÔNG (ẩn, hiện khi shipper tới nơi) -->
    <div id="delivered-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;justify-content:center;align-items:center;animation:fadeIn 0.4s ease;">
        <div style="background:white;border-radius:24px;padding:40px 45px;max-width:460px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.25);position:relative;animation:popIn 0.5s cubic-bezier(0.34,1.56,0.64,1);">
            <!-- Nút đóng -->
            <button onclick="document.getElementById('delivered-overlay').style.display='none'" style="position:absolute;top:14px;right:18px;background:none;border:none;font-size:1.4rem;cursor:pointer;color:#94a3b8;">✕</button>

            <!-- Icon xác nhận -->
            <div style="width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,#22c55e,#16a34a);display:flex;align-items:center;justify-content:center;font-size:2.8rem;margin:0 auto 20px;box-shadow:0 8px 24px rgba(34,197,94,0.35);">✅</div>

            <h2 style="font-size:1.7rem;font-weight:800;color:#15803d;margin-bottom:8px;">Giao Hàng Thành Công!</h2>
            <p style="color:#64748b;font-size:1rem;margin-bottom:22px;">Shipper <strong><?php echo htmlspecialchars($shipper['name']); ?></strong> đã giao đồ uống tới địa chỉ của bạn!</p>

            <!-- Thông tin đơn hàng -->
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:16px 20px;margin-bottom:22px;text-align:left;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                    <img src="<?php echo $shipper['avatar']; ?>" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid #22c55e;" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($shipper['fallback']); ?>&background=22c55e&color=fff&size=48'">
                    <div>
                        <div style="font-weight:700;font-size:0.95rem;color:#15803d;"><?php echo htmlspecialchars($shipper['name']); ?></div>
                        <div style="font-size:0.82rem;color:#64748b;">📞 <?php echo $shipper['phone']; ?> &nbsp;|&nbsp; 🏍️ <?php echo $shipper['plate']; ?></div>
                    </div>
                </div>
                <div style="font-size:0.88rem;color:#374151;">
                    📍 Đã giao tới: <strong><?php echo htmlspecialchars($order['address']); ?></strong>
                </div>
                <div style="font-size:0.88rem;color:#374151;margin-top:4px;">
                    🕐 Thời gian giao: <strong id="delivered-time">--:--</strong>
                </div>
            </div>

            <!-- Rating / Đánh giá -->
            <div style="margin-bottom:22px;">
                <div style="font-size:0.88rem;color:#64748b;margin-bottom:8px;font-weight:600;">Bạn cảm thấy dịch vụ thế nào?</div>
                <div style="display:flex;justify-content:center;gap:8px;font-size:2rem;" id="star-rating">
                    <span onclick="rateStar(1)" style="cursor:pointer;transition:transform 0.2s;" title="1 sao">⭐</span>
                    <span onclick="rateStar(2)" style="cursor:pointer;transition:transform 0.2s;" title="2 sao">⭐</span>
                    <span onclick="rateStar(3)" style="cursor:pointer;transition:transform 0.2s;" title="3 sao">⭐</span>
                    <span onclick="rateStar(4)" style="cursor:pointer;transition:transform 0.2s;" title="4 sao">⭐</span>
                    <span onclick="rateStar(5)" style="cursor:pointer;transition:transform 0.2s;" title="5 sao">⭐</span>
                </div>
                <div id="rating-msg" style="font-size:0.82rem;color:#22c55e;font-weight:700;margin-top:6px;min-height:18px;"></div>
            </div>

            <div style="display:flex;gap:10px;justify-content:center;">
                <a href="/cart/my_orders.php" class="btn btn-outline" style="padding:10px 22px;">📋 Xem Đơn Hàng</a>
                <a href="/index/?type=products" class="btn btn-primary" style="padding:10px 22px;">🥤 Mua Thêm</a>
            </div>
        </div>
    </div>

    <!-- KHU VỰC BẢN ĐỒ GIAO HÀNG TRỰC TIẾP (LIVE DELIVERY ROUTE MAP) -->
    <div style="background:white;padding:30px;border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);border:1px solid var(--border);margin-bottom:25px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
            <div>
                <h3 style="font-size:1.3rem;font-weight:800;color:var(--dark);display:flex;align-items:center;gap:10px;">
                    <span>🗺️</span> Bản Đồ Theo Dõi Người Giao Hàng (Shipper)
                </h3>
                <p style="color:var(--text-muted);font-size:0.88rem;margin-top:3px;">Theo dõi vị trí shipper trên lộ trình từ Cửa hàng GlowDrinks tới địa chỉ của bạn</p>
            </div>
            <div style="background:#f0f9ff;border:1px solid #bae6fd;padding:8px 16px;border-radius:20px;font-size:0.85rem;color:#0284c7;font-weight:700;display:flex;align-items:center;gap:8px;">
                <span class="live-dot" style="display:inline-block;width:9px;height:9px;background:#0284c7;border-radius:50%;"></span>
                Đang truyền tọa độ Shipper trực tiếp
            </div>
        </div>

        <!-- Khung bản đồ Leaflet Map -->
        <div id="delivery-map"></div>

        <!-- Tuyến đường: Điểm đi / Điểm đến -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:18px;">
            <div style="background:#f8fafc;padding:14px 18px;border-radius:var(--radius-md);border:1px solid #e2e8f0;display:flex;align-items:center;gap:12px;">
                <span style="font-size:2rem;">🏬</span>
                <div>
                    <span style="font-size:0.75rem;color:#0d9488;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;">Điểm đi – Cửa Hàng</span><br>
                    <strong style="font-size:0.92rem;color:var(--dark);">GlowDrinks Central Store</strong>
                    <p style="font-size:0.82rem;color:var(--text-muted);margin:2px 0 0;">123 Đường Ba Tháng Hai, P.11, Q.10, TP.HCM</p>
                </div>
            </div>
            <div style="background:#fffbeb;padding:14px 18px;border-radius:var(--radius-md);border:1px solid #fef3c7;display:flex;align-items:center;gap:12px;">
                <span style="font-size:2rem;">📍</span>
                <div>
                    <span style="font-size:0.75rem;color:#d97706;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;">Điểm đến – Khách Hàng</span><br>
                    <strong style="font-size:0.92rem;color:var(--primary);"><?php echo htmlspecialchars($order['fullname']); ?></strong>
                    <p style="font-size:0.82rem;color:var(--text-muted);margin:2px 0 0;"><?php echo htmlspecialchars($order['address']); ?></p>
                </div>
            </div>
        </div>

        <!-- THẺ THÔNG TIN NGƯỜI GIAO HÀNG (SHIPPER PROFILE CARD) -->
        <div style="margin-top:18px;background:linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);border:2px solid #38bdf8;border-radius:var(--radius-lg);padding:22px 25px;display:flex;align-items:center;gap:22px;flex-wrap:wrap;box-shadow:0 8px 24px rgba(2,132,199,0.08);">
            <!-- Avatar shipper với vòng trạng thái -->
            <div style="position:relative;flex-shrink:0;">
                <img src="<?php echo $shipper['avatar']; ?>" alt="Ảnh Người Giao Hàng"
                     style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3.5px solid #0284c7;box-shadow:0 6px 16px rgba(2,132,199,0.35);"
                     onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($shipper['fallback']); ?>&background=0284c7&color=fff&size=90&rounded=true'">
                <span style="position:absolute;bottom:4px;right:4px;width:18px;height:18px;background:#22c55e;border-radius:50%;border:2.5px solid white;" title="Đang trực tuyến & giao hàng"></span>
            </div>
            <!-- Thông tin chi tiết shipper -->
            <div style="flex:1;min-width:220px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <span style="background:#e0f2fe;color:#0284c7;padding:3px 10px;border-radius:12px;font-size:0.75rem;font-weight:800;text-transform:uppercase;">🛵 Người Giao Hàng Phụ Trách</span>
                    <span id="shipper-status-badge" style="background:#dcfce7;color:#15803d;padding:3px 10px;border-radius:12px;font-size:0.75rem;font-weight:700;">🟢 Đang di chuyển trên đường</span>
                </div>
                <div style="font-size:1.3rem;font-weight:800;color:var(--dark);margin-bottom:6px;">
                    <?php echo htmlspecialchars($shipper['name']); ?>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <a href="tel:<?php echo $shipper['tel']; ?>" style="display:inline-flex;align-items:center;gap:6px;background:#0284c7;color:white;padding:7px 16px;border-radius:20px;font-size:0.88rem;font-weight:700;text-decoration:none;box-shadow:0 3px 10px rgba(2,132,199,0.3);transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.04)'" onmouseout="this.style.transform='scale(1)'">
                        📞 Gọi <?php echo $shipper['phone']; ?>
                    </a>
                    <a href="sms:<?php echo $shipper['tel']; ?>" style="display:inline-flex;align-items:center;gap:6px;background:white;color:#0284c7;border:1.5px solid #0284c7;padding:6px 14px;border-radius:20px;font-size:0.85rem;font-weight:700;text-decoration:none;">
                        💬 Nhắn Tin
                    </a>
                    <span style="background:white;border:1px solid #cbd5e1;padding:6px 12px;border-radius:20px;font-size:0.82rem;color:var(--dark);font-weight:600;">🏍️ <?php echo $shipper['vehicle']; ?> (<?php echo $shipper['plate']; ?>)</span>
                    <span style="background:#fef3c7;border:1px solid #fde68a;padding:6px 12px;border-radius:20px;font-size:0.82rem;color:#b45309;font-weight:700;">⭐ <?php echo $shipper['rating']; ?>/5 (<?php echo $shipper['orders']; ?> chuyến)</span>
                </div>
            </div>
            <!-- Đồng hồ khoảng cách & ETA còn lại -->
            <div style="text-align:center;padding:14px 22px;background:white;border-radius:var(--radius-md);border:1.5px solid #bae6fd;box-shadow:0 4px 12px rgba(2,132,199,0.06);flex-shrink:0;">
                <div id="distance-display" style="font-size:1.6rem;font-weight:800;color:#0284c7;">~<?php echo number_format(1.5 + ($order_id % 8) * 0.4, 1); ?> km</div>
                <div style="font-size:0.76rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;">Khoảng cách còn lại</div>
                <div style="margin-top:6px;font-size:0.88rem;font-weight:800;color:var(--primary);">Dự kiến: <?php echo $delivery_from; ?> - <?php echo $delivery_to; ?></div>
            </div>
        </div>
    </div>

    <!-- KHU VỰC 2: MÃ QR THANH TOÁN NGÂN HÀNG (QR CODE PAYMENT SECTION) -->
    <?php if (in_array($order['payment_method'], ['QR_CODE', 'BANK_TRANSFER', 'MOMO'])): ?>
    <div style="background:white;padding:30px;border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);border:2px solid var(--primary);margin-bottom:25px;">
        <h3 style="font-size:1.3rem;font-weight:800;color:var(--dark);margin-bottom:20px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border);padding-bottom:12px;">
            <span>📱</span> Thanh Toán Qua Mã QR Ngân Hàng
            <?php if ($order['payment_status'] === 'paid'): ?>
                <span style="margin-left:auto;background:#d1fae5;color:#059669;padding:4px 12px;border-radius:20px;font-size:0.8rem;font-weight:700;">✅ Đã Thanh Toán</span>
            <?php else: ?>
                <span style="margin-left:auto;background:#fffbe0;color:#d97706;padding:4px 12px;border-radius:20px;font-size:0.8rem;font-weight:700;">⏳ Quét QR Thanh Toán</span>
            <?php endif; ?>
        </h3>

        <div style="display:grid;grid-template-columns:1fr 1.2fr;gap:25px;align-items:center;">
            <!-- Khung hiển thị ảnh Mã QR -->
            <div style="text-align:center;background:var(--light);padding:20px;border-radius:var(--radius-md);border:1px solid var(--border);">
                <?php
                $vietqr_url = "https://img.vietqr.io/image/MB-0987654321-compact2.png?amount=" . $order['total_amount'] . "&addInfo=DH" . $order['id'] . "&accountName=GLOWDRINKS%20STORE";
                ?>
                <img src="<?php echo $vietqr_url; ?>" 
                     onerror="this.src='/anh/qr_code.png'" 
                     alt="Mã QR Thanh Toán Ngân Hàng" 
                     style="max-width:240px;width:100%;height:auto;border-radius:12px;box-shadow:var(--shadow-md);border:2px solid white;">
                <p style="font-size:0.82rem;color:var(--text-muted);margin-top:12px;">Mở ứng dụng Ngân hàng (App Banking) bất kỳ để Quét QR</p>
            </div>

            <!-- Thông tin tài khoản chuyển khoản -->
            <div style="display:flex;flex-direction:column;gap:14px;">
                <div style="background:#f8fafc;padding:12px 16px;border-radius:var(--radius-sm);border:1px solid #e2e8f0;">
                    <span style="font-size:0.8rem;color:var(--text-muted);display:block;">Ngân hàng thụ hưởng:</span>
                    <strong style="font-size:1.05rem;color:var(--dark);">MBBank (Ngân Hàng Quân Đội)</strong>
                </div>

                <div style="background:#f8fafc;padding:12px 16px;border-radius:var(--radius-sm);border:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <span style="font-size:0.8rem;color:var(--text-muted);display:block;">Số tài khoản:</span>
                        <strong style="font-size:1.15rem;color:var(--primary);letter-spacing:1px;" id="acc-num">0987654321</strong>
                    </div>
                    <button type="button" onclick="navigator.clipboard.writeText('0987654321');alert('Đã sao chép số tài khoản!')" class="btn btn-outline" style="padding:4px 10px;font-size:0.78rem;">Sao Chép</button>
                </div>

                <div style="background:#f8fafc;padding:12px 16px;border-radius:var(--radius-sm);border:1px solid #e2e8f0;">
                    <span style="font-size:0.8rem;color:var(--text-muted);display:block;">Tên chủ tài khoản:</span>
                    <strong style="font-size:1rem;color:var(--dark);">GLOWDRINKS STORE</strong>
                </div>

                <div style="background:#f8fafc;padding:12px 16px;border-radius:var(--radius-sm);border:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <span style="font-size:0.8rem;color:var(--text-muted);display:block;">Số tiền chuyển khoản:</span>
                        <strong style="font-size:1.2rem;color:var(--primary);"><?php echo formatVND($order['total_amount']); ?></strong>
                    </div>
                </div>

                <div style="background:#fffbeb;padding:12px 16px;border-radius:var(--radius-sm);border:1px solid #fef3c7;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <span style="font-size:0.8rem;color:#b45309;display:block;">Nội dung chuyển khoản (Bắt buộc):</span>
                        <strong style="font-size:1.1rem;color:#b45309;">DH<?php echo $order['id']; ?></strong>
                    </div>
                    <button type="button" onclick="navigator.clipboard.writeText('DH<?php echo $order['id']; ?>');alert('Đã sao chép nội dung!')" class="btn btn-outline" style="padding:4px 10px;font-size:0.78rem;border-color:#b45309;color:#b45309;">Sao Chép</button>
                </div>

                <?php if ($order['payment_status'] !== 'paid'): ?>
                    <form action="/cart/order_success.php?id=<?php echo $order['id']; ?>" method="POST" style="margin-top:5px;">
                        <button type="submit" name="confirm_qr_paid" class="btn btn-primary" style="width:100%;padding:14px;font-weight:700;font-size:1rem;">
                            ✔ Tôi Đã Chuyển Khoản / Xác Nhận Thanh Toán
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- KHU VỰC 3: CHI TIẾT ĐƠN HÀNG VỪA ĐẶT -->
    <div style="background:white;padding:30px;border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);border:1px solid var(--border);margin-bottom:30px;">
        <h3 style="font-size:1.2rem;font-weight:800;color:var(--dark);margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:10px;">Chi Tiết Đồ Uống Trong Đơn</h3>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Hình ảnh</th>
                        <th>Món uống</th>
                        <th>Kích thước & Topping</th>
                        <th>Đơn giá</th>
                        <th>Số lượng</th>
                        <th>Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><img src="<?php echo getProductImage($item['image']); ?>" style="width:50px;height:50px;border-radius:8px;object-fit:cover;" onerror="this.src='/anh/trasuatranchau.png'"></td>
                        <td style="font-weight:700;"><?php echo htmlspecialchars($item['name']); ?></td>
                        <td style="font-size:0.85rem;">
                            <span>Size: <?php echo $item['size']; ?></span>
                            <?php if (!empty($item['toppings'])): ?>
                                <br><span style="color:var(--text-muted);">+ <?php echo htmlspecialchars($item['toppings']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:600;"><?php echo formatVND($item['price']); ?></td>
                        <td style="font-weight:700;"><?php echo $item['quantity']; ?> ly</td>
                        <td style="font-weight:700;color:var(--primary);"><?php echo formatVND($item['total_item_amount']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top:20px;border-top:1px solid var(--border);padding-top:15px;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <span style="font-size:0.9rem;color:var(--text-muted);">Người nhận: <strong><?php echo htmlspecialchars($order['fullname']); ?></strong> (<?php echo htmlspecialchars($order['phone']); ?>)</span><br>
                <span style="font-size:0.9rem;color:var(--text-muted);">Địa chỉ: <strong><?php echo htmlspecialchars($order['address']); ?></strong></span>
            </div>
            <div style="text-align:right;">
                <span style="font-size:0.9rem;color:var(--text-muted);">Tổng cộng thanh toán:</span>
                <span style="display:block;font-size:1.5rem;font-weight:800;color:var(--primary);"><?php echo formatVND($order['total_amount']); ?></span>
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:center;gap:15px;">
        <a href="/cart/my_orders.php" class="btn btn-outline" style="padding:12px 25px;">📋 Quản Lý Đơn Hàng Của Tôi</a>
        <a href="/index/?type=products" class="btn btn-primary" style="padding:12px 25px;">🥤 Tiếp Tục Chọn Nước</a>
    </div>
</section>

<!-- Countdown & Live Map Script -->
<script>
// 1. Countdown Timer
(function() {
    let secondsLeft = 25 * 60; // 25 min
    const timerElem = document.getElementById('countdown-timer');
    if (!timerElem) return;

    const interval = setInterval(function() {
        if (secondsLeft <= 0) {
            clearInterval(interval);
            timerElem.innerText = "00:00 - Đã tới thời gian giao hàng!";
            return;
        }
        secondsLeft--;
        let m = Math.floor(secondsLeft / 60);
        let s = secondsLeft % 60;
        timerElem.innerText = (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
    }, 1000);
})();

// 2. Leaflet Delivery Route Map & Live Shipper Tracking
document.addEventListener("DOMContentLoaded", function() {
    const storeLat = <?php echo $store_lat; ?>;
    const storeLng = <?php echo $store_lng; ?>;
    const custLat  = <?php echo $cust_lat; ?>;
    const custLng  = <?php echo $cust_lng; ?>;

    // Center map between Store and Customer
    const centerLat = (storeLat + custLat) / 2;
    const centerLng = (storeLng + custLng) / 2;
    const map = L.map('delivery-map', {
        zoomControl: true,
        scrollWheelZoom: true
    }).setView([centerLat, centerLng], 14);

    // Humanitarian OpenStreetMap Tile Layer - Crisp, full Vietnamese street names, NO watermark
    const hotTileLayer = L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
        maxZoom: 19,
        subdomains: ['a', 'b'],
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Tiles &copy; <a href="https://www.hotosm.org/">HOT</a>'
    }).addTo(map);

    // Fallback to Esri World Street if tile load fails
    hotTileLayer.on('tileerror', function() {
        if (!window._esriFallbackLoaded) {
            window._esriFallbackLoaded = true;
            console.warn('[GlowDrinks] Switching to fallback Esri tile layer...');
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 18,
                attribution: '&copy; Esri &mdash; GlowDrinks Map'
            }).addTo(map);
        }
    });

    // Invalidate size to ensure container fits perfectly
    setTimeout(function() { map.invalidateSize(); }, 300);

    // Realistic waypoints along streets in HCMC
    const dLat = custLat - storeLat;
    const dLng = custLng - storeLng;
    const waypoints = [
        [storeLat, storeLng],
        [storeLat + dLat * 0.12, storeLng + dLng * 0.05],
        [storeLat + dLat * 0.28, storeLng + dLng * 0.18],
        [storeLat + dLat * 0.45, storeLng + dLng * 0.40],
        [storeLat + dLat * 0.65, storeLng + dLng * 0.62],
        [storeLat + dLat * 0.82, storeLng + dLng * 0.80],
        [storeLat + dLat * 0.94, storeLng + dLng * 0.92],
        [custLat, custLng]
    ];

    // High-contrast white casing behind route
    L.polyline(waypoints, {
        color: '#ffffff',
        weight: 9,
        opacity: 0.95,
        lineCap: 'round',
        lineJoin: 'round'
    }).addTo(map);

    // Remaining path (dashed vibrant amber)
    L.polyline(waypoints, {
        color: '#f59e0b',
        weight: 5,
        opacity: 0.9,
        dashArray: '8, 8',
        lineCap: 'round',
        lineJoin: 'round'
    }).addTo(map);

    // Completed path traveled by shipper (solid vibrant emerald green)
    const traveledLine = L.polyline([[storeLat, storeLng]], {
        color: '#10b981',
        weight: 5,
        opacity: 0.95,
        lineCap: 'round',
        lineJoin: 'round'
    }).addTo(map);

    // Store Marker (Point A)
    const storeIcon = L.divIcon({
        html: `<div class="custom-pin-store">
                 <div class="pin-label-tag" style="border-color:#0d9488;color:#0d9488;">🏬 GlowDrinks Central</div>
                 <div style="font-size:32px;line-height:1;filter:drop-shadow(0 4px 8px rgba(0,0,0,0.3));">🏬</div>
               </div>`,
        className: '',
        iconSize: [150, 56],
        iconAnchor: [75, 56]
    });
    L.marker([storeLat, storeLng], {icon: storeIcon})
     .addTo(map)
     .bindPopup('<b>🏬 GlowDrinks Central Store</b><br>123 Đường Ba Tháng Hai, P.11, Q.10, TP.HCM<br><span style="color:#0d9488;font-weight:700;">Điểm bắt đầu giao hàng</span>');

    // Customer Marker (Point B)
    const custIcon = L.divIcon({
        html: `<div class="custom-pin-cust">
                 <div class="pin-label-tag" style="border-color:#e11d48;color:#e11d48;">📍 <?php echo htmlspecialchars(addslashes($order['fullname'])); ?></div>
                 <div style="font-size:34px;line-height:1;filter:drop-shadow(0 4px 8px rgba(0,0,0,0.35));">📍</div>
               </div>`,
        className: '',
        iconSize: [160, 58],
        iconAnchor: [80, 58]
    });
    L.marker([custLat, custLng], {icon: custIcon})
     .addTo(map)
     .bindPopup('<b>📍 Điểm nhận hàng của bạn</b><br><b><?php echo htmlspecialchars(addslashes($order['fullname'])); ?></b><br><?php echo htmlspecialchars(addslashes($order['address'])); ?>');

    // Shipper Data
    const shipperAvatar  = '<?php echo $shipper['avatar']; ?>';
    const shipperFallback= 'https://ui-avatars.com/api/?name=<?php echo urlencode($shipper['fallback']); ?>&background=0284c7&color=fff&size=52&rounded=true';
    const shipperName    = '<?php echo addslashes(htmlspecialchars($shipper['name'])); ?>';
    const shipperPhone   = '<?php echo $shipper['phone']; ?>';
    const shipperTel     = '<?php echo $shipper['tel']; ?>';
    const shipperPlate   = '<?php echo $shipper['plate']; ?>';
    const shipperVehicle = '<?php echo $shipper['vehicle']; ?>';
    const shipperRating  = '<?php echo $shipper['rating']; ?>';

    // Rich Shipper Marker: Radar ripple ring, floating nametag, avatar and motorcycle badge
    const shipperIcon = L.divIcon({
        html: `<div class="shipper-marker-container">
                 <div class="shipper-radar-ring"></div>
                 <div class="shipper-radar-ring-inner"></div>
                 <div class="shipper-floating-label">
                   <span class="live-dot" style="display:inline-block;width:7px;height:7px;background:#22c55e;border-radius:50%;"></span>
                   <span>Shipper: ${shipperName}</span>
                   <span class="shipper-eta-pill" id="marker-eta-badge">~12 phút</span>
                 </div>
                 <div class="shipper-avatar-wrapper">
                   <img src="${shipperAvatar}" class="shipper-avatar-pic" onerror="this.src='${shipperFallback}'">
                   <div class="shipper-vehicle-badge">🛵</div>
                 </div>
               </div>`,
        className: '',
        iconSize: [68, 68],
        iconAnchor: [34, 34]
    });

    let progress = 0.35;
    const isAlreadyCompleted = <?php echo $order['order_status'] === 'completed' ? 'true' : 'false'; ?>;
    if (isAlreadyCompleted) {
        progress = 1.0;
    }

    const startLat = storeLat + dLat * progress;
    const startLng = storeLng + dLng * progress;
    const shipperMarker = L.marker([startLat, startLng], {icon: shipperIcon, zIndexOffset: 1000}).addTo(map);

    shipperMarker.bindPopup(
        `<div style="text-align:center;min-width:180px;padding:4px 0;">
           <img src="${shipperAvatar}" style="width:62px;height:62px;border-radius:50%;object-fit:cover;border:3px solid #0284c7;margin-bottom:6px;box-shadow:0 3px 10px rgba(2,132,199,0.35);" onerror="this.src='${shipperFallback}'">
           <br><b style="color:#0284c7;font-size:1.05rem;">🛵 ${shipperName}</b>
           <br><span style="font-size:0.8rem;color:#16a34a;font-weight:700;">⭐ ${shipperRating}/5 • Đang giao đồ uống</span>
           <br><span style="font-size:0.8rem;color:#64748b;">🏍️ ${shipperVehicle} (${shipperPlate})</span>
           <div style="margin-top:8px;">
             <a href="tel:${shipperTel}" style="display:inline-flex;align-items:center;gap:5px;background:#0284c7;color:white;padding:5px 14px;border-radius:15px;font-size:0.82rem;font-weight:700;text-decoration:none;">
               📞 Gọi ${shipperPhone}
             </a>
           </div>
         </div>`
    );

    // Add Map overlay toolbar: [Định vị Shipper] [Toàn tuyến] [Giao ngay Demo]
    const toolbar = L.control({position: 'topright'});
    toolbar.onAdd = function() {
        const div = L.DomUtil.create('div', 'map-control-overlay');
        div.innerHTML = `
            <button type="button" class="map-tool-btn" id="btn-focus-shipper" title="Phóng to theo dõi người giao hàng">
                <span>🎯</span> Định vị Shipper
            </button>
            <button type="button" class="map-tool-btn" id="btn-fit-route" title="Xem toàn bộ tuyến đường">
                <span>🗺️</span> Xem toàn tuyến
            </button>
            <button type="button" class="map-tool-btn map-tool-btn-demo" id="btn-quick-deliver" title="Mô phỏng shipper giao hàng thành công ngay lập tức">
                <span>⚡</span> Giao ngay (Demo)
            </button>
        `;
        L.DomEvent.disableClickPropagation(div);
        return div;
    };
    toolbar.addTo(map);

    // Initial fit bounds
    map.fitBounds([[storeLat, storeLng], [custLat, custLng]], {padding: [70, 70]});

    document.getElementById('btn-focus-shipper')?.addEventListener('click', function() {
        map.flyTo(shipperMarker.getLatLng(), 16, {animate: true, duration: 1.0});
        shipperMarker.openPopup();
    });

    document.getElementById('btn-fit-route')?.addEventListener('click', function() {
        map.fitBounds([[storeLat, storeLng], [custLat, custLng]], {padding: [70, 70]});
    });

    document.getElementById('btn-quick-deliver')?.addEventListener('click', function() {
        progress = 0.999;
    });

    const totalDistKm = 1.8 + ((<?php echo $order_id; ?> % 6) * 0.4);
    let delivered = isAlreadyCompleted;

    function updatePosition(frac) {
        const totalWP = waypoints.length - 1;
        const wpIndex = Math.min(Math.floor(frac * totalWP), totalWP - 1);
        const wpFrac  = (frac * totalWP) - wpIndex;
        const wpA = waypoints[wpIndex];
        const wpB = waypoints[wpIndex + 1];
        const curLat = wpA[0] + (wpB[0] - wpA[0]) * wpFrac;
        const curLng = wpA[1] + (wpB[1] - wpA[1]) * wpFrac;

        shipperMarker.setLatLng([curLat, curLng]);

        // Update traveled line
        const traveledPts = waypoints.slice(0, wpIndex + 1);
        traveledPts.push([curLat, curLng]);
        traveledLine.setLatLngs(traveledPts);

        // Update remaining distance and ETA
        const remKm = Math.max(0.05, totalDistKm * (1 - frac)).toFixed(1);
        const remMin = Math.max(1, Math.round((1 - frac) * 18));

        const distEl = document.getElementById('distance-display');
        if (distEl) distEl.innerText = '~' + remKm + ' km';

        const etaBadge = document.getElementById('marker-eta-badge');
        if (etaBadge) etaBadge.innerText = '~' + remMin + ' phút';

        const statusBadge = document.getElementById('shipper-status-badge');
        if (statusBadge) statusBadge.innerText = '🟢 Đang di chuyển (cách bạn ~' + remKm + ' km)';
    }

    // Initialize position
    updatePosition(progress);

    if (isAlreadyCompleted) {
        triggerDelivered(map, custLat, custLng, shipperMarker, false);
    } else {
        const shipperInterval = setInterval(function() {
            if (progress < 1.0) {
                progress = Math.min(progress + 0.004, 1.0);
                updatePosition(progress);
            } else if (!delivered) {
                delivered = true;
                clearInterval(shipperInterval);
                triggerDelivered(map, custLat, custLng, shipperMarker, true);
            }
        }, 1200);
    }
});

function triggerDelivered(map, custLat, custLng, shipperMarker, shouldShowOverlay = true) {
    // 0. Gọi API cập nhật database: order_status = completed, payment_status = paid
    const orderId = <?php echo $order['id']; ?>;
    fetch('/cart/update_order_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'order_id=' + orderId
    })
    .then(r => r.json())
    .then(data => {
        console.log('[GlowDrinks] Cập nhật đơn hàng:', data.message);
    })
    .catch(err => console.error('[GlowDrinks] Lỗi cập nhật đơn hàng:', err));

    // 1. Update progress tracker step 4 to completed
    const iconEl    = document.getElementById('step-done-icon');
    const labelEl   = document.getElementById('step-done-label');
    const subLabel  = document.getElementById('step-done-sublabel');
    const shipLabel = document.getElementById('step-shipping-label');
    if (iconEl)    { iconEl.style.background='#22c55e'; iconEl.style.color='white'; iconEl.style.boxShadow='0 4px 10px rgba(34,197,94,0.4)'; }
    if (labelEl)   { labelEl.style.color='#15803d'; labelEl.style.fontWeight='700'; }
    if (subLabel)  { subLabel.innerText='Hoàn thành ✅'; subLabel.style.color='#22c55e'; subLabel.style.fontWeight='700'; }
    if (shipLabel) { shipLabel.innerText='Đã giao xong'; }

    // 2. Update countdown timer to Done
    const timer = document.getElementById('countdown-timer');
    if (timer) { timer.innerText = '00:00'; timer.style.color = '#22c55e'; }

    // 3. Zoom map to customer location with celebration marker
    const doneIcon = L.divIcon({
        html: `<div style="font-size:36px;filter:drop-shadow(0 4px 8px rgba(0,0,0,0.3));animation:bounce 0.6s infinite alternate;">🎉</div>`,
        className: '',
        iconSize: [40, 40],
        iconAnchor: [20, 40]
    });
    const doneMarker = L.marker([custLat, custLng], {icon: doneIcon}).addTo(map)
     .bindPopup('<b style="color:#15803d;">✅ Đã giao hàng & thanh toán thành công!</b>');
    if (shouldShowOverlay) {
        map.flyTo([custLat, custLng], 15, {animate: true, duration: 1.2});
        doneMarker.openPopup();
    }

    // 4. Record delivered time
    const now = new Date();
    const timeStr = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
    const timeEl = document.getElementById('delivered-time');
    if (timeEl) timeEl.innerText = timeStr;

    // 5. Update payment status badge in page (nếu hiển thị)
    const payBadge = document.getElementById('payment-status-badge');
    if (payBadge) {
        payBadge.innerText = '✅ Đã Thanh Toán';
        payBadge.style.background = '#d1fae5';
        payBadge.style.color = '#059669';
    }

    // 6. Show delivered modal after short delay
    if (shouldShowOverlay) {
        setTimeout(function() {
            const overlay = document.getElementById('delivered-overlay');
            if (overlay) {
                overlay.style.display = 'flex';
                burstConfetti();
            }
        }, 1400);
    }
}

function rateStar(n) {
    const msgs = ['','Rất tệ 😞','Tệ 😕','Bình thường 😊','Tốt 😄','Tuyệt vời! 🤩'];
    const stars = document.querySelectorAll('#star-rating span');
    stars.forEach((s,i) => { s.style.filter = i < n ? 'none' : 'grayscale(1)'; s.style.transform = i < n ? 'scale(1.2)' : 'scale(1)'; });
    const msg = document.getElementById('rating-msg');
    if (msg) msg.innerText = msgs[n];
}

function burstConfetti() {
    const colors = ['#f5a623','#22c55e','#0284c7','#e11d48','#8b5cf6'];
    for (let i = 0; i < 50; i++) {
        const c = document.createElement('div');
        const color = colors[Math.floor(Math.random() * colors.length)];
        c.style.cssText = `position:fixed;top:${20+Math.random()*30}%;left:${Math.random()*100}%;width:${6+Math.random()*8}px;height:${6+Math.random()*8}px;background:${color};border-radius:${Math.random()>0.5?'50%':'2px'};z-index:10000;pointer-events:none;animation:confettiFall ${1.5+Math.random()*2}s ease-out forwards;`;
        document.body.appendChild(c);
        setTimeout(() => c.remove(), 3500);
    }
}
</script>

<?php require_once __DIR__ . '/../ThanhNgang/footer.php'; ?>
