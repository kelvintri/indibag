CREATE DATABASE IF NOT EXISTS bananina;
USE bananina;

-- Users table (simplified)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    phone VARCHAR(20),
    avatar_url VARCHAR(255) DEFAULT NULL,
    avatar_updated_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email_password (email, password),
    INDEX idx_username (username)
);

-- Addresses table (modified for Indonesian addresses)
CREATE TABLE addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    address_type ENUM('shipping', 'billing') DEFAULT 'shipping',
    is_default BOOLEAN DEFAULT FALSE,
    recipient_name VARCHAR(100),
    street_address TEXT NOT NULL,
    district VARCHAR(100) NOT NULL, -- Kecamatan
    city VARCHAR(100) NOT NULL, -- Kota/Kabupaten
    province VARCHAR(100) NOT NULL, -- Provinsi
    postal_code VARCHAR(5) NOT NULL, -- Kode Pos (Indonesia uses 5 digits)
    phone VARCHAR(15), -- Indonesian phone numbers
    additional_info TEXT, -- For specific landmarks or delivery notes
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_address (user_id, address_type)
);

-- Categories table
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Brands table (new)
CREATE TABLE brands (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    logo_url VARCHAR(255),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Products table (modified to include details)
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT,
    brand_id INT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    details TEXT,
    meta_title VARCHAR(100),
    meta_description VARCHAR(255),
    price DECIMAL(10,2) NOT NULL,
    sale_price DECIMAL(10,2),
    stock INT NOT NULL DEFAULT 0,
    sku VARCHAR(100) UNIQUE,
    condition_status VARCHAR(50) DEFAULT 'New With Tag',
    is_active BOOLEAN DEFAULT TRUE,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (brand_id) REFERENCES brands(id),
    INDEX idx_brand (brand_id),
    INDEX idx_category (category_id),
    INDEX idx_price (price),
    INDEX idx_stock (stock),
    INDEX idx_active_deleted (is_active, deleted_at)
);

-- Product Galleries table
CREATE TABLE product_galleries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_product_gallery (product_id, is_primary)
);

-- Orders table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    shipping_address_id INT,
    billing_address_id INT,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM(
        'pending_payment',        -- Initial state when order is placed
        'payment_uploaded',       -- Customer has uploaded payment proof
        'payment_verified',       -- Admin verified the payment
        'processing',            -- Order is being processed
        'shipped',               -- Order has been shipped
        'delivered',             -- Order has been delivered
        'cancelled',             -- Order was cancelled
        'refunded'               -- Order was refunded
    ) DEFAULT 'pending_payment',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (shipping_address_id) REFERENCES addresses(id),
    FOREIGN KEY (billing_address_id) REFERENCES addresses(id)
);

-- Order Items table
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT,
    product_id INT,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Cart table (for temporary storage)
CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    product_id INT,
    quantity INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Payment Details table (simplified)
CREATE TABLE payment_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    payment_method ENUM('bank_transfer', 'e-wallet') NOT NULL,
    transfer_proof_url VARCHAR(255),
    payment_amount DECIMAL(10,2) NOT NULL,
    payment_date DATETIME,
    verified_by INT, -- reference to admin user who verified the payment
    verified_at DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
);

-- Shipping Details table (new)
CREATE TABLE shipping_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    courier_name VARCHAR(50) NOT NULL, -- e.g., JNE, J&T, SiCepat
    service_type VARCHAR(50), -- e.g., REG, YES, OKE
    tracking_number VARCHAR(100),
    shipping_cost DECIMAL(10,2) NOT NULL,
    estimated_delivery_date DATE,
    shipped_at DATETIME,
    shipped_by INT, -- reference to admin user who processed the shipment
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (shipped_by) REFERENCES users(id)
);

-- Order Status History table (new - for tracking status changes)
CREATE TABLE order_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    notes TEXT,
    changed_by INT NOT NULL, -- reference to user who changed the status
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (changed_by) REFERENCES users(id)
);

-- Roles table (new)
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Roles table (new - for many-to-many relationship)
CREATE TABLE user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- Insert default roles
INSERT INTO roles (name, description) VALUES
('admin', 'Administrator with full access'),
('customer', 'Regular customer user');

-- Sample User Data
INSERT INTO users (username, email, password, full_name, phone, is_active) VALUES
('admin_user', 'admin@bananina.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', '+6281234567890', true),
('john_doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', '+6281234567891', true);

-- Sample User Roles Data
INSERT INTO user_roles (user_id, role_id) VALUES
(1, 1), -- admin_user as admin
(2, 2); -- john_doe as customer

-- Sample Brands Data
INSERT INTO brands (name, slug, description) VALUES
('FOSSIL', 'fossil', 'American watch and lifestyle company'),
('KATE SPADE', 'kate-spade', 'American luxury fashion design house'),
('MICHAEL KORS', 'michael-kors', 'American luxury fashion company');

-- Sample Categories Data
INSERT INTO categories (name, slug, description) VALUES
('Backpacks', 'backpacks', 'All types of backpacks'),
('Small Backpacks', 'small-backpacks', 'Compact and small-sized backpacks'),
('Medium Backpacks', 'medium-backpacks', 'Medium-sized everyday backpacks');

-- Sample Products Data with details
INSERT INTO products (category_id, brand_id, name, slug, description, details, meta_title, meta_description, price, stock, sku, condition_status, is_active) VALUES
(1, 1, 'Megan Small Backpack Misty Blue', 'shb3088421-megan-small-backpack-misty-blue', 
'Made of pebbled leather with zip closure and polyester lining',
'Made in Cambodia | Style number SHB3088421 | Old brass english tone hardware | Printed  pvc and polyurethane trim | Zip closure | Polyester lining | Interior 1 zip pocket | Interior 2 slip pockets | Exterior 1 zip pocket at front', 
'Megan Small Backpack Misty Blue | Fossil Bags', 
'Shop Fossil Megan Small Backpack in Misty Blue. Made of premium pebbled leather with zip closure and polyester lining.',
1850000, 10, 'shb3088421-megan-small-backpack-misty-blue-121692', 'New With Tag', true),

(1, 2, 'Chelsea Medium Backpack Deep Cornflower', 'chelsea-medium-backpack-deep-cornflower', 
'Made of nylon. The bag features a zip closure that opens to polyester lining with three slip pockets, one zip pocket, three card slots, exterior one zip pocket at front, two side pockets, adjustable strap.', 
'Made In Philippines | Style Number : WKR00556 | Zip Closure | Gold Tone Hardware | Polyester Lining | 3 Three Slip Pockets | 3 Card Slots | 1 Zip Pocket | 1 Zip Pocket at Front | 2 Side Pockets | Adjustable Strap | Metal Pinmount Logo',
'Chelsea Medium Backpack Deep Cornflower | Kate Spade',
'Shop Kate Spade Chelsea Medium Backpack in Deep Cornflower. Features multiple pockets and adjustable straps for comfort.',
2500000, 8, 'chelsea-medium-backpack-deep-cornflower-130152', 'New With Tag', true),

(1, 3, 'Signature Maisie Xs 2 In 1 Backpack Brown', 'signature-maisie-xs-2-in-1-backpack-brown', 
'Made of PVC and leather. The bag features a zip closure that opens to polyester lining with two slip pockets, one zip pocket, exterior one zip pocket, one slip pocket, removable Pochette.', 
'Made In Indonesia | Serial Number : 35F3G5MB0B | Gold Tone Hardware | Zip Closure | Polyester Lining | Two Slip Pockets | One Zip Pocket | One Zip Pocket at Front | One Slip Pocket at Front | Removable Pochette | Adjustable Strap',
'Signature Maisie XS 2-in-1 Backpack Brown | Michael Kors',
'Shop Michael Kors Signature Maisie XS 2-in-1 Backpack in Brown. Versatile design with removable pochette.',
4250000, 5, 'signature-maisie-xs-2-in-1-backpack-brown-129772', 'New With Tag', true),

(1, 3, 'Valerie Signature Medium Backpack Brown Acorn', 'valerie-signature-medium-backpack-brown-acorn', 
'Made of coated canvas and leather. The bag features a zip closure that opens to polyester lining with two slip pockets, one zip pocket, and exterior one zip pocket at front.', 
'Made in Indonesia | Serial Number 30F2G9VB2B | Gold-tone Hardware | Polyester Lining | One Zip Pocket | Two Slip Pockets | Exterior One Zip Pocket At Front | Michael Kors Metal Logo',
'Valerie Signature Medium Backpack Brown Acorn | Michael Kors',
'Shop Michael Kors Valerie Signature Medium Backpack in Brown Acorn. Classic design with premium materials.',
4000000, 5, 'valerie-signature-medium-backpack-brown-acorn', 'New With Tag', true),

(1, 2, 'Madison Saffiano Flap Backpack Parisian Navy', 'madison-saffiano-flap-backpack-parisian-navy', 
'Made of saffiano leather with logo polyester lining. The backpack features a button magnetic flap closure, silver tone hardware, 1 zip pocket and 1 slip pocket.', 
'Made in Philippines | Style Number : KC428 | Silver Tone Hardware | Magnetic Snap Closure | Polyester Lining | One Slip Pocket | One Zip Pocket | Adjustable Strap | Metal Pinmount Logo',
'Madison Saffiano Flap Backpack Parisian Navy | Kate Spade',
'Shop Kate Spade Madison Saffiano Flap Backpack in Parisian Navy. Elegant design with premium saffiano leather.',
2775000, 8, 'madison-saffiano-flap-backpack-parisian-navy', 'New With Tag', true),

(1, 1, 'SHB3088844 Megan Small Backpack Berry Stripe', 'shb3088844-megan-small-backpack-berry-stripe', 
'Made of printed pvc. The backpack features a zip closure that opens to polyester lining with interior one zip pocket, two slip pockets and exterior one zip pocket at front.', 
'Made in Cambodia | Style Number SHB3088844 | Old-Brass Tone Hardware | Zip Closure | Polyester Lining | 1 Zip Pocket | 2 Slip Pockets | 1 Zip Pocket at Front | Adjustable Strap',
'Megan Small Backpack Berry Stripe | Fossil Bags',
'Shop Fossil Megan Small Backpack in Berry Stripe. Stylish printed PVC design with multiple pockets.',
1950000, 10, 'shb3088844-megan-small-backpack-berry-stripe-128548', 'New With Tag', true);

-- Sample Product Galleries Data
INSERT INTO product_galleries (product_id, image_url, is_primary, sort_order) VALUES
(1, 'images/backpacks/primary/SHB3088421 Megan Small Backpack Misty Blue_primary.jpg', TRUE, 1),
(2, 'images/backpacks/primary/Chelsea Medium Backpack Deep Cornflower_primary.jpg', TRUE, 1),
(2, 'images/backpacks/hover/Chelsea Medium Backpack Deep Cornflower_hover.jpg', FALSE, 2),
(3, 'images/backpacks/primary/Signature Maisie Xs 2 In 1 Backpack Brown_primary.jpg', TRUE, 1),
(3, 'images/backpacks/hover/Signature Maisie Xs 2 In 1 Backpack Brown_hover.jpg', FALSE, 2),
(4, 'images/backpacks/primary/Valerie Signature Medium Backpack Brown Acorn_primary.jpg', TRUE, 1),
(4, 'images/backpacks/hover/Valerie Signature Medium Backpack Brown Acorn_hover.jpg', FALSE, 2),
(5, 'images/backpacks/primary/Madison Saffiano Flap Backpack Parisian Navy_primary.jpg', TRUE, 1),
(5, 'images/backpacks/hover/Madison Saffiano Flap Backpack Parisian Navy_hover.jpg', FALSE, 2),
(6, 'images/backpacks/primary/SHB3088844 Megan Small Backpack Berry Stripe_primary.jpg', TRUE, 1),
(6, 'images/backpacks/hover/SHB3088844 Megan Small Backpack Berry Stripe_hover.jpg', FALSE, 2);

-- Wishlist table (new)
CREATE TABLE wishlist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    UNIQUE KEY unique_wishlist_item (user_id, product_id)
);

-- Update the image paths in product_galleries table
UPDATE product_galleries 
SET image_url = CONCAT('/assets/images', SUBSTRING(image_url, LOCATE('images/', image_url) + 6))
WHERE image_url LIKE '%images/%';