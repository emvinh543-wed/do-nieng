-- =====================================================================
-- CẬP NHẬT HÌNH ẢNH SẢN PHẨM THỰC TẾ (UNSPLASH PHOTOS)
-- Chạy file này sau khi đã import drink_shop.sql
-- =====================================================================

USE `drink_shop`;

-- Cập nhật ảnh sản phẩm theo ID
-- ID 1: Cà Phê Sữa Đá Sài Gòn
UPDATE `products` SET `image` = 'https://images.unsplash.com/photo-1461023058943-07fcbe16d735?w=600&auto=format&fit=crop&q=80' WHERE `id` = 1;

-- ID 2: Cà Phê Đen Đá Pha Phin
UPDATE `products` SET `image` = 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?w=600&auto=format&fit=crop&q=80' WHERE `id` = 2;

-- ID 3: Bạc Xỉu Đá Thơm Béo
UPDATE `products` SET `image` = 'https://images.unsplash.com/photo-1572442388796-11668a67e53d?w=600&auto=format&fit=crop&q=80' WHERE `id` = 3;

-- ID 4: Cà Phê Muối Huế
UPDATE `products` SET `image` = 'https://images.unsplash.com/photo-1517701604599-bb29b565090c?w=600&auto=format&fit=crop&q=80' WHERE `id` = 4;

-- ID 5: Trà Sữa Trân Châu Hoàng Gia
UPDATE `products` SET `image` = 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=600&auto=format&fit=crop&q=80' WHERE `id` = 5;

-- ID 6: Trà Sữa Ô Long Kem Cheese
UPDATE `products` SET `image` = 'https://images.unsplash.com/photo-1577805947697-89e18249d767?w=600&auto=format&fit=crop&q=80' WHERE `id` = 6;

-- ID 7: Trà Sữa Matcha Nhật Bản
UPDATE `products` SET `image` = 'https://images.unsplash.com/photo-1617450365226-9bf28c04e130?w=600&auto=format&fit=crop&q=80' WHERE `id` = 7;

-- ID 8: Trà Đào Cam Sả Đặc Biệt
UPDATE `products` SET `image` = 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80' WHERE `id` = 8;

-- ID 9: Trà Vải Lài Hạt Chia
UPDATE `products` SET `image` = 'https://images.unsplash.com/photo-1544145945-f90425340c7e?w=600&auto=format&fit=crop&q=80' WHERE `id` = 9;

-- ID 10: Trà Dâu Tằm Pha Lê
UPDATE `products` SET `image` = 'https://images.unsplash.com/photo-1553361371-9b22f78e8b1d?w=600&auto=format&fit=crop&q=80' WHERE `id` = 10;

-- ID 11: Matcha Đá Xay Thượng Hạng
UPDATE `products` SET `image` = 'https://images.unsplash.com/photo-1515823064-d6e0c04616a7?w=600&auto=format&fit=crop&q=80' WHERE `id` = 11;

-- ID 12: Cà Phê Cốt Dừa Đá Xay
UPDATE `products` SET `image` = 'https://images.unsplash.com/photo-1490474418585-ba9bad8fd0ea?w=600&auto=format&fit=crop&q=80' WHERE `id` = 12;

-- ID 13: Chanh Tuyết Đá Xay
UPDATE `products` SET `image` = 'https://images.unsplash.com/photo-1621263764928-df1444c5e859?w=600&auto=format&fit=crop&q=80' WHERE `id` = 13;

-- ID 14: Trân Châu Đen Đường Đen
UPDATE `products` SET `image` = 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=600&auto=format&fit=crop&q=80' WHERE `id` = 14;

-- ID 15: Trân Châu Trắng 3Q
UPDATE `products` SET `image` = 'https://images.unsplash.com/photo-1547592180-85f173990554?w=600&auto=format&fit=crop&q=80' WHERE `id` = 15;

-- ID 16: Kem Phô Mai Cheese Salted
UPDATE `products` SET `image` = 'https://images.unsplash.com/photo-1497034825429-c343d7c6a68f?w=600&auto=format&fit=crop&q=80' WHERE `id` = 16;

-- Thêm sản phẩm đồ ăn mới (danh mục 5 hoặc tạo mới)
-- Thêm danh mục Đồ Ăn Nhẹ
INSERT IGNORE INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `status`) VALUES
(6, 'Đồ Ăn Nhẹ & Snack', 'do-an-nhe-snack', 'Các món ăn nhẹ, bánh ngọt, snack ăn kèm đồ uống', 'categories/food.jpg', 1);

-- Thêm sản phẩm đồ ăn mới
INSERT IGNORE INTO `products` (`id`, `category_id`, `name`, `slug`, `price`, `discount_price`, `image`, `description`, `content`, `quantity`, `status`, `is_featured`) VALUES
(17, 6, 'Bánh Croissant Bơ Pháp', 'banh-croissant-bo-phap', 35000, 29000,
 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80',
 'Bánh croissant bơ Pháp xếp lớp giòn tan, thơm lừng từ lò mới nướng mỗi sáng.',
 'Được làm từ bột mì chất lượng cao và bơ Pháp thượng hạng, bánh croissant của chúng tôi có lớp vỏ giòn vàng ươm, ruột mềm mịn và thơm ngào ngạt.',
 80, 1, 1),

(18, 6, 'Bánh Mì Que Pate Thịt', 'banh-mi-que-pate-thit', 25000, 0,
 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=600&auto=format&fit=crop&q=80',
 'Bánh mì que giòn rụm nhân pate thịt thơm ngon, kẹp rau sống tươi mát.',
 'Bánh mì que truyền thống Việt Nam với vỏ ngoài giòn vàng, nhân pate thịt đậm đà, kết hợp dưa leo, rau mùi và ớt tươi.',
 100, 1, 1),

(19, 6, 'Sushi Cá Hồi Cuộn Rong Biển', 'sushi-ca-hoi-cuon-rong-bien', 65000, 55000,
 'https://images.unsplash.com/photo-1617196034183-421b4040ed20?w=600&auto=format&fit=crop&q=80',
 'Set sushi 6 miếng cá hồi tươi cuộn cùng cơm nhật và rong biển, dùng kèm gừng wasabi.',
 'Sushi cá hồi nhập khẩu Na Uy thượng hạng, thịt cá tươi mịn màng, béo ngậy tự nhiên. Mỗi miếng sushi được cuộn thủ công tỉ mỉ theo kiểu Nhật truyền thống.',
 50, 1, 1),

(20, 6, 'Mì Ý Sốt Bò Bằm Bolognese', 'mi-y-sot-bo-bam-bolognese', 75000, 65000,
 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?w=600&auto=format&fit=crop&q=80',
 'Mì Ý sợi to nấu al-dente, sốt bò bằm cà chua thơm đậm đà kiểu Ý chính gốc.',
 'Công thức sốt Bolognese được nấu sầm sờ hơn 3 tiếng từ thịt bò xay, cà chua San Marzano, rau thơm và rượu vang đỏ. Mì ống chính hãng Barilla nhập từ Ý.',
 60, 1, 0),

(21, 6, 'Bánh Xèo Miền Tây Giòn Tan', 'banh-xeo-mien-tay-gion-tan', 45000, 0,
 'https://images.unsplash.com/photo-1625944525533-473f1a3d54e7?w=600&auto=format&fit=crop&q=80',
 'Bánh xèo miền Tây giòn vàng thơm nghệ, nhân tôm thịt giá đỗ ăn kèm rau sống.',
 'Bánh xèo được đổ bằng chảo gang dày, bột pha nghệ tươi vàng ươm, nhân gồm tôm sú tươi, thịt ba chỉ, giá đỗ và hành lá. Ăn kèm rau sống và nước mắm chua ngọt đặc biệt.',
 70, 1, 1),

(22, 6, 'Tteokbokki Bánh Gạo Cay Hàn Quốc', 'tteokbokki-banh-gao-cay-han-quoc', 55000, 49000,
 'https://images.unsplash.com/photo-1635363638580-c2809d049eee?w=600&auto=format&fit=crop&q=80',
 'Bánh gạo Hàn Quốc dai mềm ngào trong sốt gochujang cay nồng, thêm chả cá và trứng luộc.',
 'Tteokbokki được nấu theo công thức Hàn Quốc chính gốc với sốt gochujang (tương ớt Hàn), đường nâu và nước dashi. Kèm chả cá fishcake, trứng luộc và hành lá thái mỏng.',
 80, 1, 1);

SELECT 'Cap nhat hinh anh va du lieu ao thanh cong!' as Result;
