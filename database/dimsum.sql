-- ============================================================
--  DIMSUM APP - DATABASE SCHEMA & SEED DATA
--  Import file ini ke phpMyAdmin atau jalankan via MySQL CLI:
--  mysql -u root -p < dimsum.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS dimsum_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dimsum_app;

-- ============================================================
-- 1. USERS (semua role: customer, employee, owner)
-- ============================================================
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,  -- bcrypt hash
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(100),
    phone       VARCHAR(20),
    role        ENUM('customer','employee','owner') NOT NULL DEFAULT 'customer',
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- 2. CUSTOMER ADDRESSES
-- ============================================================
CREATE TABLE addresses (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    label       VARCHAR(50) DEFAULT 'Rumah',
    address     TEXT NOT NULL,
    city        VARCHAR(100),
    is_default  TINYINT(1) DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- 3. CATEGORIES
-- ============================================================
CREATE TABLE categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    type        ENUM('matang','frozen') NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 4. PRODUCTS
-- ============================================================
CREATE TABLE products (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name        VARCHAR(150) NOT NULL,
    description TEXT,
    price       DECIMAL(10,2) NOT NULL,
    stock       INT NOT NULL DEFAULT 0,
    min_stock   INT NOT NULL DEFAULT 10,
    unit        VARCHAR(20)  DEFAULT 'pcs',
    emoji       VARCHAR(10)  DEFAULT '🥟',
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- ============================================================
-- 5. RAW MATERIALS (Bahan Baku)
-- ============================================================
CREATE TABLE raw_materials (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    unit        VARCHAR(20)  NOT NULL DEFAULT 'kg',
    stock       DECIMAL(10,2) NOT NULL DEFAULT 0,
    min_stock   DECIMAL(10,2) NOT NULL DEFAULT 5,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- 6. SUPPLIERS
-- ============================================================
CREATE TABLE suppliers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    product     VARCHAR(200),
    contact     VARCHAR(50),
    email       VARCHAR(100),
    address     TEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 7. ORDERS
-- ============================================================
CREATE TABLE orders (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    order_code      VARCHAR(20)  NOT NULL UNIQUE,
    customer_id     INT NOT NULL,
    address_id      INT,
    delivery_method ENUM('delivery','pickup') DEFAULT 'delivery',
    payment_method  ENUM('qris','cash') DEFAULT 'cash',
    payment_status  ENUM('pending','paid','failed') DEFAULT 'pending',
    status          ENUM('pending','processing','production','shipping','delivered','cancelled') DEFAULT 'pending',
    subtotal        DECIMAL(10,2) DEFAULT 0,
    delivery_fee    DECIMAL(10,2) DEFAULT 0,
    total           DECIMAL(10,2) DEFAULT 0,
    notes           TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (address_id)  REFERENCES addresses(id) ON DELETE SET NULL
);

-- ============================================================
-- 8. ORDER ITEMS
-- ============================================================
CREATE TABLE order_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    order_id    INT NOT NULL,
    product_id  INT NOT NULL,
    qty         INT NOT NULL DEFAULT 1,
    price       DECIMAL(10,2) NOT NULL,
    subtotal    DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ============================================================
-- 9. PRODUCTION (tugas produksi karyawan)
-- ============================================================
CREATE TABLE production_orders (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    prod_code   VARCHAR(20)  NOT NULL UNIQUE,
    order_id    INT,
    employee_id INT,
    status      ENUM('pending','processing','completed') DEFAULT 'pending',
    priority    ENUM('high','medium','low') DEFAULT 'medium',
    progress    INT DEFAULT 0,
    deadline    DATETIME,
    started_at  DATETIME,
    finished_at DATETIME,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)    REFERENCES orders(id)   ON DELETE SET NULL,
    FOREIGN KEY (employee_id) REFERENCES users(id)    ON DELETE SET NULL
);

-- ============================================================
-- 10. PRODUCTION ITEMS (item dalam satu produksi)
-- ============================================================
CREATE TABLE production_items (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    production_order_id INT NOT NULL,
    product_id          INT NOT NULL,
    qty                 INT NOT NULL,
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id)          REFERENCES products(id)
);

-- ============================================================
-- 11. DELIVERIES
-- ============================================================
CREATE TABLE deliveries (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    del_code        VARCHAR(20)  NOT NULL UNIQUE,
    order_id        INT NOT NULL,
    employee_id     INT,
    status          ENUM('ready','shipping','delivered') DEFAULT 'ready',
    distance_km     DECIMAL(5,2),
    estimated_time  VARCHAR(50),
    departure_time  DATETIME,
    delivery_time   DATETIME,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)    REFERENCES orders(id)  ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(id)   ON DELETE SET NULL
);

-- ============================================================
-- 12. STOCK LOG (riwayat perubahan stok)
-- ============================================================
CREATE TABLE stock_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    type        ENUM('product','material') NOT NULL,
    item_id     INT NOT NULL,
    change      INT NOT NULL,   -- positif = tambah, negatif = kurang
    reason      VARCHAR(200),
    user_id     INT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- ============================================================
--  SEED DATA
-- ============================================================
-- ============================================================

-- USERS
-- Password untuk semua: lihat komentar, hash bcrypt cost=10
-- customer123  => $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- employee123  => $2y$10$TKh8H1.PFbuSpgzz5l9WA.rOBvqOiQFvixXlE1LQUkIaLpKFpD9mO  (sesuaikan)
-- owner123     => sama saja pakai password_hash() saat install

INSERT INTO users (username, password, name, email, phone, role) VALUES
('customer1',  '$2y$10$YKqhTRLrJvLR3g.kzEiDbe9DCFBz4eMpB5wKbFB3oFW2t7aNLxj2i', 'Budi Santoso',    'budi@email.com',  '08123456789', 'customer'),
('customer2',  '$2y$10$YKqhTRLrJvLR3g.kzEiDbe9DCFBz4eMpB5wKbFB3oFW2t7aNLxj2i', 'Siti Rahayu',     'siti@email.com',  '08198765432', 'customer'),
('customer3',  '$2y$10$YKqhTRLrJvLR3g.kzEiDbe9DCFBz4eMpB5wKbFB3oFW2t7aNLxj2i', 'Ahmad Hidayat',   'ahmad@email.com', '08156781234', 'customer'),
('customer4',  '$2y$10$YKqhTRLrJvLR3g.kzEiDbe9DCFBz4eMpB5wKbFB3oFW2t7aNLxj2i', 'Rina Wijaya',     'rina@email.com',  '08134567890', 'customer'),
('employee1',  '$2y$10$YKqhTRLrJvLR3g.kzEiDbe9DCFBz4eMpB5wKbFB3oFW2t7aNLxj2i', 'Joko Widodo',     'joko@dimsum.com', '08123456789', 'employee'),
('employee2',  '$2y$10$YKqhTRLrJvLR3g.kzEiDbe9DCFBz4eMpB5wKbFB3oFW2t7aNLxj2i', 'Siti Nurhaliza',  'snur@dimsum.com', '08198765432', 'employee'),
('employee3',  '$2y$10$YKqhTRLrJvLR3g.kzEiDbe9DCFBz4eMpB5wKbFB3oFW2t7aNLxj2i', 'Budi Setiawan',   'bset@dimsum.com', '08156781234', 'employee'),
('employee4',  '$2y$10$YKqhTRLrJvLR3g.kzEiDbe9DCFBz4eMpB5wKbFB3oFW2t7aNLxj2i', 'Ani Susanti',     'anis@dimsum.com', '08134567890', 'employee'),
('employee5',  '$2y$10$YKqhTRLrJvLR3g.kzEiDbe9DCFBz4eMpB5wKbFB3oFW2t7aNLxj2i', 'Dedi Kurniawan',  'dedi@dimsum.com', '08176543210', 'employee'),
('owner1',     '$2y$10$YKqhTRLrJvLR3g.kzEiDbe9DCFBz4eMpB5wKbFB3oFW2t7aNLxj2i', 'Pak Owner',       'owner@dimsum.com','08111111111', 'owner');

-- NOTE: hash di atas adalah untuk password 'demo123' (universal untuk testing)
-- Untuk ganti password, jalankan: php -r "echo password_hash('passwordbaru', PASSWORD_BCRYPT);"

-- ADDRESSES
INSERT INTO addresses (user_id, label, address, city, is_default) VALUES
(1, 'Rumah',  'Jl. Raya Dimsum No. 123', 'Jakarta Selatan', 1),
(2, 'Rumah',  'Jl. Merdeka No. 45',      'Jakarta Pusat',   1),
(3, 'Rumah',  'Jl. Kebon Jeruk No. 78',  'Jakarta Barat',   1),
(4, 'Rumah',  'Jl. Sudirman No. 234',    'Jakarta Selatan', 1);

-- CATEGORIES
INSERT INTO categories (name, type) VALUES
('Dimsum Matang', 'matang'),
('Dimsum Frozen', 'frozen');

-- PRODUCTS
INSERT INTO products (category_id, name, description, price, stock, min_stock, unit, emoji) VALUES
(1, 'Dimsum Ayam',               'Dimsum ayam kukus lembut isi 5 pcs',     25000, 150, 50,  'pcs',  '🥟'),
(1, 'Dimsum Udang',              'Dimsum udang segar isi 5 pcs',           30000, 30,  50,  'pcs',  '🦐'),
(1, 'Dimsum Sayur',              'Dimsum sayuran sehat isi 5 pcs',         20000, 200, 50,  'pcs',  '🥬'),
(1, 'Dimsum Kepiting',           'Dimsum kepiting premium isi 5 pcs',      35000, 40,  30,  'pcs',  '🦀'),
(2, 'Frozen Pack Ayam (10pcs)',  'Frozen pack ayam untuk dimasak sendiri', 45000, 60,  20,  'pack', '❄️'),
(2, 'Frozen Pack Udang (10pcs)', 'Frozen pack udang segar',                55000, 30,  20,  'pack', '❄️'),
(2, 'Frozen Pack Mix (10pcs)',   'Frozen pack campuran isi 10 pcs',        50000, 40,  20,  'pack', '❄️');

-- RAW MATERIALS
INSERT INTO raw_materials (name, unit, stock, min_stock) VALUES
('Tepung Terigu',     'kg',   15, 20),
('Daging Ayam',       'kg',   25, 10),
('Udang',             'kg',   8,  15),
('Sayuran',           'kg',   30, 10),
('Bumbu Pelengkap',   'pack', 5,  8),
('Kepiting',          'kg',   6,  5),
('Kulit Dimsum',      'pack', 20, 10);

-- SUPPLIERS
INSERT INTO suppliers (name, product, contact, email) VALUES
('PT Tepung Jaya',   'Tepung Terigu',       '021-12345678', 'tepungjaya@email.com'),
('CV Daging Segar',  'Daging Ayam & Udang', '021-87654321', 'dagingsegar@email.com'),
('Toko Sayur Segar', 'Sayuran',             '08123456789',  'sayursegar@email.com');

-- ORDERS (sample)
INSERT INTO orders (order_code, customer_id, address_id, delivery_method, payment_method, payment_status, status, subtotal, delivery_fee, total) VALUES
('ORD-1230', 1, 1, 'delivery', 'cash',  'paid',    'cancelled',  75000,  15000, 90000),
('ORD-1231', 4, 4, 'delivery', 'qris',  'pending', 'pending',    60000,  15000, 75000),
('ORD-1232', 3, 3, 'delivery', 'cash',  'paid',    'processing', 130000, 15000, 145000),
('ORD-1233', 2, 2, 'delivery', 'qris',  'paid',    'shipping',   65000,  15000, 80000),
('ORD-1234', 1, 1, 'delivery', 'cash',  'paid',    'delivered',  80000,  15000, 95000);

-- ORDER ITEMS
INSERT INTO order_items (order_id, product_id, qty, price, subtotal) VALUES
(1, 2, 2, 30000, 60000), (1, 3, 1, 20000, 20000),  -- ORD-1230 (cancelled, jadi bisa isi apapun)
(2, 5, 1, 45000, 45000),                            -- ORD-1231 Frozen Pack Ayam
(3, 3, 3, 20000, 60000), (3, 4, 2, 35000, 70000),  -- ORD-1232
(4, 7, 1, 50000, 50000),                            -- ORD-1233 Frozen Pack Mix
(5, 1, 2, 25000, 50000), (5, 2, 1, 30000, 30000);  -- ORD-1234

-- PRODUCTION ORDERS
INSERT INTO production_orders (prod_code, order_id, employee_id, status, priority, progress, deadline) VALUES
('PROD-001', 3, 5, 'pending',    'high',   0,  '2024-12-11 14:00:00'),
('PROD-002', 4, 5, 'processing', 'medium', 60, '2024-12-11 16:00:00'),
('PROD-003', 5, 7, 'completed',  'low',    100,'2024-12-11 12:00:00'),
('PROD-004', 2, 5, 'pending',    'medium', 0,  '2024-12-11 18:00:00');

-- PRODUCTION ITEMS
INSERT INTO production_items (production_order_id, product_id, qty) VALUES
(1, 1, 20), (1, 2, 15),
(2, 7, 5),
(3, 3, 30),
(4, 4, 10), (4, 1, 10);

-- DELIVERIES
INSERT INTO deliveries (del_code, order_id, employee_id, status, distance_km, estimated_time, departure_time, delivery_time) VALUES
('DEL-001', 3, 7, 'ready',     3.5, '30 menit', NULL,                        NULL),
('DEL-002', 4, 7, 'shipping',  5.2, '20 menit', '2024-12-10 13:30:00',       NULL),
('DEL-003', 5, 7, 'delivered', 4.1, '25 menit', '2024-12-10 12:20:00',       '2024-12-10 12:45:00'),
('DEL-004', 2, 7, 'ready',     2.8, '25 menit', NULL,                        NULL);

-- ============================================================
-- 13. TRANSACTIONS (PB-07: Pencatatan transaksi otomatis)
-- ============================================================
CREATE TABLE IF NOT EXISTS transactions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    order_id        INT NOT NULL,
    transaction_code VARCHAR(30) NOT NULL UNIQUE,
    amount          DECIMAL(10,2) NOT NULL,
    payment_method  ENUM('qris','cash') NOT NULL,
    payment_status  ENUM('pending','paid','failed') DEFAULT 'pending',
    notes           TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
