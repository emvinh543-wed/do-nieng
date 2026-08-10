<?php
/**
 * API: Cập nhật trạng thái đơn hàng khi giao thành công
 * Được gọi bằng fetch() từ trang order_success.php
 */
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$order_id = intval($_POST['order_id'] ?? 0);
if ($order_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order ID']);
    exit();
}

try {
    // Cập nhật: order_status = completed, payment_status = paid
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET order_status = 'completed',
            payment_status = 'paid',
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$order_id]);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Đơn hàng #' . $order_id . ' đã được cập nhật: Hoàn thành & Đã thanh toán.',
        'order_id'=> $order_id
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
