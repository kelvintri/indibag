<?php
require_once __DIR__ . '/../../../includes/AdminAuth.php';
require_once __DIR__ . '/../../../config/database.php';

AdminAuth::requireAdmin();
header('Content-Type: application/json');

try {
    if (!isset($_POST['order_id']) || !isset($_POST['courier_name']) || !isset($_POST['shipping_cost'])) {
        throw new Exception('Missing required fields');
    }

    $db = new Database();
    $conn = $db->getConnection();
    
    $conn->beginTransaction();
    
    // Check if shipping details already exist
    $stmt = $conn->prepare("SELECT id FROM shipping_details WHERE order_id = ?");
    $stmt->execute([$_POST['order_id']]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing shipping details
        $stmt = $conn->prepare("
            UPDATE shipping_details 
            SET courier_name = ?,
                service_type = ?,
                tracking_number = ?,
                shipping_cost = ?,
                estimated_delivery_date = ?,
                notes = ?,
                shipped_by = ?,
                shipped_at = NOW(),
                updated_at = NOW()
            WHERE order_id = ?
        ");
    } else {
        // Insert new shipping details
        $stmt = $conn->prepare("
            INSERT INTO shipping_details (
                order_id,
                courier_name,
                service_type,
                tracking_number,
                shipping_cost,
                estimated_delivery_date,
                notes,
                shipped_by,
                shipped_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
    }
    
    $params = [
        $_POST['courier_name'],
        $_POST['service_type'] ?? null,
        $_POST['tracking_number'] ?? null,
        $_POST['shipping_cost'],
        $_POST['estimated_delivery_date'] ?? null,
        $_POST['notes'] ?? null,
        $_SESSION['user_id']
    ];
    
    if ($existing) {
        $params[] = $_POST['order_id'];
    } else {
        array_unshift($params, $_POST['order_id']);
    }
    
    $stmt->execute($params);
    
    // Update order status to shipped
    $stmt = $conn->prepare("UPDATE orders SET status = 'shipped' WHERE id = ?");
    $stmt->execute([$_POST['order_id']]);
    
    // Add status history
    $stmt = $conn->prepare("
        INSERT INTO order_status_history (
            order_id, status, notes, changed_by, created_at
        ) VALUES (?, 'shipped', ?, ?, NOW())
    ");
    
    $stmt->execute([
        $_POST['order_id'],
        'Shipping details updated: ' . $_POST['courier_name'] . ' - ' . ($_POST['tracking_number'] ?? 'No tracking'),
        $_SESSION['user_id']
    ]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Shipping details updated successfully'
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 