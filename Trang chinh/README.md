# 🥤 GlowDrinks - Website Bán Nước Uống Cao Cấp

GlowDrinks là một trang web giới thiệu và đặt nước uống trực tuyến được xây dựng bằng **PHP (PDO)** và cơ sở dữ liệu **MySQL**, thiết kế bằng **CSS Vanilla** hiện đại, responsive đầy đủ trên thiết bị di động.

---

## 🛠️ Hướng dẫn cài đặt & Chạy ứng dụng

### 1. Yêu cầu hệ thống
- Máy tính cài đặt sẵn máy chủ PHP & MySQL (Khuyên dùng **XAMPP** hoặc **Laragon**).
- PHP phiên bản **7.4 trở lên**.

### 2. Thiết lập cơ sở dữ liệu (Database)
1. Khởi động **Apache** và **MySQL** trên công cụ quản lý máy chủ ảo của bạn (XAMPP/Laragon).
2. Mở trình duyệt truy cập vào đường dẫn: `http://localhost/phpmyadmin/`
3. Tạo một database mới tên là: `drink_shop` với bảng mã (Collation): `utf8mb4_unicode_ci`.
4. Chọn cơ sở dữ liệu `drink_shop` vừa tạo, nhấn vào thẻ **Import** (Nhập).
5. Tải lên tệp tin SQL mẫu nằm ở thư mục:
   `e:\Duanrieng\dulieu\drink_shop.sql`
6. Nhấn **Import/Go** để tiến hành import toàn bộ cấu hình bảng và dữ liệu mẫu.

### 3. Chạy Website
- Di chuyển toàn bộ mã nguồn trong thư mục `Trang chinh` vào thư mục gốc của máy chủ local (ví dụ: `C:\xampp\htdocs\drink_shop` nếu dùng XAMPP).
- Hoặc nếu bạn muốn khởi chạy nhanh bằng Command Line:
  1. Mở Terminal (PowerShell hoặc Command Prompt).
  2. Truy cập thư mục dự án và gõ lệnh:
     ```bash
     php -S localhost:8000
     ```
  3. Mở trình duyệt truy cập địa chỉ: `http://localhost:8000/`

---

## 🔑 Tài khoản kiểm thử nhanh (Demo Accounts)

Bạn có thể nhấn vào nút **Đăng Nhập** trên thanh điều hướng để chọn chế độ đăng nhập nhanh hoặc điền thông tin tài khoản:

| Quyền hạn | Tên đăng nhập | Mật khẩu | Chức năng kiểm thử |
| :--- | :--- | :--- | :--- |
| **Quản trị viên (Admin)** | `admin` | `123456` | Quản lý hóa đơn, chỉnh sửa danh mục sản phẩm, xem góp ý của khách, giám sát đánh giá. |
| **Khách hàng (Customer)** | `nguyenvana` | `123456` | Thực hiện mua hàng trực tiếp, tùy chỉnh thêm Toppings / Size ly nước, gửi đánh giá. |

---

## 📁 Cấu trúc các tệp tin trong dự án
- `/css/style.css`: Hệ thống thiết kế CSS dùng chung (Màu Teal-Amber, chuyển động Hover, Grid).
- `config.php`: Kết nối cơ sở dữ liệu MySQL bằng PDO và định dạng tiền tệ.
- `header.php` & `footer.php`: Giao diện đầu trang (thanh điều hướng, thanh tìm kiếm, giỏ hàng động) và cuối trang.
- `index.php`: Trang chủ hiển thị thanh lọc danh mục và danh sách đồ uống.
- `detail.php`: Xem chi tiết nước uống, tùy chỉnh size (S, M, L), topping (trân châu, kem cheese) và xem/gửi bình luận.
- `cart_action.php`: Xử lý thêm, cập nhật số lượng hoặc xóa sản phẩm trong giỏ hàng (sử dụng AJAX).
- `cart.php`: Trang hiển thị giỏ hàng hiện tại của khách hàng.
- `checkout.php`: Trang đặt hàng, xử lý trừ số lượng hàng tồn trong kho và lưu hóa đơn vào database.
- `contact.php`: Biểu mẫu tiếp nhận phản hồi đóng góp ý kiến.
- `admin.php`: Bảng điều khiển quản lý doanh số, hóa đơn, tồn kho, phản hồi và bình luận đánh giá.
