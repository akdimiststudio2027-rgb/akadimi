CREATE DATABASE IF NOT EXISTS akadimi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE akadimi;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, phone VARCHAR(30) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL, role ENUM('student','teacher','admin') NOT NULL DEFAULT 'student',
 governorate_id INT NULL, stage_id INT NULL, curriculum_id INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE governorates (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE);
CREATE TABLE stages (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, active TINYINT(1) DEFAULT 1);
CREATE TABLE curricula (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, stage_id INT NOT NULL, active TINYINT(1) DEFAULT 1);
CREATE TABLE subjects (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, curriculum_id INT NOT NULL, active TINYINT(1) DEFAULT 1);
CREATE TABLE teacher_subjects (teacher_id INT NOT NULL, subject_id INT NOT NULL, PRIMARY KEY(teacher_id,subject_id));
CREATE TABLE courses (id INT AUTO_INCREMENT PRIMARY KEY, teacher_id INT NOT NULL, subject_id INT NOT NULL, title VARCHAR(200) NOT NULL, description TEXT, price DECIMAL(10,2) NOT NULL DEFAULT 0, duration_days INT NOT NULL DEFAULT 30, active TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE lectures (id INT AUTO_INCREMENT PRIMARY KEY, course_id INT NOT NULL, title VARCHAR(200) NOT NULL, video_url VARCHAR(500), sort_order INT DEFAULT 0, is_free TINYINT(1) DEFAULT 0);
CREATE TABLE files (id INT AUTO_INCREMENT PRIMARY KEY, course_id INT NOT NULL, title VARCHAR(200) NOT NULL, file_path VARCHAR(500) NOT NULL);
CREATE TABLE exams (id INT AUTO_INCREMENT PRIMARY KEY, course_id INT NOT NULL, title VARCHAR(200) NOT NULL, questions_json LONGTEXT, total_marks INT DEFAULT 100);
CREATE TABLE subscriptions (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, course_id INT NOT NULL, starts_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, status ENUM('active','expired','cancelled') DEFAULT 'active');
CREATE TABLE payments (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, course_id INT NOT NULL, amount DECIMAL(10,2) NOT NULL, provider VARCHAR(100), transaction_ref VARCHAR(200), status ENUM('pending','paid','failed') DEFAULT 'pending', paid_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE announcements (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, body TEXT NOT NULL, active TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

INSERT INTO governorates(name) VALUES ('بغداد'),('النجف'),('كربلاء'),('البصرة'),('واسط'),('بابل'),('ذي قار'),('ميسان'),('ديالى'),('الأنبار'),('نينوى'),('صلاح الدين'),('كركوك'),('المثنى'),('القادسية');
INSERT INTO stages(name) VALUES ('الثالث المتوسط'),('الرابع العلمي'),('الخامس العلمي'),('السادس العلمي');
INSERT INTO curricula(name,stage_id) VALUES ('المنهاج العراقي',1),('المنهاج العراقي',2),('المنهاج العراقي',3),('المنهاج العراقي',4);
INSERT INTO subjects(name,curriculum_id) VALUES ('رياضيات',4),('فيزياء',4),('كيمياء',4),('أحياء',4),('عربي',4),('إنكليزي',4),('إسلامية',4);
-- حساب المدير: الهاتف 07700000000 وكلمة المرور Admin@12345
INSERT INTO users(name,phone,password_hash,role) VALUES ('مدير أكاديمي','07700000000','$2y$12$Kasy9A81drD2juH7CckLN.o3TA1EuexgdxGlQsC3kE1CohYpe3FEC','admin');
