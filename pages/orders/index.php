<?php
Auth::requireLogin();

$db = new Database();
$conn = $db->getConnection();

// Get all orders for the user with latest first
$query = "SELECT o.*, 
                 pd.payment_method, pd.payment_date, pd.verified_at,
                 (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
          FROM orders o
          LEFT JOIN payment_details pd ON o.id = pd.order_id
          WHERE o.user_id = :user_id
          ORDER BY o.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Profile Navigation -->
    <div class="mb-8 border-b">
        <nav class="flex space-x-8">
            <a href="/profile" 
               class="border-b-2 border-transparent px-1 pb-4 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                Profile
            </a>
            <a href="/profile/addresses" 
               class="border-b-2 border-transparent px-1 pb-4 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                Addresses
            </a>
            <a href="/orders" 
               class="border-b-2 border-blue-500 px-1 pb-4 text-sm font-medium text-blue-600">
                Orders
            </a>
        </nav>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">My Orders</h1>
    </div>

    <?php if (empty($orders)): ?>
        <div class="bg-white rounded-lg shadow-sm p-6 text-center">
            <p class="text-gray-500 mb-4">You haven't placed any orders yet</p>
            <a href="/products" class="inline-block bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600">
                Start Shopping
            </a>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($orders as $order): ?>
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <!-- Order Header -->
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">
                                Order #<?= $order['id'] ?>
                            </h3>
                            <p class="text-sm text-gray-500">
                                Placed on <?= date('F j, Y', strtotime($order['created_at'])) ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <div class="mb-1">
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
                            <p class="text-sm text-gray-500">
                                <?= $order['item_count'] ?> item<?= $order['item_count'] > 1 ? 's' : '' ?>
                            </p>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="px-6 py-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm text-gray-600">Payment Method</p>
                                <p class="font-medium">
                                    <?= ucwords(str_replace('_', ' ', $order['payment_method'])) ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-600">Total Amount</p>
                                <p class="text-lg font-semibold text-gray-900">
                                    Rp <?= number_format($order['total_amount'], 0, ',', '.') ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Order Actions -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-600">
                                Payment Status: 
                                <span class="font-medium">
                                    <?= $order['verified_at'] ? 'Verified' : ($order['payment_date'] ? 'Uploaded' : 'Pending') ?>
                                </span>
                            </div>
                            <div>
                                <a href="/orders/<?= $order['id'] ?>" 
                                   class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800">
                                    View Details
                                    <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div> 