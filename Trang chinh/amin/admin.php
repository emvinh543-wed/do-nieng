<?php
require_once __DIR__ . '/../ThanhNgang/header.php';

if (!$current_user || $current_user['role'] !== 'admin') {
    echo '<section class="section" style="max-width:600px;margin:80px auto;text-align:center;"><div style="font-size:60px;">🔒</div><h2>Khu Vuc Han Che!</h2><p style="color:var(--text-muted);margin:10px 0 30px;">Ban can dang nhap bang tai khoan Quản Trị Viên (Admin).</p><a href="/login/login_demo.php?quick_login=admin" class="btn btn-primary">Dang nhap Admin ngay</a></section>';
    require_once __DIR__ . '/../ThanhNgang/footer.php'; exit();
}

// Auto create tables if needed for coupons
$pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    discount_percent INT NOT NULL,
    max_discount INT DEFAULT 0,
    min_order_amount INT DEFAULT 0,
    status TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$tab = $_GET['tab'] ?? 'dashboard';
$msg = $error = '';

// 1. Cap nhat don hang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $pdo->prepare("UPDATE orders SET order_status=?, payment_status=? WHERE id=?")->execute([$_POST['order_status'], $_POST['payment_status'], intval($_POST['order_id'])]);
    $msg = "Cap nhat don hang #" . intval($_POST['order_id']) . " thanh cong!";
}

// 2. Them san pham moi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name  = trim($_POST['name']);
    $cat   = intval($_POST['category_id']);
    $price = intval($_POST['price']);
    $image = trim($_POST['image'] ?? '');
    if (empty($image)) { $image = 'anh/trasuatranchau.png'; }

    if (!empty($name) && $cat > 0 && $price > 0) {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/','-',$name)).'-'.rand(100,999);
        $pdo->prepare("INSERT INTO products (category_id,name,slug,price,discount_price,quantity,description,image,status,is_featured) VALUES (?,?,?,?,?,?,?,?,1,?)")
            ->execute([$cat, $name, $slug, $price, intval($_POST['discount_price']), intval($_POST['quantity']), trim($_POST['description']), $image, isset($_POST['is_featured'])?1:0]);
        $msg = "Them san pham '$name' thanh cong!";
    } else { $error = "Vui long nhap day du Ten san pham, Danh muc va Gia goc!"; }
}

// 3. Xoa san pham
if (isset($_GET['delete_product_id'])) {
    $pid = intval($_GET['delete_product_id']);
    $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$pid]);
    $msg = "Da xoa san pham #$pid thanh cong!";
}

// 4. Bat / Tat trang thai san pham
if (isset($_GET['toggle_product_id'])) {
    $pid = intval($_GET['toggle_product_id']);
    $pdo->prepare("UPDATE products SET status = IF(status=1, 0, 1) WHERE id=?")->execute([$pid]);
    $msg = "Da thay doi trang thai san pham #$pid!";
}

// 5. Them danh muc moi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $cat_name = trim($_POST['cat_name']);
    $cat_desc = trim($_POST['cat_desc']);
    if (!empty($cat_name)) {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/','-',$cat_name)).'-'.rand(100,999);
        $pdo->prepare("INSERT INTO categories (name, slug, description, status) VALUES (?,?,?,1)")->execute([$cat_name, $slug, $cat_desc]);
        $msg = "Them danh muc '$cat_name' thanh cong!";
    } else { $error = "Vui long nhap ten danh muc!"; }
}

// 6. Bat / Tat trang thai danh muc
if (isset($_GET['toggle_category_id'])) {
    $cid = intval($_GET['toggle_category_id']);
    $pdo->prepare("UPDATE categories SET status = IF(status=1, 0, 1) WHERE id=?")->execute([$cid]);
    $msg = "Da thay doi trang thai danh muc #$cid!";
}

// 7. Khoa / Mo tai khoan nguoi dung
if (isset($_GET['toggle_user_id'])) {
    $uid = intval($_GET['toggle_user_id']);
    if ($uid !== $current_user['id']) {
        $pdo->prepare("UPDATE users SET status = IF(status=1, 0, 1) WHERE id=?")->execute([$uid]);
        $msg = "Da thay doi trang thai tai khoan #$uid!";
    } else { $error = "Khong the khoa tai khoan admin hien tai!"; }
}

// 8. CONTACTS ACTIONS (Cac thanh thao tac tren thu lien he)
if (isset($_GET['mark_contact_id'])) {
    $cid = intval($_GET['mark_contact_id']);
    $pdo->prepare("UPDATE contacts SET status = 1 WHERE id=?")->execute([$cid]);
    $msg = "Da danh dau thu #$cid la Da Doc!";
}

if (isset($_GET['mark_all_contacts_read'])) {
    $pdo->exec("UPDATE contacts SET status = 1 WHERE status = 0");
    $msg = "Da danh dau TAT CA thu chua doc thanh Da Doc!";
}

if (isset($_GET['delete_contact_id'])) {
    $cid = intval($_GET['delete_contact_id']);
    $pdo->prepare("DELETE FROM contacts WHERE id=?")->execute([$cid]);
    $msg = "Da xoa thu lien he #$cid!";
}

if (isset($_GET['delete_read_contacts'])) {
    $pdo->exec("DELETE FROM contacts WHERE status = 1 OR status = 2");
    $msg = "Da xoa tat ca thu da xu ly!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_contact'])) {
    $cid = intval($_POST['contact_id']);
    $reply_text = trim($_POST['reply_text']);
    if (!empty($reply_text)) {
        $pdo->prepare("UPDATE contacts SET status = 2 WHERE id=?")->execute([$cid]);
        $msg = "Da gui phan hoi thanh cong cho thu #$cid!";
    } else { $error = "Nội dung phản hồi không được để trống!"; }
}

// 9. COUPON ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coupon'])) {
    $code = strtoupper(trim($_POST['code']));
    $pct  = intval($_POST['discount_percent']);
    $min  = intval($_POST['min_order_amount']);
    if (!empty($code) && $pct > 0) {
        try {
            $pdo->prepare("INSERT INTO coupons (code, discount_percent, min_order_amount) VALUES (?,?,?)")->execute([$code, $pct, $min]);
            $msg = "Them ma giam gia '$code' (-$pct%) thanh cong!";
        } catch (Exception $e) { $error = "Ma giam gia da ton tai!"; }
    } else { $error = "Vui long nhap Ma va % giam gia!"; }
}

if (isset($_GET['delete_coupon_id'])) {
    $cpid = intval($_GET['delete_coupon_id']);
    $pdo->prepare("DELETE FROM coupons WHERE id=?")->execute([$cpid]);
    $msg = "Da xoa ma giam gia #$cpid!";
}

// Stats
$total_sales   = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE order_status='completed'")->fetchColumn() ?: 0;
$total_orders  = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pending_orders= $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='pending'")->fetchColumn();
$total_products= $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$total_users   = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$new_contacts  = $pdo->query("SELECT COUNT(*) FROM contacts WHERE status=0")->fetchColumn();
$total_contacts= $pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
$replied_contacts = $pdo->query("SELECT COUNT(*) FROM contacts WHERE status=2")->fetchColumn();
?>

<section class="section" style="max-width:1350px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <div>
            <h2 class="section-title" style="margin-bottom:5px;">He Thong <span>Quan Tri Vien</span></h2>
            <p class="section-desc">Trang quan ly ban hang, san pham, danh muc, khach hang va thu phan hoi</p>
        </div>
        <div style="background:white;padding:10px 20px;border-radius:var(--radius-md);border:1px solid var(--border);box-shadow:var(--shadow-sm);display:flex;align-items:center;gap:10px;">
            <div style="width:36px;height:36px;background:var(--primary-light);color:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;">👑</div>
            <div>
                <div style="font-weight:700;font-size:0.9rem;"><?php echo htmlspecialchars($current_user['fullname']); ?></div>
                <div style="font-size:0.75rem;color:var(--text-muted);">Quan tri vien he thong</div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Bar -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:18px;margin:25px 0 35px;">
        <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-md);padding:18px;box-shadow:var(--shadow-sm);">
            <span style="font-size:0.78rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;">💰 Doanh thu hoan thanh</span>
            <span style="display:block;font-size:1.5rem;font-weight:800;color:var(--primary);margin-top:4px;"><?php echo formatVND($total_sales); ?></span>
        </div>
        <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-md);padding:18px;box-shadow:var(--shadow-sm);">
            <span style="font-size:0.78rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;">📦 Don cho duyet</span>
            <span style="display:block;font-size:1.5rem;font-weight:800;color:var(--secondary);margin-top:4px;"><?php echo $pending_orders; ?> <span style="font-size:0.9rem;font-weight:500;">/ <?php echo $total_orders; ?> don</span></span>
        </div>
        <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-md);padding:18px;box-shadow:var(--shadow-sm);">
            <span style="font-size:0.78rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;">🥤 Tong mon do uong</span>
            <span style="display:block;font-size:1.5rem;font-weight:800;color:var(--dark);margin-top:4px;"><?php echo $total_products; ?> <span style="font-size:0.9rem;font-weight:500;">mon</span></span>
        </div>
        <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-md);padding:18px;box-shadow:var(--shadow-sm);">
            <span style="font-size:0.78rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;">👥 Khach hang</span>
            <span style="display:block;font-size:1.5rem;font-weight:800;color:#2563eb;margin-top:4px;"><?php echo $total_users; ?> <span style="font-size:0.9rem;font-weight:500;">tai khoan</span></span>
        </div>
        <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-md);padding:18px;box-shadow:var(--shadow-sm);">
            <span style="font-size:0.78rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;">✉️ Thu chua doc</span>
            <span style="display:block;font-size:1.5rem;font-weight:800;color:<?php echo $new_contacts>0?'var(--danger)':'var(--success)'; ?>;margin-top:4px;"><?php echo $new_contacts; ?> <span style="font-size:0.9rem;font-weight:500;">/ <?php echo $total_contacts; ?> thu</span></span>
        </div>
    </div>

    <?php if ($msg):   echo "<div style='background:#d1fae5;color:#059669;padding:12px 16px;border-radius:var(--radius-sm);margin-bottom:25px;font-weight:600;display:flex;align-items:center;gap:10px;'>✅ $msg</div>"; endif; ?>
    <?php if ($error): echo "<div style='background:#fee2e2;color:#dc2626;padding:12px 16px;border-radius:var(--radius-sm);margin-bottom:25px;font-weight:600;display:flex;align-items:center;gap:10px;'>⚠️ $error</div>"; endif; ?>

    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-sidebar-menu">
                <?php
                $tabs = [
                    'dashboard'  => ['icon'=>'📊', 'label'=>'Tong Quan Báo Cáo'],
                    'orders'     => ['icon'=>'📦', 'label'=>'Don Dat Hang'],
                    'products'   => ['icon'=>'🥤', 'label'=>'Menu Do Uong'],
                    'categories' => ['icon'=>'🏷️', 'label'=>'Danh Muc Mon'],
                    'users'      => ['icon'=>'👥', 'label'=>'Khach Hang'],
                    'contacts'   => ['icon'=>'✉️', 'label'=>'Thu Lien He'],
                    'promotions' => ['icon'=>'🎟️', 'label'=>'Ma Giam Gia'],
                    'reviews'    => ['icon'=>'⭐', 'label'=>'Danh Gia'],
                    'settings'   => ['icon'=>'⚙️', 'label'=>'Cau Hinh Quan'],
                ];
                foreach ($tabs as $k=>$v):
                ?>
                <a href="/amin/admin.php?tab=<?php echo $k; ?>" class="admin-sidebar-link <?php echo $tab===$k?'active':''; ?>">
                    <span><?php echo $v['icon']; ?></span>
                    <span><?php echo $v['label']; ?></span>
                    <?php if ($k==='orders' && $pending_orders > 0): ?>
                        <span style="margin-left:auto;background:var(--secondary);color:white;padding:2px 8px;border-radius:10px;font-size:0.75rem;font-weight:700;"><?php echo $pending_orders; ?></span>
                    <?php endif; ?>
                    <?php if ($k==='contacts' && $new_contacts > 0): ?>
                        <span style="margin-left:auto;background:var(--danger);color:white;padding:2px 8px;border-radius:10px;font-size:0.75rem;font-weight:700;"><?php echo $new_contacts; ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <main class="admin-content">
        <!-- 1. DASHBOARD -->
        <?php if ($tab === 'dashboard'): ?>
            <h3 style="font-size:1.4rem;font-weight:800;margin-bottom:10px;">Tong Quan Hoat Dong Cua Hang</h3>
            <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:25px;">Bao cao nhanh tinh hinh kinh doanh va don hang moi nhat</p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:30px;">
                <div style="background:white;padding:25px;border-radius:var(--radius-md);border:1px solid var(--border);">
                    <h4 style="font-weight:700;margin-bottom:15px;color:var(--primary);">🔥 Top Mon Uong Ban Chay</h4>
                    <ul style="list-style:none;padding:0;display:flex;flex-direction:column;gap:12px;">
                    <?php foreach ($pdo->query("SELECT p.name, p.price, p.image, COUNT(od.id) as total_sold FROM products p JOIN order_details od ON p.id = od.product_id GROUP BY p.id ORDER BY total_sold DESC LIMIT 4")->fetchAll() as $top): ?>
                        <li style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:10px;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <img src="<?php echo getProductImage($top['image']); ?>" style="width:36px;height:36px;border-radius:6px;object-fit:cover;">
                                <span style="font-weight:600;font-size:0.92rem;"><?php echo htmlspecialchars($top['name']); ?></span>
                            </div>
                            <span style="font-weight:700;color:var(--primary);font-size:0.88rem;"><?php echo $top['total_sold']; ?> luot mua</span>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </div>

                <div style="background:white;padding:25px;border-radius:var(--radius-md);border:1px solid var(--border);">
                    <h4 style="font-weight:700;margin-bottom:15px;color:var(--dark);">📋 Trang Thai Don Hang</h4>
                    <?php
                    $st_counts = [
                        'pending'   => ['label'=>'Cho duyet', 'color'=>'var(--secondary)'],
                        'processing'=> ['label'=>'Dang pha che', 'color'=>'#3b82f6'],
                        'shipping'  => ['label'=>'Dang giao hang', 'color'=>'#8b5cf6'],
                        'completed' => ['label'=>'Hoan thanh', 'color'=>'var(--success)'],
                        'cancelled' => ['label'=>'Da huy', 'color'=>'var(--danger)']
                    ];
                    foreach ($st_counts as $st_key => $st_info):
                        $cnt = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='$st_key'")->fetchColumn();
                    ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;font-size:0.9rem;">
                            <span><strong style="color:<?php echo $st_info['color']; ?>;">●</strong> <?php echo $st_info['label']; ?></span>
                            <span style="font-weight:700;"><?php echo $cnt; ?> don</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <!-- 2. ORDERS -->
        <?php elseif ($tab === 'orders'): ?>
            <h3 style="font-size:1.4rem;font-weight:800;margin-bottom:5px;">Quan Ly Don Dat Hang</h3>
            <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:20px;">Duyet don hang va cap nhat trang thai giao hang</p>
            <div class="table-responsive">
                <table><thead><tr><th>Ma don</th><th>Khach hang</th><th>SDT & Dia chi</th><th>Tong tien</th><th>Trang thai don</th><th>Thanh toan</th><th>Thao tac</th></tr></thead>
                <tbody>
                <?php foreach ($pdo->query("SELECT * FROM orders ORDER BY id DESC")->fetchAll() as $ord): ?>
                <tr>
                    <td style="font-weight:700;color:var(--primary);">#<?php echo $ord['id']; ?></td>
                    <td style="font-weight:600;"><?php echo htmlspecialchars($ord['fullname']); ?></td>
                    <td style="font-size:0.85rem;max-width:200px;"><?php echo htmlspecialchars($ord['phone']); ?><br><span style="color:var(--text-muted);"><?php echo htmlspecialchars($ord['address']); ?></span></td>
                    <td style="font-weight:700;color:var(--primary);"><?php echo formatVND($ord['total_amount']); ?></td>
                    <form action="/amin/admin.php?tab=orders" method="POST">
                        <input type="hidden" name="order_id" value="<?php echo $ord['id']; ?>">
                        <td><select name="order_status" class="form-control" style="padding:4px 8px;font-size:0.85rem;width:130px;">
                            <?php foreach (['pending'=>'Cho duyet','processing'=>'Dang pha','shipping'=>'Dang ship','completed'=>'Hoan thanh','cancelled'=>'Huy bo'] as $v=>$l): ?>
                                <option value="<?php echo $v; ?>" <?php echo $ord['order_status']===$v?'selected':''; ?>><?php echo $l; ?></option>
                            <?php endforeach; ?></select></td>
                        <td><select name="payment_status" class="form-control" style="padding:4px 8px;font-size:0.85rem;width:140px;">
                            <?php foreach (['unpaid'=>'Chua thanh toan','paid'=>'Da thanh toan','refunded'=>'Da hoan tien'] as $v=>$l): ?>
                                <option value="<?php echo $v; ?>" <?php echo $ord['payment_status']===$v?'selected':''; ?>><?php echo $l; ?></option>
                            <?php endforeach; ?></select></td>
                        <td><button type="submit" name="update_order" class="btn btn-primary" style="padding:6px 12px;font-size:0.8rem;border-radius:var(--radius-sm);">Luu</button></td>
                    </form>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>

        <!-- 3. PRODUCTS -->
        <?php elseif ($tab === 'products'): ?>
            <h3 style="font-size:1.4rem;font-weight:800;margin-bottom:20px;">Quan Ly Thuc Don Do Uong</h3>
            <div style="background:white;padding:25px;border-radius:var(--radius-md);border:1px solid var(--border);margin-bottom:30px;box-shadow:var(--shadow-sm);">
                <h4 style="font-weight:700;margin-bottom:15px;color:var(--primary);">➕ Them Mon Nuoc Moi</h4>
                <form action="/amin/admin.php?tab=products" method="POST">
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Ten mon uong *</label><input type="text" name="name" class="form-control" placeholder="Vi du: Tra sua matcha" required></div>
                        <div class="form-group"><label class="form-label">Danh muc *</label>
                            <select name="category_id" class="form-control" style="background:white;" required>
                                <option value="">-- Chon danh muc --</option>
                                <?php foreach ($pdo->query("SELECT * FROM categories")->fetchAll() as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Gia goc (VND) *</label><input type="number" name="price" class="form-control" placeholder="35000" required></div>
                        <div class="form-group"><label class="form-label">Gia khuyen mai (VND)</label><input type="number" name="discount_price" class="form-control" value="0"></div>
                        <div class="form-group"><label class="form-label">So luong kho</label><input type="number" name="quantity" class="form-control" value="100"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Duong dan file anh (tuy chon)</label><input type="text" name="image" class="form-control" placeholder="anh/trasuatranchau.png"></div>
                        <div class="form-group"><label class="form-label">Mo ta ngan</label><input type="text" name="description" class="form-control" placeholder="Mo ta huong vi..."></div>
                    </div>
                    <div style="display:flex;align-items:center;gap:15px;margin-top:10px;">
                        <label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;"><input type="checkbox" name="is_featured" value="1"> Mon noi bat / Ban chay</label>
                        <button type="submit" name="add_product" class="btn btn-primary" style="padding:10px 25px;margin-left:auto;">Them Mon Moi</button>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table><thead><tr><th>Anh</th><th>Ten do uong</th><th>Danh muc</th><th>Gia ban</th><th>Ton kho</th><th>Trang thai</th><th>Thao tac</th></tr></thead>
                <tbody>
                <?php foreach ($pdo->query("SELECT p.*,c.name as cat_name FROM products p JOIN categories c ON p.category_id=c.id ORDER BY p.id DESC")->fetchAll() as $p): ?>
                <tr>
                    <td><img src="<?php echo getProductImage($p['image']); ?>" style="width:42px;height:42px;border-radius:var(--radius-sm);object-fit:cover;"
                             onerror="this.src='https://images.unsplash.com/photo-1544025162-d76694265947?w=500&auto=format&fit=crop&q=60'"></td>
                    <td style="font-weight:700;"><?php echo htmlspecialchars($p['name']); ?></td>
                    <td><?php echo htmlspecialchars($p['cat_name']); ?></td>
                    <td style="font-weight:700;color:var(--primary);"><?php echo formatVND($p['discount_price']>0?$p['discount_price']:$p['price']); ?></td>
                    <td style="font-weight:700;color:<?php echo $p['quantity']<=5?'var(--danger)':'inherit'; ?>"><?php echo $p['quantity']; ?> coc</td>
                    <td>
                        <a href="/amin/admin.php?tab=products&toggle_product_id=<?php echo $p['id']; ?>" class="status-badge <?php echo $p['status']?'status-completed':'status-cancelled'; ?>" style="font-size:0.75rem;text-decoration:none;">
                            <?php echo $p['status']?'Dang ban':'Ngung ban'; ?>
                        </a>
                    </td>
                    <td>
                        <a href="/amin/admin.php?tab=products&delete_product_id=<?php echo $p['id']; ?>" onclick="return confirm('Xoa mon nuoc nay?')" style="color:var(--danger);font-weight:700;font-size:0.85rem;text-decoration:none;">❌ Xoa</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>

        <!-- 4. CATEGORIES -->
        <?php elseif ($tab === 'categories'): ?>
            <h3 style="font-size:1.4rem;font-weight:800;margin-bottom:20px;">Quan Ly Danh Muc Mon</h3>
            <div style="background:white;padding:25px;border-radius:var(--radius-md);border:1px solid var(--border);margin-bottom:30px;box-shadow:var(--shadow-sm);">
                <h4 style="font-weight:700;margin-bottom:15px;color:var(--primary);">🏷️ Them Danh Muc Moi</h4>
                <form action="/amin/admin.php?tab=categories" method="POST">
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Ten danh muc *</label><input type="text" name="cat_name" class="form-control" placeholder="Vi du: Tra Sinh To" required></div>
                        <div class="form-group"><label class="form-label">Mo ta danh muc</label><input type="text" name="cat_desc" class="form-control" placeholder="Mo ta ngan..."></div>
                    </div>
                    <button type="submit" name="add_category" class="btn btn-primary" style="padding:10px 25px;">Them Danh Muc</button>
                </form>
            </div>

            <div class="table-responsive">
                <table><thead><tr><th>ID</th><th>Ten danh muc</th><th>Slug</th><th>Mo ta</th><th>So san pham</th><th>Trang thai</th></tr></thead>
                <tbody>
                <?php foreach ($pdo->query("SELECT c.*, COUNT(p.id) as total_p FROM categories c LEFT JOIN products p ON c.id=p.category_id GROUP BY c.id ORDER BY c.id ASC")->fetchAll() as $cat): ?>
                <tr>
                    <td style="font-weight:700;">#<?php echo $cat['id']; ?></td>
                    <td style="font-weight:700;color:var(--dark);"><?php echo htmlspecialchars($cat['name']); ?></td>
                    <td style="font-size:0.85rem;color:var(--text-muted);"><?php echo htmlspecialchars($cat['slug']); ?></td>
                    <td style="font-size:0.88rem;"><?php echo htmlspecialchars($cat['description'] ?: 'Chua co mo ta'); ?></td>
                    <td style="font-weight:700;color:var(--primary);"><?php echo $cat['total_p']; ?> mon</td>
                    <td>
                        <a href="/amin/admin.php?tab=categories&toggle_category_id=<?php echo $cat['id']; ?>" class="status-badge <?php echo $cat['status']?'status-completed':'status-cancelled'; ?>" style="font-size:0.75rem;text-decoration:none;">
                            <?php echo $cat['status']?'Hien thi':'Dang an'; ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>

        <!-- 5. USERS -->
        <?php elseif ($tab === 'users'): ?>
            <h3 style="font-size:1.4rem;font-weight:800;margin-bottom:10px;">Quan Ly Khach Hang & Tai Khoan</h3>
            <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:20px;">Danh sach tài khoản khách hàng đăng ký trên hệ thống</p>
            <div class="table-responsive">
                <table><thead><tr><th>ID</th><th>Ho ten</th><th>Username</th><th>Email</th><th>SDT</th><th>Vai tro</th><th>Trang thai</th></tr></thead>
                <tbody>
                <?php foreach ($pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll() as $u): ?>
                <tr>
                    <td style="font-weight:700;">#<?php echo $u['id']; ?></td>
                    <td style="font-weight:700;"><?php echo htmlspecialchars($u['fullname']); ?></td>
                    <td><code><?php echo htmlspecialchars($u['username']); ?></code></td>
                    <td style="font-size:0.88rem;"><?php echo htmlspecialchars($u['email']); ?></td>
                    <td style="font-size:0.88rem;"><?php echo htmlspecialchars($u['phone'] ?: 'Chua cap nhat'); ?></td>
                    <td><span style="font-weight:700;color:<?php echo $u['role']==='admin'?'var(--primary)':'var(--dark)'; ?>;"><?php echo strtoupper($u['role']); ?></span></td>
                    <td>
                        <?php if ($u['id'] !== $current_user['id']): ?>
                            <a href="/amin/admin.php?tab=users&toggle_user_id=<?php echo $u['id']; ?>" class="status-badge <?php echo $u['status']?'status-completed':'status-cancelled'; ?>" style="font-size:0.75rem;text-decoration:none;">
                                <?php echo $u['status']?'Hoat dong':'Bi khoa'; ?>
                            </a>
                        <?php else: ?>
                            <span class="status-badge status-completed" style="font-size:0.75rem;">Admin Hien Tai</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>

        <!-- 6. CONTACTS (THƯ LIÊN HỆ - CÓ NHIỀU THANH CÔNG CỤ NÂNG CAO) -->
        <?php elseif ($tab === 'contacts'):
            $q  = trim($_GET['q'] ?? '');
            $st = $_GET['st'] ?? 'all';

            $sql = "SELECT * FROM contacts WHERE 1=1";
            $params = [];
            if ($st !== 'all') {
                $sql .= " AND status = ?";
                $params[] = intval($st);
            }
            if (!empty($q)) {
                $sql .= " AND (fullname LIKE ? OR email LIKE ? OR phone LIKE ? OR subject LIKE ? OR message LIKE ?)";
                $search_term = "%$q%";
                $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
            }
            $sql .= " ORDER BY id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $contact_list = $stmt->fetchAll();
        ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;flex-wrap:wrap;gap:15px;">
                <div>
                    <h3 style="font-size:1.4rem;font-weight:800;margin-bottom:5px;">Quan Ly Thu Phan Hoi Tu Khach Hang</h3>
                    <p style="color:var(--text-muted);font-size:0.9rem;">Xem, tim kiem, phan hoi va xu ly cac thu dong gop tu khach hang</p>
                </div>
            </div>

            <!-- THANH CÔNG CỤ 1: KPI STATS TOOLBAR -->
            <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:15px;margin-bottom:20px;">
                <a href="/amin/admin.php?tab=contacts&st=all" style="text-decoration:none;background:white;padding:15px;border-radius:var(--radius-md);border:1px solid var(--border);box-shadow:var(--shadow-sm);display:flex;align-items:center;justify-content:space-between;">
                    <div><span style="font-size:0.8rem;color:var(--text-muted);font-weight:700;">Tong so thu</span><span style="display:block;font-size:1.3rem;font-weight:800;color:var(--dark);"><?php echo $total_contacts; ?></span></div>
                    <span style="font-size:1.5rem;">📬</span>
                </a>
                <a href="/amin/admin.php?tab=contacts&st=0" style="text-decoration:none;background:white;padding:15px;border-radius:var(--radius-md);border:<?php echo $st==='0'?'2px solid var(--danger)':'1px solid var(--border)'; ?>;box-shadow:var(--shadow-sm);display:flex;align-items:center;justify-content:space-between;">
                    <div><span style="font-size:0.8rem;color:var(--danger);font-weight:700;">Thư chưa đọc</span><span style="display:block;font-size:1.3rem;font-weight:800;color:var(--danger);"><?php echo $new_contacts; ?></span></div>
                    <span style="font-size:1.5rem;">🔴</span>
                </a>
                <a href="/amin/admin.php?tab=contacts&st=1" style="text-decoration:none;background:white;padding:15px;border-radius:var(--radius-md);border:<?php echo $st==='1'?'2px solid var(--primary)':'1px solid var(--border)'; ?>;box-shadow:var(--shadow-sm);display:flex;align-items:center;justify-content:space-between;">
                    <div><span style="font-size:0.8rem;color:var(--primary);font-weight:700;">Đã xem / Đã đọc</span><span style="display:block;font-size:1.3rem;font-weight:800;color:var(--primary);"><?php echo $total_contacts - $new_contacts - $replied_contacts; ?></span></div>
                    <span style="font-size:1.5rem;">🔵</span>
                </a>
                <a href="/amin/admin.php?tab=contacts&st=2" style="text-decoration:none;background:white;padding:15px;border-radius:var(--radius-md);border:<?php echo $st==='2'?'2px solid var(--success)':'1px solid var(--border)'; ?>;box-shadow:var(--shadow-sm);display:flex;align-items:center;justify-content:space-between;">
                    <div><span style="font-size:0.8rem;color:var(--success);font-weight:700;">Đã phản hồi</span><span style="display:block;font-size:1.3rem;font-weight:800;color:var(--success);"><?php echo $replied_contacts; ?></span></div>
                    <span style="font-size:1.5rem;">🟢</span>
                </a>
            </div>

            <!-- THANH CÔNG CỤ 2: SEARCH & FILTER TOOLBAR -->
            <div style="background:white;padding:18px;border-radius:var(--radius-md);border:1px solid var(--border);margin-bottom:20px;box-shadow:var(--shadow-sm);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px;">
                <form action="/amin/admin.php" method="GET" style="display:flex;align-items:center;gap:10px;flex:1;max-width:550px;">
                    <input type="hidden" name="tab" value="contacts">
                    <input type="hidden" name="st" value="<?php echo htmlspecialchars($st); ?>">
                    <div style="position:relative;width:100%;">
                        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="form-control" placeholder="Tim kiem theo ten, email, sdt, noi dung..." style="padding-left:35px;">
                        <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);">🔍</span>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding:8px 18px;white-space:nowrap;">Tim kiem</button>
                    <?php if (!empty($q) || $st!=='all'): ?>
                        <a href="/amin/admin.php?tab=contacts" class="btn btn-outline" style="padding:8px 12px;white-space:nowrap;font-size:0.85rem;">Dat lai</a>
                    <?php endif; ?>
                </form>

                <!-- THANH CÔNG CỤ 3: BULK ACTIONS TOOLBAR -->
                <div style="display:flex;align-items:center;gap:10px;">
                    <a href="/amin/admin.php?tab=contacts&mark_all_contacts_read=1" onclick="return confirm('Danh dau tat ca thư la Da Doc?')" class="btn btn-outline" style="padding:8px 14px;font-size:0.85rem;border-color:var(--primary);color:var(--primary);">
                        ✔ Đánh dấu tất cả đã đọc
                    </a>
                    <a href="/amin/admin.php?tab=contacts&delete_read_contacts=1" onclick="return confirm('Xoa tat ca thu da xu ly?')" class="btn btn-outline" style="padding:8px 14px;font-size:0.85rem;border-color:var(--danger);color:var(--danger);">
                        🗑️ Xóa thư đã xử lý
                    </a>
                </div>
            </div>

            <!-- BẢNG DANH SÁCH THƯ -->
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nguoi gui</th>
                            <th>Email / SDT</th>
                            <th>Chu de</th>
                            <th>Noi dung phan hoi</th>
                            <th>Ngay gui</th>
                            <th>Trang thai</th>
                            <th>Thao tac quan tri</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($contact_list) > 0): ?>
                        <?php foreach ($contact_list as $c): ?>
                        <tr style="<?php echo $c['status']==0?'background-color:#fffbeb;font-weight:600;':''; ?>">
                            <td style="font-weight:700;color:var(--text-muted);">#<?php echo $c['id']; ?></td>
                            <td style="font-weight:700;"><?php echo htmlspecialchars($c['fullname']); ?></td>
                            <td style="font-size:0.85rem;">
                                <a href="mailto:<?php echo htmlspecialchars($c['email']); ?>" style="color:var(--primary);text-decoration:none;font-weight:600;"><?php echo htmlspecialchars($c['email']); ?></a>
                                <br><span style="color:var(--text-muted);"><?php echo htmlspecialchars($c['phone'] ?: 'Khong co SDT'); ?></span>
                            </td>
                            <td style="font-weight:600;color:var(--dark);"><?php echo htmlspecialchars($c['subject']); ?></td>
                            <td style="font-size:0.88rem;max-width:260px;line-height:1.4;">
                                <?php echo nl2br(htmlspecialchars($c['message'])); ?>
                            </td>
                            <td style="font-size:0.8rem;color:var(--text-muted);"><?php echo date('d/m/Y H:i',strtotime($c['created_at'])); ?></td>
                            <td>
                                <?php if ($c['status'] == 0): ?>
                                    <span class="status-badge status-pending" style="font-size:0.75rem;background:#fee2e2;color:#dc2626;">🔴 Mới (Chưa đọc)</span>
                                <?php elseif ($c['status'] == 1): ?>
                                    <span class="status-badge status-completed" style="font-size:0.75rem;background:#e0f2fe;color:#0284c7;">🔵 Đã đọc</span>
                                <?php else: ?>
                                    <span class="status-badge status-completed" style="font-size:0.75rem;background:#d1fae5;color:#059669;">🟢 Đã phản hồi</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex;flex-direction:column;gap:6px;">
                                    <?php if ($c['status'] == 0): ?>
                                        <a href="/amin/admin.php?tab=contacts&mark_contact_id=<?php echo $c['id']; ?>" class="btn btn-primary" style="padding:4px 8px;font-size:0.75rem;text-align:center;">✔ Đọc</a>
                                    <?php endif; ?>
                                    <button type="button" onclick="openReplyModal(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['fullname'])); ?>', '<?php echo htmlspecialchars(addslashes($c['email'])); ?>')" class="btn btn-secondary" style="padding:4px 8px;font-size:0.75rem;">✉️ Trả lời</button>
                                    <a href="/amin/admin.php?tab=contacts&delete_contact_id=<?php echo $c['id']; ?>" onclick="return confirm('Xoa thu nay?')" style="color:var(--danger);font-weight:700;font-size:0.78rem;text-decoration:none;text-align:center;">❌ Xóa</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted);">Khong tim thay thu lien he nao khop voi dieu kien.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- MODAL THANH TRẢ LỜI KHÁCH HÀNG (QUICK REPLY MODAL TOOL) -->
            <div id="replyModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
                <div style="background:white;border-radius:var(--radius-lg);max-width:550px;width:90%;padding:30px;box-shadow:var(--shadow-lg);position:relative;">
                    <button type="button" onclick="closeReplyModal()" style="position:absolute;top:15px;right:20px;border:none;background:none;font-size:1.5rem;cursor:pointer;">✖</button>
                    <h3 style="font-weight:800;margin-bottom:15px;color:var(--dark);">✉️ Trả Lời Thư Khách Hàng</h3>
                    <form action="/amin/admin.php?tab=contacts" method="POST">
                        <input type="hidden" name="contact_id" id="modal_contact_id">
                        <div style="margin-bottom:15px;background:var(--light);padding:12px;border-radius:var(--radius-sm);font-size:0.9rem;">
                            <div><strong>Người nhận:</strong> <span id="modal_fullname"></span></div>
                            <div><strong>Email:</strong> <span id="modal_email"></span></div>
                        </div>
                        <div class="form-group" style="margin-bottom:20px;">
                            <label class="form-label">Nội dung phản hồi (Gửi tới Email khách hàng):</label>
                            <textarea name="reply_text" rows="5" class="form-control" required placeholder="Kính gửi quý khách, cảm ơn bạn đã gửi ý kiến đóng góp cho GlowDrinks..."></textarea>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:10px;">
                            <button type="button" onclick="closeReplyModal()" class="btn btn-outline" style="padding:8px 16px;">Hủy</button>
                            <button type="submit" name="reply_contact" class="btn btn-primary" style="padding:8px 20px;">Gui Phan Hoi</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            function openReplyModal(id, name, email) {
                document.getElementById('modal_contact_id').value = id;
                document.getElementById('modal_fullname').innerText = name;
                document.getElementById('modal_email').innerText = email;
                document.getElementById('replyModal').style.display = 'flex';
            }
            function closeReplyModal() {
                document.getElementById('replyModal').style.display = 'none';
            }
            </script>

        <!-- 7. PROMOTIONS (MÃ GIẢM GIÁ) -->
        <?php elseif ($tab === 'promotions'): ?>
            <h3 style="font-size:1.4rem;font-weight:800;margin-bottom:20px;">Quan Ly Ma Giam Gia & Khuyen Mai</h3>
            <div style="background:white;padding:25px;border-radius:var(--radius-md);border:1px solid var(--border);margin-bottom:30px;box-shadow:var(--shadow-sm);">
                <h4 style="font-weight:700;margin-bottom:15px;color:var(--primary);">🎟️ Them Ma Giam Gia Moi</h4>
                <form action="/amin/admin.php?tab=promotions" method="POST">
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Ma khuyen mai *</label><input type="text" name="code" class="form-control" placeholder="Vi du: GLOW2026" required style="text-transform:uppercase;"></div>
                        <div class="form-group"><label class="form-label">% Giam gia *</label><input type="number" name="discount_percent" class="form-control" placeholder="10" min="1" max="100" required></div>
                        <div class="form-group"><label class="form-label">Don toi thieu (VND)</label><input type="number" name="min_order_amount" class="form-control" value="0"></div>
                    </div>
                    <button type="submit" name="add_coupon" class="btn btn-primary" style="padding:10px 25px;">Them Ma Khuyen Mai</button>
                </form>
            </div>

            <div class="table-responsive">
                <table><thead><tr><th>ID</th><th>Ma Coupon</th><th>Phan tram giam</th><th>Don toi thieu</th><th>Trang thai</th><th>Thao tac</th></tr></thead>
                <tbody>
                <?php foreach ($pdo->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll() as $cp): ?>
                <tr>
                    <td style="font-weight:700;">#<?php echo $cp['id']; ?></td>
                    <td><code style="font-size:1rem;font-weight:800;color:var(--primary);"><?php echo htmlspecialchars($cp['code']); ?></code></td>
                    <td style="font-weight:700;color:var(--success);">-<?php echo $cp['discount_percent']; ?>%</td>
                    <td style="font-weight:600;"><?php echo formatVND($cp['min_order_amount']); ?></td>
                    <td><span class="status-badge status-completed" style="font-size:0.75rem;">Hoat dong</span></td>
                    <td>
                        <a href="/amin/admin.php?tab=promotions&delete_coupon_id=<?php echo $cp['id']; ?>" onclick="return confirm('Xoa ma nay?')" style="color:var(--danger);font-weight:700;font-size:0.85rem;text-decoration:none;">❌ Xoa</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>

        <!-- 8. REVIEWS -->
        <?php elseif ($tab === 'reviews'): ?>
            <h3 style="font-size:1.4rem;font-weight:800;margin-bottom:10px;">Quan Ly Danh Gia & Binh Luan</h3>
            <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:20px;">Danh sách bình luận từ người mua hàng</p>
            <div class="table-responsive">
                <table><thead><tr><th>Khach hang</th><th>Do uong</th><th>Diem sao</th><th>Binh luan</th><th>Thoi gian</th><th>Thao tac</th></tr></thead>
                <tbody>
                <?php foreach ($pdo->query("SELECT r.*,u.fullname,p.name as prod_name FROM reviews r JOIN users u ON r.user_id=u.id JOIN products p ON r.product_id=p.id ORDER BY r.id DESC")->fetchAll() as $r): ?>
                <tr>
                    <td style="font-weight:700;"><?php echo htmlspecialchars($r['fullname']); ?></td>
                    <td style="font-weight:600;color:var(--primary);"><?php echo htmlspecialchars($r['prod_name']); ?></td>
                    <td style="color:var(--secondary);"><?php echo str_repeat('★',$r['rating']).str_repeat('☆',5-$r['rating']); ?></td>
                    <td style="font-size:0.9rem;"><?php echo htmlspecialchars($r['comment']); ?></td>
                    <td style="font-size:0.8rem;"><?php echo date('d/m/Y H:i',strtotime($r['created_at'])); ?></td>
                    <td>
                        <a href="/amin/admin.php?tab=reviews&delete_review_id=<?php echo $r['id']; ?>" onclick="return confirm('Xoa binh luan nay?')" style="color:var(--danger);font-weight:700;font-size:0.85rem;text-decoration:none;">❌ Xoa</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>

        <!-- 9. SETTINGS -->
        <?php elseif ($tab === 'settings'): ?>
            <h3 style="font-size:1.4rem;font-weight:800;margin-bottom:20px;">Cau Hinh Cua Hang & Thuong Hieu</h3>
            <div style="background:white;padding:30px;border-radius:var(--radius-md);border:1px solid var(--border);box-shadow:var(--shadow-sm);max-width:750px;">
                <form action="#" method="POST" onsubmit="alert('Cap nhat cau hinh thanh cong!'); return false;">
                    <div class="form-group"><label class="form-label">Ten thuong hieu cửa hàng</label><input type="text" class="form-control" value="GlowDrinks - World of Fine Drinks"></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Hotline tong đài</label><input type="text" class="form-control" value="1900 6868"></div>
                        <div class="form-group"><label class="form-label">Email phan hoi</label><input type="email" class="form-control" value="lienhe@glowdrinks.com"></div>
                    </div>
                    <div class="form-group"><label class="form-label">Dia chi chi nhanh chinh</label><input type="text" class="form-control" value="123 Duong Ba Thang Hai, Quan 10, TP.HCM"></div>
                    <div class="form-group"><label class="form-label">Gio mo cua</label><input type="text" class="form-control" value="07:00 - 22:30 hang ngay"></div>
                    <button type="submit" class="btn btn-primary" style="padding:12px 25px;margin-top:10px;">Luu Cau Hinh Cua Hang</button>
                </form>
            </div>
        <?php endif; ?>
        </main>
    </div>
</section>

<?php require_once __DIR__ . '/../ThanhNgang/footer.php'; ?>
