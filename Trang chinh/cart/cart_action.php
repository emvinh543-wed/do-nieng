<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$site_lang = get_site_lang();

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        echo json_encode([
            'status'  => 'require_login',
            'message' => 'Vui lòng đăng nhập tài khoản trước khi chọn mua đồ uống!',
            'redirect'=> '/login/login_demo.php'
        ]);
        exit();
    }

    $product_id  = intval($_POST['product_id'] ?? 0);
    $qty         = intval($_POST['qty'] ?? 1);
    $size        = $_POST['size'] ?? 'M';
    $toppings_in = $_POST['toppings'] ?? [];
    if (is_string($toppings_in)) $toppings_in = array_filter(explode(',', $toppings_in));

    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    if ($product) $product = localize_row($product, $site_lang, ['name','description']);

    if (!$product) { echo json_encode(['status'=>'error','message'=>'San pham khong ton tai!']); exit(); }

    $price = $product['discount_price'] > 0 ? $product['discount_price'] : $product['price'];
    $size_surcharge = ($size==='S') ? -5000 : (($size==='L') ? 5000 : 0);

    $toppings_cost = 0;
    $toppings_list = [];
    foreach ($toppings_in as $tid) {
        $ts = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $ts->execute([intval($tid)]);
        $top = $ts->fetch();
        if ($top) { $top = localize_row($top, $site_lang, ['name','description']); $toppings_cost += $top['price']; $toppings_list[] = $top['name']; }
    }

    $final_price  = $price + $size_surcharge + $toppings_cost;
    $toppings_str = implode(', ', $toppings_list);
    $cart_key     = $product_id . '_' . $size . '_' . md5($toppings_str);

    if (isset($_SESSION['cart'][$cart_key])) {
        $_SESSION['cart'][$cart_key]['qty']               += $qty;
        $_SESSION['cart'][$cart_key]['total_item_amount']  = $_SESSION['cart'][$cart_key]['qty'] * $final_price;
    } else {
        $_SESSION['cart'][$cart_key] = [
            'product_id'        => $product_id,
            'name'              => $product['name'],
            'image'             => $product['image'],
            'price'             => $final_price,
            'size'              => $size,
            'toppings'          => $toppings_str,
            'qty'               => $qty,
            'total_item_amount' => $final_price * $qty,
        ];
    }

    $total_count = array_sum(array_column($_SESSION['cart'], 'qty'));
    echo json_encode(['status'=>'success','product_name'=>$product['name'],'total_count'=>$total_count]);
    exit();
}

if ($action === 'update') {
    $cart_key = $_POST['cart_key'] ?? '';
    $qty      = intval($_POST['qty'] ?? 1);
    if (!empty($cart_key) && isset($_SESSION['cart'][$cart_key])) {
        if ($qty <= 0) {
            unset($_SESSION['cart'][$cart_key]);
        } else {
            $_SESSION['cart'][$cart_key]['qty']               = $qty;
            $_SESSION['cart'][$cart_key]['total_item_amount'] = $_SESSION['cart'][$cart_key]['price'] * $qty;
        }
        $total  = array_sum(array_column($_SESSION['cart'], 'total_item_amount'));
        $count  = array_sum(array_column($_SESSION['cart'], 'qty'));
        echo json_encode(['status'=>'success','total_count'=>$count,'total_amount'=>$total,'formatted_total'=>formatVND($total)]);
        exit();
    }
}

if ($action === 'delete') {
    $cart_key = $_POST['cart_key'] ?? '';
    if (!empty($cart_key) && isset($_SESSION['cart'][$cart_key])) {
        unset($_SESSION['cart'][$cart_key]);
        $total = array_sum(array_column($_SESSION['cart'], 'total_item_amount'));
        $count = array_sum(array_column($_SESSION['cart'], 'qty'));
        echo json_encode(['status'=>'success','total_count'=>$count,'total_amount'=>$total,'formatted_total'=>formatVND($total)]);
        exit();
    }
}

if ($action === 'apply_voucher') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if (empty($code)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập mã giảm giá!']);
        exit();
    }

    $discount_pct = 0;
    // Known game vouchers
    if ($code === 'GLOWFARM25') {
        $discount_pct = 25;
    } elseif ($code === 'GLOWFARM15') {
        $discount_pct = 15;
    } elseif ($code === 'GLOWFARM10') {
        $discount_pct = 10;
    } elseif ($code === 'GLOWDUCK20') {
        $discount_pct = 20;
    } else {
        // Check database coupons table if available
        if (isset($pdo)) {
            try {
                $stmt = $pdo->prepare("SELECT discount_percent FROM coupons WHERE code = ? AND (expiry_date IS NULL OR expiry_date >= CURDATE())");
                $stmt->execute([$code]);
                $c = $stmt->fetch();
                if ($c) {
                    $discount_pct = intval($c['discount_percent']);
                }
            } catch (Exception $e) {}
        }
    }

    if ($discount_pct > 0) {
        $_SESSION['voucher'] = [
            'code' => $code,
            'discount_percent' => $discount_pct
        ];
        echo json_encode([
            'status' => 'success',
            'code' => $code,
            'discount_percent' => $discount_pct,
            'message' => "Áp dụng thành công mã {$code} (Giảm {$discount_pct}%)!"
        ]);
        exit();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Mã giảm giá không hợp lệ hoặc đã hết hạn!']);
        exit();
    }
}

if ($action === 'remove_voucher') {
    unset($_SESSION['voucher']);
    echo json_encode(['status' => 'success', 'message' => 'Đã hủy áp dụng voucher.']);
    exit();
}

echo json_encode(['status'=>'error','message'=>'Yeu cau khong hop le!']);
