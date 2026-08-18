<?php
require_once __DIR__ . '/../Trang chinh/config/config.php';

echo "Updating Drink Shop Database Seed Data...\n";

try {
    // 1. Ensure Category 6 exists
    $pdo->exec("INSERT IGNORE INTO categories (id, name, slug, description, image, status) VALUES
    (6, 'Đồ Ăn Nhẹ & Snack', 'do-an-nhe-snack', 'Bánh ngọt, bánh mì và đồ ăn vặt hấp dẫn dùng kèm nước uống', 'categories/snack.jpg', 1)");

    // 2. Map of product IDs to real Unsplash image URLs and metadata
    $product_updates = [
        1 => 'https://images.unsplash.com/photo-1541167760496-1628856ab772?w=600&auto=format&fit=crop&q=80', // Cà Phê Sữa Đá
        2 => 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=600&auto=format&fit=crop&q=80', // Cà Phê Đen Đá
        3 => 'https://images.unsplash.com/photo-1517256064527-09c73fc73e38?w=600&auto=format&fit=crop&q=80', // Bạc Xỉu
        4 => 'https://images.unsplash.com/photo-1534778101976-62847782c213?w=600&auto=format&fit=crop&q=80', // Cà Phê Muối
        5 => 'https://images.unsplash.com/photo-1558877385-81a1c7e67d72?w=600&auto=format&fit=crop&q=80', // Trà Sữa Trân Châu
        6 => 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', // Trà Sữa Ô Long Cheese
        7 => 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?w=600&auto=format&fit=crop&q=80', // Matcha Nhật Bản
        8 => 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', // Trà Đào Cam Sả
        9 => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=600&auto=format&fit=crop&q=80', // Trà Vải Lài
        10 => 'https://images.unsplash.com/photo-1546173159-315724a31696?w=600&auto=format&fit=crop&q=80', // Trà Dâu Tằm
        11 => 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?w=600&auto=format&fit=crop&q=80', // Matcha Đá Xay
        12 => 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', // Cà Phê Cốt Dừa
        13 => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=600&auto=format&fit=crop&q=80', // Chanh Tuyết
        14 => 'https://images.unsplash.com/photo-1558877385-81a1c7e67d72?w=600&auto=format&fit=crop&q=80', // Trân Châu Đen
        15 => 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', // Trân Châu Trắng
        16 => 'https://images.unsplash.com/photo-1534778101976-62847782c213?w=600&auto=format&fit=crop&q=80', // Kem Phô Mai
    ];

    $stmt = $pdo->prepare("UPDATE products SET image = ? WHERE id = ?");
    foreach ($product_updates as $id => $img) {
        $stmt->execute([$img, $id]);
    }
    echo "Updated " . count($product_updates) . " drinks with high-quality Unsplash image URLs.\n";

    // 3. Add snacks and food items
    $food_items = [
        [17, 6, 'Bánh Croissant Bơ Pháp', 'banh-croissant-bo-phap', 35000, 29000, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80', 'Bánh sừng bò ngàn lớp thơm lừng vị bơ Pháp cao cấp, giòn rụm bên ngoài, mềm xốp bên trong.', 100, 1, 1],
        [18, 6, 'Bánh Mì Thịt Nướng Giòn Rụm', 'banh-mi-thit-nuong', 39000, 35000, 'https://images.unsplash.com/photo-1626804475297-41608ea09aeb?w=600&auto=format&fit=crop&q=80', 'Bánh mì Việt Nam giòn kẹp thịt nướng thơm phức, đồ chua, dưa leo và sốt nhà làm độc quyền.', 80, 1, 1],
        [19, 6, 'Khoai Tây Chiên Sốt Phô Mai', 'khoai-tay-chien-sot-pho-mai', 32000, 0, 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 'Khoai tây vàng giòn rụm phủ lớp sốt phô mai béo ngậy cực cuốn.', 120, 1, 0],
        [20, 6, 'Gà Rán Sốt Cay Hàn Quốc', 'ga-ran-sot-cay-han-quoc', 49000, 45000, 'https://images.unsplash.com/photo-1626645738196-c2a7c87a8f58?w=600&auto=format&fit=crop&q=80', 'Gà giòn tan đẫm sốt cay ngọt chuẩn vị Hàn Quốc, rắc thêm vừng trắng thơm nồng.', 90, 1, 1],
        [21, 6, 'Bánh Tiramisu Cà Phê', 'banh-tiramisu-ca-phe', 42000, 38000, 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=600&auto=format&fit=crop&q=80', 'Bánh Tiramisu mềm mịn thơm đậm vị Espresso kết hợp lớp kem mascarpone béo ngậy.', 60, 1, 1],
        [22, 6, 'Bánh Mì Bơ Tỏi Hàn Quốc', 'banh-mi-bo-toi-han-quoc', 35000, 0, 'https://images.unsplash.com/photo-1619535860434-ba1d8fa12536?w=600&auto=format&fit=crop&q=80', 'Bánh mì mềm thơm lừng vị bơ tỏi ngậy tràn ngập lớp kem phô mai bên trong.', 75, 1, 0],
    ];

    $insert_stmt = $pdo->prepare("INSERT INTO products (id, category_id, name, slug, price, discount_price, image, description, quantity, status, is_featured) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
    ON DUPLICATE KEY UPDATE name=VALUES(name), price=VALUES(price), discount_price=VALUES(discount_price), image=VALUES(image), description=VALUES(description)");

    foreach ($food_items as $item) {
        $insert_stmt->execute($item);
    }
    echo "Added/updated 6 delicious snack and food items!\n";
    echo "SUCCESS: All product images & seed data updated!\n";
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
