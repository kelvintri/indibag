<?php
header('Content-Type: application/json');
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$order_id = $_POST['order_id'] ?? null;
$payment_proof = $_FILES['payment_proof'] ?? null;

if (!$order_id || !$payment_proof) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Validate order exists and belongs to user
$orderObj = new Order();
$order = $orderObj->getOrder($order_id);

if (!$order || $order['user_id'] != $_SESSION['user_id']) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
}

// Validate file type
$allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
if (!in_array($payment_proof['type'], $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Only JPG and PNG are allowed']);
    exit;
}

// Validate file size (5MB max)
if ($payment_proof['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large. Maximum size is 5MB']);
    exit;
}

// Create uploads directory if it doesn't exist
$upload_dir = ROOT_PATH . '/public/uploads/payments';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$extension = pathinfo($payment_proof['name'], PATHINFO_EXTENSION);
$filename = 'payment_' . $order_id . '_' . time() . '.' . $extension;
$filepath = $upload_dir . '/' . $filename;

// Move uploaded file
if (!move_uploaded_file($payment_proof['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to upload file']);
    exit;
}

// Update payment details in database
$db = new Database();
$conn = $db->getConnection();

try {
    $conn->beginTransaction();

    // Get existing payment details
    $query = "SELECT transfer_proof_url FROM payment_details WHERE order_id = :order_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    // Delete old file if exists
    if ($existing && $existing['transfer_proof_url']) {
        $old_file = ROOT_PATH . '/public' . $existing['transfer_proof_url'];
        if (file_exists($old_file)) {
            unlink($old_file);
        }
    }

    // Update payment details
    $query = "UPDATE payment_details 
              SET payment_date = NOW(),
                  transfer_proof_url = :proof_url
              WHERE order_id = :order_id";
    
    $stmt = $conn->prepare($query);
    $proof_url = '/uploads/payments/' . $filename;
    $stmt->bindParam(':order_id', $order_id);
    $stmt->bindParam(':proof_url', $proof_url);
    $stmt->execute();

    // Update order status
    $query = "UPDATE orders SET status = 'payment_uploaded', updated_at = NOW() WHERE id = :order_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment proof uploaded successfully'
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    // Delete the new file if database update failed
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    error_log('Error uploading payment: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to process payment upload']);
    exit;
} 