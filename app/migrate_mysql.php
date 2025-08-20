<?php
// app/migrate_mysql.php
require_once __DIR__ . '/db.php';

function migrate(){
  $db = get_db();
  $db->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','manager','staff') NOT NULL DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $db->exec("CREATE TABLE IF NOT EXISTS children (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    dob DATE NOT NULL,
    gender VARCHAR(20),
    admission_date DATE,
    guardian_name VARCHAR(160),
    guardian_contact VARCHAR(120),
    social_worker VARCHAR(120),
    local_authority VARCHAR(120),
    placement_type VARCHAR(120),
    care_plan TEXT,
    medical_notes TEXT,
    risk_flags VARCHAR(255) DEFAULT NULL,
    gp_name VARCHAR(160) DEFAULT NULL,
    gp_phone VARCHAR(60) DEFAULT NULL,
    nhs_number VARCHAR(40) DEFAULT NULL,
    sen_status VARCHAR(60) DEFAULT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (last_name, first_name)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $db->exec("CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(190) UNIQUE,
    phone VARCHAR(60),
    role VARCHAR(80),
    qualifications TEXT,
    dbs_check_date DATE,
    training_completed TEXT,
    start_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $db->exec("CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present','absent','excused','offsite') NOT NULL,
    notes VARCHAR(255),
    UNIQUE KEY uniq_child_date(child_id, date),
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $db->exec("CREATE TABLE IF NOT EXISTS incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NOT NULL,
    date_time DATETIME NOT NULL,
    type VARCHAR(120) NOT NULL,
    severity ENUM('low','med','high') DEFAULT NULL,
    status ENUM('open','review','closed') DEFAULT 'open',
    location VARCHAR(120) DEFAULT NULL,
    injury VARCHAR(120) DEFAULT NULL,
    restraint_used TINYINT(1) DEFAULT 0,
    description TEXT,
    action_taken TEXT,
    reported_to VARCHAR(160),
    staff_id INT NULL,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX (date_time), INDEX (type)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $db->exec("CREATE TABLE IF NOT EXISTS medications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NOT NULL,
    med_name VARCHAR(160) NOT NULL,
    dose VARCHAR(120),
    schedule VARCHAR(120),
    administered_at DATETIME,
    administered_by VARCHAR(160),
    notes TEXT,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE,
    INDEX (administered_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $db->exec("CREATE TABLE IF NOT EXISTS visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NOT NULL,
    visitor_name VARCHAR(160) NOT NULL,
    relationship VARCHAR(120),
    visit_date DATE NOT NULL,
    id_checked TINYINT(1) DEFAULT 0,
    notes VARCHAR(255),
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $db->exec("CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NULL,
    date_received DATE NOT NULL,
    complainant_name VARCHAR(160),
    details TEXT,
    outcome VARCHAR(160),
    closed_date DATE NULL,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $db->exec("CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NULL,
    title VARCHAR(160) NOT NULL,
    category VARCHAR(120),
    file_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // Seed
  if ((int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn() === 0) {
    $st = $db->prepare("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)");
    $st->execute(['Admin User','admin@example.com', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
  }
  if ((int)$db->query("SELECT COUNT(*) FROM children")->fetchColumn() === 0) {
    $db->exec("INSERT INTO children (first_name,last_name,dob,gender,admission_date,guardian_name,guardian_contact,social_worker,local_authority,placement_type,care_plan,medical_notes,risk_flags,sen_status)
      VALUES
      ('Ava','Johnson','2014-05-12','F','2024-09-01','Mary Johnson','07123 456789','J. Smith','Buckinghamshire','Short-term','Focus on school attendance and confidence building','Asthma - inhaler PRN','allergy','SEN Support'),
      ('Leo','Brown','2012-11-03','M','2023-03-18','Paul Brown','07987 654321','K. Patel','Oxfordshire','Long-term','Weekly counselling and sports activities','Nut allergy - EpiPen','allergy,behaviour','EHC Plan');");
  }
  if ((int)$db->query("SELECT COUNT(*) FROM staff")->fetchColumn() === 0) {
    $db->exec("INSERT INTO staff (name,email,phone,role,qualifications,dbs_check_date,training_completed,start_date) VALUES
      ('Karissa Prince','karissa@example.com','07700 112233','Key Worker','Level 3 Diploma; First Aid','2024-01-10','Safeguarding L2; Medication','2023-08-01'),
      ('Del Brown','del@example.com','07700 445566','Manager','Leadership L5; DSL','2023-12-15','DSL; Safer Recruitment','2022-04-15');");
  }
}
migrate();


// v4: add is_active if missing and ensure created_at exists
try { get_db()->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1"); } catch (Throwable $e) { }
try { get_db()->exec("ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (Throwable $e) { }


try { get_db()->exec("ALTER TABLE users MODIFY password_hash VARCHAR(255) NOT NULL"); } catch (Throwable $e) { }
