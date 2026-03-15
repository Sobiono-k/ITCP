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
