<?php
require_once __DIR__ . '/../../includes/AdminAuth.php';
require_once __DIR__ . '/../../config/database.php';
AdminAuth::requireAdmin();

$db = new Database();
$conn = $db->getConnection();

// Fetch dashboard statistics with role information
$stats = [
    'orders' => $conn->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'users' => $conn->query("
        SELECT COUNT(DISTINCT u.id) 
        FROM users u 
        LEFT JOIN user_roles ur ON u.id = ur.user_id 
        LEFT JOIN roles r ON ur.role_id = r.id 
        WHERE u.deleted_at IS NULL
    ")->fetchColumn(),
    'revenue' => $conn->query("
        SELECT COALESCE(SUM(total_amount), 0) 
        FROM orders 
        WHERE status = 'completed'
    ")->fetchColumn(),
    'admins' => $conn->query("
        SELECT COUNT(DISTINCT u.id) 
        FROM users u 
        JOIN user_roles ur ON u.id = ur.user_id 
        JOIN roles r ON ur.role_id = r.id 
        WHERE r.name = 'admin' AND u.deleted_at IS NULL
    ")->fetchColumn()
];

// Update recent orders query to include user role information
$recentOrders = $conn->query("
    SELECT o.*, 
           o.total_amount as total,
           u.username as customer_name,
           u.email as customer_email,
           GROUP_CONCAT(r.name) as user_roles
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    WHERE u.deleted_at IS NULL
    GROUP BY o.id
    ORDER BY o.created_at DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-8">Admin Dashboard</h1>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-gray-500 text-sm">Total Orders</h3>
            <p class="text-2xl font-semibold"><?= number_format($stats['orders']) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-gray-500 text-sm">Total Users</h3>
            <p class="text-2xl font-semibold"><?= number_format($stats['users']) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-gray-500 text-sm">Total Revenue</h3>
            <p class="text-2xl font-semibold">Rp <?= number_format($stats['revenue'], 0, ',', '.') ?></p>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-gray-500 text-sm">Total Admins</h3>
            <p class="text-2xl font-semibold"><?= number_format($stats['admins']) ?></p>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent Orders</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php
                    foreach ($recentOrders as $order):
                    ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">#<?= $order['id'] ?></td>
                        <td class="px-6 py-4"><?= htmlspecialchars($order['customer_name']) ?></td>
                        <td class="px-6 py-4">Rp <?= number_format($order['total'], 0, ',', '.') ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs rounded-full 
                                <?= $order['status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                    ($order['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                                <?= ucfirst($order['status']) ?>
                            </span>
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