HR MANAGEMENT SYSTEM
By Md Abdur Rahim Ratul
==================================================

TECHNOLOGIES
- PHP (PDO) - 100% Pure PHP (No JavaScript required)
- MySQL
- HTML5
- CSS3
- XAMPP

FEATURES
- Dashboard summary: payroll, employees, departments, attendance rate
- Department CRUD: add, view, edit, delete (Pure PHP)
- Employee CRUD: add, view, edit, delete (Pure PHP)
- Salary CRUD: add, view, edit, delete (Pure PHP)
- Attendance CRUD: add, view, edit, delete (Pure PHP)
- HR report using SQL JOIN across the four assignment tables
- Search by employee or department (Pure PHP)
- Filter report by attendance status (Pure PHP)
- Export report to CSV directly via PHP output stream
- Responsive desktop and mobile layout with clean light theme
- Elegant typography pairing (Outfit, Playfair Display, Plus Jakarta Sans)

INSTALLATION ON WINDOWS / XAMPP
1. Install and open XAMPP.
2. Start Apache and MySQL.
3. Keep the project folder inside C:\xampp\htdocs\ (e.g. C:\xampp\htdocs\hr_management\)
4. Open:
   http://localhost/phpmyadmin
5. Click the SQL tab.
6. Open database.sql in a text editor, copy all SQL, paste into phpMyAdmin SQL tab and click Go.
7. Open in your browser:
   http://localhost/hr_management/ (or your local directory name)

DATABASE CONNECTION
Default XAMPP settings are in db.php:
Host: localhost
Username: root
Password: (empty)
Database: hr_management

If your MySQL password is different, update db.php.

IMPORTANT NOTES
- Use the website through localhost, not by opening files directly.
- PHP runs completely through Apache with zero JavaScript dependencies.
- The database follows the four tables and fields in the HR Management Report Exercise.
- Foreign keys protect related records. A department or employee cannot be deleted while another table still references it.
- Payroll on the dashboard is the sum of basic_salary + bonus for all Salary records.
- Attendance rate is Present records divided by all attendance records.
