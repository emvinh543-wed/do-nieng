<footer>
    <div class="footer-container">
        <div class="footer-about">
            <a href="/" class="logo" style="margin-bottom:15px; display:inline-flex;">
                <svg viewBox="0 0 24 24">
                    <path d="M2 21h18v-2H2v2M20 8h-2V5h2v3M4 19h12v-4H4v4m0-6h12V9H4v4m0-6h12V5H4v4m16-1c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-4v6h4z"/>
                </svg>
                <span>GlowDrinks</span>
            </a>
            <p style="margin-top:10px; font-size:0.9rem;">
                GlowDrinks mang den trai nghiem do uong tinh te va phong cach voi chat luong nguyen lieu sach tot nhat.
            </p>
        </div>

        <div>
            <h4 class="footer-title" data-i18n="footer_title_explore">Kham Pha</h4>
            <ul class="footer-links">
                <li><a href="/" class="footer-link" data-i18n="footer_links_home">Trang chu</a></li>
                <li><a href="/index/?type=products" class="footer-link" data-i18n="footer_links_menu">Thuc don do uong</a></li>
                <li><a href="/contact.php" class="footer-link" data-i18n="footer_links_contact">Lien he - Gop y</a></li>
                <li><a href="/amin/admin.php" class="footer-link" data-i18n="nav_admin">Quan tri vien</a></li>
            </ul>
        </div>

        <div>
            <h4 class="footer-title" data-i18n="footer_title_hours">Gio Hoat Dong</h4>
            <ul class="footer-links" style="font-size:0.9rem;">
                <li data-i18n="footer_hours_weekdays">Thu 2 - Thu 6: 07:00 - 22:30</li>
                <li data-i18n="footer_hours_weekend">Thu 7 - Chu Nhat: 08:00 - 23:00</li>
                <li style="color:var(--secondary); font-weight:600;" data-i18n="footer_hotline_label">Hotline giao hang nhanh:</li>
                <li style="color:#fff; font-size:1.1rem; font-weight:700;" data-i18n="footer_hotline">1900 6868</li>
            </ul>
        </div>

        <div>
            <h4 class="footer-title" data-i18n="footer_title_contact">Dia Chi Lien He</h4>
                <div class="footer-contact-item"><span>📍</span><span data-i18n="footer_address">123 Duong Ba Thang Hai, Quan 10, TP.HCM</span></div>
                <div class="footer-contact-item"><span>✉️</span><span data-i18n="footer_email">lienhe@glowdrinks.com</span></div>
                <div class="footer-contact-item"><span>💳</span><span data-i18n="footer_payment_methods">MoMo, VNPay, Chuyen khoan, COD</span></div>
        </div>
    </div>

    <div class="footer-bottom">
        <p data-i18n="footer_copyright">&copy; 2026 GlowDrinks Store. Tat ca quyen duoc bao luu.</p>
        <p data-i18n="footer_designer">Thiet ke boi Antigravity AI</p>
    </div>
</footer>

<?php
$ai_path = __DIR__ . '/../../Ai/duck_ai.php';
if (file_exists($ai_path)) {
    include_once $ai_path;
}
?>


</body>
</html>
