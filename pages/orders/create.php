<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../config/database.php';

Auth::requireLogin();

header('Content-Type: application/json');

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    error_log('Raw input: ' . file_get_contents('php://input'));
    error_log('Decoded data: ' . print_r($data, true));
    
    if (!isset($data['shipping_address_id']) || !isset($data['payment_method'])) {
        throw new Exception('Missing required fields');
    }
    
    $cart = new Cart();
    $cartItems = $cart->getItems();
    
    if (empty($cartItems)) {
        throw new Exception('Cart is empty');
    }
    
    // Prepare order data
    $orderData = [
        'shipping_address_id' => $data['shipping_address_id'],
        'payment_method' => $data['payment_method'],
        'shipping_cost' => floatval($data['shipping_cost']),
        'total_amount' => floatval($data['total_amount']),
        'items' => $cartItems
    ];
    error_log('Prepared order data: ' . print_r($orderData, true));
    
    // Create order
    $order = new Order();
    $result = $order->create($orderData);
    
    if ($result['success']) {
        // Clear cart after successful order creation
        $cart->clear();
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 