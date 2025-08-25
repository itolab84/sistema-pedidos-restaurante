-- Database: restaurante_pedidos

CREATE DATABASE IF NOT EXISTS restaurante_pedidos;
USE restaurante_pedidos;

-- Table: products
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    category VARCHAR(100),
    image VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: orders
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255),
    customer_phone VARCHAR(50),
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: order_items
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Insert sample products
INSERT INTO products (name, description, price, category, image) VALUES
('Hamburguesa Clásica', 'Jugosa hamburguesa con carne de res, lechuga, tomate y queso', 8.99, 'Hamburguesas', 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=500'),
('Pizza Margherita', 'Pizza tradicional con salsa de tomate, mozzarella y albahaca', 12.99, 'Pizzas', 'https://images.unsplash.com/photo-1604382354936-07c5d9983bd3?w=500'),
('Ensalada César', 'Ensalada fresca con pollo a la parrilla, crutones y aderezo César', 7.99, 'Ensaladas', 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=500'),
('Tacos de Carne', 'Tres tacos de carne asada con cebolla, cilantro y salsa', 9.99, 'Tacos', 'https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=500'),
('Pasta Alfredo', 'Pasta con salsa Alfredo cremosa y queso parmesano', 11.99, 'Pastas', 'https://images.unsplash.com/photo-1551183053-bf91a1d81141?w=500'),
('Sándwich Club', 'Sándwich triple capas con pollo, tocino, lechuga y tomate', 6.99, 'Sándwiches', 'https://images.unsplash.com/photo-1528735602780-2552fd46c7af?w=500'),
('Sopa de Tomate', 'Sopa cremosa de tomate con albahaca fresca', 4.99, 'Sopas', 'https://images.unsplash.com/photo-1547592166-23ac45744acd?w=500'),
('Helado de Vainilla', 'Helado artesanal de vainilla con salsa de chocolate', 3.99, 'Postres', 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=500'),
('Refresco', 'Bebida gaseosa de diferentes sabores', 1.99, 'Bebidas', 'https://images.unsplash.com/photo-1544145945-f90425340c7e?w=500'),
('Café Americano', 'Café negro recién preparado', 2.49, 'Bebidas', 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=500');

-- Create indexes for better performance
CREATE INDEX idx_products_category ON products(category);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_created_at ON orders(created_at);
CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_order_items_product_id ON order_items(product_id);
