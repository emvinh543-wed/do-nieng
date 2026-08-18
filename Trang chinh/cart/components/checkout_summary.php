<!-- ===== CHECKOUT ORDER SUMMARY COMPONENT ===== -->
<div class="summary-card">
    <h3 class="summary-title">Món Uống & Đồ Ăn Đã Chọn</h3>
    <div style="display:flex;flex-direction:column;gap:15px;margin-bottom:20px;max-height:300px;overflow-y:auto;padding-right:5px;">
        <?php foreach ($cart as $item): ?>
        <div style="display:flex;justify-content:space-between;font-size:0.9rem;border-bottom:1px dashed var(--border);padding-bottom:10px;">
            <div>
                <strong style="color:var(--primary);"><?php echo $item['qty']; ?>x</strong> 
                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">
                    Size: <?php echo $item['size']; ?>
                    <?php if (!empty($item['toppings'])) echo ' | +' . htmlspecialchars($item['toppings']); ?>
                </div>
            </div>
            <span style="font-weight:700;color:var(--primary);"><?php echo formatVND($item['total_item_amount']); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="summary-row" style="font-size:0.9rem;">
        <span>Tạm tính:</span>
        <span><?php echo formatVND($subtotal); ?></span>
    </div>
    <div class="summary-row" style="font-size:0.9rem;">
        <span>Phí vận chuyển (Freeship > 100k):</span>
        <span><?php echo $shipping == 0 ? '🎉 Miễn phí' : formatVND($shipping); ?></span>
    </div>
    <div class="summary-row-total" style="margin-top:15px;padding-top:15px;border-top:2px solid var(--border);">
        <span>Tổng thanh toán:</span>
        <span class="total-price" style="font-size:1.4rem;color:var(--primary);font-weight:900;"><?php echo formatVND($grand); ?></span>
    </div>
</div>
