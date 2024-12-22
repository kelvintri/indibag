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
$tracking_number = $_POST['tracking_number'] ?? null;
$notes = $_POST['notes'] ?? null;

if (!$order_id || !$tracking_number) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

try {
    $conn->beginTransaction();

    // Check if shipping details exist
    $check_sql = "SELECT id FROM shipping_details WHERE order_id = :order_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':order_id', $order_id);
    $check_stmt->execute();
    $exists = $check_stmt->fetch();

    if ($exists) {
        // Update existing shipping details
        $sql = "UPDATE shipping_details 
                SET tracking_number = :tracking_number,
                    notes = :notes,
                    shipped_by = :shipped_by,
                    shipped_at = NOW()
                WHERE order_id = :order_id";
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Shipping details not found']);
        exit;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->bindParam(':tracking_number', $tracking_number);
    $stmt->bindParam(':notes', $notes);
    $stmt->bindParam(':shipped_by', $_SESSION['user_id']);
    $stmt->execute();

    // Update order status to shipped
    $order_sql = "UPDATE orders 
                  SET status = 'shipped',
                      updated_at = NOW()
                  WHERE id = :order_id";
    $order_stmt = $conn->prepare($order_sql);
    $order_stmt->bindParam(':order_id', $order_id);
    $order_stmt->execute();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Shipping details updated successfully'
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    error_log('Error updating shipping details: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update shipping details'
    ]);
} 