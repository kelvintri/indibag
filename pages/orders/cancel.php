<?php
Auth::requireLogin();
header('Content-Type: application/json');

$order_id = $matches[1];

// Get order details
$orderObj = new Order();
$order = $orderObj->getOrder($order_id);

// Verify order belongs to user and is in pending_payment status
if (!$order || $order['user_id'] != $_SESSION['user_id']) {
    echo json_encode([
        'success' => false,
        'error' => 'Order not found'
    ]);
    exit;
}

if ($order['status'] !== 'pending_payment') {
    echo json_encode([
        'success' => false,
        'error' => 'Order cannot be cancelled'
    ]);
    exit;
}

// Update order status to cancelled
$db = new Database();
$conn = $db->getConnection();

try {
    $conn->beginTransaction();
    
    // Update order status
    $updateQuery = "UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = :order_id";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->execute();
    
    // Delete payment details if exists
    $deletePaymentQuery = "DELETE FROM payment_details WHERE order_id = :order_id";
    $stmt = $conn->prepare($deletePaymentQuery);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order cancelled successfully'
    ]);
} catch (Exception $e) {
    $conn->rollBack();
    error_log('Error cancelling order: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to cancel order'
    ]);
}
exit; 