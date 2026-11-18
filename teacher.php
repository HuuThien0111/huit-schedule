<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';

// 1. Xử lý Đăng xuất
if (isset($_GET['logout'])) {
    unset($_SESSION['teacher_id']);
    header("Location: teacher.php");
    exit;
}

// 2. Xử lý truy cập qua đường dẫn riêng (VD: teacher.php?id=1)
if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
    $stmtCheck = $pdo->prepare("SELECT id FROM teachers WHERE id = ? LIMIT 1");
    $stmtCheck->execute([(int)$_GET['id']]);
    if ($stmtCheck->fetch()) {
        $_SESSION['teacher_id'] = (int)$_GET['id'];
        header("Location: teacher.php");
        exit;
    }
}

// 3. Xử lý Đăng nhập bằng Email giảng viên
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $loginError = 'Vui lòng nhập email giảng viên để đăng nhập.';
    } else {
        $stmtLogin = $pdo->prepare("SELECT * FROM teachers WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmtLogin->execute([$email]);
        $teacherFound = $stmtLogin->fetch(PDO::FETCH_ASSOC);

        if ($teacherFound) {
            $_SESSION['teacher_id'] = (int)$teacherFound['id'];
            header("Location: teacher.php");
            exit;
        } else {
            $loginError = 'Không tìm thấy giảng viên nào sử dụng email này trong hệ thống.';
        }
    }
}

// Kiểm tra trạng thái đăng nhập hiện tại
$currentTeacherId = (int)($_SESSION['teacher_id'] ?? 0);
$currentTeacher   = null;

if ($currentTeacherId > 0) {
    $stmtMe = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
    $stmtMe->execute([$currentTeacherId]);
    $currentTeacher = $stmtMe->fetch(PDO::FETCH_ASSOC);

    if (!$currentTeacher) {
        unset($_SESSION['teacher_id']);
        $currentTeacherId = 0;
    }
}

// Danh sách giảng viên (dùng cho gợi ý đăng nhập nhanh khi demo)
$allTeachers = $pdo->query("SELECT id, full_name, email, expertise FROM teachers ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Khai báo khung giờ chuẩn khớp với index.php
$daysOfWeek = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ nhật'];
$shifts     = [
    'Ca 1 (Tiết 1 - 3)',
    'Ca 2 (Tiết 4 - 6)',
    'Ca 3 (Tiết 7 - 9)',
    'Ca 4 (Tiết 10 - 12)',
    'Ca 5 (Tiết 13 - 15)'
];

$mySchedules  = [];
$scheduleGrid = [];
$myBranches   = [];
$dayBranches  = [];

if ($currentTeacher) {
    // Lấy toàn bộ lịch dạy của giảng viên kèm thông tin cơ sở & địa chỉ
    $stmtSch = $pdo->prepare("
        SELECT s.*, b.branch_name, b.address 
        FROM schedules s
        JOIN branches b ON s.branch_id = b.id
        WHERE s.teacher_id = ?
        ORDER BY FIELD(s.day_of_week, 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ nhật'), s.shift_or_periods ASC
    ");
    $stmtSch->execute([$currentTeacher['id']]);
    $mySchedules = $stmtSch->fetchAll(PDO::FETCH_ASSOC);

    // Đưa dữ liệu vào mảng lưới 2 chiều [$ca][$thu] và gom danh sách cơ sở cần đến
    foreach ($mySchedules as $item) {
        $shiftKey = $item['shift_or_periods'];
        $dayKey   = $item['day_of_week'];
        $scheduleGrid[$shiftKey][$dayKey] = $item;

        $bId = (int)$item['branch_id'];
        if (!isset($myBranches[$bId])) {
            $myBranches[$bId] = [
                'id'          => $bId,
                'branch_name' => $item['branch_name'],
                'address'     => $item['address'],
                'shift_count' => 0
            ];
        }
        $myBranches[$bId]['shift_count']++;
        $dayBranches[$dayKey][$bId] = $item['branch_name'];
    }
}

// Bảng màu phân biệt từng cơ sở
$branchColors = ['primary', 'success', 'warning', 'info', 'danger'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cổng thông tin Giảng viên - HUIT Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .navbar-huit { background: linear-gradient(135deg, #003366 0%, #0059b3 100%); }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        .timetable th { text-align: center; vertical-align: middle; background-color: #eef3f8; color: #003366; }
        .timetable td { vertical-align: middle; min-width: 145px; height: 95px; }
        .shift-col { width: 140px; font-weight: 600; background-color: #f8f9fa; text-align: center; font-size: 0.88rem; }
        .slot-card { border-left: 4px solid; background-color: #ffffff; border-radius: 6px; padding: 6px 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); text-align: left; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-huit mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="teacher.php">
            <i class="bi bi-person-workspace me-2"></i>CỔNG GIẢNG VIÊN - HUIT
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <a href="index.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-speedometer2 me-1"></i>Trang Quản trị
            </a>
            <?php if ($currentTeacher): ?>
                <a href="teacher.php?logout=1" class="btn btn-danger btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Đăng xuất
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <?php if (!$currentTeacher): ?>
        <!-- GIAO DIỆN ĐĂNG NHẬP GIẢNG VIÊN -->
        <div class="row justify-content-center mt-4">
            <div class="col-md-6 col-lg-5">
                <div class="card p-4">
                    <div class="text-center mb-3">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 56px; height: 56px;">
                            <i class="bi bi-person-badge fs-3"></i>
                        </div>
                        <h5 class="fw-bold mb-1">Đăng nhập Lịch giảng dạy</h5>
                        <p class="text-muted small">Nhập email giảng viên hoặc chọn tài khoản bên dưới để xem lịch cá nhân</p>
                    </div>

                    <?php if ($loginError): ?>
                        <div class="alert alert-danger small py-2"><?= htmlspecialchars($loginError) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="teacher.php">
                        <input type="hidden" name="action" value="login">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Giảng viên</label>
                            <input type="email" name="email" class="form-control" placeholder="VD: annv@huit.edu.vn" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-semibold">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Xem Lịch Giảng Dạy
                        </button>
                    </form>

                    <?php if (!empty($allTeachers)): ?>
                        <hr class="my-4">
                        <div class="small fw-semibold text-muted mb-2">
                            <i class="bi bi-link-45deg"></i> Đường dẫn truy cập nhanh theo Giảng viên:
                        </div>
                        <div class="list-group list-group-flush small">
                            <?php foreach ($allTeachers as $t): ?>
                                <a href="teacher.php?id=<?= $t['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-2">
                                    <div>
                                        <strong><?= htmlspecialchars($t['full_name']) ?></strong>
                                        <div class="text-muted" style="font-size: 0.78rem;"><?= htmlspecialchars($t['email']) ?></div>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary">Xem lịch &rarr;</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- GIAO DIỆN LỊCH DẠY CÁ NHÂN CỦA GIẢNG VIÊN -->
        <?php
            $weeklyShifts  = count($mySchedules);
            $weeklyPeriods = $weeklyShifts * 3;
            $monthlyShifts = $weeklyShifts * 4;
            $monthlyPeriods = $weeklyPeriods * 4;
            $personalLink  = "http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080') . "/huit-schedule/teacher.php?id=" . $currentTeacher['id'];
        ?>

        <!-- Thông tin giảng viên & Đường dẫn riêng -->
        <div class="card p-3 mb-4 border-start border-4 border-primary">
            <div class="row align-items-center g-3">
                <div class="col-md-7">
                    <h4 class="fw-bold text-primary mb-1"><?= htmlspecialchars($currentTeacher['full_name']) ?></h4>
                    <div class="text-muted small">
                        <span class="me-3"><i class="bi bi-award me-1"></i>Chuyên môn: <strong><?= htmlspecialchars($currentTeacher['expertise']) ?></strong></span>
                        <span><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($currentTeacher['email']) ?></span>
                    </div>
                </div>
                <div class="col-md-5 text-md-end">
                    <div class="small text-muted mb-1">Đường dẫn tra cứu nhanh cá nhân:</div>
                    <div class="input-group input-group-sm">
                        <input type="text" id="personalUrl" class="form-control bg-light" value="<?= htmlspecialchars($personalLink) ?>" readonly>
                        <button class="btn btn-outline-primary" type="button" onclick="copyLink()">
                            <i class="bi bi-clipboard"></i> Sao chép
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Thống kê khối lượng Tuần & Tháng -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card p-3 text-center">
                    <div class="text-muted small">Số ca dạy / Tuần</div>
                    <div class="fs-4 fw-bold text-primary"><?= $weeklyShifts ?> ca</div>
                    <div class="small text-secondary">(<?= $weeklyPeriods ?> tiết/tuần)</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card p-3 text-center">
                    <div class="text-muted small">Dự kiến / Tháng (4 tuần)</div>
                    <div class="fs-4 fw-bold text-success"><?= $monthlyShifts ?> ca</div>
                    <div class="small text-secondary">(<?= $monthlyPeriods ?> tiết/tháng)</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card p-3 text-center">
                    <div class="text-muted small">Số cơ sở cần di chuyển</div>
                    <div class="fs-4 fw-bold text-warning"><?= count($myBranches) ?> cơ sở</div>
                    <div class="small text-secondary">Trong tuần hiện tại</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card p-3 text-center">
                    <div class="text-muted small">Số ngày có lịch dạy</div>
                    <div class="fs-4 fw-bold text-info"><?= count($dayBranches) ?> ngày</div>
                    <div class="small text-secondary">Trên tổng 7 ngày/tuần</div>
                </div>
            </div>
        </div>

        <!-- Cảnh báo các ngày phải di chuyển qua nhiều cơ sở -->
        <?php foreach ($dayBranches as $dName => $bList): ?>
            <?php if (count($bList) > 1): ?>
                <div class="alert alert-warning d-flex align-items-center shadow-sm mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                    <div>
                        <strong>Lưu ý di chuyển trong <?= htmlspecialchars($dName) ?>:</strong> Bạn có lịch giảng dạy tại <?= count($bList) ?> cơ sở khác nhau (<?= htmlspecialchars(implode(' và ', $bList)) ?>).
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Danh sách Cơ sở & Địa chỉ cụ thể giảng viên cần đến dạy -->
        <div class="card mb-4">
            <div class="card-header bg-white fw-bold text-dark py-3">
                <i class="bi bi-geo-alt-fill text-danger me-1"></i> Địa điểm các Cơ sở bạn cần đến dạy
            </div>
            <div class="card-body">
                <?php if (empty($myBranches)): ?>
                    <div class="text-muted small">Bạn chưa được phân công lịch dạy tại cơ sở nào.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php $idx = 0; foreach ($myBranches as $b): $color = $branchColors[$idx % count($branchColors)]; $idx++; ?>
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100 bg-light">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <span class="badge bg-<?= $color ?>"><?= htmlspecialchars($b['branch_name']) ?></span>
                                        <span class="small fw-semibold text-muted"><?= $b['shift_count'] ?> ca/tuần</span>
                                    </div>
                                    <div class="small text-dark mt-2">
                                        <i class="bi bi-pin-map text-danger me-1"></i><?= htmlspecialchars($b['address']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bảng Lưới Thời Khóa Biểu Tuần & Tổng hợp Tháng -->
        <div class="card">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center py-3 gap-2">
                <span class="fw-bold text-primary"><i class="bi bi-calendar3 me-1"></i> Thời Khóa Biểu Cá Nhân</span>
                <ul class="nav nav-pills nav-sm" id="scheduleTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active btn-sm py-1" data-bs-toggle="tab" data-bs-target="#tab-week" type="button">
                            Lịch theo Tuần
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link btn-sm py-1" data-bs-toggle="tab" data-bs-target="#tab-month" type="button">
                            Tổng hợp theo Tháng (4 tuần)
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- TAB 1: LƯỚI LỊCH TUẦN -->
                    <div class="tab-pane fade show active" id="tab-week">
                        <div class="table-responsive">
                            <table class="table table-bordered timetable mb-0">
                                <thead>
                                    <tr>
                                        <th class="shift-col">Ca / Tiết</th>
                                        <?php foreach ($daysOfWeek as $day): ?>
                                            <th><?= $day ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($shifts as $shift): ?>
                                        <tr>
                                            <td class="shift-col"><?= htmlspecialchars($shift) ?></td>
                                            <?php foreach ($daysOfWeek as $day): ?>
                                                <td class="text-center">
                                                    <?php if (isset($scheduleGrid[$shift][$day])): 
                                                        $slot = $scheduleGrid[$shift][$day];
                                                        $bKeys = array_keys($myBranches);
                                                        $colorIndex = array_search((int)$slot['branch_id'], $bKeys);
                                                        $slotColor = $branchColors[$colorIndex % count($branchColors)];
                                                    ?>
                                                        <div class="slot-card border-<?= $slotColor ?>">
                                                            <div class="fw-bold text-dark small mb-1"><?= htmlspecialchars($slot['subject_name']) ?></div>
                                                            <div class="badge bg-<?= $slotColor ?> mb-1" style="font-size: 0.72rem;">
                                                                <?= htmlspecialchars($slot['branch_name']) ?>
                                                            </div>
                                                            <div class="text-muted" style="font-size: 0.78rem;">
                                                                <i class="bi bi-door-open"></i> Phòng <strong><?= htmlspecialchars($slot['room']) ?></strong>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted opacity-25 small">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 2: TỔNG HỢP THEO THÁNG -->
                    <div class="tab-pane fade" id="tab-month">
                        <?php if (empty($mySchedules)): ?>
                            <div class="text-center text-muted py-4">Chưa có dữ liệu lịch dạy trong tháng.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle border">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Thứ & Ca dạy</th>
                                            <th>Học phần</th>
                                            <th>Cơ sở & Địa chỉ đến dạy</th>
                                            <th>Phòng</th>
                                            <th class="text-center">Tần suất / Tháng</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($mySchedules as $row): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($row['day_of_week']) ?></strong><br>
                                                    <span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($row['shift_or_periods']) ?></span>
                                                </td>
                                                <td class="fw-semibold"><?= htmlspecialchars($row['subject_name']) ?></td>
                                                <td>
                                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($row['branch_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($row['address']) ?></small>
                                                </td>
                                                <td><span class="badge bg-secondary">Phòng <?= htmlspecialchars($row['room']) ?></span></td>
                                                <td class="text-center">
                                                    <span class="fw-bold text-success">4 buổi</span>
                                                    <div class="small text-muted">(12 tiết/tháng)</div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function copyLink() {
    const input = document.getElementById('personalUrl');
    input.select();
    navigator.clipboard.writeText(input.value);
    alert('Đã sao chép đường dẫn cá nhân: ' + input.value);
}
</script>
</body>
</html>