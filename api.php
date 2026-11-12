<?php
header("Content-Type: application/json; charset=UTF-8");

require_once "db.php";

function send_json($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function get_input() {
    $data = json_decode(file_get_contents("php://input"), true);
    return is_array($data) ? $data : [];
}

function clean_text($value) {
    return trim((string)($value ?? ""));
}

function required_value($data, $key) {
    if (!isset($data[$key]) || trim((string)$data[$key]) === "") {
        send_json(["error" => ucfirst(str_replace("_", " ", $key)) . " is required."], 422);
    }
    return trim((string)$data[$key]);
}

$resource = $_GET["r"] ?? "";
$method = $_SERVER["REQUEST_METHOD"];

$config = [
    "departments" => [
        "table" => "Department",
        "pk" => "department_id",
        "fields" => ["department_name"],
        "list" => "SELECT * FROM Department ORDER BY department_id DESC"
    ],
    "employees" => [
        "table" => "Employee",
        "pk" => "employee_id",
        "fields" => ["employee_name", "department_id", "email"],
        "list" => "SELECT e.employee_id, e.employee_name, e.department_id, d.department_name, e.email
                   FROM Employee e LEFT JOIN Department d ON e.department_id = d.department_id
                   ORDER BY e.employee_id DESC"
    ],
    "salaries" => [
        "table" => "Salary",
        "pk" => "salary_id",
        "fields" => ["employee_id", "basic_salary", "bonus"],
        "list" => "SELECT s.salary_id, s.employee_id, e.employee_name, s.basic_salary, s.bonus
                   FROM Salary s INNER JOIN Employee e ON s.employee_id = e.employee_id
                   ORDER BY s.salary_id DESC"
    ],
    "attendance" => [
        "table" => "Attendance",
        "pk" => "attendance_id",
        "fields" => ["employee_id", "attendance_date", "status"],
        "list" => "SELECT a.attendance_id, a.employee_id, e.employee_name, a.attendance_date, a.status
                   FROM Attendance a INNER JOIN Employee e ON a.employee_id = e.employee_id
                   ORDER BY a.attendance_date DESC, a.attendance_id DESC"
    ]
];

try {
    // Dashboard summary
    if ($resource === "stats" && $method === "GET") {
        $emp = (int)$pdo->query("SELECT COUNT(*) FROM Employee")->fetchColumn();
        $dept = (int)$pdo->query("SELECT COUNT(*) FROM Department")->fetchColumn();
        $payroll = $pdo->query("SELECT COALESCE(SUM(basic_salary + COALESCE(bonus, 0)), 0) FROM Salary")->fetchColumn();
        $total = (int)$pdo->query("SELECT COUNT(*) FROM Attendance")->fetchColumn();
        $present = (int)$pdo->query("SELECT COUNT(*) FROM Attendance WHERE status = 'Present'")->fetchColumn();
        $rate = $total > 0 ? round(($present / $total) * 100) : 0;
        send_json(["emp" => $emp, "dept" => $dept, "payroll" => $payroll, "rate" => $rate]);
    }

    // Combined HR report with optional search and status filter
    if ($resource === "report" && $method === "GET") {
        $sql = "SELECT e.employee_name, d.department_name, e.email,
                       s.basic_salary, s.bonus, a.attendance_date, a.status
                FROM Employee e
                INNER JOIN Department d ON e.department_id = d.department_id
                INNER JOIN Salary s ON e.employee_id = s.employee_id
                INNER JOIN Attendance a ON e.employee_id = a.employee_id
                WHERE 1 = 1";
        $params = [];

        $q = clean_text($_GET["q"] ?? "");
        $status = clean_text($_GET["status"] ?? "");

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
        send_json($stmt->fetchAll());
    }

    // List records
    if (isset($config[$resource]) && $method === "GET") {
        $stmt = $pdo->query($config[$resource]["list"]);
        send_json($stmt->fetchAll());
    }

    // Create record
    if (isset($config[$resource]) && $method === "POST") {
        $data = get_input();
        $table = $config[$resource]["table"];
        $fields = $config[$resource]["fields"];

        $values = [];
        foreach ($fields as $field) {
            if ($field === "bonus" && (!isset($data[$field]) || $data[$field] === "")) {
                $values[$field] = 0;
            } else {
                $values[$field] = required_value($data, $field);
            }
        }

        if ($resource === "employees" && !filter_var($values["email"], FILTER_VALIDATE_EMAIL)) {
            send_json(["error" => "Please enter a valid email address."], 422);
        }
        if (($resource === "salaries") && ((float)$values["basic_salary"] < 0 || (float)$values["bonus"] < 0)) {
            send_json(["error" => "Salary and bonus cannot be negative."], 422);
        }
        if ($resource === "attendance" && !in_array($values["status"], ["Present", "Absent", "Leave"], true)) {
            send_json(["error" => "Invalid attendance status."], 422);
        }

        $columns = implode(", ", $fields);
        $marks = implode(", ", array_fill(0, count($fields), "?"));
        $stmt = $pdo->prepare("INSERT INTO $table ($columns) VALUES ($marks)");
        $stmt->execute(array_values($values));
        send_json(["message" => "Record added successfully.", "id" => $pdo->lastInsertId()], 201);
    }

    // Update record
    if (isset($config[$resource]) && $method === "PUT") {
        $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
        if (!$id) send_json(["error" => "A valid record ID is required."], 400);

        $data = get_input();
        $table = $config[$resource]["table"];
        $pk = $config[$resource]["pk"];
        $fields = $config[$resource]["fields"];
        $sets = [];
        $values = [];

        foreach ($fields as $field) {
            if ($field === "bonus" && (!isset($data[$field]) || $data[$field] === "")) {
                $data[$field] = 0;
            }
            $values[] = required_value($data, $field);
            $sets[] = "$field = ?";
        }

        if ($resource === "employees" && !filter_var($data["email"], FILTER_VALIDATE_EMAIL)) {
            send_json(["error" => "Please enter a valid email address."], 422);
        }
        if (($resource === "salaries") && ((float)$data["basic_salary"] < 0 || (float)$data["bonus"] < 0)) {
            send_json(["error" => "Salary and bonus cannot be negative."], 422);
        }
        if ($resource === "attendance" && !in_array($data["status"], ["Present", "Absent", "Leave"], true)) {
            send_json(["error" => "Invalid attendance status."], 422);
        }

        $values[] = $id;
        $stmt = $pdo->prepare("UPDATE $table SET " . implode(", ", $sets) . " WHERE $pk = ?");
        $stmt->execute($values);
        send_json(["message" => "Record updated successfully."]);
    }

    // Delete record
    if (isset($config[$resource]) && $method === "DELETE") {
        $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
        if (!$id) send_json(["error" => "A valid record ID is required."], 400);

        $table = $config[$resource]["table"];
        $pk = $config[$resource]["pk"];
        $stmt = $pdo->prepare("DELETE FROM $table WHERE $pk = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            send_json(["error" => "Record not found."], 404);
        }
        send_json(["message" => "Record deleted successfully."]);
    }

    send_json(["error" => "Invalid API request."], 404);

} catch (PDOException $e) {
    if ($e->getCode() === "23000") {
        send_json(["error" => "This record is linked to other data, or a duplicate/invalid relationship was provided. Check related records first."], 409);
    }
    send_json(["error" => "A database error occurred. Check your input and database setup."], 500);
} catch (Throwable $e) {
    send_json(["error" => "Something went wrong. Please try again."], 500);
}
?>
