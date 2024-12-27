<?php
Auth::requireLogin();

// Get order ID from URL
$order_id = $matches[1] ?? null;

if (!$order_id) {
    header('Location: /orders');
    exit;
}

// Get order details
$orderObj = new Order();
$order = $orderObj->getOrder($order_id);

// Verify order belongs to user
if (!$order || $order['user_id'] != $_SESSION['user_id']) {
    header('Location: /orders');
    exit;
}

// Get order items
$items = $orderObj->getOrderItems($order_id);
?>

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Success Icon and Message -->
    <div class="text-center mb-12">
        <div class="mb-4">
            <div class="mx-auto h-24 w-24 rounded-full bg-green-50 flex items-center justify-center">
                <svg class="h-12 w-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
        </div>
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Order Confirmed!</h1>
        <p class="text-lg text-gray-600">Thank you for your order. Your order number is #<?= $order['order_number'] ?></p>
    </div>

    <!-- Order Details -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <!-- Shipping Address -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Shipping Address</h2>
            <div class="text-gray-600">
                <p class="font-medium"><?= htmlspecialchars($shippingAddress['recipient_name']) ?></p>
                <p><?= htmlspecialchars($shippingAddress['phone']) ?></p>
                <p><?= htmlspecialchars($shippingAddress['street_address']) ?></p>
                <p>
                    <?= htmlspecialchars($shippingAddress['district']) ?>,
                    <?= htmlspecialchars($shippingAddress['city']) ?>
                </p>
                <p>
                    <?= htmlspecialchars($shippingAddress['province']) ?>,
                    <?= htmlspecialchars($shippingAddress['postal_code']) ?>
                </p>
            </div>
        </div>

        <!-- Payment Method -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Payment Method</h2>
            <div class="text-gray-600">
                <p class="capitalize"><?= str_replace('_', ' ', $order['payment_method']) ?></p>
                <?php if ($order['payment_method'] === 'bank_transfer'): ?>
                    <div class="mt-2 p-4 bg-yellow-50 rounded-md">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    Please upload your payment proof immediately to avoid order cancellation.
                                </p>
                                <p class="mt-1 text-sm text-yellow-700">
                                    Your order will be processed after payment verification.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Shipping Method -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Shipping Method</h2>
            <div class="text-gray-600">
                <?php if ($shippingDetails): ?>
                    <p>
                        <?= htmlspecialchars($shippingDetails['courier_name']) ?> 
                        <?= htmlspecialchars($shippingDetails['service_type']) ?>
                    </p>
                    <?php if ($shippingDetails['estimated_delivery_date']): ?>
                        <p class="mt-1">
                            Estimated delivery: <?= date('d F Y', strtotime($shippingDetails['estimated_delivery_date'])) ?>
                        </p>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Standard Shipping</p>
                    <p class="mt-1">Estimated delivery: <?= date('d F Y', strtotime('+3 days')) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order Items -->
        <div class="px-6 py-4 space-y-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Order Items</h2>
            <?php foreach ($items as $item): ?>
                <div class="flex items-start">
                    <div class="w-20 h-20 flex-shrink-0">
                        <img src="<?= getImageUrl($item['image_url']) ?>" 
                             alt="<?= htmlspecialchars($item['name']) ?>"
                             class="w-full h-full object-contain rounded-md">
                    </div>
                    <div class="ml-4 flex-1 flex justify-between">
                        <div>
                            <h3 class="text-base font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></h3>
                            <p class="text-gray-600">Rp <?= number_format($item['price'], 0, ',', '.') ?> × <?= $item['quantity'] ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-base font-medium text-gray-900">
                                Rp <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Order Summary -->
        <div class="px-6 py-4 border-t border-gray-200">
            <div class="space-y-2">
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal</span>
                    <span>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>Shipping</span>
                    <span>Rp <?= number_format($order['shipping_cost'], 0, ',', '.') ?></span>
                </div>
                <div class="pt-4 border-t border-gray-200">
                    <div class="flex justify-between">
                        <span class="text-lg font-bold text-gray-900">Total</span>
                        <span class="text-lg font-bold text-gray-900">
                            Rp <?= number_format($order['total_amount'] + $order['shipping_cost'], 0, ',', '.') ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="px-6 py-4 bg-gray-50 flex justify-between items-center">
            <a href="/products" class="text-blue-600 hover:text-blue-800 font-medium">
                Continue Shopping
            </a>
            <a href="/orders" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                View Order Details
            </a>
        </div>
    </div>
</div> 