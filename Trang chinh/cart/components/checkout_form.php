<!-- ===== CHECKOUT FORM COMPONENT ===== -->
<h3 style="font-size:1.3rem;font-weight:800;color:var(--dark);margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:10px;">
    Thông Tin Giao Hàng & Đặt Hàng
</h3>

<form action="/cart/checkout.php" method="POST" id="checkout-form" onsubmit="return validateCheckoutClient(event)">
    <div class="form-group">
        <label class="form-label">Họ và tên người nhận *</label>
        <input type="text" 
               id="input-fullname"
               name="fullname" 
               class="form-control" 
               value="<?php echo htmlspecialchars($fullname); ?>" 
               required 
               minlength="2"
               maxlength="60"
               placeholder="Ví dụ: Nguyễn Văn A"
               onblur="validateFieldRealtime('fullname')">
        <small class="error-msg" id="err-fullname" style="color:#e11d48;font-weight:600;display:none;margin-top:4px;"></small>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Số điện thoại nhận hàng *</label>
            <input type="tel" 
                   id="input-phone"
                   name="phone" 
                   class="form-control" 
                   value="<?php echo htmlspecialchars($phone); ?>" 
                   required 
                   pattern="^(0[3|5|7|8|9])[0-9]{8}$"
                   placeholder="0987654321 (10 chữ số)"
                   onblur="validateFieldRealtime('phone')">
            <small class="error-msg" id="err-phone" style="color:#e11d48;font-weight:600;display:none;margin-top:4px;"></small>
        </div>

        <div class="form-group">
            <label class="form-label">Email liên hệ *</label>
            <input type="email" 
                   id="input-email"
                   name="email" 
                   class="form-control" 
                   value="<?php echo htmlspecialchars($email); ?>" 
                   required 
                   placeholder="khachhang@gmail.com"
                   onblur="validateFieldRealtime('email')">
            <small class="error-msg" id="err-email" style="color:#e11d48;font-weight:600;display:none;margin-top:4px;"></small>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Địa chỉ nhận hàng chi tiết *</label>
        <input type="text" 
               id="input-address"
               name="address" 
               class="form-control" 
               value="<?php echo htmlspecialchars($address); ?>" 
               required 
               minlength="10"
               maxlength="250"
               placeholder="Số nhà, tên đường, phường/xã, quận/huyện..."
               onblur="validateFieldRealtime('address')">
        <small class="error-msg" id="err-address" style="color:#e11d48;font-weight:600;display:none;margin-top:4px;"></small>
    </div>

    <!-- Include Payment Component -->
    <?php include __DIR__ . '/checkout_payment.php'; ?>

    <div class="form-group" style="margin-top:20px;">
        <label class="form-label">Ghi chú cho quán (nếu có)</label>
        <textarea name="note" rows="2" class="form-control" placeholder="Ví dụ: ít đá, 50% đường, giao giờ hành chính..."></textarea>
    </div>

    <div style="margin-top:30px;border-top:1px solid var(--border);padding-top:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;">
        <a href="/cart/cart.php" style="color:var(--text-muted);font-weight:600;text-decoration:none;">← Trở về giỏ hàng</a>
        <button type="submit" name="place_order" class="btn btn-primary" style="padding:15px 35px;font-weight:700;font-size:1.05rem;border-radius:30px;">
            🛒 Xác Nhận Đặt Hàng
        </button>
    </div>
</form>

<script>
function validateFieldRealtime(fieldName) {
    const el = document.getElementById('input-' + fieldName);
    const errEl = document.getElementById('err-' + fieldName);
    if (!el || !errEl) return true;

    let msg = '';
    const val = el.value.trim();

    if (fieldName === 'fullname') {
        if (val.length < 2) msg = '⚠️ Họ và tên phải từ 2 ký tự trở lên!';
        else if (/[0-9!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(val)) msg = '⚠️ Họ tên không được chứa số hoặc ký tự đặc biệt!';
    } else if (fieldName === 'phone') {
        if (!/^(0[3|5|7|8|9])[0-9]{8}$/.test(val)) msg = '⚠️ Số điện thoại phải gồm 10 số, bắt đầu bằng 03, 05, 07, 08 hoặc 09!';
    } else if (fieldName === 'email') {
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) msg = '⚠️ Email không đúng định dạng!';
    } else if (fieldName === 'address') {
        if (val.length < 10) msg = '⚠️ Vui lòng nhập địa chỉ chi tiết hơn (tối thiểu 10 ký tự)!';
    }

    if (msg) {
        errEl.innerText = msg;
        errEl.style.display = 'block';
        el.style.borderColor = '#e11d48';
        return false;
    } else {
        errEl.style.display = 'none';
        el.style.borderColor = '#22c55e';
        return true;
    }
}

function validateCheckoutClient(e) {
    const v1 = validateFieldRealtime('fullname');
    const v2 = validateFieldRealtime('phone');
    const v3 = validateFieldRealtime('email');
    const v4 = validateFieldRealtime('address');
    if (!v1 || !v2 || !v3 || !v4) {
        e.preventDefault();
        alert('⚠️ Vui lòng kiểm tra và sửa các thông tin bị lỗi trước khi đặt hàng!');
        return false;
    }
    return true;
}
</script>
