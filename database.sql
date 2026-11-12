CREATE DATABASE IF NOT EXISTS hr_management
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE hr_management;

CREATE TABLE Department (
    department_id INT PRIMARY KEY AUTO_INCREMENT,
    department_name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE Employee (
    employee_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_name VARCHAR(100) NOT NULL,
    department_id INT,
    email VARCHAR(100),
    CONSTRAINT fk_employee_department
        FOREIGN KEY (department_id) REFERENCES Department(department_id)
) ENGINE=InnoDB;

CREATE TABLE Salary (
    salary_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT,
    basic_salary DECIMAL(10,2) NOT NULL,
    bonus DECIMAL(10,2) DEFAULT 0,
    CONSTRAINT fk_salary_employee
        FOREIGN KEY (employee_id) REFERENCES Employee(employee_id)
) ENGINE=InnoDB;

CREATE TABLE Attendance (
    attendance_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT,
    attendance_date DATE NOT NULL,
    status VARCHAR(50),
    CONSTRAINT fk_attendance_employee
        FOREIGN KEY (employee_id) REFERENCES Employee(employee_id)
) ENGINE=InnoDB;

-- Sample departments
INSERT INTO Department (department_id, department_name) VALUES
(1, 'Human Resources'),
(2, 'Finance'),
(3, 'IT'),
(4, 'Marketing');

-- Sample employees
INSERT INTO Employee (employee_id, employee_name, department_id, email) VALUES
(101, 'Alice Smith', 1, 'alice@company.com'),
(102, 'Bob Johnson', 2, 'bob@company.com'),
(103, 'Charlie Brown', 3, 'charlie@company.com'),
(104, 'Diana Prince', 4, 'diana@company.com'),
(105, 'Edward Norton', 3, 'edward@company.com');

-- Sample salaries
INSERT INTO Salary (salary_id, employee_id, basic_salary, bonus) VALUES
(1, 101, 50000.00, 5000.00),
(2, 102, 60000.00, 6000.00),
(3, 103, 70000.00, 7000.00),
(4, 104, 55000.00, 5500.00),
(5, 105, 72000.00, 7500.00);

-- Sample attendance
INSERT INTO Attendance (attendance_id, employee_id, attendance_date, status) VALUES
(1, 101, '2025-04-01', 'Present'),
(2, 101, '2025-04-02', 'Present'),
(3, 102, '2025-04-01', 'Absent'),
(4, 102, '2025-04-02', 'Present'),
(5, 103, '2025-04-01', 'Leave'),
(6, 103, '2025-04-02', 'Present'),
(7, 104, '2025-04-01', 'Present'),
(8, 105, '2025-04-01', 'Absent');
