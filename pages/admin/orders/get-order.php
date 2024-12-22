<?php
require_once __DIR__ . '/../../../includes/AdminAuth.php';
require_once __DIR__ . '/../../../config/database.php';

error_log('=== Get Order Details ===');
error_log('Request Method: ' . $_SERVER['REQUEST_METHOD']);
error_log('Path: ' . $_SERVER['REQUEST_URI']);

AdminAuth::requireAdmin();

// Get order ID from URL matches
$order_id = $matches[1] ?? null;
error_log('Order ID: ' . $order_id);

if (!$order_id) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

try {
    // Get order details with all related information
    $stmt = $conn->prepare("
        SELECT o.*, 
               u.email as user_email,
               u.full_name as user_name,
               a.recipient_name,
               a.street_address,
               a.district,
               a.city,
               a.province,
               a.postal_code,
               a.phone,
               pd.payment_method,
               pd.transfer_proof_url,
               pd.payment_amount,
               pd.payment_date,
               pd.verified_at,
               pd.notes as payment_notes,
               u_verified.username as verified_by_username,
               u_verified.full_name as verified_by_name,
               sd.courier_name,
               sd.service_type,
               sd.tracking_number,
               sd.shipping_cost,
               sd.estimated_delivery_date,
               sd.shipped_at,
               sd.notes as shipping_notes,
               u_shipped.username as shipped_by_username,
               u_shipped.full_name as shipped_by_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN addresses a ON o.shipping_address_id = a.id
        LEFT JOIN payment_details pd ON o.id = pd.order_id
        LEFT JOIN shipping_details sd ON o.id = sd.order_id
        LEFT JOIN users u_verified ON pd.verified_by = u_verified.id
        LEFT JOIN users u_shipped ON sd.shipped_by = u_shipped.id
        WHERE o.id = :order_id
    ");
    
    $stmt->bindParam(':order_id', $order_id);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        error_log('Order not found in database');
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }
    
    error_log('Found order: ' . json_encode($order));
    
    // Get order items
    $stmt = $conn->prepare("
        SELECT oi.*, 
               p.name as product_name,
               p.sku,
               p.slug,
               pg.image_url as product_image
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_galleries pg ON p.id = pg.product_id AND pg.is_primary = 1
        WHERE oi.order_id = :order_id
    ");
    
    $stmt->bindParam(':order_id', $order_id);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $order['items'] = $items;
    error_log('Found items: ' . count($items));
    
    echo json_encode([
        'success' => true,
        'data' => $order
    ]);

} catch (Exception $e) {
    error_log('Error getting order details: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to get order details'
    ]);
} 