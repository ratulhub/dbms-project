<?php
session_start();
require_once "db.php";

function set_flash($message, $type = "success") {
    $_SESSION["flash"] = ["msg" => $message, "type" => $type];
}

function get_flash() {
    if (!empty($_SESSION["flash"])) {
        $flash = $_SESSION["flash"];
        unset($_SESSION["flash"]);
        return $flash;
    }
    return null;
}

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function money($value) {
    return "৳" . number_format((float)($value ?? 0), 2);
}

$v = $_GET["v"] ?? "dashboard";
$valid_views = ["dashboard", "report", "employees", "departments", "salaries", "attendance"];
if (!in_array($v, $valid_views, true)) {
    $v = "dashboard";
}

$action = $_GET["action"] ?? "";
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

// Handle CSV export for HR report
if ($v === "report" && isset($_GET["export"]) && $_GET["export"] === "csv") {
    $q = trim($_GET["q"] ?? "");
    $status = trim($_GET["status"] ?? "");

    $sql = "SELECT e.employee_name, d.department_name, e.email,
                   s.basic_salary, s.bonus, a.attendance_date, a.status
            FROM Employee e
            INNER JOIN Department d ON e.department_id = d.department_id
            INNER JOIN Salary s ON e.employee_id = s.employee_id
            INNER JOIN Attendance a ON e.employee_id = a.employee_id
            WHERE 1 = 1";
    $params = [];

    if ($q !== "") {
        $sql .= " AND (e.employee_name LIKE ? OR d.department_name LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if ($status !== "") {
        $sql .= " AND a.status = ?";
        $params[] = $status;
    }
    $sql .= " ORDER BY a.attendance_date DESC, e.employee_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"hr_management_report.csv\"");
    $out = fopen("php://output", "w");
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM
    fputcsv($out, ["Employee Name", "Department", "Email", "Basic Salary", "Bonus", "Attendance Date", "Status"]);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r["employee_name"],
            $r["department_name"],
            $r["email"],
            $r["basic_salary"],
            $r["bonus"],
            $r["attendance_date"],
            $r["status"]
        ]);
    }
    fclose($out);
    exit;
}

// Handle Record Deletion (Pure PHP)
if ($action === "delete" && $id > 0) {
    try {
        if ($v === "departments") {
            $stmt = $pdo->prepare("DELETE FROM Department WHERE department_id = ?");
            $stmt->execute([$id]);
            set_flash("Department deleted successfully.");
        } elseif ($v === "employees") {
            $stmt = $pdo->prepare("DELETE FROM Employee WHERE employee_id = ?");
            $stmt->execute([$id]);
            set_flash("Employee deleted successfully.");
        } elseif ($v === "salaries") {
            $stmt = $pdo->prepare("DELETE FROM Salary WHERE salary_id = ?");
            $stmt->execute([$id]);
            set_flash("Salary record deleted successfully.");
        } elseif ($v === "attendance") {
            $stmt = $pdo->prepare("DELETE FROM Attendance WHERE attendance_id = ?");
            $stmt->execute([$id]);
            set_flash("Attendance record deleted successfully.");
        }
    } catch (PDOException $ex) {
        set_flash("Cannot delete this record because it is referenced by other records (e.g. employee, salary, or attendance).", "error");
    }
    header("Location: index.php?v=" . urlencode($v));
    exit;
}

// Handle Form Submissions (Add / Edit)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $post_action = $_POST["form_action"] ?? "save";
    $edit_id = isset($_POST["record_id"]) && $_POST["record_id"] !== "" ? (int)$_POST["record_id"] : 0;

    try {
        if ($v === "departments") {
            $dept_name = trim($_POST["department_name"] ?? "");
            if ($dept_name === "") throw new Exception("Department name is required.");

            if ($edit_id > 0) {
                $stmt = $pdo->prepare("UPDATE Department SET department_name = ? WHERE department_id = ?");
                $stmt->execute([$dept_name, $edit_id]);
                set_flash("Department updated successfully.");
            } else {
                $stmt = $pdo->prepare("INSERT INTO Department (department_name) VALUES (?)");
                $stmt->execute([$dept_name]);
                set_flash("Department added successfully.");
            }
        } elseif ($v === "employees") {
            $name = trim($_POST["employee_name"] ?? "");
            $dept_id = (int)($_POST["department_id"] ?? 0);
            $email = trim($_POST["email"] ?? "");

            if ($name === "") throw new Exception("Employee name is required.");
            if ($dept_id <= 0) throw new Exception("Department is required.");
            if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Valid email address is required.");

            if ($edit_id > 0) {
                $stmt = $pdo->prepare("UPDATE Employee SET employee_name = ?, department_id = ?, email = ? WHERE employee_id = ?");
                $stmt->execute([$name, $dept_id, $email, $edit_id]);
                set_flash("Employee updated successfully.");
            } else {
                $stmt = $pdo->prepare("INSERT INTO Employee (employee_name, department_id, email) VALUES (?, ?, ?)");
                $stmt->execute([$name, $dept_id, $email]);
                set_flash("Employee added successfully.");
            }
        } elseif ($v === "salaries") {
            $emp_id = (int)($_POST["employee_id"] ?? 0);
            $basic = (float)($_POST["basic_salary"] ?? 0);
            $bonus = isset($_POST["bonus"]) && $_POST["bonus"] !== "" ? (float)$_POST["bonus"] : 0.0;

            if ($emp_id <= 0) throw new Exception("Employee is required.");
            if ($basic <= 0) throw new Exception("Basic salary must be greater than zero.");

            if ($edit_id > 0) {
                $stmt = $pdo->prepare("UPDATE Salary SET employee_id = ?, basic_salary = ?, bonus = ? WHERE salary_id = ?");
                $stmt->execute([$emp_id, $basic, $bonus, $edit_id]);
                set_flash("Salary record updated successfully.");
            } else {
                $stmt = $pdo->prepare("INSERT INTO Salary (employee_id, basic_salary, bonus) VALUES (?, ?, ?)");
                $stmt->execute([$emp_id, $basic, $bonus]);
                set_flash("Salary record added successfully.");
            }
        } elseif ($v === "attendance") {
            $emp_id = (int)($_POST["employee_id"] ?? 0);
            $att_date = trim($_POST["attendance_date"] ?? "");
            $status = trim($_POST["status"] ?? "");

            if ($emp_id <= 0) throw new Exception("Employee is required.");
            if ($att_date === "") throw new Exception("Attendance date is required.");
            if (!in_array($status, ["Present", "Absent", "Leave"], true)) throw new Exception("Invalid attendance status.");

            if ($edit_id > 0) {
                $stmt = $pdo->prepare("UPDATE Attendance SET employee_id = ?, attendance_date = ?, status = ? WHERE attendance_id = ?");
                $stmt->execute([$emp_id, $att_date, $status, $edit_id]);
                set_flash("Attendance record updated successfully.");
            } else {
                $stmt = $pdo->prepare("INSERT INTO Attendance (employee_id, attendance_date, status) VALUES (?, ?, ?)");
                $stmt->execute([$emp_id, $att_date, $status]);
                set_flash("Attendance record added successfully.");
            }
        }
        header("Location: index.php?v=" . urlencode($v));
        exit;
    } catch (Exception $e) {
        set_flash($e->getMessage(), "error");
        $redirect_action = $edit_id > 0 ? "&action=edit&id=$edit_id" : "&action=add";
        header("Location: index.php?v=" . urlencode($v) . $redirect_action);
        exit;
    }
}

// Fetch view-specific data
$flash = get_flash();
$today_display = date("j M Y");

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#ffffff">
    <title>HR Management System — By Md Abdur Rahim Ratul</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,500&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <aside class="sidebar">
        <a class="brand" href="index.php?v=dashboard" aria-label="HR Management Home">
            <span class="brand-mark">HR</span>
            <span>HR System <small>BY MD ABDUR RAHIM RATUL</small></span>
        </a>
        <p class="nav-caption">WORKSPACE</p>
        <nav aria-label="Main navigation">
            <a class="nav-link <?= $v === 'dashboard' ? 'on' : '' ?>" href="index.php?v=dashboard"><span>◫</span> Overview</a>
            <a class="nav-link <?= $v === 'report' ? 'on' : '' ?>" href="index.php?v=report"><span>▤</span> HR Report</a>
            <p class="nav-caption">MANAGEMENT</p>
            <a class="nav-link <?= $v === 'employees' ? 'on' : '' ?>" href="index.php?v=employees"><span>♙</span> Employees</a>
            <a class="nav-link <?= $v === 'departments' ? 'on' : '' ?>" href="index.php?v=departments"><span>▦</span> Departments</a>
            <a class="nav-link <?= $v === 'salaries' ? 'on' : '' ?>" href="index.php?v=salaries"><span>＄</span> Salaries</a>
            <a class="nav-link <?= $v === 'attendance' ? 'on' : '' ?>" href="index.php?v=attendance"><span>◷</span> Attendance</a>
        </nav>
        <div class="sidebar-note">
            <span class="live-dot"></span>
            <div><b>HR Management</b><small>By Md Abdur Rahim Ratul</small></div>
        </div>
    </aside>

    <main class="content">
        <header class="topbar">
            <div>
                <span class="eyebrow">HUMAN RESOURCES</span>
                <p class="topbar-title">HR Management System · By Md Abdur Rahim Ratul</p>
            </div>
            <div class="topbar-right">
                <span class="today"><?= e($today_display) ?></span>
                <span class="avatar">HR</span>
            </div>
        </header>

        <section id="main">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash["type"]) ?>">
                    <?= e($flash["msg"]) ?>
                </div>
            <?php endif; ?>

            <?php if ($v === "dashboard"): ?>
                <?php
                $emp_count = (int)$pdo->query("SELECT COUNT(*) FROM Employee")->fetchColumn();
                $dept_count = (int)$pdo->query("SELECT COUNT(*) FROM Department")->fetchColumn();
                $payroll_sum = $pdo->query("SELECT COALESCE(SUM(basic_salary + COALESCE(bonus, 0)), 0) FROM Salary")->fetchColumn();
                $total_att = (int)$pdo->query("SELECT COUNT(*) FROM Attendance")->fetchColumn();
                $present_att = (int)$pdo->query("SELECT COUNT(*) FROM Attendance WHERE status = 'Present'")->fetchColumn();
                $absent_att = (int)$pdo->query("SELECT COUNT(*) FROM Attendance WHERE status = 'Absent'")->fetchColumn();
                $leave_att = (int)$pdo->query("SELECT COUNT(*) FROM Attendance WHERE status = 'Leave'")->fetchColumn();
                $rate = $total_att > 0 ? round(($present_att / $total_att) * 100) : 0;

                $latest_emps = $pdo->query("SELECT e.employee_name, d.department_name, e.email FROM Employee e LEFT JOIN Department d ON e.department_id = d.department_id ORDER BY e.employee_id DESC LIMIT 5")->fetchAll();
                ?>
                <div class="page-heading">
                    <div>
                        <span class="eyebrow">YOUR WORKSPACE</span>
                        <h1>Overview</h1>
                        <p>A clear view of your people, payroll and attendance.</p>
                    </div>
                    <span class="date-chip">● Database connected</span>
                </div>

                <div class="stat-grid">
                    <article class="stat-card payroll-card">
                        <span>Total payroll</span>
                        <strong><?= money($payroll_sum) ?></strong>
                        <small>Basic salary + bonus</small>
                    </article>
                    <article class="stat-card">
                        <span>Total employees</span>
                        <strong><?= $emp_count ?></strong>
                        <small>Registered employees</small>
                    </article>
                    <article class="stat-card">
                        <span>Departments</span>
                        <strong><?= $dept_count ?></strong>
                        <small>Active departments</small>
                    </article>
                    <article class="stat-card">
                        <span>Attendance rate</span>
                        <strong><?= $rate ?>%</strong>
                        <small>Present records / all records</small>
                    </article>
                </div>

                <div class="dashboard-grid">
                    <section class="surface">
                        <div class="surface-heading">
                            <div>
                                <h2>Attendance overview</h2>
                                <p>Record distribution by status</p>
                            </div>
                        </div>
                        <div class="progress-row">
                            <div><span>Present</span><b><?= $present_att ?></b></div>
                            <div class="progress-track"><i style="width: <?= $total_att ? ($present_att / $total_att * 100) : 0 ?>%"></i></div>
                        </div>
                        <div class="progress-row">
                            <div><span>Absent</span><b><?= $absent_att ?></b></div>
                            <div class="progress-track"><i class="progress-absent" style="width: <?= $total_att ? ($absent_att / $total_att * 100) : 0 ?>%"></i></div>
                        </div>
                        <div class="progress-row">
                            <div><span>Leave</span><b><?= $leave_att ?></b></div>
                            <div class="progress-track"><i class="progress-leave" style="width: <?= $total_att ? ($leave_att / $total_att * 100) : 0 ?>%"></i></div>
                        </div>
                    </section>

                    <section class="surface">
                        <div class="surface-heading">
                            <div>
                                <h2>Quick actions</h2>
                                <p>Common management tasks</p>
                            </div>
                        </div>
                        <div class="quick-actions">
                            <a href="index.php?v=employees&action=add">＋ Add employee <span>→</span></a>
                            <a href="index.php?v=attendance&action=add">＋ Add attendance <span>→</span></a>
                            <a href="index.php?v=salaries&action=add">＋ Manage salary <span>→</span></a>
                            <a href="index.php?v=report">▤ View HR report <span>→</span></a>
                        </div>
                    </section>
                </div>

                <section class="surface recent-surface">
                    <div class="surface-heading">
                        <div>
                            <h2>Employee directory</h2>
                            <p>Your latest registered employees</p>
                        </div>
                        <a href="index.php?v=employees" class="text-btn">View all →</a>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Employee Name</th>
                                    <th>Department Name</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($latest_emps) > 0): ?>
                                    <?php foreach ($latest_emps as $le): ?>
                                        <tr>
                                            <td data-label="Employee Name"><?= e($le["employee_name"]) ?></td>
                                            <td data-label="Department Name"><?= e($le["department_name"] ?? "—") ?></td>
                                            <td data-label="Email"><?= e($le["email"]) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="empty"><span class="empty-icon">⌕</span><b>No records found</b><small>Add employees to see them here.</small></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

            <?php elseif ($v === "report"): ?>
                <?php
                $q = trim($_GET["q"] ?? "");
                $status = trim($_GET["status"] ?? "");

                $sql = "SELECT e.employee_name, d.department_name, e.email,
                               s.basic_salary, s.bonus, a.attendance_date, a.status
                        FROM Employee e
                        INNER JOIN Department d ON e.department_id = d.department_id
                        INNER JOIN Salary s ON e.employee_id = s.employee_id
                        INNER JOIN Attendance a ON e.employee_id = a.employee_id
                        WHERE 1 = 1";
                $params = [];

                if ($q !== "") {
                    $sql .= " AND (e.employee_name LIKE ? OR d.department_name LIKE ?)";
                    $params[] = "%$q%";
                    $params[] = "%$q%";
                }
                if ($status !== "") {
                    $sql .= " AND a.status = ?";
                    $params[] = $status;
                }
                $sql .= " ORDER BY a.attendance_date DESC, e.employee_name ASC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $report_rows = $stmt->fetchAll();
                ?>
                <div class="page-heading">
                    <div>
                        <span class="eyebrow">INSIGHTS</span>
                        <h1>HR Report</h1>
                        <p>Combined employee, department, salary and attendance information.</p>
                    </div>
                    <a href="index.php?v=report&export=csv<?= $q !== '' ? '&q=' . urlencode($q) : '' ?><?= $status !== '' ? '&status=' . urlencode($status) : '' ?>" class="primary-btn">↓ Export CSV</a>
                </div>

                <form method="GET" action="index.php" class="filter-bar">
                    <input type="hidden" name="v" value="report">
                    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search employee or department…">
                    <select name="status">
                        <option value="">All statuses</option>
                        <option value="Present" <?= $status === "Present" ? "selected" : "" ?>>Present</option>
                        <option value="Absent" <?= $status === "Absent" ? "selected" : "" ?>>Absent</option>
                        <option value="Leave" <?= $status === "Leave" ? "selected" : "" ?>>Leave</option>
                    </select>
                    <button type="submit" class="secondary-btn">Search & Filter</button>
                    <?php if ($q !== "" || $status !== ""): ?>
                        <a href="index.php?v=report" class="secondary-btn">Reset</a>
                    <?php endif; ?>
                </form>

                <div class="section-meta">
                    <span><?= count($report_rows) ?> <?= count($report_rows) === 1 ? "report row" : "report rows" ?></span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th>Department Name</th>
                                <th>Email</th>
                                <th>Basic Salary</th>
                                <th>Bonus</th>
                                <th>Attendance Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($report_rows) > 0): ?>
                                <?php foreach ($report_rows as $row): ?>
                                    <tr>
                                        <td data-label="Employee Name"><?= e($row["employee_name"]) ?></td>
                                        <td data-label="Department Name"><?= e($row["department_name"]) ?></td>
                                        <td data-label="Email"><?= e($row["email"]) ?></td>
                                        <td data-label="Basic Salary"><?= money($row["basic_salary"]) ?></td>
                                        <td data-label="Bonus"><?= money($row["bonus"]) ?></td>
                                        <td data-label="Attendance Date"><?= e($row["attendance_date"]) ?></td>
                                        <td data-label="Status"><span class="pill <?= e($row["status"]) ?>"><?= e($row["status"]) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty">
                                        <span class="empty-icon">⌕</span>
                                        <b>No report rows match your criteria</b>
                                        <small>Try clearing the filters to view all records.</small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($v === "departments"): ?>
                <?php
                $edit_row = null;
                if ($action === "edit" && $id > 0) {
                    $st = $pdo->prepare("SELECT * FROM Department WHERE department_id = ?");
                    $st->execute([$id]);
                    $edit_row = $st->fetch();
                }
                $rows = $pdo->query("SELECT * FROM Department ORDER BY department_id DESC")->fetchAll();
                ?>
                <div class="page-heading">
                    <div>
                        <span class="eyebrow">MANAGEMENT</span>
                        <h1>Departments</h1>
                        <p>Manage your organization departments in one place.</p>
                    </div>
                    <?php if ($action !== "add" && $action !== "edit"): ?>
                        <a href="index.php?v=departments&action=add" class="primary-btn"><span>＋</span> Add department</a>
                    <?php endif; ?>
                </div>

                <?php if ($action === "add" || ($action === "edit" && $edit_row)): ?>
                    <div class="surface form-card">
                        <div class="form-header">
                            <div>
                                <span class="eyebrow"><?= $edit_row ? "UPDATE RECORD" : "NEW RECORD" ?></span>
                                <h2><?= $edit_row ? "Edit" : "Add" ?> Department</h2>
                            </div>
                            <a href="index.php?v=departments" class="close-btn" title="Cancel">×</a>
                        </div>
                        <form method="POST" action="index.php?v=departments">
                            <input type="hidden" name="record_id" value="<?= $edit_row ? e($edit_row["department_id"]) : "" ?>">
                            <label class="form-field">
                                <span>Department Name</span>
                                <input type="text" name="department_name" required value="<?= $edit_row ? e($edit_row["department_name"]) : "" ?>" placeholder="e.g. Finance">
                            </label>
                            <div class="form-actions">
                                <a href="index.php?v=departments" class="secondary-btn">Cancel</a>
                                <button type="submit" class="primary-btn">Save Department</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="section-meta"><span><?= count($rows) ?> <?= count($rows) === 1 ? "record" : "records" ?></span></div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Department ID</th>
                                <th>Department Name</th>
                                <th class="action-heading">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($rows) > 0): ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td data-label="Department ID"><?= e($r["department_id"]) ?></td>
                                        <td data-label="Department Name"><?= e($r["department_name"]) ?></td>
                                        <td class="actions">
                                            <a href="index.php?v=departments&action=edit&id=<?= e($r["department_id"]) ?>" class="action-btn edit-btn">Edit</a>
                                            <a href="index.php?v=departments&action=delete&id=<?= e($r["department_id"]) ?>" class="action-btn delete-btn">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="empty"><span class="empty-icon">⌕</span><b>No records found</b><small>Add a department to see it here.</small></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($v === "employees"): ?>
                <?php
                $edit_row = null;
                if ($action === "edit" && $id > 0) {
                    $st = $pdo->prepare("SELECT * FROM Employee WHERE employee_id = ?");
                    $st->execute([$id]);
                    $edit_row = $st->fetch();
                }
                $departments = $pdo->query("SELECT * FROM Department ORDER BY department_name ASC")->fetchAll();
                $rows = $pdo->query("SELECT e.employee_id, e.employee_name, e.department_id, d.department_name, e.email FROM Employee e LEFT JOIN Department d ON e.department_id = d.department_id ORDER BY e.employee_id DESC")->fetchAll();
                ?>
                <div class="page-heading">
                    <div>
                        <span class="eyebrow">MANAGEMENT</span>
                        <h1>Employees</h1>
                        <p>Manage employee records, department affiliations and emails.</p>
                    </div>
                    <?php if ($action !== "add" && $action !== "edit"): ?>
                        <a href="index.php?v=employees&action=add" class="primary-btn"><span>＋</span> Add employee</a>
                    <?php endif; ?>
                </div>

                <?php if ($action === "add" || ($action === "edit" && $edit_row)): ?>
                    <div class="surface form-card">
                        <div class="form-header">
                            <div>
                                <span class="eyebrow"><?= $edit_row ? "UPDATE RECORD" : "NEW RECORD" ?></span>
                                <h2><?= $edit_row ? "Edit" : "Add" ?> Employee</h2>
                            </div>
                            <a href="index.php?v=employees" class="close-btn" title="Cancel">×</a>
                        </div>
                        <form method="POST" action="index.php?v=employees">
                            <input type="hidden" name="record_id" value="<?= $edit_row ? e($edit_row["employee_id"]) : "" ?>">
                            <label class="form-field">
                                <span>Employee Name</span>
                                <input type="text" name="employee_name" required value="<?= $edit_row ? e($edit_row["employee_name"]) : "" ?>" placeholder="e.g. John Doe">
                            </label>
                            <label class="form-field">
                                <span>Department</span>
                                <select name="department_id" required>
                                    <option value="">Choose department</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= e($d["department_id"]) ?>" <?= $edit_row && (string)$edit_row["department_id"] === (string)$d["department_id"] ? "selected" : "" ?>>
                                            <?= e($d["department_name"]) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="form-field">
                                <span>Email Address</span>
                                <input type="email" name="email" required value="<?= $edit_row ? e($edit_row["email"]) : "" ?>" placeholder="e.g. john@company.com">
                            </label>
                            <div class="form-actions">
                                <a href="index.php?v=employees" class="secondary-btn">Cancel</a>
                                <button type="submit" class="primary-btn">Save Employee</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="section-meta"><span><?= count($rows) ?> <?= count($rows) === 1 ? "record" : "records" ?></span></div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Employee Name</th>
                                <th>Department Name</th>
                                <th>Email</th>
                                <th class="action-heading">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($rows) > 0): ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td data-label="Employee ID"><?= e($r["employee_id"]) ?></td>
                                        <td data-label="Employee Name"><?= e($r["employee_name"]) ?></td>
                                        <td data-label="Department Name"><?= e($r["department_name"] ?? "—") ?></td>
                                        <td data-label="Email"><?= e($r["email"]) ?></td>
                                        <td class="actions">
                                            <a href="index.php?v=employees&action=edit&id=<?= e($r["employee_id"]) ?>" class="action-btn edit-btn">Edit</a>
                                            <a href="index.php?v=employees&action=delete&id=<?= e($r["employee_id"]) ?>" class="action-btn delete-btn">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty"><span class="empty-icon">⌕</span><b>No records found</b><small>Add an employee to see it here.</small></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($v === "salaries"): ?>
                <?php
                $edit_row = null;
                if ($action === "edit" && $id > 0) {
                    $st = $pdo->prepare("SELECT * FROM Salary WHERE salary_id = ?");
                    $st->execute([$id]);
                    $edit_row = $st->fetch();
                }
                $employees = $pdo->query("SELECT employee_id, employee_name FROM Employee ORDER BY employee_name ASC")->fetchAll();
                $rows = $pdo->query("SELECT s.salary_id, s.employee_id, e.employee_name, s.basic_salary, s.bonus FROM Salary s INNER JOIN Employee e ON s.employee_id = e.employee_id ORDER BY s.salary_id DESC")->fetchAll();
                ?>
                <div class="page-heading">
                    <div>
                        <span class="eyebrow">MANAGEMENT</span>
                        <h1>Salaries</h1>
                        <p>Track basic salaries, performance bonuses and payroll records.</p>
                    </div>
                    <?php if ($action !== "add" && $action !== "edit"): ?>
                        <a href="index.php?v=salaries&action=add" class="primary-btn"><span>＋</span> Add salary record</a>
                    <?php endif; ?>
                </div>

                <?php if ($action === "add" || ($action === "edit" && $edit_row)): ?>
                    <div class="surface form-card">
                        <div class="form-header">
                            <div>
                                <span class="eyebrow"><?= $edit_row ? "UPDATE RECORD" : "NEW RECORD" ?></span>
                                <h2><?= $edit_row ? "Edit" : "Add" ?> Salary Record</h2>
                            </div>
                            <a href="index.php?v=salaries" class="close-btn" title="Cancel">×</a>
                        </div>
                        <form method="POST" action="index.php?v=salaries">
                            <input type="hidden" name="record_id" value="<?= $edit_row ? e($edit_row["salary_id"]) : "" ?>">
                            <label class="form-field">
                                <span>Employee</span>
                                <select name="employee_id" required>
                                    <option value="">Choose employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= e($emp["employee_id"]) ?>" <?= $edit_row && (string)$edit_row["employee_id"] === (string)$emp["employee_id"] ? "selected" : "" ?>>
                                            <?= e($emp["employee_name"]) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="form-field">
                                <span>Basic Salary (৳)</span>
                                <input type="number" step="0.01" min="0" name="basic_salary" required value="<?= $edit_row ? e($edit_row["basic_salary"]) : "" ?>" placeholder="Enter basic salary">
                            </label>
                            <label class="form-field">
                                <span>Bonus (৳)</span>
                                <input type="number" step="0.01" min="0" name="bonus" value="<?= $edit_row ? e($edit_row["bonus"]) : "0.00" ?>" placeholder="Enter bonus amount">
                            </label>
                            <div class="form-actions">
                                <a href="index.php?v=salaries" class="secondary-btn">Cancel</a>
                                <button type="submit" class="primary-btn">Save Salary</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="section-meta"><span><?= count($rows) ?> <?= count($rows) === 1 ? "record" : "records" ?></span></div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Salary ID</th>
                                <th>Employee Name</th>
                                <th>Basic Salary</th>
                                <th>Bonus</th>
                                <th class="action-heading">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($rows) > 0): ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td data-label="Salary ID"><?= e($r["salary_id"]) ?></td>
                                        <td data-label="Employee Name"><?= e($r["employee_name"]) ?></td>
                                        <td data-label="Basic Salary"><?= money($r["basic_salary"]) ?></td>
                                        <td data-label="Bonus"><?= money($r["bonus"]) ?></td>
                                        <td class="actions">
                                            <a href="index.php?v=salaries&action=edit&id=<?= e($r["salary_id"]) ?>" class="action-btn edit-btn">Edit</a>
                                            <a href="index.php?v=salaries&action=delete&id=<?= e($r["salary_id"]) ?>" class="action-btn delete-btn">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty"><span class="empty-icon">⌕</span><b>No records found</b><small>Add a salary record to see it here.</small></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($v === "attendance"): ?>
                <?php
                $edit_row = null;
                if ($action === "edit" && $id > 0) {
                    $st = $pdo->prepare("SELECT * FROM Attendance WHERE attendance_id = ?");
                    $st->execute([$id]);
                    $edit_row = $st->fetch();
                }
                $employees = $pdo->query("SELECT employee_id, employee_name FROM Employee ORDER BY employee_name ASC")->fetchAll();
                $rows = $pdo->query("SELECT a.attendance_id, a.employee_id, e.employee_name, a.attendance_date, a.status FROM Attendance a INNER JOIN Employee e ON a.employee_id = e.employee_id ORDER BY a.attendance_date DESC, a.attendance_id DESC")->fetchAll();
                ?>
                <div class="page-heading">
                    <div>
                        <span class="eyebrow">MANAGEMENT</span>
                        <h1>Attendance</h1>
                        <p>Track employee presence, leaves, and absences daily.</p>
                    </div>
                    <?php if ($action !== "add" && $action !== "edit"): ?>
                        <a href="index.php?v=attendance&action=add" class="primary-btn"><span>＋</span> Add attendance</a>
                    <?php endif; ?>
                </div>

                <?php if ($action === "add" || ($action === "edit" && $edit_row)): ?>
                    <div class="surface form-card">
                        <div class="form-header">
                            <div>
                                <span class="eyebrow"><?= $edit_row ? "UPDATE RECORD" : "NEW RECORD" ?></span>
                                <h2><?= $edit_row ? "Edit" : "Add" ?> Attendance Record</h2>
                            </div>
                            <a href="index.php?v=attendance" class="close-btn" title="Cancel">×</a>
                        </div>
                        <form method="POST" action="index.php?v=attendance">
                            <input type="hidden" name="record_id" value="<?= $edit_row ? e($edit_row["attendance_id"]) : "" ?>">
                            <label class="form-field">
                                <span>Employee</span>
                                <select name="employee_id" required>
                                    <option value="">Choose employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= e($emp["employee_id"]) ?>" <?= $edit_row && (string)$edit_row["employee_id"] === (string)$emp["employee_id"] ? "selected" : "" ?>>
                                            <?= e($emp["employee_name"]) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="form-field">
                                <span>Attendance Date</span>
                                <input type="date" name="attendance_date" required value="<?= $edit_row ? e($edit_row["attendance_date"]) : date("Y-m-d") ?>">
                            </label>
                            <label class="form-field">
                                <span>Status</span>
                                <select name="status" required>
                                    <option value="Present" <?= $edit_row && $edit_row["status"] === "Present" ? "selected" : "" ?>>Present</option>
                                    <option value="Absent" <?= $edit_row && $edit_row["status"] === "Absent" ? "selected" : "" ?>>Absent</option>
                                    <option value="Leave" <?= $edit_row && $edit_row["status"] === "Leave" ? "selected" : "" ?>>Leave</option>
                                </select>
                            </label>
                            <div class="form-actions">
                                <a href="index.php?v=attendance" class="secondary-btn">Cancel</a>
                                <button type="submit" class="primary-btn">Save Attendance</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="section-meta"><span><?= count($rows) ?> <?= count($rows) === 1 ? "record" : "records" ?></span></div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Attendance ID</th>
                                <th>Employee Name</th>
                                <th>Attendance Date</th>
                                <th>Status</th>
                                <th class="action-heading">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($rows) > 0): ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td data-label="Attendance ID"><?= e($r["attendance_id"]) ?></td>
                                        <td data-label="Employee Name"><?= e($r["employee_name"]) ?></td>
                                        <td data-label="Attendance Date"><?= e($r["attendance_date"]) ?></td>
                                        <td data-label="Status"><span class="pill <?= e($r["status"]) ?>"><?= e($r["status"]) ?></span></td>
                                        <td class="actions">
                                            <a href="index.php?v=attendance&action=edit&id=<?= e($r["attendance_id"]) ?>" class="action-btn edit-btn">Edit</a>
                                            <a href="index.php?v=attendance&action=delete&id=<?= e($r["attendance_id"]) ?>" class="action-btn delete-btn">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty"><span class="empty-icon">⌕</span><b>No records found</b><small>Add an attendance record to see it here.</small></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <footer>
            <strong>HR Management System</strong> <span>•</span> Developed by <strong>Md Abdur Rahim Ratul</strong>
            <span class="footer-right">DBMS Project · PHP + MySQL</span>
        </footer>
    </main>
</body>
</html>

