<?php
error_log('=== Create Order API Request ===');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

// Set error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    // CORS Headers
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: http://bananina.test');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Credentials: true');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    // Require authentication
    $user_id = AuthMiddleware::authenticate();

    // Get and validate input
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($data['shipping_address']) || !isset($data['payment_method'])) {
        throw new Exception('Shipping address and payment method are required', 400);
    }

    // Validate payment method
    if (!in_array($data['payment_method'], ['bank_transfer', 'e-wallet'])) {
        throw new Exception('Invalid payment method', 400);
    }

    // Validate address fields
    $required_address_fields = [
        'recipient_name', 'phone', 'street_address', 
        'district', 'city', 'province', 'postal_code'
    ];

    foreach ($required_address_fields as $field) {
        if (!isset($data['shipping_address'][$field]) || trim($data['shipping_address'][$field]) === '') {
            throw new Exception("Address field '$field' is required", 400);
        }
    }

    // Database connection
    $db = new APIDatabase();
    $conn = $db->getConnection();
    if (!$conn) {
        throw new Exception('Database connection failed', 500);
    }

    // Start transaction
    $conn->beginTransaction();

    try {
        // Create shipping address
        $stmt = $conn->prepare("
            INSERT INTO addresses (
                user_id,
                address_type,
                recipient_name,
                phone,
                street_address,
                district,
                city,
                province,
                postal_code,
                additional_info
            ) VALUES (?, 'shipping', ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $data['shipping_address']['recipient_name'],
            $data['shipping_address']['phone'],
            $data['shipping_address']['street_address'],
            $data['shipping_address']['district'],
            $data['shipping_address']['city'],
            $data['shipping_address']['province'],
            $data['shipping_address']['postal_code'],
            $data['shipping_address']['additional_info'] ?? null
        ]);
        
        $shipping_address_id = $conn->lastInsertId();

        // Get cart items
        $stmt = $conn->prepare("
            SELECT 
                c.*,
                p.price,
                p.sale_price,
                p.stock,
                p.name as product_name
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_id = ?
            AND p.is_active = 1
            AND p.deleted_at IS NULL
        ");
        $stmt->execute([$user_id]);
        $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($cart_items)) {
            throw new Exception('Cart is empty', 400);
        }

        // Calculate totals and validate stock
        $subtotal = 0;
        $total_items = 0;
        foreach ($cart_items as $item) {
            if ($item['stock'] < $item['quantity']) {
                throw new Exception("Insufficient stock for {$item['product_name']}", 400);
            }
            $price = $item['sale_price'] ?? $item['price'];
            $subtotal += $price * $item['quantity'];
            $total_items += $item['quantity'];
        }

        // Generate order number
        $order_number = 'ORD' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Create order
        $stmt = $conn->prepare("
            INSERT INTO orders (
                order_number,
                user_id, 
                shipping_address_id,
                total_amount,
                shipping_cost,
                payment_method,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending_payment')
        ");
        $stmt->execute([
            $order_number,
            $user_id,
            $shipping_address_id,
            $subtotal,
            0.00,
            $data['payment_method']
        ]);
        $order_id = $conn->lastInsertId();

        // Create order items
        foreach ($cart_items as $item) {
            $price = $item['sale_price'] ?? $item['price'];
            
            // Create order item
            $stmt = $conn->prepare("
                INSERT INTO order_items (
                    order_id,
                    product_id,
                    quantity,
                    price
                ) VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $order_id,
                $item['product_id'],
                $item['quantity'],
                $price
            ]);

            // Update stock
            $stmt = $conn->prepare("
                UPDATE products 
                SET stock = stock - ? 
                WHERE id = ?
            ");
            $stmt->execute([$item['quantity'], $item['product_id']]);
        }

        // Clear cart
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // Get order details
        $stmt = $conn->prepare("
            SELECT 
                o.*,
                COUNT(oi.id) as items_count,
                SUM(oi.quantity) as total_items
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.id = ?
            GROUP BY o.id
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        // Commit transaction
        $conn->commit();

        // After creating the order
        error_log('Created order with ID: ' . $order_id);
        error_log('For user_id: ' . $user_id);

        echo json_encode([
            'success' => true,
            'message' => 'Order created successfully',
            'data' => [
                'order_id' => $order_id,
                'order_number' => $order['order_number'],
                'status' => $order['status'],
                'total_amount' => (float)$order['total_amount'],
                'shipping_cost' => (float)$order['shipping_cost'],
                'items_count' => (int)$order['items_count'],
                'total_items' => (int)$order['total_items']
            ]
        ], JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log('Database error in orders/create.php: ' . $e->getMessage());
    error_log('SQL State: ' . $e->getCode());
    error_log('Error trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'type' => 'database_error',
            'message' => 'A database error occurred',
            'debug' => $e->getMessage() // Remove this in production
        ]
    ]);
} catch (Exception $e) {
    error_log('Error in orders/create.php: ' . $e->getMessage());
    $status_code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
    http_response_code($status_code);
    echo json_encode([
        'success' => false,
        'error' => [
            'type' => 'request_error',
            'message' => $e->getMessage()
        ]
    ]);
} finally {
    restore_error_handler();
} 