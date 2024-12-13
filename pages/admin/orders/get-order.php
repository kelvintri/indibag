<?php
require_once __DIR__ . '/../../../includes/AdminAuth.php';
require_once __DIR__ . '/../../../config/database.php';

AdminAuth::requireAdmin();

$order_id = basename($_SERVER['REQUEST_URI']);

$db = new Database();
$conn = $db->getConnection();

try {
    // Get order details with all related information
    $stmt = $conn->prepare("
        SELECT o.*, 
               u.email as user_email,
               u.full_name as user_name,
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
               pd.notes,
               u_verified.username as verified_by_username,
               u_verified.full_name as verified_by_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN addresses a ON o.shipping_address_id = a.id
        LEFT JOIN payment_details pd ON o.id = pd.order_id
        LEFT JOIN users u_verified ON pd.verified_by = u_verified.id
        WHERE o.id = ?
    ");
    
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
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
        WHERE oi.order_id = ?
    ");
    
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $order['items'] = $items;
    
    echo json_encode([
        'success' => true,
        'data' => $order
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 