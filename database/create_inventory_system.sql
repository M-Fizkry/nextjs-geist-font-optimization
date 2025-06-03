-- Create database
CREATE DATABASE IF NOT EXISTS inventory_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventory_system;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- User permissions table
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    menu_id VARCHAR(50) NOT NULL,
    can_access TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Materials table
CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    type ENUM('raw', 'semi', 'finished') NOT NULL,
    min_stock DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_stock DECIMAL(10,2) NOT NULL DEFAULT 0,
    current_stock DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Bill of Materials (BOM) table
CREATE TABLE IF NOT EXISTS bom (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    material_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES materials(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Production plans table
CREATE TABLE IF NOT EXISTS production_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    plan_date DATE NOT NULL,
    plan_type INT NOT NULL,
    plan_code VARCHAR(20) DEFAULT NULL,
    status ENUM('pending', 'completed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES materials(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Stock movements table
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    type ENUM('in', 'out') NOT NULL,
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
