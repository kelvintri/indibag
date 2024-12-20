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
    <div class="text-center mb-12">
        <div class="mb-4">
            <svg class="mx-auto h-16 w-16 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" transform="scale(2)" />
            </svg>
        </div>
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Thank You for Your Order!</h1>
        <p class="text-lg text-gray-600">Your order has been placed successfully.</p>
    </div>

    <!-- Order Details -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Order #<?= $order['id'] ?></h2>
                    <p class="text-sm text-gray-500">Placed on <?= date('F j, Y', strtotime($order['created_at'])) ?></p>
                </div>
                <span class="px-3 py-1 rounded-full text-sm 
                    <?php
                    switch ($order['status']) {
                        case 'pending_payment':
                            echo 'bg-yellow-100 text-yellow-800';
                            break;
                        case 'payment_uploaded':
                            echo 'bg-blue-100 text-blue-800';
                            break;
                        case 'payment_verified':
                        case 'processing':
                            echo 'bg-indigo-100 text-indigo-800';
                            break;
                        case 'shipped':
                            echo 'bg-purple-100 text-purple-800';
                            break;
                        case 'delivered':
                            echo 'bg-green-100 text-green-800';
                            break;
                        case 'cancelled':
                        case 'refunded':
                            echo 'bg-red-100 text-red-800';
                            break;
                    }
                    ?>">
                    <?= ucwords(str_replace('_', ' ', $order['status'])) ?>
                </span>
            </div>
        </div>

        <!-- Order Items -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900 mb-4">Order Items</h3>
            <div class="space-y-4">
                <?php foreach ($items as $item): ?>
                    <div class="flex items-center">
                        <div class="w-16 h-16 flex-shrink-0">
                            <img src="<?= getImageUrl($item['image_url']) ?>" 
                                 alt="<?= htmlspecialchars($item['name']) ?>"
                                 class="w-full h-full object-contain rounded-md">
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></p>
                            <p class="text-sm text-gray-500">Quantity: <?= $item['quantity'] ?></p>
                            <p class="text-sm font-medium text-gray-900">
                                Rp <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Subtotal</span>
                    <span class="text-gray-900">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Shipping</span>
                    <span class="text-gray-900">Rp <?= number_format($order['shipping_cost'] ?? 0, 0, ',', '.') ?></span>
                </div>
                <div class="border-t border-gray-200 mt-4 pt-4">
                    <div class="flex justify-between">
                        <span class="text-base font-medium text-gray-900">Total</span>
                        <span class="text-base font-medium text-gray-900">
                            Rp <?= number_format($order['total_amount'] + ($order['shipping_cost'] ?? 0), 0, ',', '.') ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Instructions -->
        <?php if ($order['status'] === 'pending_payment'): ?>
            <div class="px-6 py-4 bg-yellow-50">
                <h3 class="text-base font-semibold text-yellow-800 mb-2">Payment Instructions</h3>
                <p class="text-sm text-yellow-700 mb-4">
                    Please complete your payment to process your order. You can upload your payment proof from your order details.
                </p>
                <div class="flex justify-end">
                    <a href="/orders" 
                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                        Go to My Orders
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="px-6 py-4 bg-gray-50">
                <div class="flex justify-end">
                    <a href="/orders" 
                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                        Go to My Orders
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div> 