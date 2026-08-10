-- =====================================================================
-- CẬP NHẬT ẢNH SẢN PHẨM - Dùng ảnh mới từ thư mục anh/
-- Chạy script này trong phpMyAdmin sau khi đã import drink_shop.sql
-- =====================================================================

USE `drink_shop`;

-- Cập nhật ảnh cho từng sản phẩm (trỏ vào file trong /anh/)
-- Cà phê - dùng ảnh trà sữa làm đại diện nhóm cà phê
UPDATE `products` SET `image` = 'anh/trasuatranchau.png' WHERE `id` = 1;  -- Cà Phê Sữa Đá Sài Gòn
UPDATE `products` SET `image` = 'anh/trasuatranchau.png' WHERE `id` = 2;  -- Cà Phê Đen Đá
UPDATE `products` SET `image` = 'anh/trasuatranchau.png' WHERE `id` = 3;  -- Bạc Xỉu
UPDATE `products` SET `image` = 'anh/trasuatranchau.png' WHERE `id` = 4;  -- Cà Phê Muối

-- Trà sữa
UPDATE `products` SET `image` = 'anh/trasuatranchau.png' WHERE `id` = 5;  -- Trà Sữa Trân Châu Hoàng Gia
UPDATE `products` SET `image` = 'anh/tradaodacbiet.png'  WHERE `id` = 6;  -- Trà Sữa Ô Long Kem Cheese
UPDATE `products` SET `image` = 'anh/tradautam.png'      WHERE `id` = 7;  -- Trà Sữa Matcha

-- Trà trái cây
UPDATE `products` SET `image` = 'anh/tradaocamsa.png'    WHERE `id` = 8;  -- Trà Đào Cam Sả
UPDATE `products` SET `image` = 'anh/tradautam.png'      WHERE `id` = 9;  -- Trà Vải Lài Hạt Chia
UPDATE `products` SET `image` = 'anh/tradautam.png'      WHERE `id` = 10; -- Trà Dâu Tằm Pha Lê

-- Đá xay & Sinh tố
UPDATE `products` SET `image` = 'anh/tratraicay.png'     WHERE `id` = 11; -- Matcha Đá Xay
UPDATE `products` SET `image` = 'anh/tratraicay.png'     WHERE `id` = 12; -- Cà Phê Cốt Dừa Đá Xay
UPDATE `products` SET `image` = 'anh/tradaocamsa.png'    WHERE `id` = 13; -- Chanh Tuyết Đá Xay

-- Toppings (giữ nguyên hoặc dùng ảnh phụ)
UPDATE `products` SET `image` = 'anh/tradautam.png'      WHERE `id` = 14; -- Trân Châu Đen
UPDATE `products` SET `image` = 'anh/trasuatranchau.png' WHERE `id` = 15; -- Trân Châu Trắng
UPDATE `products` SET `image` = 'anh/tradaodacbiet.png'  WHERE `id` = 16; -- Kem Phô Mai

-- Xác nhận kết quả
SELECT id, name, image FROM `products` ORDER BY id;
