-- Fake data for Clothes_Shop_Web
-- Import with: mysql -u user -p clothes_shop < database/fake_data.sql

SET FOREIGN_KEY_CHECKS=0;

-- Users
INSERT INTO `users` (id, email, name, phone, password, avatar, role, status, created_at, updated_at) VALUES
(1, 'alice@example.com', 'Alice Nguyen', '+84900000001', NULL, NULL, 'ROLE_CUSTOMER', 'ACTIVE', NOW(), NOW())
ON DUPLICATE KEY UPDATE email=VALUES(email);

-- Categories
INSERT INTO `categories` (id, name, parent_id, created_at, updated_at) VALUES
(1, 'T-Shirts', NULL, NOW(), NOW()),
(2, 'Hoodies', NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Products
INSERT INTO `products` (id, name, description, price, discount_price, image, created_at, updated_at) VALUES
(1, 'Basic White T-Shirt', 'Comfortable cotton tee', 199000.00, NULL, NULL, NOW(), NOW()),
(2, 'Gray Hoodie', 'Warm hoodie with logo', 499000.00, 429000.00, NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- category_product pivot
INSERT IGNORE INTO `category_product` (product_id, category_id) VALUES
(1,1),(2,2);

-- Product variants
INSERT INTO `product_variants` (id, sku, price, discount_price, stock, image, product_id, created_at, updated_at) VALUES
(1, 'TSHIRT-WHT-S', 199000.00, NULL, 50, NULL, 1, NOW(), NOW()),
(2, 'HOODIE-GRY-M', 499000.00, 429000.00, 20, NULL, 2, NOW(), NOW())
ON DUPLICATE KEY UPDATE sku=VALUES(sku);

-- Attribute types and values
INSERT INTO `attribute_types` (id, name, display_name, created_at, updated_at) VALUES
(1, 'color', 'Color', NOW(), NOW()),
(2, 'size', 'Size', NOW(), NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO `attribute_values` (id, value, display_value, meta_data, attribute_type_id, created_at, updated_at) VALUES
(1, 'white', 'White', NULL, 1, NOW(), NOW()),
(2, 'gray', 'Gray', NULL, 1, NOW(), NOW()),
(3, 'S', 'Small', NULL, 2, NOW(), NOW()),
(4, 'M', 'Medium', NULL, 2, NOW(), NOW())
ON DUPLICATE KEY UPDATE value=VALUES(value);

-- Pivot between variants and attribute values
INSERT IGNORE INTO `attribute_value_product_variant` (product_variant_id, attribute_value_id) VALUES
(1,1),(1,3),(2,2),(2,4);

-- Orders and order_details (for review foreign key)
INSERT INTO `orders` (id, total_price, discount_price, final_price, status, user_id, created_at, updated_at) VALUES
(1, 199000.00, NULL, 199000.00, 'completed', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE total_price=VALUES(total_price);

INSERT INTO `order_details` (id, quantity, unit_price, unit_discount_price, order_id, product_variant_id, created_at, updated_at) VALUES
(1, 1, 199000.00, NULL, 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE quantity=VALUES(quantity);

-- Reviews
INSERT INTO `reviews` (id, rating, comment, user_id, product_id, order_detail_id, created_at, updated_at) VALUES
(1, 5, 'Great quality and fit.', 1, 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE rating=VALUES(rating);

INSERT IGNORE INTO `review_images` (id, image_path, review_id, created_at, updated_at) VALUES
(1, 'reviews/1/photo1.jpg', 1, NOW(), NOW());

SET FOREIGN_KEY_CHECKS=1;

-- End of fake data
