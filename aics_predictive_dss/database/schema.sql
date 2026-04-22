CREATE DATABASE aics_dss;

USE aics_dss;

CREATE TABLE users(
id INT AUTO_INCREMENT PRIMARY KEY,
username VARCHAR(50),
password VARCHAR(255),
role VARCHAR(20)
);


ALTER TABLE `aics_sample_data` 
CHANGE `COL 1` `request_date` VARCHAR(30),
CHANGE `COL 2` `medical_cause` VARCHAR(35),
CHANGE `COL 3` `assistance_type` VARCHAR(36);

 CREATE TABLE save_applicants (
	id INT AUTO_INCREMENT PRIMARY KEY,
	request_date DATE,
	first_name VARCHAR(100),
	last_name VARCHAR(100),
	medical_cause VARCHAR(255),
	assistance_type VARCHAR(255),
	status VARCHAR(50) DEFAULT 'Pending'
);

DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    password VARCHAR(50),
    role VARCHAR(20)
);

INSERT INTO users (username, password, role) VALUES ('admin', 'admin123', 'Admin');
INSERT INTO users (username, password, role) VALUES ('staff', 'staff123', 'Staff');


ALTER TABLE aics_sample_data ADD COLUMN barangay VARCHAR(100) AFTER medical_cause;

ALTER TABLE aics_sample_data 
ADD COLUMN id_number VARCHAR(50) AFTER id,
ADD COLUMN fname VARCHAR(100) AFTER status,
ADD COLUMN mname VARCHAR(100) AFTER fname,
ADD COLUMN lname VARCHAR(100) AFTER mname;
ADD COLUMN birth_date DATE AFTER lname;


CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL, -- e.g., 'UPDATE', 'DELETE'
    changed_column VARCHAR(100),      -- e.g., 'status'
    old_value TEXT,
    new_value TEXT,
    user_name VARCHAR(100) DEFAULT 'Admin', -- Placeholder for logged-in user
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

