<?php
require_once __DIR__ . '/../../../includes/AdminAuth.php';
require_once __DIR__ . '/../../../config/database.php';

AdminAuth::requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$order_id = $_POST['order_id'] ?? null;
$status = $_POST['status'] ?? null;
$notes = $_POST['notes'] ?? null;

if (!$order_id || !$status) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Validate status
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

if (!in_array($status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

try {
    $conn->beginTransaction();

    // Update order status
    $sql = "UPDATE orders 
            SET status = :status,
                updated_at = NOW()
            WHERE id = :order_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->bindParam(':status', $status);
    $stmt->execute();

    // If status is payment_verified, update payment details
    if ($status === 'payment_verified') {
        $sql = "UPDATE payment_details 
                SET verified_at = NOW(),
                    verified_by = :verified_by,
                    notes = CASE 
                        WHEN :notes IS NOT NULL AND :notes != '' 
                        THEN :notes 
                        ELSE notes 
                    END
                WHERE order_id = :order_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->bindParam(':verified_by', $_SESSION['user_id']);
        $stmt->bindParam(':notes', $notes);
        $stmt->execute();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order status updated successfully'
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    error_log('Error updating order status: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update order status'
    ]);
} 