-- =====================================================================
-- CƠ SỞ DỮ LIỆU CHO WEBSITE BÁN NƯỚC UỐNG (DRINK SHOP DATABASE)
-- Hệ quản trị cơ sở dữ liệu: MySQL / MariaDB
-- UTF-8 Unicode (utf8mb4) hỗ trợ tiếng Việt đầy đủ
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `drink_shop` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `drink_shop`;

-- =====================================================================
-- 1. BẢNG PHÂN QUYỀN / VAI TRÒ (ROLES) - Mở rộng nếu cần
-- Hoặc có thể định nghĩa trực tiếp vai trò trong bảng Users bằng ENUM.
-- Ở đây sử dụng ENUM trực tiếp trong bảng Users để đơn giản và tối ưu.
-- =====================================================================

-- =====================================================================
-- 2. BẢNG NGƯỜI DÙNG (USERS)
-- Lưu trữ thông tin của quản trị viên (Admin) và khách hàng (Customer)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Tên đăng nhập',
    `password` VARCHAR(255) NOT NULL COMMENT 'Mật khẩu (nên hash bằng bcrypt/md5)',
    `fullname` VARCHAR(100) NOT NULL COMMENT 'Họ và tên',
    `email` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Địa chỉ email',
    `phone` VARCHAR(15) NULL COMMENT 'Số điện thoại',
    `address` VARCHAR(255) NULL COMMENT 'Địa chỉ giao hàng mặc định',
    `role` ENUM('admin', 'customer') DEFAULT 'customer' COMMENT 'Vai trò người dùng',
    `status` TINYINT DEFAULT 1 COMMENT 'Trạng thái: 1 = Hoạt động, 0 = Bị khóa',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. BẢNG DANH MỤC SẢN PHẨM (CATEGORIES)
-- Ví dụ: Cà phê, Trà sữa, Trà trái cây, Đá xay, Bánh ngọt...
-- =====================================================================
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Tên danh mục',
    `slug` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Đường dẫn thân thiện (SEO)',
    `description` TEXT NULL COMMENT 'Mô tả danh mục',
    `image` VARCHAR(255) NULL COMMENT 'Hình ảnh đại diện danh mục',
    `status` TINYINT DEFAULT 1 COMMENT 'Trạng thái hiển thị: 1 = Hiện, 0 = Ẩn',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 4. BẢNG SẢN PHẨM / ĐỒ UỐNG (PRODUCTS)
-- Lưu thông tin chi tiết của từng loại nước uống
-- =====================================================================
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL COMMENT 'Mã danh mục',
    `name` VARCHAR(150) NOT NULL COMMENT 'Tên đồ uống',
    `slug` VARCHAR(150) NOT NULL UNIQUE COMMENT 'Đường dẫn thân thiện (SEO)',
    `price` DECIMAL(10, 0) NOT NULL COMMENT 'Giá bán gốc (VND)',
    `discount_price` DECIMAL(10, 0) DEFAULT 0 COMMENT 'Giá khuyến mãi (nếu có)',
    `image` VARCHAR(255) NULL COMMENT 'Hình ảnh sản phẩm',
    `description` TEXT NULL COMMENT 'Mô tả ngắn gọn',
    `content` LONGTEXT NULL COMMENT 'Bài viết giới thiệu chi tiết sản phẩm',
    `quantity` INT DEFAULT 100 COMMENT 'Số lượng còn lại trong kho',
    `status` TINYINT DEFAULT 1 COMMENT 'Trạng thái bán: 1 = Đang bán, 0 = Ngừng bán',
    `is_featured` TINYINT DEFAULT 0 COMMENT 'Sản phẩm nổi bật: 1 = Có, 0 = Không',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 5. BẢNG ĐƠN HÀNG (ORDERS)
-- Lưu trữ thông tin đơn đặt hàng tổng quan
-- =====================================================================
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL COMMENT 'Mã khách hàng (NULL nếu khách mua không cần tài khoản)',
    `fullname` VARCHAR(100) NOT NULL COMMENT 'Họ tên người nhận',
    `email` VARCHAR(100) NOT NULL COMMENT 'Email liên hệ',
    `phone` VARCHAR(15) NOT NULL COMMENT 'Số điện thoại nhận hàng',
    `address` VARCHAR(255) NOT NULL COMMENT 'Địa chỉ giao hàng',
    `total_amount` DECIMAL(12, 0) NOT NULL COMMENT 'Tổng tiền đơn hàng (VND)',
    `payment_method` ENUM('COD', 'MOMO', 'VNPAY', 'BANK_TRANSFER') DEFAULT 'COD' COMMENT 'Phương thức thanh toán',
    `payment_status` ENUM('unpaid', 'paid', 'refunded') DEFAULT 'unpaid' COMMENT 'Trạng thái thanh toán',
    `order_status` ENUM('pending', 'processing', 'shipping', 'completed', 'cancelled') DEFAULT 'pending' COMMENT 'Trạng thái đơn hàng',
    `note` TEXT NULL COMMENT 'Ghi chú của khách hàng',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 6. BẢNG CHI TIẾT ĐƠN HÀNG (ORDER_DETAILS)
-- Lưu trữ danh sách sản phẩm trong từng đơn hàng, kèm size và topping
-- =====================================================================
CREATE TABLE IF NOT EXISTS `order_details` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL COMMENT 'Mã đơn hàng',
    `product_id` INT NOT NULL COMMENT 'Mã sản phẩm',
    `price` DECIMAL(10, 0) NOT NULL COMMENT 'Giá sản phẩm tại thời điểm mua',
    `quantity` INT NOT NULL COMMENT 'Số lượng mua',
    `size` ENUM('S', 'M', 'L') DEFAULT 'M' COMMENT 'Kích cỡ ly',
    `toppings` VARCHAR(255) NULL COMMENT 'Danh sách topping kèm theo (ví dụ: Trân châu đen, Thạch nha đam)',
    `total_item_amount` DECIMAL(12, 0) NOT NULL COMMENT 'Thành tiền dòng này (quantity * price + phụ thu size/topping nếu có)',
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 7. BẢNG ĐÁNH GIÁ SẢN PHẨM (REVIEWS)
-- Khách hàng đánh giá chất lượng đồ uống
-- =====================================================================
CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL COMMENT 'Mã người đánh giá',
    `product_id` INT NOT NULL COMMENT 'Mã đồ uống được đánh giá',
    `rating` TINYINT NOT NULL COMMENT 'Số sao đánh giá (từ 1 đến 5)',
    `comment` TEXT NULL COMMENT 'Nội dung bình luận',
    `status` TINYINT DEFAULT 1 COMMENT 'Trạng thái: 1 = Hiển thị, 0 = Ẩn bình luận xấu',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 8. BẢNG LIÊN HỆ & PHẢN HỒI (CONTACTS)
-- Khách gửi tin nhắn đóng góp ý kiến hoặc phản hồi dịch vụ
-- =====================================================================
CREATE TABLE IF NOT EXISTS `contacts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fullname` VARCHAR(100) NOT NULL COMMENT 'Họ và tên người gửi',
    `email` VARCHAR(100) NOT NULL COMMENT 'Email người gửi',
    `phone` VARCHAR(15) NULL COMMENT 'Số điện thoại',
    `subject` VARCHAR(150) NOT NULL COMMENT 'Chủ đề liên hệ',
    `message` TEXT NOT NULL COMMENT 'Nội dung tin nhắn',
    `status` TINYINT DEFAULT 0 COMMENT 'Trạng thái xử lý: 0 = Chưa đọc, 1 = Đã đọc, 2 = Đã phản hồi',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TẠO CÁC CHỈ MỤC (INDEXES) ĐỂ TỐI ƯU HÓA TỐC ĐỘ TRUY VẤN
-- =====================================================================
CREATE INDEX idx_products_category ON `products` (`category_id`);
CREATE INDEX idx_products_slug ON `products` (`slug`);
CREATE INDEX idx_orders_user ON `orders` (`user_id`);
CREATE INDEX idx_order_details_order ON `order_details` (`order_id`);
CREATE INDEX idx_reviews_product ON `reviews` (`product_id`);


-- =====================================================================
-- DỮ LIỆU MẪU (SEED DATA)
-- =====================================================================

-- 1. Chèn dữ liệu người dùng mẫu
-- Mật khẩu mẫu: '123456' (được mã hóa MD5: e10adc3949ba59abbe56e057f20f883e)
INSERT INTO `users` (`id`, `username`, `password`, `fullname`, `email`, `phone`, `address`, `role`, `status`) VALUES
(1, 'admin', 'e10adc3949ba59abbe56e057f20f883e', 'Quản trị viên hệ thống', 'admin@drinkshop.com', '0987654321', '123 Đường Ba Tháng Hai, Quận 10, TP. Hồ Chí Minh', 'admin', 1),
(2, 'nguyenvana', 'e10adc3949ba59abbe56e057f20f883e', 'Nguyễn Văn A', 'nguyenvana@gmail.com', '0912345678', '456 Lê Lợi, Quận 1, TP. Hồ Chí Minh', 'customer', 1),
(3, 'tranlhithib', 'e10adc3949ba59abbe56e057f20f883e', 'Trần Thị B', 'tranthib@gmail.com', '0908889999', '789 Nguyễn Huệ, Quận Hải Châu, Đà Nẵng', 'customer', 1);

-- 2. Chèn dữ liệu danh mục nước uống mẫu
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `status`) VALUES
(1, 'Cà Phê Truyền Thống', 'ca-phe-truyen-thong', 'Các dòng cà phê đậm vị Việt Nam như Cà phê sữa đá, Cà phê đen, Bạc xỉu...', 'categories/caphe.jpg', 1),
(2, 'Trà Sữa Đặc Biệt', 'tra-sua-dac-biet', 'Trà sữa thơm béo kết hợp cùng các loại topping đa dạng', 'categories/trasua.jpg', 1),
(3, 'Trà Trái Cây Thanh Nhiệt', 'tra-trai-cay-thanh-nhiet', 'Trà kết hợp trái cây tươi mát lạnh, giải nhiệt ngày hè cực tốt', 'categories/tratraicay.jpg', 1),
(4, 'Đá Xay & Sinh Tố (Ice Blended & Smoothie)', 'da-xay-sinh-to', 'Thức uống đá xay mát lạnh, béo ngậy kèm kem tươi whipping cream', 'categories/daxay.jpg', 1),
(5, 'Toppings Thêm', 'toppings-them', 'Các loại topping ăn kèm giúp gia tăng hương vị cho đồ uống của bạn', 'categories/topping.jpg', 1);

-- 3. Chèn dữ liệu sản phẩm mẫu (Nước uống)
INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `price`, `discount_price`, `image`, `description`, `content`, `quantity`, `status`, `is_featured`) VALUES
-- Danh mục 1: Cà phê
(1, 1, 'Cà Phê Sữa Đá Sài Gòn', 'ca-phe-sua-da-sai-gon', 29000, 25000, 'anh/trasuatranchau.png', 'Cà phê Robusta đậm đặc pha phin kết hợp với sữa đặc có đường và đá nhuyễn.', 'Cà Phê Sữa Đá là linh hồn ẩm thực đường phố Việt Nam. Hạt cà phê được rang xay nguyên chất mang đến hương thơm nồng nàn, kết hợp hoàn hảo cùng vị béo ngọt của sữa đặc.', 150, 1, 1),
(2, 1, 'Cà Phê Đen Đá Pha Phin', 'ca-phe-den-da-pha-phin', 25000, 0, 'anh/trasuatranchau.png', 'Cà phê đen nguyên chất pha phin truyền thống, hương vị đậm đà mộc mạc.', 'Dành cho những tín đồ yêu thích vị đắng nguyên bản và sự tỉnh táo tức thì từ những giọt cà phê Robusta tuyển chọn.', 200, 1, 0),
(3, 1, 'Bạc Xỉu Đá Thơm Béo', 'bac-xiu-da-thom-beo', 32000, 29000, 'anh/tradaodacbiet.png', 'Nhiều sữa ít cà phê, sự lựa chọn ngọt ngào cho ngày mới nhẹ nhàng.', 'Sự hòa quyện ngọt ngào của sữa tươi, sữa đặc và một chút cà phê Espresso thơm lừng tạo nên ly Bạc Xỉu phân tầng đẹp mắt và dễ uống.', 180, 1, 1),
(4, 1, 'Cà Phê Muối Huế', 'ca-phe-muoi-hue', 35000, 0, 'anh/tradaodacbiet.png', 'Cà phê phin kết hợp lớp kem muối béo mặn độc đáo và đậm đà.', 'Xu hướng cà phê muối mang hương vị đậm đà từ cà phê phin, vị béo ngậy của kem sữa và chút mặn nhẹ của muối tinh.', 120, 1, 1),

-- Danh mục 2: Trà sữa
(5, 2, 'Trà Sữa Trân Châu Hoàng Gia', 'tra-sua-tran-chau-hoang-gia', 45000, 39000, 'anh/trasuatranchau.png', 'Trà sữa truyền thống đậm vị trà cùng trân châu đen dai giòn ngọt lịm.', 'Được nấu từ lá hồng trà thượng hạng cùng bột sữa béo chuyên dụng, kết hợp với trân châu đen được rim đường nâu dẻo dai ngon khó cưỡng.', 300, 1, 1),
(6, 2, 'Trà Sữa Ô Long Kem Cheese', 'tra-sua-o-long-kem-cheese', 49000, 45000, 'anh/tradaodacbiet.png', 'Trà sữa ô long thơm nhẹ kết hợp với lớp kem sữa phô mai mặn béo.', 'Vị chát nhẹ thanh tao của trà ô long hòa quyện cùng vị ngọt của sữa, phủ thêm một lớp kem mặn cheese ngậy béo bên trên.', 150, 1, 1),
(7, 2, 'Trà Sữa Matcha Nhật Bản', 'tra-sua-matcha-nhat-ban', 45000, 0, 'anh/tradautam.png', 'Bột trà xanh Uji nhập khẩu trực tiếp từ Nhật Bản kết hợp sữa tươi béo.', 'Màu xanh lục tự nhiên bắt mắt, vị đắng nhẹ đặc trưng của matcha thượng hạng hòa quyện cùng dòng sữa ngọt lành.', 160, 1, 0),

-- Danh mục 3: Trà trái cây
(8, 3, 'Trà Đào Cam Sả Đặc Biệt', 'tra-dao-cam-sa-dac-biet', 39000, 35000, 'anh/tradaocamsa.png', 'Trà đào thanh ngọt kết hợp sả tươi thơm nồng và những lát cam vàng mọng nước.', 'Thức uống quốc dân được làm từ nền trà đen hảo hạng, cam tươi nguyên quả cắt lát, sả tươi đập dập thơm lừng và 3 miếng đào ngâm giòn ngọt.', 250, 1, 1),
(9, 3, 'Trà Vải Lài Hạt Chia', 'tra-vai-lai-hat-chia', 39000, 0, 'anh/tradautam.png', 'Trà lài thanh mát kết hợp với quả vải ngâm ngọt lịm và hạt chia bổ dưỡng.', 'Hương hoa lài thơm dịu nhẹ kết hợp với thịt vải thiều trắng giòn, điểm thêm hạt chia thanh lọc cơ thể rất tốt.', 200, 1, 0),
(10, 3, 'Trà Dâu Tằm Pha Lê', 'tra-dau-tam-pha-le', 42000, 39000, 'anh/tradautam.png', 'Trà dâu tằm chua ngọt, kèm trân châu 3Q trắng pha lê giòn sần sật.', 'Trà dâu tằm được làm từ siro dâu tằm tự nhiên và mứt dâu tằm nguyên quả, mang lại hương vị chua thanh ngọt mát đầy sảng khoái.', 120, 1, 1),

-- Danh mục 4: Đá xay & sinh tố
(11, 4, 'Matcha Đá Xay Thượng Hạng', 'matcha-da-xay-thuong-hang', 49000, 45000, 'anh/tratraicay.png', 'Trà xanh Nhật Bản xay cùng đá, phủ kem whipping cream béo ngậy.', 'Sự kết hợp hoàn hảo giữa vị thơm đắng của trà xanh Uji Matcha Nhật Bản, sữa tươi và đá viên xay nhuyễn mịn, phủ thêm lớp kem bông tuyết.', 100, 1, 1),
(12, 4, 'Cà Phê Cốt Dừa Đá Xay', 'ca-phe-cot-dua-da-xay', 45000, 0, 'anh/tratraicay.png', 'Cà phê espresso đậm đà hòa quyện cùng sữa dừa béo ngậy xay mịn.', 'Món ngon nổi tiếng kết hợp giữa cà phê đắng thơm nồng nàn và phần cốt dừa ngọt béo thơm lừng xay nhuyễn lạnh buốt.', 110, 1, 1),
(13, 4, 'Chanh Tuyết Đá Xay Giải Nhiệt', 'chanh-tuyet-da-xay', 35000, 29000, 'anh/tradaocamsa.png', 'Chanh tươi nguyên vỏ xay nhuyễn cùng sữa đặc và đá bào tạo lớp tuyết trắng.', 'Vị chua sảng khoái từ chanh, mùi thơm tinh dầu vỏ chanh cùng vị ngọt thanh của sữa đặc, cực kỳ thích hợp để giải nhiệt.', 140, 1, 0),

-- Danh mục 5: Toppings thêm
(14, 5, 'Trân Châu Đen Đường Đen', 'tran-chau-den-duong-den', 8000, 0, 'anh/tradautam.png', 'Trân châu đen dẻo dai rim mật đường đen thơm ngọt.', 'Topping quốc dân không thể thiếu cho trà sữa.', 999, 1, 0),
(15, 5, 'Trân Châu Trắng 3Q', 'tran-chau-trang-3q', 8000, 0, 'anh/trasuatranchau.png', 'Trân châu 3Q giòn sần sật ngọt nhẹ thanh mát.', 'Topping cực ngon ăn kèm trà sữa hoặc trà trái cây.', 999, 1, 0),
(16, 5, 'Kem Phô Mai Cheese Salted', 'kem-pho-mai-cheese-salted', 10000, 0, 'anh/tradaodacbiet.png', 'Lớp kem béo mặn mịn màng phủ trên bề mặt ly nước.', 'Kem phô mai muối độc quyền tự làm tại quán.', 999, 1, 0);

-- 4. Chèn dữ liệu đơn hàng mẫu (Orders)
INSERT INTO `orders` (`id`, `user_id`, `fullname`, `email`, `phone`, `address`, `total_amount`, `payment_method`, `payment_status`, `order_status`, `note`) VALUES
(1, 2, 'Nguyễn Văn A', 'nguyenvana@gmail.com', '0912345678', '456 Lê Lợi, Quận 1, TP. Hồ Chí Minh', 97000, 'COD', 'unpaid', 'pending', 'Giao giờ hành chính giúp em, cảm ơn quán!'),
(2, 3, 'Trần Thị B', 'tranthib@gmail.com', '0908889999', '789 Nguyễn Huệ, Quận Hải Châu, Đà Nẵng', 123000, 'MOMO', 'paid', 'completed', 'Giao nước ít đá, nhiều trân châu nhé.');

-- 5. Chèn dữ liệu chi tiết đơn hàng mẫu (Order Details)
INSERT INTO `order_details` (`id`, `order_id`, `product_id`, `price`, `quantity`, `size`, `toppings`, `total_item_amount`) VALUES
-- Đơn hàng 1: Tổng tiền 97,000 VND
-- 2 Cà Phê Sữa Đá Sài Gòn (giá KM 25,000đ) -> 50,000đ
(1, 1, 1, 25000, 2, 'M', NULL, 50000),
-- 1 Trà Sữa Trân Châu Hoàng Gia (giá KM 39,000đ) + thêm Trân châu đen (8,000đ) -> 47,000đ
(2, 1, 5, 39000, 1, 'M', 'Trân Châu Đen Đường Đen', 47000),

-- Đơn hàng 2: Tổng tiền 123,000 VND
-- 1 Trà Đào Cam Sả Đặc Biệt (giá KM 35,000đ) size L (+5,000đ phụ thu size tự tính ở code ứng dụng) -> 40,000đ
(3, 2, 8, 35000, 1, 'L', NULL, 40000),
-- 1 Trà Sữa Ô Long Kem Cheese (giá KM 45,000đ) + trân châu trắng (8,000đ) -> 53,000đ
(4, 2, 6, 45000, 1, 'M', 'Trân Châu Trắng 3Q', 53000),
-- 1 Chanh Tuyết Đá Xay (giá KM 29,000đ) -> 29,000đ
(5, 2, 13, 29000, 1, 'S', NULL, 30000);

-- 6. Chèn dữ liệu đánh giá sản phẩm mẫu (Reviews)
INSERT INTO `reviews` (`id`, `user_id`, `product_id`, `rating`, `comment`) VALUES
(1, 2, 1, 5, 'Cà phê rất đậm đà, vị béo ngậy chuẩn vị Sài Gòn. Sẽ ủng hộ quán tiếp.'),
(2, 3, 5, 4, 'Trà sữa ngon, vị ngọt vừa phải, trân châu dai mềm ngon lắm.'),
(3, 2, 8, 5, 'Trà đào thơm phức, lát cam to đùng, đào giòn ngọt lịm. Đáng đồng tiền bát gạo.');

-- 7. Chèn dữ liệu liên hệ mẫu (Contacts)
INSERT INTO `contacts` (`id`, `fullname`, `email`, `phone`, `subject`, `message`, `status`) VALUES
(1, 'Lê Hoàng Nam', 'namlh@gmail.com', '0944555666', 'Hỏi về chính sách nhượng quyền thương hiệu', 'Chào ad, mình muốn mở đại lý nhượng quyền tại khu vực Thủ Đức. Vui lòng gửi tài liệu chi tiết qua mail giúp mình.', 0),
(2, 'Phạm Minh Trí', 'tripm@yahoo.com', '0933222111', 'Phản hồi về thái độ phục vụ của shipper', 'Hôm qua shipper giao hàng hơi trễ 10 phút nhưng thái độ rất lịch sự và nhiệt tình. Điểm 10 cho dịch vụ chăm sóc của quán!', 1);
