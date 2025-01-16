<?php
require_once __DIR__ . '/../../includes/AdminAuth.php';
require_once __DIR__ . '/../../config/database.php';
AdminAuth::requireAdmin();

$db = new Database();
$conn = $db->getConnection();

// Fetch dashboard statistics with role information
$stats = [
    'orders' => [
        'total' => $conn->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'today' => $conn->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
        'week' => $conn->query("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
        'month' => $conn->query("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
        'status_breakdown' => $conn->query("
            SELECT status, COUNT(*) as count 
            FROM orders 
            GROUP BY status
        ")->fetchAll(PDO::FETCH_KEY_PAIR),
        'pending_verification' => $conn->query("
            SELECT COUNT(*) FROM orders 
            WHERE status = 'payment_uploaded'
        ")->fetchColumn()
    ],
    'users' => [
        'total' => $conn->query("
            SELECT COUNT(DISTINCT u.id) 
            FROM users u 
            WHERE u.deleted_at IS NULL
        ")->fetchColumn(),
        'new_this_month' => $conn->query("
            SELECT COUNT(*) FROM users 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
            AND deleted_at IS NULL
        ")->fetchColumn()
    ],
    'revenue' => [
        'total' => $conn->query("
            SELECT COALESCE(SUM(total_amount), 0) 
            FROM orders 
            WHERE status IN ('completed', 'shipped', 'delivered')
        ")->fetchColumn(),
        'today' => $conn->query("
            SELECT COALESCE(SUM(total_amount), 0) 
            FROM orders 
            WHERE status IN ('completed', 'shipped', 'delivered') 
            AND DATE(created_at) = CURDATE()
        ")->fetchColumn(),
        'week' => $conn->query("
            SELECT COALESCE(SUM(total_amount), 0) 
            FROM orders 
            WHERE status IN ('completed', 'shipped', 'delivered') 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ")->fetchColumn(),
        'month' => $conn->query("
            SELECT COALESCE(SUM(total_amount), 0) 
            FROM orders 
            WHERE status IN ('completed', 'shipped', 'delivered') 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ")->fetchColumn(),
        'prev_month' => $conn->query("
            SELECT COALESCE(SUM(total_amount), 0) 
            FROM orders 
            WHERE status IN ('completed', 'shipped', 'delivered') 
            AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY)
        ")->fetchColumn(),
        'avg_order' => $conn->query("
            SELECT COALESCE(AVG(total_amount), 0) 
            FROM orders 
            WHERE status IN ('completed', 'shipped', 'delivered')
        ")->fetchColumn()
    ],
    'products' => [
        'total' => $conn->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL")->fetchColumn(),
        'low_stock' => $conn->query("SELECT COUNT(*) FROM products WHERE stock <= 5 AND deleted_at IS NULL")->fetchColumn(),
        'out_of_stock' => $conn->query("SELECT COUNT(*) FROM products WHERE stock = 0 AND deleted_at IS NULL")->fetchColumn(),
        'categories' => $conn->query("
            SELECT c.name, COUNT(p.id) as count 
            FROM categories c 
            LEFT JOIN products p ON c.id = p.category_id AND p.deleted_at IS NULL 
            GROUP BY c.id 
            ORDER BY count DESC 
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC)
    ],
    'admins' => $conn->query("
        SELECT COUNT(DISTINCT u.id) 
        FROM users u 
        JOIN user_roles ur ON u.id = ur.user_id 
        JOIN roles r ON ur.role_id = r.id 
        WHERE r.name = 'admin' AND u.deleted_at IS NULL
    ")->fetchColumn()
];

// Calculate revenue trend (percentage change from previous month)
$revenueTrend = $stats['revenue']['prev_month'] > 0 
    ? (($stats['revenue']['month'] - $stats['revenue']['prev_month']) / $stats['revenue']['prev_month']) * 100 
    : 0;

// Get recent orders with more details
$recentOrders = $conn->query("
    SELECT 
        o.*,
        o.total_amount as total,
        u.username as customer_name,
        u.email as customer_email,
        GROUP_CONCAT(DISTINCT r.name) as user_roles,
        MAX(pd.payment_method) as payment_method,
        MAX(pd.transfer_proof_url) as transfer_proof_url,
        MAX(sd.courier_name) as courier_name,
        MAX(sd.tracking_number) as tracking_number
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    LEFT JOIN payment_details pd ON o.id = pd.order_id
    LEFT JOIN shipping_details sd ON o.id = sd.order_id
    WHERE u.deleted_at IS NULL
    GROUP BY o.id, o.total_amount, u.username, u.email
    ORDER BY o.created_at DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get top selling products
$topProducts = $conn->query("
    SELECT 
        p.id,
        p.name, 
        p.stock,
        COALESCE(order_stats.total_orders, 0) as total_orders,
        COALESCE(order_stats.total_quantity, 0) as total_quantity
    FROM products p
    LEFT JOIN (
        SELECT 
            oi.product_id,
            COUNT(DISTINCT o.id) as total_orders,
            SUM(oi.quantity) as total_quantity
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status IN ('completed', 'shipped', 'delivered')
        GROUP BY oi.product_id
    ) order_stats ON p.id = order_stats.product_id
    WHERE p.deleted_at IS NULL
    ORDER BY COALESCE(order_stats.total_quantity, 0) DESC, p.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// If no completed orders exist, get the most viewed/recent products instead
if (empty($topProducts)) {
    $topProducts = $conn->query("
        SELECT 
            p.id,
            p.name,
            p.stock,
            0 as total_orders,
            0 as total_quantity
        FROM products p
        WHERE p.deleted_at IS NULL
        ORDER BY p.created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
        <div class="text-sm text-gray-500">Last updated: <?= date('d M Y H:i') ?></div>
    </div>

    <!-- Alert Section -->
    <?php if ($stats['orders']['pending_verification'] > 0): ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-8">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    You have <?= $stats['orders']['pending_verification'] ?> payment(s) waiting for verification
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Orders Card -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-gray-500 text-sm">Orders</h3>
            <p class="text-2xl font-semibold"><?= number_format($stats['orders']['total']) ?></p>
            <div class="mt-2 text-sm">
                <p class="text-gray-600">Today: <?= number_format($stats['orders']['today']) ?></p>
                <p class="text-gray-600">This Week: <?= number_format($stats['orders']['week']) ?></p>
                <p class="text-gray-600">This Month: <?= number_format($stats['orders']['month']) ?></p>
            </div>
            <div class="mt-4 pt-4 border-t">
                <div class="text-xs font-medium text-gray-500 mb-1">Status Breakdown</div>
                <?php foreach ($stats['orders']['status_breakdown'] as $status => $count): ?>
                <div class="flex justify-between items-center text-sm">
                    <span class="capitalize"><?= str_replace('_', ' ', $status) ?></span>
                    <span class="font-medium"><?= $count ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Revenue Card -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-gray-500 text-sm">Revenue</h3>
            <p class="text-2xl font-semibold">Rp <?= number_format($stats['revenue']['total'], 0, ',', '.') ?></p>
            <div class="mt-2">
                <div class="flex items-center">
                    <span class="text-sm text-gray-600">Monthly Trend:</span>
                    <span class="ml-2 text-sm <?= $revenueTrend >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= ($revenueTrend >= 0 ? '+' : '') . number_format($revenueTrend, 1) ?>%
                    </span>
                </div>
                <p class="text-sm text-gray-600">This Month: Rp <?= number_format($stats['revenue']['month'], 0, ',', '.') ?></p>
                <p class="text-sm text-gray-600">Avg Order: Rp <?= number_format($stats['revenue']['avg_order'], 0, ',', '.') ?></p>
            </div>
        </div>

        <!-- Products Card -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-gray-500 text-sm">Products</h3>
            <p class="text-2xl font-semibold"><?= number_format($stats['products']['total']) ?></p>
            <div class="mt-2">
                <p class="text-sm text-yellow-600">Low Stock: <?= number_format($stats['products']['low_stock']) ?></p>
                <p class="text-sm text-red-600">Out of Stock: <?= number_format($stats['products']['out_of_stock']) ?></p>
            </div>
            <div class="mt-4 pt-4 border-t">
                <div class="text-xs font-medium text-gray-500 mb-1">Top Categories</div>
                <?php foreach ($stats['products']['categories'] as $category): ?>
                <div class="flex justify-between items-center text-sm">
                    <span><?= $category['name'] ?></span>
                    <span class="font-medium"><?= $category['count'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Users Card -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-gray-500 text-sm">Users & Admins</h3>
            <p class="text-2xl font-semibold"><?= number_format($stats['users']['total']) ?></p>
            <div class="mt-2">
                <p class="text-sm text-gray-600">New This Month: <?= number_format($stats['users']['new_this_month']) ?></p>
                <p class="text-sm text-gray-600">Admins: <?= number_format($stats['admins']) ?></p>
            </div>
        </div>
    </div>

    <!-- Top Products -->
    <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Top Selling Products</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Orders</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity Sold</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current Stock</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($topProducts as $product): ?>
                    <tr>
                        <td class="px-6 py-4"><?= htmlspecialchars($product['name']) ?></td>
                        <td class="px-6 py-4"><?= number_format($product['total_orders']) ?></td>
                        <td class="px-6 py-4"><?= number_format($product['total_quantity']) ?></td>
                        <td class="px-6 py-4">
                            <span class="<?= $product['stock'] <= 5 ? 'text-red-600' : 'text-green-600' ?>">
                                <?= number_format($product['stock']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Recent Orders</h2>
            <div class="flex gap-2">
                <button onclick="filterOrders('all')" class="px-3 py-1 text-sm rounded-full bg-gray-100 hover:bg-gray-200">All</button>
                <button onclick="filterOrders('pending_payment')" class="px-3 py-1 text-sm rounded-full bg-yellow-100 hover:bg-yellow-200">Pending</button>
                <button onclick="filterOrders('completed')" class="px-3 py-1 text-sm rounded-full bg-green-100 hover:bg-green-200">Completed</button>
                <button onclick="filterOrders('shipped')" class="px-3 py-1 text-sm rounded-full bg-blue-100 hover:bg-blue-200">Shipped</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shipping</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($recentOrders as $order): ?>
                    <tr class="order-row" data-status="<?= $order['status'] ?>">
                        <td class="px-6 py-4 whitespace-nowrap">
                            #<?= $order['order_number'] ?? $order['id'] ?>
                        </td>
                        <td class="px-6 py-4">
                            <div>
                                <div class="font-medium"><?= htmlspecialchars($order['customer_name']) ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($order['customer_email']) ?></div>
                                <?php if ($order['user_roles']): ?>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($order['user_roles']) ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4">Rp <?= number_format($order['total'], 0, ',', '.') ?></td>
                        <td class="px-6 py-4">
                            <div class="text-sm">
                                <div><?= ucfirst($order['payment_method'] ?? '-') ?></div>
                                <?php if ($order['transfer_proof_url']): ?>
                                    <a href="<?= htmlspecialchars($order['transfer_proof_url']) ?>" class="text-blue-600 hover:text-blue-800 text-xs" target="_blank">View Proof</a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs rounded-full 
                                <?= $order['status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                    ($order['status'] === 'pending_payment' ? 'bg-yellow-100 text-yellow-800' : 
                                    ($order['status'] === 'shipped' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800')) ?>">
                                <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm">
                                <?php if ($order['courier_name']): ?>
                                    <div><?= htmlspecialchars($order['courier_name']) ?></div>
                                    <?php if ($order['tracking_number']): ?>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($order['tracking_number']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4"><?= date('d M Y', strtotime($order['created_at'])) ?></td>
                        <td class="px-6 py-4">
                            <a href="/admin/orders/<?= $order['id'] ?>" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function filterOrders(status) {
    const rows = document.querySelectorAll('.order-row');
    rows.forEach(row => {
        if (status === 'all' || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script> 