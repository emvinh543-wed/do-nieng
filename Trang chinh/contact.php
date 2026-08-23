<?php
require_once __DIR__ . '/ThanhNgang/header.php';

$success_msg = $error_msg = '';
$form_data   = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $fullname = trim($_POST['fullname']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone'] ?? '');
    $subject  = trim($_POST['subject']);
    $message  = trim($_POST['message']);
    $form_data = compact('fullname','email','phone','subject','message');

    // ---- RÀNG BUỘC PHP SERVER-SIDE ----
    $errs = [];
    if (empty($fullname))               $errs[] = 'Họ tên không được để trống.';
    elseif (mb_strlen($fullname) < 2)   $errs[] = 'Họ tên phải có ít nhất 2 ký tự.';
    elseif (mb_strlen($fullname) > 100) $errs[] = 'Họ tên không được quá 100 ký tự.';

    if (empty($email))                      $errs[] = 'Email không được để trống.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'Email không đúng định dạng (ví dụ: abc@gmail.com).';

    if (!empty($phone) && !preg_match('/^(0|\+84)(3|5|7|8|9)\d{8}$/', $phone))
        $errs[] = 'Số điện thoại không hợp lệ (ví dụ: 0987654321).';

    if (empty($subject))               $errs[] = 'Chủ đề không được để trống.';
    elseif (mb_strlen($subject) < 3)   $errs[] = 'Chủ đề phải có ít nhất 3 ký tự.';
    elseif (mb_strlen($subject) > 200) $errs[] = 'Chủ đề không được quá 200 ký tự.';

    if (empty($message))               $errs[] = 'Nội dung không được để trống.';
    elseif (mb_strlen($message) < 10)  $errs[] = 'Nội dung phải có ít nhất 10 ký tự.';
    elseif (mb_strlen($message) > 2000)$errs[] = 'Nội dung không được quá 2000 ký tự.';

    if (empty($errs)) {
        try {
            $pdo->prepare("INSERT INTO contacts (fullname,email,phone,subject,message,status) VALUES (?,?,?,?,?,0)")->execute([$fullname,$email,$phone,$subject,$message]);
            $success_msg = '✅ Gửi liên hệ thành công! Chúng tôi sẽ phản hồi trong vòng 24 giờ.';
            $form_data   = []; // xóa form sau khi gửi thành công
        } catch (Exception $e) { $error_msg = 'Lỗi hệ thống: ' . $e->getMessage(); }
    } else {
        $error_msg = implode('<br>', $errs);
    }
}
?>
<section class="section">
    <h2 class="section-title" style="margin-bottom:10px;text-align:center;">Lien He <span>Voi Chung Toi</span></h2>
    <p style="text-align:center;color:var(--text-muted);margin-bottom:50px;">Moi y kien dong gop cua ban deu giup chung toi hoan thien hon</p>

    <div class="cart-layout" style="grid-template-columns:1fr 1.3fr;">
        <div style="background:white;padding:40px;border-radius:var(--radius-lg);border:1px solid var(--border);box-shadow:var(--shadow-sm);display:flex;flex-direction:column;gap:25px;">
            <div>
                <h3 style="font-weight:800;margin-bottom:10px;">Thong Tin Chi Nhanh</h3>
                <p style="color:var(--text-muted);font-size:0.95rem;">GlowDrinks mang den khong gian thu gian va thức uong cao cap.</p>
            </div>
            <div style="display:flex;gap:12px;"><span style="font-size:1.4rem;">📍</span>
                <div><strong>Dia chi:</strong><p style="color:var(--text-muted);margin-top:3px;font-size:0.92rem;">123 Duong Ba Thang Hai, Quan 10, TP.HCM</p></div></div>
            <div style="display:flex;gap:12px;"><span style="font-size:1.4rem;">📞</span>
                <div><strong>Dien thoai:</strong><p style="color:var(--text-muted);margin-top:3px;font-size:0.92rem;">1900 6868 - 0987 654 321</p></div></div>
            <div style="display:flex;gap:12px;"><span style="font-size:1.4rem;">✉️</span>
                <div><strong>Email:</strong><p style="color:var(--text-muted);margin-top:3px;font-size:0.92rem;">lienhe@glowdrinks.com</p></div></div>
            <div style="background:#f1f5f9;border-radius:var(--radius-md);height:180px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:0.9rem;border:1px dashed var(--border);">
                [Ban do Google Maps]
            </div>
        </div>

        <div class="cart-table-card">
            <h3 style="font-size:1.3rem;font-weight:800;color:var(--dark);margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:10px;">📨 Gửi Tin Nhắn Phản Hồi</h3>
            <?php if (!empty($success_msg)): ?><div style="background:#d1fae5;color:#059669;padding:14px 16px;border-radius:var(--radius-sm);margin-bottom:20px;font-weight:600;font-size:0.95rem;"><?php echo $success_msg; ?></div><?php endif; ?>
            <?php if (!empty($error_msg)):   ?><div style="background:#fee2e2;color:#dc2626;padding:14px 16px;border-radius:var(--radius-sm);margin-bottom:20px;font-weight:600;">⚠️ <?php echo $error_msg; ?></div><?php endif; ?>
            <form action="/contact.php" method="POST" id="contactForm" novalidate>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Họ và tên *</label>
                        <input type="text" name="fullname" id="c_fullname" class="form-control" placeholder="Họ và tên đầy đủ..." value="<?php echo htmlspecialchars($form_data['fullname'] ?? ''); ?>">
                        <div class="field-error" id="ce_fullname" style="display:none;"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="c_email" class="form-control" placeholder="mail@example.com" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>">
                        <div class="field-error" id="ce_email" style="display:none;"></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Số điện thoại <span style="color:var(--text-muted);font-weight:400;">(không bắt buộc)</span></label>
                        <input type="text" name="phone" id="c_phone" class="form-control" placeholder="0987 654 321" value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>">
                        <div class="field-error" id="ce_phone" style="display:none;"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Chủ đề *</label>
                        <input type="text" name="subject" id="c_subject" class="form-control" placeholder="Góp ý, nhượng quyền..." value="<?php echo htmlspecialchars($form_data['subject'] ?? ''); ?>">
                        <div class="field-error" id="ce_subject" style="display:none;"></div>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:8px;">
                    <label class="form-label">Nội dung * <span id="msg-counter" style="float:right;font-size:0.8rem;color:var(--text-muted);font-weight:400;">0/2000</span></label>
                    <textarea name="message" id="c_message" rows="5" class="form-control" placeholder="Nội dung chi tiết... (tối thiểu 10 ký tự)"><?php echo htmlspecialchars($form_data['message'] ?? ''); ?></textarea>
                    <div class="field-error" id="ce_message" style="display:none;"></div>
                </div>
                <button type="submit" name="submit_contact" class="btn btn-primary" style="width:100%;padding:14px;margin-top:16px;font-weight:700;font-size:1rem;">📨 Gửi Tin Nhắn</button>
            </form>
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
const cErrs = {};
function cShow(id,msg){ const el=document.getElementById(id); el.textContent=msg; el.style.display='flex'; cErrs[id]=true; }
function cClear(id)  { document.getElementById(id).style.display='none'; delete cErrs[id]; }
function cOk(inp)    { inp.classList.remove('is-invalid'); inp.classList.add('is-valid'); }
function cBad(inp)   { inp.classList.remove('is-valid'); inp.classList.add('is-invalid'); }

const phoneRx = /^(0|\+84)(3|5|7|8|9)\d{8}$/;
const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

function validateField(inp) {
    const n = inp.id, v = inp.value.trim();
    if (n === 'c_fullname') {
        if (!v) { cBad(inp); cShow('ce_fullname','Họ tên không được để trống.'); }
        else if (v.length < 2) { cBad(inp); cShow('ce_fullname','Họ tên phải có ít nhất 2 ký tự.'); }
        else if (v.length > 100) { cBad(inp); cShow('ce_fullname','Họ tên không được quá 100 ký tự.'); }
        else { cOk(inp); cClear('ce_fullname'); }
    } else if (n === 'c_email') {
        if (!v) { cBad(inp); cShow('ce_email','Email không được để trống.'); }
        else if (!emailRx.test(v)) { cBad(inp); cShow('ce_email','Email không đúng định dạng (abc@gmail.com).'); }
        else { cOk(inp); cClear('ce_email'); }
    } else if (n === 'c_phone') {
        if (v && !phoneRx.test(v)) { cBad(inp); cShow('ce_phone','Số điện thoại không hợp lệ (ví dụ: 0987654321).'); }
        else { cOk(inp); cClear('ce_phone'); }
    } else if (n === 'c_subject') {
        if (!v) { cBad(inp); cShow('ce_subject','Chủ đề không được để trống.'); }
        else if (v.length < 3) { cBad(inp); cShow('ce_subject','Chủ đề phải có ít nhất 3 ký tự.'); }
        else { cOk(inp); cClear('ce_subject'); }
    } else if (n === 'c_message') {
        if (!v) { cBad(inp); cShow('ce_message','Nội dung không được để trống.'); }
        else if (v.length < 10) { cBad(inp); cShow('ce_message','Nội dung phải có ít nhất 10 ký tự (hiện tại: '+v.length+').'); }
        else if (v.length > 2000) { cBad(inp); cShow('ce_message','Nội dung không được quá 2000 ký tự.'); }
        else { cOk(inp); cClear('ce_message'); }
    }
}
['c_fullname','c_email','c_phone','c_subject','c_message'].forEach(id =>{
    const el = document.getElementById(id);
    if (el) el.addEventListener('blur', function(){ validateField(this); });
    if (el) el.addEventListener('input',function(){ if(this.classList.contains('is-invalid')) validateField(this); });
});
// Character counter for message
const msgEl = document.getElementById('c_message');
const cntEl = document.getElementById('msg-counter');
if (msgEl && cntEl) {
    msgEl.addEventListener('input', function(){
        const len = this.value.length;
        cntEl.textContent = len + '/2000';
        cntEl.style.color = len > 1900 ? '#dc2626' : len > 1500 ? '#f59e0b' : 'var(--text-muted)';
    });
}
document.getElementById('contactForm').addEventListener('submit', function(e){
    ['c_fullname','c_email','c_phone','c_subject','c_message'].forEach(id=>{
        const el = document.getElementById(id); if(el) validateField(el);
    });
    if (Object.keys(cErrs).length > 0) {
        e.preventDefault();
        const firstErr = document.querySelector('.is-invalid');
        if (firstErr) firstErr.scrollIntoView({behavior:'smooth',block:'center'});
    }
});
</script>
