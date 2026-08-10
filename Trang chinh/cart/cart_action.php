<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

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

    if (!$product) { echo json_encode(['status'=>'error','message'=>'San pham khong ton tai!']); exit(); }

    $price = $product['discount_price'] > 0 ? $product['discount_price'] : $product['price'];
    $size_surcharge = ($size==='S') ? -5000 : (($size==='L') ? 5000 : 0);

    $toppings_cost = 0;
    $toppings_list = [];
    foreach ($toppings_in as $tid) {
        $ts = $pdo->prepare("SELECT name, price FROM products WHERE id = ?");
        $ts->execute([intval($tid)]);
        $top = $ts->fetch();
        if ($top) { $toppings_cost += $top['price']; $toppings_list[] = $top['name']; }
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

echo json_encode(['status'=>'error','message'=>'Yeu cau khong hop le!']);
