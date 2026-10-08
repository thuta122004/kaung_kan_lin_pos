CREATE DATABASE IF NOT EXISTS kaungkanlin_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kaungkanlin_db;

CREATE TABLE IF NOT EXISTS products (
    id VARCHAR(20) PRIMARY KEY,
    name_mm VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    category ENUM('Small', 'Normal', 'Large') NOT NULL,
    original_price_mmk DECIMAL(10,2) NOT NULL,
    selling_price_mmk DECIMAL(10,2) NOT NULL,
    original_price_usd DECIMAL(10,2) NOT NULL,
    selling_price_usd DECIMAL(10,2) NOT NULL,
    burn_time_mm VARCHAR(50),
    burn_time_en VARCHAR(50),
    description_mm TEXT,
    description_en TEXT,
    image_path VARCHAR(255),
    is_bestseller TINYINT(1) DEFAULT 0,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(50) NOT NULL UNIQUE,
    preferred_channel ENUM('Viber', 'Telegram', 'In-Store', 'Website') DEFAULT 'In-Store',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id INT,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    channel ENUM('Viber', 'Telegram', 'In-Store', 'Website') NOT NULL,
    status ENUM('Pending', 'Completed', 'Cancelled') DEFAULT 'Pending',
    total_revenue_mmk DECIMAL(12,2) DEFAULT 0.00,
    total_cost_mmk DECIMAL(12,2) DEFAULT 0.00,
    net_profit_mmk DECIMAL(12,2) DEFAULT 0.00,
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_orders_date (order_date),
    INDEX idx_orders_status (status),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id VARCHAR(20) NOT NULL,
    quantity INT NOT NULL,
    original_price_mmk DECIMAL(10,2) NOT NULL,
    unit_price_mmk DECIMAL(10,2) NOT NULL,
    subtotal_revenue_mmk DECIMAL(12,2) NOT NULL,
    subtotal_cost_mmk DECIMAL(12,2) NOT NULL,
    subtotal_profit_mmk DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO products
(id, name_mm, name_en, category, original_price_mmk, selling_price_mmk, original_price_usd, selling_price_usd, burn_time_mm, burn_time_en, description_mm, description_en, image_path, is_bestseller) VALUES
('candle-01','ဖယောင်းတိုင် အဖြူ','White candle','Large',3600,3850,1.71,1.82,'၂ နာရီ','2 Hours','၆ တိုင်ပါ အကြီးထုပ်','6-piece Large set','images/6candlespack3600whitelarge.jpg',1),
('candle-02','ဖယောင်းတိုင် အဖြူ','White candle','Large',3200,3500,1.52,1.66,'၁ နာရီခွဲ','1.5 Hours','၁၀ တိုင်ပါ အကြီးထုပ်','10-piece Large set','images/10candlespack3200whitelarge.jpg',0),
('candle-03','ဖယောင်းတိုင် အဖြူ','White candle','Large',2700,3000,1.28,1.42,'၁ နာရီခွဲ','1.5 Hours','၈ တိုင်ပါ အကြီးထုပ်','8-piece Large set','images/8candlespack2700whitelarge.jpg',0),
('candle-04','ဖယောင်းတိုင် အဖြူ','White candle','Normal',2100,2550,1.00,1.21,'၁ နာရီ','1 Hour','၁၆ တိုင်ပါ ပုံမှန်ထုပ်','16-piece Normal set','images/16candlespack2100whitenormal.jpg',0),
('candle-05','ဖယောင်းတိုင် အနီ','Red candle','Normal',2180,2630,1.03,1.24,'၁ နာရီ','1 Hour','၁၆ တိုင်ပါ ပုံမှန်ထုပ်','16-piece Normal set','images/16candlespack2180rednormal.jpg',0),
('candle-06','ဖယောင်းတိုင် အဖြူ','White candle','Normal',2100,2550,1.00,1.21,'၃၀ မိနစ်','30 Mins','၃၂ တိုင်ပါ ပုံမှန်ထုပ်','32-piece Normal set','images/32candlespack2100whitenormal.jpg',1),
('candle-07','ဖယောင်းတိုင် အနီ','Red candle','Normal',2180,2630,1.03,1.24,'၃၀ မိနစ်','30 Mins','၃၂ တိုင်ပါ ပုံမှန်ထုပ်','32-piece Normal set','images/32candlespack2180rednormal.jpg',0),
('candle-08','ဖယောင်းတိုင် အဖြူ','White candle','Small',1650,2150,0.78,1.01,'၂၀ မိနစ်','20 Mins','၃၂ တိုင်ပါ အသေးထုပ်','32-piece Small set','images/32candlespack1650whitesmall.jpg',1),
('candle-09','ဖယောင်းတိုင် အနီ','Red candle','Small',1700,2280,0.80,1.07,'၂၀ မိနစ်','20 Mins','၃၂ တိုင်ပါ အသေးထုပ်','32-piece Small set','images/32candlespack1700redsmall.jpg',0),
('candle-10','ဖယောင်းတိုင် အဖြူ','White candle','Small',1030,1500,0.49,0.71,'၂၀ မိနစ်','20 Mins','၂၀ တိုင်ပါ အသေးထုပ်','20-piece Small set','images/20candlespack1030whitesmall.jpg',0);

INSERT IGNORE INTO products
(id, name_mm, name_en, category, original_price_mmk, selling_price_mmk, original_price_usd, selling_price_usd, burn_time_mm, burn_time_en, description_mm, description_en, image_path, is_bestseller) VALUES
('candle-11', 'ဖယောင်းတိုင် အစိမ်း', 'Green candle', 'Normal', 2280, 2730, 1.08, 1.29, '၁ နာရီ', '1 Hour', '၁၆ တိုင်ပါ ပုံမှန်ထုပ်', '16-piece Normal set', 'images/16candlespack2280greennormal.jpg', 0),
('candle-12', 'ဖယောင်းတိုင် အစိမ်း', 'Green candle', 'Normal', 2280, 2730, 1.08, 1.29, '၃၀ မိနစ်', '30 Mins', '၃၂ တိုင်ပါ ပုံမှန်ထုပ်', '32-piece Normal set', 'images/32candlespack2280greennormal.jpg', 0),
('candle-13', 'ဖယောင်းတိုင် အစိမ်း', 'Green candle', 'Small', 1800, 2300, 0.85, 1.08, '၂၀ မိနစ်', '20 Mins', '၃၂ တိုင်ပါ အသေးထုပ်', '32-piece small set', 'images/32candlespack1800greensmall.jpg', 0);

INSERT IGNORE INTO products
(id, name_mm, name_en, category, original_price_mmk, selling_price_mmk, original_price_usd, selling_price_usd, burn_time_mm, burn_time_en, description_mm, description_en, image_path, is_bestseller) VALUES
('candle-14', 'ဖယောင်းတိုင် အစိမ်း', 'Green candle', 'Small', 1130, 1600, 0.52, 0.74, '၂၀ မိနစ်', '20 Mins', '၂၀ တိုင်ပါ အသေးထုပ်', '20-piece small set', 'images/20candlespack1130greensmall.jpg', 0),
('candle-15', 'ဖယောင်းတိုင် အနီ', 'Red candle', 'Small', 1060, 1530, 0.50, 0.72, '၂၀ မိနစ်', '20 Mins', '၂၀ တိုင်ပါ အသေးထုပ်', '20-piece small set', 'images/20candlespack1060redsmall.jpg', 0);
