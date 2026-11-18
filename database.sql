CREATE DATABASE IF NOT EXISTS huit_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE huit_db;

CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_name VARCHAR(150) NOT NULL,
    address VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20),
    expertise VARCHAR(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    branch_id INT NOT NULL,
    subject_name VARCHAR(150) NOT NULL,
    room VARCHAR(50) NOT NULL,
    day_of_week VARCHAR(20) NOT NULL,
    shift_or_periods VARCHAR(50) NOT NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO branches (branch_name, address) VALUES
('Cơ sở chính - Lê Trọng Tấn', '140 Lê Trọng Tấn, P. Tây Thạnh, Q. Tân Phú, TP.HCM'),
('Cơ sở 2 - Tân Kỳ Tân Quý', '93 Tân Kỳ Tân Quý, P. Tân Sơn Nhì, Q. Tân Phú, TP.HCM'),
('Trung tâm Thực hành - Chế Lan Viên', '70 Chế Lan Viên, P. Tây Thạnh, Q. Tân Phú, TP.HCM');

INSERT INTO teachers (full_name, email, phone, expertise) VALUES
('ThS. Nguyễn Văn An', 'annv@huit.edu.vn', '0901234567', 'Công nghệ Phần mềm'),
('TS. Trần Thị Bình', 'binhtt@huit.edu.vn', '0912345678', 'Hệ thống Thông tin'),
('ThS. Lê Hoàng Cường', 'cuonglh@huit.edu.vn', '0987654321', 'Mạng máy tính & IoT');