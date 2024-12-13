<?php
require_once __DIR__ . '/../../../includes/AdminAuth.php';
require_once __DIR__ . '/../../../config/database.php';

AdminAuth::requireAdmin();
header('Content-Type: application/json');

try {
    if (!isset($_POST['order_id']) || !isset($_POST['status'])) {
        throw new Exception('Missing required fields');
    }

    // Debug log
    error_log("Received status: " . $_POST['status']);
    
    // Validate status value
    $valid_statuses = [
        'pending_payment',
        'payment_uploaded',
        'payment_verified',
        'processing',
        'shipped',
        'delivered',
        'cancelled',
        'refunded'
    ];
    
    if (!in_array($_POST['status'], $valid_statuses)) {
        throw new Exception('Invalid status value: ' . $_POST['status']);
    }

    $db = new Database();
    $conn = $db->getConnection();
    
    $conn->beginTransaction();
    
    // Update order status
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$_POST['status'], $_POST['order_id']]);
    
    // Add status history
    $stmt = $conn->prepare("
        INSERT INTO order_status_history (
            order_id, status, notes, changed_by, created_at
        ) VALUES (?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $_POST['order_id'],
        $_POST['status'],
        $_POST['notes'] ?? null,
        $_SESSION['user_id']
    ]);
    
    // If status is payment_verified, update payment_details
    if ($_POST['status'] === 'payment_verified') {
        $stmt = $conn->prepare("
            UPDATE payment_details 
            SET verified_by = ?, 
                verified_at = NOW() 
            WHERE order_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $_POST['order_id']]);
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order status updated successfully'
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