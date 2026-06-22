CREATE DATABASE IF NOT EXISTS gsf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gsf;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matric_id VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Student','Admin') DEFAULT 'Student',
    approved TINYINT(1) NOT NULL DEFAULT 1,
    program VARCHAR(100),
    bio TEXT,
    avatar VARCHAR(255),
    banner VARCHAR(255),
    primary_color VARCHAR(7) DEFAULT '#009688',
    secondary_color VARCHAR(7) DEFAULT '#ffffff',
    text_color VARCHAR(7) DEFAULT '#333333',
    font_choice VARCHAR(30) DEFAULT 'Satoshi',
    card_style VARCHAR(20) DEFAULT 'plain',
    bg_type VARCHAR(20) DEFAULT 'default',
    bg_color VARCHAR(7) DEFAULT '#f0f2f5',
    bg_image VARCHAR(255),
    last_active DATETIME,
    banned_until DATETIME,
    ban_reason TEXT,
    muted_until DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Safety net: if the users table already existed from an older version
-- without the approved column, this adds it. Existing rows default to 1 (approved).
ALTER TABLE users ADD COLUMN IF NOT EXISTS approved TINYINT(1) NOT NULL DEFAULT 1 AFTER role;

CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(20) UNIQUE NOT NULL,
    subject_name VARCHAR(100) NOT NULL,
    program VARCHAR(100),
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS bad_words (
    id INT AUTO_INCREMENT PRIMARY KEY,
    word VARCHAR(50) NOT NULL,
    added_by INT,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS groups_ (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    subject_code VARCHAR(20),
    leader_id INT NOT NULL,
    visibility ENUM('public','private') DEFAULT 'public',
    max_members INT DEFAULT 15,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (leader_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_membership (group_id, user_id),
    FOREIGN KEY (group_id) REFERENCES groups_(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT,
    attachment VARCHAR(255),
    attachment_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups_(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS friends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status ENUM('pending','accepted') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    reported_id INT NOT NULL,
    reported_matric VARCHAR(20) NOT NULL,
    reason TEXT NOT NULL,
    image_path VARCHAR(255),
    status ENUM('pending','resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS study_trackers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tracker_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tracker_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    completed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tracker_id) REFERENCES study_trackers(id) ON DELETE CASCADE
);

INSERT IGNORE INTO users (matric_id, full_name, password, role)
VALUES ('admin', 'Administrator', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin');

INSERT IGNORE INTO users (matric_id, full_name, password, role, program) VALUES
('RC24163', 'Muhammad Faiq al-Amin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Student', 'Diploma in Computer Science'),
('RC24306', 'Muhammad Afiq Azrein', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Student', 'Diploma in Computer Science'),
('RC24984', 'Muhammad Danish aiman bin abdullah', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Student', 'Diploma in Computer Science'),
('RC24308', 'Wan bani adam bin Aiman hakimi', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Student', 'Diploma in Computer Science'),
('CE24001', 'Amirul Hakim Razali',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Student', 'Bachelor of Computer Science (Software Engineering)'),
('CN24002', 'Nur Izzatie Mohd Yusof','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Student', 'Bachelor of Computer Science (Computer Networking)'),
('CC24003', 'Ahmad Firdaus Ismail',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Student', 'Bachelor of Computer Science (Cybersecurity)'),
('CM24004', 'Siti Nurhaliza Zakaria','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Student', 'Bachelor of Computer Science (Multimedia)'),
('CS24005', 'Fatin Aishah Hassan',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Student', 'Bachelor of Computer Science');

INSERT IGNORE INTO subjects (subject_code, subject_name, program, created_by) VALUES
('DRC1113','Programming Techniques','Diploma in Computer Science',1),
('DRC1133','Web Programming','Diploma in Computer Science',1),
('DRC1213','Object-Oriented Design & Implementation','Diploma in Computer Science',1),
('DRC2153','Mobile Application Development','Diploma in Computer Science',1),
('DRC2183','Web Application Development','Diploma in Computer Science',1),
('DRC2323','Human Computer Interaction','Diploma in Computer Science',1),
('DRC2353','Information Security','Diploma in Computer Science',1),
('BCI1023','Programming Techniques','Bachelor of Computer Science (Software Engineering)',1),
('BCI2023','Database Systems','Bachelor of Computer Science (Software Engineering)',1),
('BCI2323','Web Development','Bachelor of Computer Science (Software Engineering)',1),
('BCI3333','Machine Learning Applications','Bachelor of Computer Science (Software Engineering)',1),
('BCN2083','Computer Networks','Bachelor of Computer Science (Computer Networking)',1),
('BCN3243','Cloud Computing Technology','Bachelor of Computer Science (Computer Networking)',1),
('BCS1033','Software Engineering','Bachelor of Computer Science (Software Engineering)',1),
('BCS2143','Object Oriented Programming','Bachelor of Computer Science (Software Engineering)',1),
('BCS2323','Artificial Intelligence','Bachelor of Computer Science (Software Engineering)',1),
('BCM2133','Computer Graphics','Bachelor of Computer Science (Multimedia)',1),
('BCM3253','Data Analytics and Visualization','Bachelor of Computer Science (Multimedia)',1),
('BCY2043','Ethical Hacking','Bachelor of Computer Science (Cybersecurity)',1),
('BCY3093','Cryptography','Bachelor of Computer Science (Cybersecurity)',1);

INSERT IGNORE INTO bad_words (word, added_by) VALUES
('bodoh',1),('babi',1),('celaka',1),('sial',1),('gila',1),
('stupid',1),('bitch',1),('pukimak',1),('kontol',1),('lancau',1);