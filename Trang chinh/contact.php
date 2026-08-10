<?php
require_once __DIR__ . '/ThanhNgang/header.php';

$success_msg = $error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $fullname = trim($_POST['fullname']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone'] ?? '');
    $subject  = trim($_POST['subject']);
    $message  = trim($_POST['message']);
    if (!empty($fullname) && !empty($email) && !empty($subject) && !empty($message)) {
        try {
            $pdo->prepare("INSERT INTO contacts (fullname,email,phone,subject,message,status) VALUES (?,?,?,?,?,0)")->execute([$fullname,$email,$phone,$subject,$message]);
            $success_msg = 'Gui lien he thanh cong! Chung toi se phan hoi sớm.';
        } catch (Exception $e) { $error_msg = 'Loi he thong: ' . $e->getMessage(); }
    } else { $error_msg = 'Vui long nhap day du cac truong bat buoc!'; }
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
            <h3 style="font-size:1.3rem;font-weight:800;color:var(--dark);margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:10px;">Gui Tin Nhan Phan Hoi</h3>
            <?php if (!empty($success_msg)): ?><div style="background:#d1fae5;color:#059669;padding:12px 16px;border-radius:var(--radius-sm);margin-bottom:20px;font-weight:600;"><?php echo $success_msg; ?></div><?php endif; ?>
            <?php if (!empty($error_msg)):   ?><div style="background:#fee2e2;color:#dc2626;padding:12px 16px;border-radius:var(--radius-sm);margin-bottom:20px;font-weight:600;"><?php echo $error_msg; ?></div><?php endif; ?>
            <form action="/contact.php" method="POST">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Ho ten *</label><input type="text" name="fullname" class="form-control" placeholder="Ho va ten..." required></div>
                    <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" placeholder="mail@example.com" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">So dien thoai</label><input type="text" name="phone" class="form-control" placeholder="Khong bat buoc"></div>
                    <div class="form-group"><label class="form-label">Chu de *</label><input type="text" name="subject" class="form-control" placeholder="Gop y, nhuong quyen..." required></div>
                </div>
                <div class="form-group" style="margin-bottom:25px;"><label class="form-label">Noi dung *</label><textarea name="message" rows="5" class="form-control" placeholder="Noi dung chi tiet..." required></textarea></div>
                <button type="submit" name="submit_contact" class="btn btn-primary" style="width:100%;padding:14px;">Gui Tin Nhan</button>
            </form>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/ThanhNgang/footer.php'; ?>
