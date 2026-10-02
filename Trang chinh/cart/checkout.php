<?php
require_once __DIR__ . '/../config/config.php';

$current_user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();
}

$cart = $_SESSION['cart'] ?? [];
if (count($cart) === 0) { 
    header("Location: /index/"); 
    exit(); 
}

$subtotal = array_sum(array_column($cart, 'total_item_amount'));
$voucher  = $_SESSION['voucher'] ?? null;
$discount = 0;
if ($voucher && !empty($voucher['discount_percent'])) {
    $discount = round($subtotal * ($voucher['discount_percent'] / 100));
}
$after_discount = max(0, $subtotal - $discount);
$shipping = ($after_discount > 100000 || $after_discount == 0) ? 0 : 15000;
$grand    = $after_discount + $shipping;

$fullname = $current_user['fullname'] ?? '';
$email    = $current_user['email']    ?? '';
$phone    = $current_user['phone']    ?? '';
$address  = $current_user['address']  ?? '';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $fullname       = trim($_POST['fullname']);
    $email          = trim($_POST['email']);
    $phone          = trim($_POST['phone']);
    $address        = trim($_POST['address']);
    $payment_method = $_POST['payment_method'];
    $raw_note       = trim($_POST['note'] ?? '');
    $note           = ($voucher ? '[Voucher: ' . $voucher['code'] . ' (-' . $voucher['discount_percent'] . '%)] ' : '') . $raw_note;

    // ---- RÀNG BUỘC SERVER-SIDE ----
    $errs = [];
    if (empty($fullname))               $errs[] = 'Họ tên không được để trống.';
    elseif (mb_strlen($fullname) < 2)   $errs[] = 'Họ tên phải có ít nhất 2 ký tự.';
    elseif (mb_strlen($fullname) > 100) $errs[] = 'Họ tên không được quá 100 ký tự.';

    if (empty($phone))                  $errs[] = 'Số điện thoại không được để trống.';
    elseif (!preg_match('/^(0|\+84)(3|5|7|8|9)\d{8}$/', $phone)) $errs[] = 'Số điện thoại không hợp lệ (ví dụ: 0987654321).';

    if (empty($email))                      $errs[] = 'Email không được để trống.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'Email không đúng định dạng.';

    if (empty($address))               $errs[] = 'Địa chỉ không được để trống.';
    elseif (mb_strlen($address) < 10)  $errs[] = 'Địa chỉ phải có ít nhất 10 ký tự.';

    if (!in_array($payment_method, ['QR_CODE','COD','MOMO'])) $errs[] = 'Phương thức thanh toán không hợp lệ.';

    if (empty($errs)) {
        try {
            $pdo->beginTransaction();
            $payment_status = ($payment_method === 'COD') ? 'unpaid' : 'pending';
            $stmt = $pdo->prepare("INSERT INTO orders (user_id,fullname,email,phone,address,total_amount,payment_method,payment_status,order_status,note) VALUES (?,?,?,?,?,?,?,?,'pending',?)");
            $stmt->execute([$current_user['id'] ?? null, $fullname, $email, $phone, $address, $grand, $payment_method, $payment_status, $note]);
            $new_order_id = $pdo->lastInsertId();

            foreach ($cart as $item) {
                $d = $pdo->prepare("INSERT INTO order_details (order_id,product_id,price,quantity,size,toppings,total_item_amount) VALUES (?,?,?,?,?,?,?)");
                $d->execute([$new_order_id, $item['product_id'], $item['price'], $item['qty'], $item['size'], $item['toppings'], $item['total_item_amount']]);
                $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?")->execute([$item['qty'], $item['product_id']]);
            }
            $pdo->commit();
            unset($_SESSION['cart']);
            unset($_SESSION['voucher']);
            
            // Redirect sang trang theo dõi đơn hàng & đếm ngược giao nước
            header("Location: /cart/order_success.php?id=" . $new_order_id);
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Lỗi hệ thống: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errs);
    }
}

$vietqr_url = "https://img.vietqr.io/image/MB-0987654321-compact2.png?amount=" . $grand . "&addInfo=TT%20GLOWDRINKS&accountName=GLOWDRINKS%20STORE";

require_once __DIR__ . '/../ThanhNgang/header.php';
?>
<section class="section">
    <h2 class="section-title" style="margin-bottom:35px;">Thông Tin <span>Thanh Toán & Đặt Hàng</span></h2>
    <?php if (!empty($error)): ?>
        <div style="background:#fee2e2;color:#dc2626;padding:12px 16px;border-radius:var(--radius-sm);margin-bottom:25px;font-weight:600;">⚠️ <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="cart-layout">
        <div class="cart-table-card">
            <h3 style="font-size:1.3rem;font-weight:800;color:var(--dark);margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:10px;">Thông Tin Giao Hàng</h3>
            <form action="/cart/checkout.php" method="POST" id="checkout-form">
                <div class="form-group">
                    <label class="form-label">Họ và tên người nhận *</label>
                    <input type="text" name="fullname" id="co_fullname" class="form-control" value="<?php echo htmlspecialchars($fullname); ?>" placeholder="Nguyễn Văn A">
                <div class="field-error" id="coe_fullname" style="display:none;"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Số điện thoại *</label>
                        <input type="text" name="phone" id="co_phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" placeholder="0987654321">
                        <div class="field-error" id="coe_phone" style="display:none;"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="co_email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" placeholder="khachhang@gmail.com">
                        <div class="field-error" id="coe_email" style="display:none;"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Địa chỉ nhận hàng *</label>
                    <input type="text" name="address" id="co_address" class="form-control" value="<?php echo htmlspecialchars($address); ?>" placeholder="Số nhà, tên đường, phường/xã, quận/huyện...">
                <div class="field-error" id="coe_address" style="display:none;"></div>
                </div>

                <div class="form-group" style="margin-top:25px;">
                    <label class="form-label" style="font-size:1.05rem;font-weight:700;color:var(--dark);margin-bottom:12px;">Phương Thức Thanh Toán *</label>
                    
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <!-- OPTION 1: QR CODE -->
                        <div style="border:2px solid var(--primary);border-radius:var(--radius-md);background:var(--light);overflow:hidden;">
                            <label style="display:flex;align-items:center;gap:12px;padding:14px 18px;cursor:pointer;">
                                <input type="radio" name="payment_method" value="QR_CODE" checked onchange="togglePaymentQR(true)" style="accent-color:var(--primary);transform:scale(1.2);">
                                <div style="display:flex;align-items:center;gap:10px;flex:1;">
                                    <span style="font-size:1.6rem;">📱</span>
                                    <div>
                                        <strong style="color:var(--primary);font-size:0.98rem;">Thanh toán bằng Mã QR Ngân Hàng (VietQR)</strong>
                                        <p style="font-size:0.82rem;color:var(--text-muted);margin-top:2px;">Quét mã QR chuyển khoản nhanh qua ứng dụng Ngân hàng (MB, Vietcombank, Techcombank...)</p>
                                    </div>
                                </div>
                            </label>

                            <!-- QR CODE PREVIEW BOX -->
                            <div id="qr-preview-box" style="display:block;padding:20px;background:white;border-top:1px solid var(--border);text-align:center;">
                                <div style="display:inline-block;background:#fff8e8;border:1.5px dashed var(--primary);padding:15px;border-radius:12px;margin-bottom:15px;">
                                    <div style="font-size:0.88rem;font-weight:700;color:var(--primary);margin-bottom:10px;">📲 MỞ APP NGÂN HÀNG & QUÉT MÃ QR DƯỚI ĐÂY</div>
                                    <img src="<?php echo $vietqr_url; ?>" 
                                         onerror="this.src='/anh/qr_code.png'" 
                                         alt="Mã QR VietQR" 
                                         style="max-width:220px;width:100%;height:auto;border-radius:10px;box-shadow:var(--shadow-md);border:2px solid white;">
                                </div>

                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:420px;margin:0 auto;text-align:left;font-size:0.85rem;">
                                    <div style="background:var(--light);padding:8px 12px;border-radius:6px;">
                                        <span style="color:var(--text-muted);display:block;font-size:0.75rem;">Ngân hàng:</span>
                                        <strong>MBBank</strong>
                                    </div>
                                    <div style="background:var(--light);padding:8px 12px;border-radius:6px;">
                                        <span style="color:var(--text-muted);display:block;font-size:0.75rem;">Số tài khoản:</span>
                                        <strong style="color:var(--primary);">0987654321</strong>
                                    </div>
                                    <div style="background:var(--light);padding:8px 12px;border-radius:6px;">
                                        <span style="color:var(--text-muted);display:block;font-size:0.75rem;">Chủ tài khoản:</span>
                                        <strong>GLOWDRINKS STORE</strong>
                                    </div>
                                    <div style="background:var(--light);padding:8px 12px;border-radius:6px;">
                                        <span style="color:var(--text-muted);display:block;font-size:0.75rem;">Số tiền thanh toán:</span>
                                        <strong style="color:var(--primary);"><?php echo formatVND($grand); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- OPTION 2: COD -->
                        <label style="display:flex;align-items:center;gap:12px;background:white;padding:14px 18px;border-radius:var(--radius-md);border:1px solid var(--border);cursor:pointer;">
                            <input type="radio" name="payment_method" value="COD" onchange="togglePaymentQR(false)" style="accent-color:var(--primary);transform:scale(1.2);">
                            <div style="display:flex;align-items:center;gap:10px;flex:1;">
                                <span style="font-size:1.6rem;">💵</span>
                                <div>
                                    <strong style="font-size:0.98rem;">Thanh toán khi nhận hàng (COD)</strong>
                                    <p style="font-size:0.82rem;color:var(--text-muted);margin-top:2px;">Thanh toán bằng tiền mặt cho shipper khi nhận được đồ uống</p>
                                </div>
                            </div>
                        </label>

                        <!-- OPTION 3: MOMO -->
                        <label style="display:flex;align-items:center;gap:12px;background:white;padding:14px 18px;border-radius:var(--radius-md);border:1px solid var(--border);cursor:pointer;">
                            <input type="radio" name="payment_method" value="MOMO" onchange="togglePaymentQR(false)" style="accent-color:var(--primary);transform:scale(1.2);">
                            <div style="display:flex;align-items:center;gap:10px;flex:1;">
                                <span style="font-size:1.6rem;">👛</span>
                                <div>
                                    <strong style="font-size:0.98rem;">Ví điện tử MoMo</strong>
                                    <p style="font-size:0.82rem;color:var(--text-muted);margin-top:2px;">Thanh toán qua ví MoMo app</p>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-group" style="margin-top:20px;">
                    <label class="form-label">Ghi chú cho quán (nếu có)</label>
                    <textarea name="note" rows="2" class="form-control" placeholder="Ví dụ: ít đá, 50% đường, giao giờ hành chính..."></textarea>
                </div>

                <div style="margin-top:30px;border-top:1px solid var(--border);padding-top:20px;display:flex;justify-content:space-between;align-items:center;">
                    <a href="/cart/cart.php" style="color:var(--text-muted);font-weight:600;">← Trở về giỏ hàng</a>
                    <button type="submit" name="place_order" class="btn btn-primary" style="padding:15px 35px;font-weight:700;font-size:1.05rem;">Xác Nhận Đặt Hàng</button>
                </div>
            </form>
        </div>

        <div class="summary-card">
            <h3 class="summary-title">Món Uống Đã Chọn</h3>
            <div style="display:flex;flex-direction:column;gap:15px;margin-bottom:20px;max-height:280px;overflow-y:auto;">
                <?php foreach ($cart as $item): ?>
                <div style="display:flex;justify-content:space-between;font-size:0.9rem;border-bottom:1px dashed var(--border);padding-bottom:8px;">
                    <div>
                        <strong><?php echo $item['qty']; ?>x</strong> <?php echo htmlspecialchars($item['name']); ?>
                        <div style="font-size:0.78rem;color:var(--text-muted);">Size: <?php echo $item['size']; ?><?php if(!empty($item['toppings'])) echo ' | +' . htmlspecialchars($item['toppings']); ?></div>
                    </div>
                    <span style="font-weight:600;color:var(--primary);"><?php echo formatVND($item['total_item_amount']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="summary-row" style="font-size:0.9rem;"><span>Tạm tính:</span><span><?php echo formatVND($subtotal); ?></span></div>
            <?php if ($discount > 0): ?>
            <div class="summary-row" style="font-size:0.9rem;color:#2563eb;font-weight:700;">
                <span>Giảm giá (Voucher <?php echo htmlspecialchars($voucher['code']); ?>):</span>
                <span>-<?php echo formatVND($discount); ?></span>
            </div>
            <?php endif; ?>
            <div class="summary-row" style="font-size:0.9rem;"><span>Phí vận chuyển:</span><span><?php echo $shipping==0?'Miễn phí':formatVND($shipping); ?></span></div>
            <div class="summary-row-total"><span>Tổng tiền:</span><span class="total-price"><?php echo formatVND($grand); ?></span></div>
        </div>
    </div>
</section>

<style>
.field-error { color:#dc2626;font-size:0.82rem;margin-top:5px;display:flex;align-items:center;gap:4px;animation:fadeIn .2s ease; }
.field-error::before { content:'⚠'; }
.form-control.is-invalid { border-color:#dc2626!important;box-shadow:0 0 0 3px rgba(220,38,38,.1)!important; }
.form-control.is-valid   { border-color:#22c55e!important;box-shadow:0 0 0 3px rgba(34,197,94,.1)!important; }
@keyframes fadeIn { from{opacity:0;transform:translateY(-4px)} to{opacity:1;transform:translateY(0)} }
</style>
<script>
function togglePaymentQR(show) {
    const qrBox = document.getElementById('qr-preview-box');
    if (qrBox) qrBox.style.display = show ? 'block' : 'none';
}
// Checkout Validation
const phoneRxCo = /^(0|\+84)(3|5|7|8|9)\d{8}$/;
const emailRxCo = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
const coErrs    = {};
function coShow(id,msg){ const el=document.getElementById(id); el.textContent=msg; el.style.display='flex'; coErrs[id]=true; }
function coClear(id)   { document.getElementById(id).style.display='none'; delete coErrs[id]; }
function coOk(inp)     { inp.classList.remove('is-invalid'); inp.classList.add('is-valid'); }
function coBad(inp)    { inp.classList.remove('is-valid');   inp.classList.add('is-invalid'); }

function validateCoField(inp) {
    const v = inp.value.trim(), n = inp.id;
    if (n==='co_fullname') {
        if (!v) { coBad(inp); coShow('coe_fullname','Họ tên không được để trống.'); }
        else if (v.length<2) { coBad(inp); coShow('coe_fullname','Họ tên phải có ít nhất 2 ký tự.'); }
        else { coOk(inp); coClear('coe_fullname'); }
    } else if (n==='co_phone') {
        if (!v) { coBad(inp); coShow('coe_phone','Số điện thoại không được để trống.'); }
        else if (!phoneRxCo.test(v)) { coBad(inp); coShow('coe_phone','Số điện thoại không hợp lệ (VD: 0987654321).'); }
        else { coOk(inp); coClear('coe_phone'); }
    } else if (n==='co_email') {
        if (!v) { coBad(inp); coShow('coe_email','Email không được để trống.'); }
        else if (!emailRxCo.test(v)) { coBad(inp); coShow('coe_email','Email không đúng định dạng.'); }
        else { coOk(inp); coClear('coe_email'); }
    } else if (n==='co_address') {
        if (!v) { coBad(inp); coShow('coe_address','Địa chỉ không được để trống.'); }
        else if (v.length<10) { coBad(inp); coShow('coe_address','Địa chỉ phải có ít nhất 10 ký tự.'); }
        else { coOk(inp); coClear('coe_address'); }
    }
}
['co_fullname','co_phone','co_email','co_address'].forEach(id=>{
    const el = document.getElementById(id);
    if (el) { el.addEventListener('blur',function(){ validateCoField(this); }); el.addEventListener('input',function(){ if(this.classList.contains('is-invalid')) validateCoField(this); }); }
});
document.getElementById('checkout-form').addEventListener('submit', function(e){
    ['co_fullname','co_phone','co_email','co_address'].forEach(id=>{ const el=document.getElementById(id); if(el) validateCoField(el); });
    if (Object.keys(coErrs).length > 0) {
        e.preventDefault();
        const firstBad = document.querySelector('.is-invalid');
        if (firstBad) firstBad.scrollIntoView({behavior:'smooth',block:'center'});
        const btn = document.querySelector('button[name=place_order]');
        const orig = btn.textContent;
        btn.textContent = '⚠ Vui lòng kiểm tra lại!';
        btn.style.background = '#dc2626';
        setTimeout(()=>{ btn.textContent=orig; btn.style.background=''; },2500);
    }
});
</script>

<?php require_once __DIR__ . '/../ThanhNgang/footer.php'; ?>
