# HUIT Schedule - Hệ thống Quản lý & Tối ưu Lịch Giảng Dạy Đa Cơ Sở

Ứng dụng web hỗ trợ xếp lịch giảng dạy thông minh cho trường đại học nhiều cơ sở, tích hợp thuật toán phát hiện xung đột lịch/phòng học, cảnh báo tối ưu di chuyển giữa các cơ sở, gợi ý giảng viên theo chuyên môn và cổng tra cứu thời khóa biểu cá nhân cho giảng viên.

## Tính năng chính
- **Kiểm tra xung đột & Tối ưu lịch dạy:** Chặn trùng lịch giảng viên và trùng phòng học; cảnh báo khi xếp 2 ca liền kề khác cơ sở hoặc tạo khoảng trống tiết chết trong ngày.
- **Gợi ý giảng viên phù hợp:** Lọc nhanh giảng viên theo chuyên môn và khung giờ còn trống.
- **Cổng thông tin Giảng viên (`teacher.php`):** Đăng nhập bằng email hoặc truy cập qua đường dẫn riêng để xem thời khóa biểu tuần/tháng và địa chỉ cơ sở cần đến dạy.
- **Thống kê trực quan:** Biểu đồ phân bổ số ca giảng dạy theo giảng viên và cơ sở (Chart.js).

## Yêu cầu môi trường
- PHP >= 8.1 (PDO MySQL extension)
- MySQL / MariaDB
- Composer

## Hướng dẫn cài đặt & chạy dự án

### 1. Clone mã nguồn
```bash
git clone https://github.com/HuuThien0111/huit-schedule.git
cd huit-schedule
```

### 2. Cài đặt thư viện phụ thuộc (Composer)
Chạy lệnh sau để cài đặt thư viện `vlucas/phpdotenv` và tạo thư mục `vendor/`:
```bash
composer install
```

### 3. Cấu hình biến môi trường (`.env`)
Sao chép file mẫu `.env.example` thành `.env`:
```bash
cp .env.example .env
```
Mở file `.env` và điều chỉnh thông số kết nối MySQL phù hợp với máy của bạn:
```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=huit_db
DB_USER=root
DB_PASS=
```

### 4. Import cơ sở dữ liệu mẫu
1. Mở **phpMyAdmin** (hoặc công cụ quản trị MySQL).
2. Tạo cơ sở dữ liệu mới tên `huit_db` (mã hóa `utf8mb4_unicode_ci`).
3. Chọn tab **Import**, tải lên file `database.sql` nằm ở thư mục gốc dự án và nhấn **Go** để khởi tạo 3 bảng (`branches`, `teachers`, `schedules`) cùng dữ liệu mẫu.

### 5. Truy cập ứng dụng
Khởi động Apache & MySQL trên Laragon/XAMPP và mở trình duyệt:
- **Trang Quản trị:** `http://localhost:8080/huit-schedule/index.php`
- **Cổng Giảng viên:** `http://localhost:8080/huit-schedule/teacher.php`

## Giấy phép
Dự án được phát hành theo giấy phép [MIT License](LICENSE).