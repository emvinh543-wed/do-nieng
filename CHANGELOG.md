# 📜 NHẬT KÝ PHÁT TRIỂN & LỊCH SỬ CẬP NHẬT (CHANGELOG)
## Dự Án: Hệ Thống Bán Đồ Uống GlowDrinks

Toàn bộ các thay đổi, tính năng mới, bản vá lỗi và nâng cấp của dự án sẽ được ghi nhận chi tiết tại file này.

---

## 📌 [v1.2.0] - 03/11/2026: Nâng Cấp Frontend Dashboard & Khởi Động Tự Động

### ✨ Tính Năng Mới (Added)
- **Frontend Web Control Center (`Trang chinh/control_panel.php`)**:
  - Giao diện bảng điều khiển quản trị web hiện đại phong cách Dark Mode & Glassmorphism.
  - Thống kê trực quan theo thời gian thực: Phiên bản PHP CLI, trạng thái MySQL, cổng HTTP, số lượng bảng và sản phẩm trong Database.
  - Bộ 4 công cụ tự động thực thi qua AJAX không cần tải lại trang:
    - 🩺 **Chẩn Đoán & Sửa Lỗi Tự Động**
    - 🔄 **Khôi Phục Database Gốc** (kèm Modal xác nhận an toàn)
    - 🧹 **Giải Phóng Port Mạng** (đóng tiến trình PHP bị treo)
    - 📝 **Quét Lỗi Cú Pháp PHP (Lint Check)**
  - Tích hợp **Terminal Log Console ảo** ngay trên trình duyệt để theo dõi chi tiết kết quả.
  - Bảo mật: Giới hạn chỉ cho phép truy cập an toàn từ máy chủ Localhost (`127.0.0.1`, `::1`).

- **Tính Năng Tự Động Khởi Động MySQL (`Auto-Start MySQL`)**:
  - Bổ sung hàm `:CHECK_AND_START_MYSQL` vào file `start.bat`.
  - Tự động phát hiện cổng 3306: nếu MySQL đang tắt, hệ thống tự động kích hoạt dịch vụ Windows `wampmysqld64` hoặc gọi trực tiếp `wampmanager.exe` để mở WampServer.

- **Nâng Cấp Giao Diện Bảng Menu `start.bat`**:
  - Tích hợp bảng mã `chcp 65001` (UTF-8) hiển thị tiếng Việt và bảng viền mượt mà.
  - Thêm lựa chọn số **`[7] Mo Frontend Dashboard (Giao dien Web Control Panel)`**.
  - Hiển thị đầy đủ danh sách link điều hướng sau khi khởi động thành công (Website khách hàng, Frontend Dashboard, Trang Admin).

### 🐛 Vá Lỗi (Fixed)
- Sửa triệt để lỗi kết nối cơ sở dữ liệu:
  `SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it`
  do dịch vụ MySQL trong WampServer chưa được khởi động.
- Dọn dẹp file thừa và chuẩn hóa encoding UTF-8 không BOM cho file thực thi script.

---

## 📌 [v1.1.0] - 30/09/2026: Bộ Công Cụ Chẩn Đoán & Tự Sửa Lỗi Tự Động

### ✨ Tính Năng Mới (Added)
- **Công Cụ Chẩn Đoán Hệ Thống (`tools/system_diagnose.php`)**:
  - Kiểm tra và tự động kill các tiến trình PHP cũ chiếm dụng cổng mạng (`free-port`).
  - Kiểm tra các Extension PHP bắt buộc: `pdo`, `pdo_mysql`, `mbstring`, `session`, `json`.
  - Tự động kiểm tra và import cơ sở dữ liệu `drink_shop.sql` khi phát hiện database trống.
  - Đồng bộ dữ liệu hình ảnh và món ăn snack mẫu qua `seed_updater.php`.
  - Quét cú pháp toàn bộ file PHP (`lint`) để phát hiện lỗi code trước khi chạy server.

- **Kịch Bản Khởi Động Nhanh (`start.bat`)**:
  - Tự động quét và nhận diện đường dẫn cài đặt PHP từ các bộ phần mềm: WampServer, Laragon, XAMPP, Warm.
  - Tự động chạy PHP Built-in Server trên cổng mạng 8000 và mở trình duyệt web mặc định.
  - Bộ đếm thời gian tự động khởi động sau 5 giây nếu không có thao tác bàn phím.

---

## 📌 [v1.0.0] - 23/08/2026: Ra Mắt Hệ Thống GlowDrinks Core

### ✨ Tính Năng Nền Tảng (Initial Release)
- **Trang Khách Hàng (Customer UI)**:
  - Trang chủ giới thiệu thương hiệu và danh mục đồ uống (`Trang chinh/index/`).
  - Trang chi tiết đồ uống (`Trang chinh/detail.php`) kèm các lựa chọn size, topping, đường, đá.
  - Giỏ hàng và thanh toán trực tuyến (`Trang chinh/cart/cart.php`).
  - Tích hợp mã QR chuyển khoản ngân hàng tự động (`banker/`).
  - Trang liên hệ và hỗ trợ khách hàng (`Trang chinh/contact.php`).
- **Trang Quản Trị Hệ Thống (Admin Dashboard)**:
  - Quản lý danh mục, sản phẩm, đơn đặt hàng và người dùng (`Trang chinh/amin/admin.php`).
  - Đăng nhập, phân quyền quản trị viên và khách hàng (`Trang chinh/login/`).
- **Mini Game & Tiện Ích Tương Tác**:
  - Trò chơi mini Vịt Vàng Duck Game (`Game/duck_game.php`).
  - Trợ lý tương tác Duck AI (`Ai/duck_ai.php`).
- **Cơ Sở Dữ Liệu**:
  - Thiết kế cấu trúc bảng dữ liệu đồ uống `drink_shop.sql` và dữ liệu mở rộng `drink_shop_full.sql`.

---

## 🧭 HƯỚNG DẪN VẬN HÀNH NHANH

1. **Khởi động toàn bộ hệ thống**:
   - Nhấp đúp chuột vào file `start.bat`.
   - Chọn phím `[1]` để khởi động server và mở website.
   - Hoặc chọn phím `[7]` để mở Frontend Dashboard quản trị.

2. **Các địa chỉ truy cập quan trọng**:
   - **Website Bán Hàng**: `http://127.0.0.1:8000/`
   - **Frontend Control Center**: `http://127.0.0.1:8000/control_panel.php`
   - **Trang Quản Trị Admin**: `http://127.0.0.1:8000/amin/admin.php` (Tài khoản: `admin` | Mật khẩu: `123456`)

