-- Food Rescue Hub — Database Schema
-- Run this entire script in phpMyAdmin's SQL tab (or MySQL CLI) to set up.

CREATE DATABASE IF NOT EXISTS food_rescue_hub;
USE food_rescue_hub;

-- ---------------------------------------------------------------
-- USERS — donors, ngos, volunteers, admins
-- Donors are auto-verified on signup. NGOs and Volunteers need
-- an admin to verify them before they can log in.
-- ---------------------------------------------------------------
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('donor','ngo','volunteer','admin') NOT NULL,
    phone VARCHAR(15),
    address VARCHAR(255),
    is_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------
-- DONATIONS — posted by donors
-- status moves: available -> requested -> approved -> in_transit -> completed
--                                      \-> rejected   (back to available)
-- ---------------------------------------------------------------
CREATE TABLE donations (
    donation_id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    food_name VARCHAR(100) NOT NULL,
    description TEXT,
    quantity VARCHAR(50),
    food_type ENUM('veg','non-veg','packaged','cooked') DEFAULT 'cooked',
    pickup_address VARCHAR(255) NOT NULL,
    expiry_time DATETIME NOT NULL,
    status ENUM('available','requested','approved','in_transit','completed','rejected','expired') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES users(user_id)
);

-- ---------------------------------------------------------------
-- REQUESTS — an NGO requesting a specific donation
-- Admin approves or rejects each request in manage_donations.php
-- ---------------------------------------------------------------
CREATE TABLE requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    donation_id INT NOT NULL,
    ngo_id INT NOT NULL,
    request_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    decision_time TIMESTAMP NULL,
    FOREIGN KEY (donation_id) REFERENCES donations(donation_id),
    FOREIGN KEY (ngo_id) REFERENCES users(user_id)
);

-- ---------------------------------------------------------------
-- DELIVERIES — created automatically when admin approves a request.
-- Sits "open" until a volunteer accepts it, then tracks pickup/delivery.
-- ---------------------------------------------------------------
CREATE TABLE deliveries (
    delivery_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL UNIQUE,
    volunteer_id INT NULL,
    status ENUM('open','assigned','picked_up','delivered') DEFAULT 'open',
    assigned_time TIMESTAMP NULL,
    picked_up_time TIMESTAMP NULL,
    delivered_time TIMESTAMP NULL,
    FOREIGN KEY (request_id) REFERENCES requests(request_id),
    FOREIGN KEY (volunteer_id) REFERENCES users(user_id)
);

-- NOTE: registration only allows 'donor', 'ngo', or 'volunteer' (by design,
-- so the public can't create admin accounts). To create your first admin,
-- visit create_admin.php once after setup, then delete that file.
