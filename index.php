<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';

// Xử lý các hành động thêm/xóa dữ liệu (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_schedule') {
            $teacher_id       = (int)($_POST['teacher_id'] ?? 0);
            $branch_id        = (int)($_POST['branch_id'] ?? 0);
            $subject_name     = trim($_POST['subject_name'] ?? '');
            $room             = strtoupper(trim($_POST['room'] ?? ''));
            $day_of_week      = trim($_POST['day_of_week'] ?? '');
            $shift_or_periods = trim($_POST['shift_or_periods'] ?? '');

            if (!$teacher_id || !$branch_id || $subject_name === '' || $room === '' || $day_of_week === '' || $shift_or_periods === '') {
                $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Vui lòng điền đầy đủ thông tin phân công lịch dạy!'];
            } else {
                // 1. Kiểm tra trùng lịch giảng viên trên toàn bộ các cơ sở
                $stmtTeacher = $pdo->prepare("
                    SELECT s.subject_name, s.room, b.branch_name 
                    FROM schedules s
                    JOIN branches b ON s.branch_id = b.id
                    WHERE s.teacher_id = ? AND s.day_of_week = ? AND s.shift_or_periods = ?
                    LIMIT 1
                ");
                $stmtTeacher->execute([$teacher_id, $day_of_week, $shift_or_periods]);
                $conflictTeacher = $stmtTeacher->fetch(PDO::FETCH_ASSOC);

                // 2. Kiểm tra trùng phòng học tại cơ sở được chọn
                $stmtRoom = $pdo->prepare("
                    SELECT s.subject_name, t.full_name 
                    FROM schedules s
                    JOIN teachers t ON s.teacher_id = t.id
                    WHERE s.branch_id = ? AND UPPER(TRIM(s.room)) = ? AND s.day_of_week = ? AND s.shift_or_periods = ?
                    LIMIT 1
                ");
                $stmtRoom->execute([$branch_id, $room, $day_of_week, $shift_or_periods]);
                $conflictRoom = $stmtRoom->fetch(PDO::FETCH_ASSOC);

                if ($conflictTeacher) {
                    $_SESSION['flash'] = [
                        'type' => 'danger',
                        'msg'  => "<strong>Trùng lịch giảng viên!</strong> Giảng viên này đã có lịch dạy môn <u>" . htmlspecialchars($conflictTeacher['subject_name']) . "</u> tại <strong>" . htmlspecialchars($conflictTeacher['branch_name']) . "</strong> (Phòng " . htmlspecialchars($conflictTeacher['room']) . ") vào <strong>$day_of_week ($shift_or_periods)</strong>."
                    ];
                } elseif ($conflictRoom) {
                    $_SESSION['flash'] = [
                        'type' => 'danger',
                        'msg'  => "<strong>Trùng phòng học!</strong> Phòng <strong>$room</strong> tại cơ sở này đã được xếp cho <strong>" . htmlspecialchars($conflictRoom['full_name']) . "</strong> dạy môn <u>" . htmlspecialchars($conflictRoom['subject_name']) . "</u> vào <strong>$day_of_week ($shift_or_periods)</strong>."
                    ];
                } else {
                    $stmtInsert = $pdo->prepare("
                        INSERT INTO schedules (teacher_id, branch_id, subject_name, room, day_of_week, shift_or_periods)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmtInsert->execute([$teacher_id, $branch_id, $subject_name, $room, $day_of_week, $shift_or_periods]);
                    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Phân công lịch giảng dạy thành công!'];
                }
            }
        } elseif ($action === 'delete_schedule') {
            $schedule_id = (int)($_POST['schedule_id'] ?? 0);
            if ($schedule_id > 0) {
                $stmtDel = $pdo->prepare("DELETE FROM schedules WHERE id = ?");
                $stmtDel->execute([$schedule_id]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã xóa lịch giảng dạy khỏi thời khóa biểu.'];
            }
        } elseif ($action === 'add_teacher') {
            $full_name = trim($_POST['full_name'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $phone     = trim($_POST['phone'] ?? '');
            $expertise = trim($_POST['expertise'] ?? '');

            if ($full_name !== '' && $email !== '' && $expertise !== '') {
                $stmt = $pdo->prepare("INSERT INTO teachers (full_name, email, phone, expertise) VALUES (?, ?, ?, ?)");
                $stmt->execute([$full_name, $email, $phone, $expertise]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => "Đã thêm giảng viên <strong>" . htmlspecialchars($full_name) . "</strong>."];
            }
        } elseif ($action === 'add_branch') {
            $branch_name = trim($_POST['branch_name'] ?? '');
            $address     = trim($_POST['address'] ?? '');

            if ($branch_name !== '' && $address !== '') {
                $stmt = $pdo->prepare("INSERT INTO branches (branch_name, address) VALUES (?, ?)");
                $stmt->execute([$branch_name, $address]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => "Đã thêm cơ sở <strong>" . htmlspecialchars($branch_name) . "</strong>."];
            }
        }
    } catch (PDOException $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Lỗi cơ sở dữ liệu: ' . htmlspecialchars($e->getMessage())];
    }

    header("Location: index.php");
    exit;
}

// Lấy thông báo flash (nếu có)
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Lấy danh sách cơ sở và giảng viên
$branches = $pdo->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$teachers = $pdo->query("SELECT * FROM teachers ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Danh sách cố định Thứ và Ca học
$daysOfWeek = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ nhật'];
$shifts     = [
    'Ca 1 (Tiết 1 - 3)',
    'Ca 2 (Tiết 4 - 6)',
    'Ca 3 (Tiết 7 - 9)',
    'Ca 4 (Tiết 10 - 12)',
    'Ca 5 (Tiết 13 - 15)'
];

// Lọc thời khóa biểu theo tham số GET
$filterBranch  = (int)($_GET['filter_branch'] ?? 0);
$filterTeacher = (int)($_GET['filter_teacher'] ?? 0);
$filterDay     = trim($_GET['filter_day'] ?? '');

$whereClauses = [];
$params       = [];

if ($filterBranch > 0) {
    $whereClauses[] = "s.branch_id = ?";
    $params[]       = $filterBranch;
}
if ($filterTeacher > 0) {
    $whereClauses[] = "s.teacher_id = ?";
    $params[]       =$filterTeacher;
}
if ($filterDay !== '') {$whereClauses[] = "s.day_of_week = ?";
    $params[]       =$filterDay;
}

$sqlSchedules = "
    SELECT s.*, t.full_name, t.expertise, b.branch_name 
    FROM schedules s
    JOIN teachers t ON s.teacher_id = t.id
    JOIN branches b ON s.branch_id = b.id
";
if (!empty($whereClauses)) {
    $sqlSchedules .= " WHERE " . implode(" AND ", $whereClauses);
}
$sqlSchedules .= " ORDER BY FIELD(s.day_of_week, 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ nhật'), s.shift_or_periods ASC, b.id ASC";

$stmtSchedules = $pdo->prepare($sqlSchedules);
$stmtSchedules->execute($params);
$schedules =$stmtSchedules->fetchAll(PDO::FETCH_ASSOC);

// Tra cứu giảng viên trống lịch theo Thứ & Ca
$checkDay   = trim($_GET['check_day'] ?? '');$checkShift = trim($_GET['check_shift'] ?? '');$freeTeachers = [];

if ($checkDay !== '' &&$checkShift !== '') {
    $stmtFree =$pdo->prepare("
        SELECT * FROM teachers 
        WHERE id NOT IN (
            SELECT teacher_id FROM schedules WHERE day_of_week = ? AND shift_or_periods = ?
        )
        ORDER BY full_name ASC
    ");
    $stmtFree->execute([$checkDay,$checkShift]);
    $freeTeachers =$stmtFree->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HUIT - Hệ thống Quản lý Lịch Giảng Dạy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .navbar-huit { background: linear-gradient(135deg, #004b8d 0%, #0072ce 100%); }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        .card-header { border-radius: 10px 10px 0 0 !important; font-weight: 600; }
        .badge-shift { background-color: #e7f1ff; color: #004b8d; border: 1px solid #b6d4fe; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-huit mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="bi bi-calendar2-week-fill me-2"></i>HUIT SCHEDULE
        </a>
        <div class="ms-auto d-flex gap-2">
            <button class="btn btn-light btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTeacher">
                <i class="bi bi-person-plus-fill text-primary me-1"></i>Thêm Giảng viên
            </button>
            <button class="btn btn-outline-light btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalBranch">
                <i class="bi bi-building-add me-1"></i>Thêm Cơ sở
            </button>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <!-- Thông báo trạng thái -->
    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $flash['msg'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Thống kê nhanh -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card p-3 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Tổng số Cơ sở</div>
                        <div class="fs-4 fw-bold text-primary"><?= count($branches) ?></div>
                    </div>
                    <i class="bi bi-buildings fs-1 text-primary opacity-25"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Đội ngũ Giảng viên</div>
                        <div class="fs-4 fw-bold text-success"><?= count($teachers) ?></div>
                    </div>
                    <i class="bi bi-people-fill fs-1 text-success opacity-25"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Lớp học đang hiển thị</div>
                        <div class="fs-4 fw-bold text-warning"><?= count($schedules) ?></div>
                    </div>
                    <i class="bi bi-journal-check fs-1 text-warning opacity-25"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Cột trái: Form phân công lịch + Gợi ý giảng viên trống lịch -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-plus-circle me-1"></i> Phân công Lịch Giảng Dạy
                </div>
                <div class="card-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="add_schedule">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Giảng viên</label>
                            <select name="teacher_id" class="form-select" required>
                                <option value="">-- Chọn giảng viên --</option>
                                <?php foreach ($teachers as$t): ?>
                                    <option value="<?= $t['id'] ?>">
                                        <?= htmlspecialchars($t['full_name']) ?> (<?= htmlspecialchars($t['expertise']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Cơ sở giảng dạy</label>
                            <select name="branch_id" class="form-select" required>
                                <option value="">-- Chọn cơ sở --</option>
                                <?php foreach ($branches as$b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tên học phần / Môn dạy</label>
                            <input type="text" name="subject_name" class="form-control" placeholder="VD: Lập trình Web PHP" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Thứ</label>
                                <select name="day_of_week" class="form-select" required>
                                    <?php foreach ($daysOfWeek as$d): ?>
                                        <option value="<?= $d ?>"><?= $d ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Phòng học</label>
                                <input type="text" name="room" class="form-control" placeholder="VD: B204" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Ca học / Tiết học</label>
                            <select name="shift_or_periods" class="form-select" required>
                                <?php foreach ($shifts as$s): ?>
                                    <option value="<?= $s ?>"><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Lưu & Kiểm tra trùng lịch
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tra cứu giảng viên rảnh -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-search me-1"></i> Gợi ý Giảng viên Trống lịch
                </div>
                <div class="card-body">
                    <form method="GET" action="index.php">
                        <div class="row g-2 mb-2">
                            <div class="col-5">
                                <select name="check_day" class="form-select form-select-sm" required>
                                    <option value="">Chọn Thứ</option>
                                    <?php foreach ($daysOfWeek as$d): ?>
                                        <option value="<?= $d ?>" <?= $checkDay === $d ? 'selected' : '' ?>><?= $d ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-7">
                                <select name="check_shift" class="form-select form-select-sm" required>
                                    <option value="">Chọn Ca học</option>
                                    <?php foreach ($shifts as$s): ?>
                                        <option value="<?= $s ?>" <?= $checkShift === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-success btn-sm w-100 fw-semibold">
                            Tìm giảng viên rảnh
                        </button>
                    </form>

                    <?php if ($checkDay !== '' &&$checkShift !== ''): ?>
                        <hr>
                        <div class="small fw-semibold text-muted mb-2">
                            Giảng viên trống lịch vào <?= htmlspecialchars($checkDay) ?> - <?= htmlspecialchars($checkShift) ?>:
                        </div>
                        <?php if (empty($freeTeachers)): ?>
                            <div class="text-danger small">Không còn giảng viên nào trống trong ca này.</div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush small">
                                <?php foreach ($freeTeachers as$ft): ?>
                                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                        <span><strong><?= htmlspecialchars($ft['full_name']) ?></strong></span>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($ft['expertise']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Cột phải: Bộ lọc & Bảng thời khóa biểu -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <span class="text-primary fs-6"><i class="bi bi-table me-1"></i> Danh sách Thời Khóa Biểu Đa Cơ Sở</span>
                    <?php if ($filterBranch || $filterTeacher ||$filterDay): ?>
                        <a href="index.php" class="btn btn-sm btn-outline-secondary">Xóa bộ lọc</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <!-- Thanh bộ lọc -->
                    <form method="GET" action="index.php" class="row g-2 mb-3">
                        <div class="col-md-4">
                            <select name="filter_branch" class="form-select form-select-sm">
                                <option value="0">-- Tất cả Cơ sở --</option>
                                <?php foreach ($branches as$b): ?>
                                    <option value="<?= $b['id'] ?>" <?= $filterBranch === (int)$b['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($b['branch_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select name="filter_teacher" class="form-select form-select-sm">
                                <option value="0">-- Tất cả Giảng viên --</option>
                                <?php foreach ($teachers as$t): ?>
                                    <option value="<?= $t['id'] ?>" <?= $filterTeacher === (int)$t['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="filter_day" class="form-select form-select-sm">
                                <option value="">-- Thứ --</option>
                                <?php foreach ($daysOfWeek as$d): ?>
                                    <option value="<?= $d ?>" <?= $filterDay === $d ? 'selected' : '' ?>><?= $d ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-funnel"></i> Lọc
                            </button>
                        </div>
                    </form>

                    <!-- Bảng dữ liệu -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle border">
                            <thead class="table-light">
                                <tr>
                                    <th>Thời gian</th>
                                    <th>Môn học</th>
                                    <th>Giảng viên</th>
                                    <th>Cơ sở & Phòng</th>
                                    <th class="text-center">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($schedules)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            Chưa có lịch giảng dạy nào phù hợp. Hãy thêm lịch mới ở khung bên trái!
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($schedules as$row): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['day_of_week']) ?></div>
                                                <span class="badge badge-shift"><?= htmlspecialchars($row['shift_or_periods']) ?></span>
                                            </td>
                                            <td class="fw-semibold text-primary">
                                                <?= htmlspecialchars($row['subject_name']) ?>
                                            </td>
                                            <td>
                                                <div><?= htmlspecialchars($row['full_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($row['expertise']) ?></small>
                                            </td>
                                            <td>
                                                <div class="small fw-semibold"><?= htmlspecialchars($row['branch_name']) ?></div>
                                                <span class="badge bg-secondary">Phòng <?= htmlspecialchars($row['room']) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <form method="POST" action="index.php" onsubmit="return confirm('Bạn có chắc muốn xóa lịch dạy này?');">
                                                    <input type="hidden" name="action" value="delete_schedule">
                                                    <input type="hidden" name="schedule_id" value="<?= $row['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Xóa lịch">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Thêm Giảng Viên -->
<div class="modal fade" id="modalTeacher" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="index.php" class="modal-content">
            <input type="hidden" name="action" value="add_teacher">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Thêm Giảng viên mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Họ và tên (kèm học hàm/học vị)</label>
                    <input type="text" name="full_name" class="form-control" placeholder="VD: ThS. Phạm Minh Tuấn" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="