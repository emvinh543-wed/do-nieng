<!-- ===== CHECKOUT PAYMENT METHOD COMPONENT ===== -->
<div class="form-group" style="margin-top:25px;">
    <label class="form-label" style="font-size:1.05rem;font-weight:700;color:var(--dark);margin-bottom:12px;">
        Phương Thức Thanh Toán *
    </label>
    
    <div style="display:flex;flex-direction:column;gap:14px;">
        <!-- OPTION 1: QR CODE (VIETQR) -->
        <div style="border:2px solid var(--primary);border-radius:var(--radius-md);background:#FFF8E8;overflow:hidden;">
            <label style="display:flex;align-items:center;gap:12px;padding:14px 18px;cursor:pointer;">
                <input type="radio" name="payment_method" value="QR_CODE" checked onchange="togglePaymentQR(true)" style="accent-color:var(--primary);transform:scale(1.2);">
                <div style="display:flex;align-items:center;gap:10px;flex:1;">
                    <span style="font-size:1.6rem;">📱</span>
                    <div>
                        <strong style="color:var(--primary);font-size:0.98rem;">Thanh toán bằng Mã QR Ngân Hàng (VietQR)</strong>
                        <p style="font-size:0.82rem;color:var(--text-muted);margin-top:2px;">Quét mã QR chuyển khoản tự động qua MBBank, Vietcombank, Techcombank...</p>
                    </div>
                </div>
            </label>

            <!-- QR CODE PREVIEW BOX -->
            <div id="qr-preview-box" style="display:block;padding:20px;background:white;border-top:1px solid var(--border);text-align:center;">
                <div style="display:inline-block;background:#fff8e8;border:1.5px dashed var(--primary);padding:15px;border-radius:14px;margin-bottom:15px;">
                    <div style="font-size:0.88rem;font-weight:700;color:var(--primary);margin-bottom:10px;">📲 MỞ APP NGÂN HÀNG & QUÉT MÃ QR DƯỚI ĐÂY</div>
                    <img src="<?php echo $vietqr_url; ?>" 
                         onerror="this.src='/anh/qr_code.png'" 
                         alt="Mã QR VietQR" 
                         style="max-width:210px;width:100%;height:auto;border-radius:10px;box-shadow:var(--shadow-md);border:2px solid white;">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:420px;margin:0 auto;text-align:left;font-size:0.85rem;">
                    <div style="background:var(--light);padding:8px 12px;border-radius:8px;">
                        <span style="color:var(--text-muted);display:block;font-size:0.75rem;">Ngân hàng:</span>
                        <strong>MBBank (Ngân Hàng Quân Đội)</strong>
                    </div>
                    <div style="background:var(--light);padding:8px 12px;border-radius:8px;">
                        <span style="color:var(--text-muted);display:block;font-size:0.75rem;">Số tài khoản:</span>
                        <strong style="color:var(--primary);">0987654321</strong>
                    </div>
                    <div style="background:var(--light);padding:8px 12px;border-radius:8px;">
                        <span style="color:var(--text-muted);display:block;font-size:0.75rem;">Chủ tài khoản:</span>
                        <strong>GLOWDRINKS STORE</strong>
                    </div>
                    <div style="background:var(--light);padding:8px 12px;border-radius:8px;">
                        <span style="color:var(--text-muted);display:block;font-size:0.75rem;">Số tiền thanh toán:</span>
                        <strong style="color:var(--primary);"><?php echo formatVND($grand); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- OPTION 2: COD -->
        <label style="display:flex;align-items:center;gap:12px;background:white;padding:14px 18px;border-radius:var(--radius-md);border:1.5px solid var(--border);cursor:pointer;transition:border-color 0.2s;">
            <input type="radio" name="payment_method" value="COD" onchange="togglePaymentQR(false)" style="accent-color:var(--primary);transform:scale(1.2);">
            <div style="display:flex;align-items:center;gap:10px;flex:1;">
                <span style="font-size:1.6rem;">💵</span>
                <div>
                    <strong style="font-size:0.98rem;">Thanh toán khi nhận hàng (COD)</strong>
                    <p style="font-size:0.82rem;color:var(--text-muted);margin-top:2px;">Thanh toán bằng tiền mặt cho shipper khi nhận được đồ uống & món ăn</p>
                </div>
            </div>
        </label>

        <!-- OPTION 3: MOMO -->
        <label style="display:flex;align-items:center;gap:12px;background:white;padding:14px 18px;border-radius:var(--radius-md);border:1.5px solid var(--border);cursor:pointer;transition:border-color 0.2s;">
            <input type="radio" name="payment_method" value="MOMO" onchange="togglePaymentQR(false)" style="accent-color:var(--primary);transform:scale(1.2);">
            <div style="display:flex;align-items:center;gap:10px;flex:1;">
                <span style="font-size:1.6rem;">👛</span>
                <div>
                    <strong style="font-size:0.98rem;">Ví điện tử MoMo</strong>
                    <p style="font-size:0.82rem;color:var(--text-muted);margin-top:2px;">Thanh toán qua ví điện tử MoMo App</p>
                </div>
            </div>
        </label>
    </div>
</div>

<script>
function togglePaymentQR(show) {
    const qrBox = document.getElementById('qr-preview-box');
    if (qrBox) {
        qrBox.style.display = show ? 'block' : 'none';
    }
}
</script>
