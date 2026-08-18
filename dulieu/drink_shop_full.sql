-- =====================================================================
-- DUCKDUCK / GLOWDRINKS DATABASE (FULL SEED DATA WITH REAL IMAGES)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `drink_shop` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `drink_shop`;

-- 1. USERS TABLE
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `fullname` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `phone` VARCHAR(15) NULL,
    `address` VARCHAR(255) NULL,
    `role` ENUM('admin', 'customer') DEFAULT 'customer',
    `status` TINYINT DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. CATEGORIES TABLE
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `image` VARCHAR(255) NULL,
    `status` TINYINT DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. PRODUCTS TABLE
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) NOT NULL UNIQUE,
    `price` DECIMAL(10, 0) NOT NULL,
    `discount_price` DECIMAL(10, 0) DEFAULT 0,
    `image` VARCHAR(500) NULL,
    `description` TEXT NULL,
    `content` LONGTEXT NULL,
    `quantity` INT DEFAULT 100,
    `status` TINYINT DEFAULT 1,
    `is_featured` TINYINT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ORDERS TABLE
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `fullname` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(15) NOT NULL,
    `address` VARCHAR(255) NOT NULL,
    `total_amount` DECIMAL(12, 0) NOT NULL,
    `payment_method` ENUM('COD', 'QR_CODE', 'MOMO', 'VNPAY') DEFAULT 'COD',
    `payment_status` ENUM('unpaid', 'paid', 'refunded') DEFAULT 'unpaid',
    `order_status` ENUM('pending', 'processing', 'shipping', 'completed', 'cancelled') DEFAULT 'pending',
    `note` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. ORDER DETAILS TABLE
CREATE TABLE IF NOT EXISTS `order_details` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `price` DECIMAL(10, 0) NOT NULL,
    `quantity` INT NOT NULL,
    `size` ENUM('S', 'M', 'L') DEFAULT 'M',
    `toppings` VARCHAR(255) NULL,
    `total_item_amount` DECIMAL(12, 0) NOT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SEED CATEGORIES
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `status`) VALUES
(1, 'Cà Phê Truyền Thống', 'ca-phe-truyen-thong', 'Các dòng cà phê đậm vị Việt Nam', 'categories/caphe.jpg', 1),
(2, 'Trà Sữa Đặc Biệt', 'tra-sua-dac-biet', 'Trà sữa thơm béo kết hợp trân châu', 'categories/trasua.jpg', 1),
(3, 'Trà Trái Cây Thanh Nhiệt', 'tra-trai-cay-thanh-nhiet', 'Trà kết hợp trái cây tươi mát lạnh', 'categories/tratraicay.jpg', 1),
(4, 'Đá Xay & Sinh Tố', 'da-xay-sinh-to', 'Thức uống đá xay mát lạnh, béo ngậy', 'categories/daxay.jpg', 1),
(5, 'Toppings Thêm', 'toppings-them', 'Các loại topping ăn kèm hấp dẫn', 'categories/topping.jpg', 1),
(6, 'Đồ Ăn Nhẹ & Snack', 'do-an-nhe-snack', 'Bánh ngọt, bánh mì và đồ ăn vặt hấp dẫn', 'categories/snack.jpg', 1)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- SEED PRODUCTS (WITH REAL FOOD & DRINK UNSPLASH IMAGES)
INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `price`, `discount_price`, `image`, `description`, `quantity`, `status`, `is_featured`) VALUES
(1, 1, 'Cà Phê Sữa Đá Sài Gòn', 'ca-phe-sua-da-sai-gon', 29000, 25000, 'https://images.unsplash.com/photo-1541167760496-1628856ab772?w=600&auto=format&fit=crop&q=80', 'Cà phê Robusta đậm đặc pha phin kết hợp với sữa đặc có đường và đá nhuyễn.', 150, 1, 1),
(2, 1, 'Cà Phê Đen Đá Pha Phin', 'ca-phe-den-da-pha-phin', 25000, 0, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=600&auto=format&fit=crop&q=80', 'Cà phê đen nguyên chất pha phin truyền thống, hương vị đậm đà mộc mạc.', 200, 1, 0),
(3, 1, 'Bạc Xỉu Đá Thơm Béo', 'bac-xiu-da-thom-beo', 32000, 29000, 'https://images.unsplash.com/photo-1517256064527-09c73fc73e38?w=600&auto=format&fit=crop&q=80', 'Nhiều sữa ít cà phê, sự lựa chọn ngọt ngào cho ngày mới nhẹ nhàng.', 180, 1, 1),
(4, 1, 'Cà Phê Muối Huế', 'ca-phe-muoi-hue', 35000, 0, 'https://images.unsplash.com/photo-1534778101976-62847782c213?w=600&auto=format&fit=crop&q=80', 'Cà phê phin kết hợp lớp kem muối béo mặn độc đáo và đậm đà.', 120, 1, 1),
(5, 2, 'Trà Sữa Trân Châu Hoàng Gia', 'tra-sua-tran-chau-hoang-gia', 45000, 39000, 'https://images.unsplash.com/photo-1558877385-81a1c7e67d72?w=600&auto=format&fit=crop&q=80', 'Trà sữa truyền thống đậm vị trà cùng trân châu đen dai giòn ngọt lịm.', 300, 1, 1),
(6, 2, 'Trà Sữa Ô Long Kem Cheese', 'tra-sua-o-long-kem-cheese', 49000, 45000, 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 'Trà sữa ô long thơm nhẹ kết hợp với lớp kem sữa phô mai mặn béo.', 150, 1, 1),
(7, 2, 'Trà Sữa Matcha Nhật Bản', 'tra-sua-matcha-nhat-ban', 45000, 0, 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?w=600&auto=format&fit=crop&q=80', 'Bột trà xanh Uji nhập khẩu trực tiếp từ Nhật Bản kết hợp sữa tươi béo.', 160, 1, 0),
(8, 3, 'Trà Đào Cam Sả Đặc Biệt', 'tra-dao-cam-sa-dac-biet', 39000, 35000, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 'Trà đào thanh ngọt kết hợp sả tươi thơm nồng và những lát cam vàng mọng nước.', 250, 1, 1),
(9, 3, 'Trà Vải Lài Hạt Chia', 'tra-vai-lai-hat-chia', 39000, 0, 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=600&auto=format&fit=crop&q=80', 'Trà lài thanh mát kết hợp với quả vải ngâm ngọt lịm và hạt chia bổ dưỡng.', 200, 1, 0),
(10, 3, 'Trà Dâu Tằm Pha Lê', 'tra-dau-tam-pha-le', 42000, 39000, 'https://images.unsplash.com/photo-1546173159-315724a31696?w=600&auto=format&fit=crop&q=80', 'Trà dâu tằm chua ngọt, kèm trân châu 3Q trắng pha lê giòn sần sật.', 120, 1, 1),
(11, 4, 'Matcha Đá Xay Thượng Hạng', 'matcha-da-xay-thuong-hang', 49000, 45000, 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?w=600&auto=format&fit=crop&q=80', 'Trà xanh Nhật Bản xay cùng đá, phủ kem whipping cream béo ngậy.', 100, 1, 1),
(12, 4, 'Cà Phê Cốt Dừa Đá Xay', 'ca-phe-cot-dua-da-xay', 45000, 0, 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 'Cà phê espresso đậm đà hòa quyện cùng sữa dừa béo ngậy xay mịn.', 110, 1, 1),
(13, 4, 'Chanh Tuyết Đá Xay Giải Nhiệt', 'chanh-tuyet-da-xay', 35000, 29000, 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=600&auto=format&fit=crop&q=80', 'Chanh tươi nguyên vỏ xay nhuyễn cùng sữa đặc và đá bào tạo lớp tuyết trắng.', 140, 1, 0),
(14, 5, 'Trân Châu Đen Đường Đen', 'tran-chau-den-duong-den', 8000, 0, 'https://images.unsplash.com/photo-1558877385-81a1c7e67d72?w=600&auto=format&fit=crop&q=80', 'Trân châu đen dẻo dai rim mật đường đen thơm ngọt.', 999, 1, 0),
(15, 5, 'Trân Châu Trắng 3Q', 'tran-chau-trang-3q', 8000, 0, 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600&auto=format&fit=crop&q=80', 'Trân châu 3Q giòn sần sật ngọt nhẹ thanh mát.', 999, 1, 0),
(16, 5, 'Kem Phô Mai Cheese Salted', 'kem-pho-mai-cheese-salted', 10000, 0, 'https://images.unsplash.com/photo-1534778101976-62847782c213?w=600&auto=format&fit=crop&q=80', 'Lớp kem béo mặn mịn màng phủ trên bề mặt ly nước.', 999, 1, 0),
(17, 6, 'Bánh Croissant Bơ Pháp', 'banh-croissant-bo-phap', 35000, 29000, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80', 'Bánh sừng bò ngàn lớp thơm lừng vị bơ Pháp cao cấp, giòn rụm bên ngoài, mềm xốp bên trong.', 100, 1, 1),
(18, 6, 'Bánh Mì Thịt Nướng Giòn Rụm', 'banh-mi-thit-nuong', 39000, 35000, 'https://images.unsplash.com/photo-1626804475297-41608ea09aeb?w=600&auto=format&fit=crop&q=80', 'Bánh mì Việt Nam giòn kẹp thịt nướng thơm phức, đồ chua, dưa leo và sốt nhà làm độc quyền.', 80, 1, 1),
(19, 6, 'Khoai Tây Chiên Sốt Phô Mai', 'khoai-tay-chien-sot-pho-mai', 32000, 0, 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&auto=format&fit=crop&q=80', 'Khoai tây vàng giòn rụm phủ lớp sốt phô mai béo ngậy cực cuốn.', 120, 1, 0),
(20, 6, 'Gà Rán Sốt Cay Hàn Quốc', 'ga-ran-sot-cay-han-quoc', 49000, 45000, 'https://images.unsplash.com/photo-1626645738196-c2a7c87a8f58?w=600&auto=format&fit=crop&q=80', 'Gà giòn tan đẫm sốt cay ngọt chuẩn vị Hàn Quốc, rắc thêm vừng trắng thơm nồng.', 90, 1, 1),
(21, 6, 'Bánh Tiramisu Cà Phê', 'banh-tiramisu-ca-phe', 42000, 38000, 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=600&auto=format&fit=crop&q=80', 'Bánh Tiramisu mềm mịn thơm đậm vị Espresso kết hợp lớp kem mascarpone béo ngậy.', 60, 1, 1),
(22, 6, 'Bánh Mì Bơ Tỏi Hàn Quốc', 'banh-mi-bo-toi-han-quoc', 35000, 0, 'https://images.unsplash.com/photo-1619535860434-ba1d8fa12536?w=600&auto=format&fit=crop&q=80', 'Bánh mì mềm thơm lừng vị bơ tỏi ngậy tràn ngập lớp kem phô mai bên trong.', 75, 1, 0)
ON DUPLICATE KEY UPDATE name=VALUES(name), image=VALUES(image), price=VALUES(price), discount_price=VALUES(discount_price);
