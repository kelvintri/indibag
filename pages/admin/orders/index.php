<?php
require_once __DIR__ . '/../../../includes/AdminAuth.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';

AdminAuth::requireAdmin();

$db = new Database();
$conn = $db->getConnection();

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

// Build query conditions
$conditions = ['1=1']; // Always true condition to start with
$params = [];

if ($search) {
    $conditions[] = "(o.order_number LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($status) {
    $conditions[] = "o.status = :status";
    $params[':status'] = $status;
}

$where_clause = implode(' AND ', $conditions);

// Get total orders count
$count_sql = "SELECT COUNT(*) FROM orders o 
              LEFT JOIN users u ON o.user_id = u.id 
              WHERE $where_clause";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_orders = $count_stmt->fetchColumn();
$total_pages = ceil($total_orders / $per_page);

// Get orders
$sql = "SELECT o.*, u.email as user_email, u.full_name as user_name,
        (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count,
        (SELECT SUM(quantity) FROM order_items oi WHERE oi.order_id = o.id) as total_items
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE u.is_active = 1 AND u.deleted_at IS NULL
        AND $where_clause
        ORDER BY o.$sort_by $sort_order
        LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{ 
         showModal: false,
         order: null,
         showStatusForm: false,
         newStatus: '',
         statusNotes: '',
         isUpdating: false,
         showShippingForm: false,
         shippingDetails: {
             courier_name: '',
             service_type: '',
             tracking_number: '',
             shipping_cost: '',
             estimated_delivery_date: '',
             notes: ''
         },
         async viewOrder(orderId) {
             try {
                 const response = await fetch(`/admin/orders/${orderId}`);
                 const result = await response.json();
                 if (result.success) {
                     this.order = result.data;
                     this.newStatus = this.order.status;
                 } else {
                     throw new Error(result.message || 'Failed to fetch order details');
                 }
                 this.showModal = true;
             } catch (error) {
                 console.error('Error:', error);
                 alert('Error fetching order details');
             }
         },
         async updateStatus(e) {
             try {
                 this.isUpdating = true;
                 const formData = new FormData();
                 formData.append('order_id', this.order.id);
                 formData.append('status', this.newStatus);
                 formData.append('notes', this.statusNotes);
                 
                 console.log('Sending status:', this.newStatus);
                 
                 const response = await fetch('/admin/orders/update-status', {
                     method: 'POST',
                     body: formData
                 });
                 
                 const result = await response.json();
                 if (result.success) {
                     this.order.status = this.newStatus;
                     this.showStatusForm = false;
                     this.statusNotes = '';
                     window.location.reload(); // Refresh to show updated status
                 } else {
                     throw new Error(result.message || 'Failed to update status');
                 }
             } catch (error) {
                 console.error('Error:', error);
                 alert('Error updating status: ' + error.message);
             } finally {
                 this.isUpdating = false;
             }
         },
         async updateShipping(e) {
             try {
                 this.isUpdating = true;
                 const formData = new FormData();
                 formData.append('order_id', this.order.id);
                 Object.keys(this.shippingDetails).forEach(key => {
                     formData.append(key, this.shippingDetails[key]);
                 });
                 
                 const response = await fetch('/admin/orders/update-shipping', {
                     method: 'POST',
                     body: formData
                 });
                 
                 const result = await response.json();
                 if (result.success) {
                     this.showShippingForm = false;
                     window.location.reload();
                 } else {
                     throw new Error(result.message || 'Failed to update shipping details');
                 }
             } catch (error) {
                 console.error('Error:', error);
                 alert('Error updating shipping details: ' + error.message);
             } finally {
                 this.isUpdating = false;
             }
         }
     }" @keydown.escape="showModal = false">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Orders</h1>
    </div>

    <!-- Search and Filter Controls -->
    <div class="mb-6 flex flex-col sm:flex-row gap-4">
        <form class="flex-1" method="GET">
            <div class="flex gap-2">
                <input type="text" 
                       name="search" 
                       value="<?= htmlspecialchars($search) ?>"
                       placeholder="Search by order number or email..."
                       class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <select name="status" 
                        class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All Status</option>
                    <?php
                    $statuses = [
                        'pending_payment' => 'Pending Payment',
                        'payment_uploaded' => 'Payment Uploaded',
                        'payment_verified' => 'Payment Verified',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded'
                    ];
                    foreach ($statuses as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" 
                        class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                    Search
                </button>
                <?php if ($search || $status): ?>
                    <a href="/admin/orders" 
                       class="bg-gray-100 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-200">
                        Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Orders Table -->
    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order Number</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?= htmlspecialchars($order['order_number']) ?>
                    </td>
                    <td class="px-6 py-4">
                        <?= htmlspecialchars($order['user_email']) ?>
                    </td>
                    <td class="px-6 py-4">
                        <?= number_format($order['total_items']) ?> items
                    </td>
                    <td class="px-6 py-4">
                        Rp <?= number_format($order['total_amount'], 0, ',', '.') ?>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                            <?php
                            switch ($order['status']) {
                                case 'pending_payment':
                                    echo 'bg-yellow-100 text-yellow-800';
                                    break;
                                case 'payment_uploaded':
                                    echo 'bg-blue-100 text-blue-800';
                                    break;
                                case 'payment_verified':
                                    echo 'bg-green-100 text-green-800';
                                    break;
                                case 'processing':
                                    echo 'bg-blue-100 text-blue-800';
                                    break;
                                case 'shipped':
                                    echo 'bg-purple-100 text-purple-800';
                                    break;
                                case 'delivered':
                                    echo 'bg-green-100 text-green-800';
                                    break;
                                case 'cancelled':
                                    echo 'bg-red-100 text-red-800';
                                    break;
                                case 'refunded':
                                    echo 'bg-gray-100 text-gray-800';
                                    break;
                                default:
                                    echo 'bg-gray-100 text-gray-800';
                            }
                            ?>">
                            <?= ucfirst(htmlspecialchars($order['status'])) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?= date('d M Y H:i', strtotime($order['created_at'])) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex items-center space-x-3">
                            <button @click="viewOrder(<?= $order['id'] ?>)"
                                    class="text-blue-600 hover:text-blue-900 p-1 rounded-full hover:bg-blue-50"
                                    title="View Order Details">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>

                            <button @click="order = { id: <?= $order['id'] ?>, status: '<?= $order['status'] ?>' }; 
                                    newStatus = '<?= $order['status'] ?>'; 
                                    showStatusForm = true"
                                    class="text-green-600 hover:text-green-900 p-1 rounded-full hover:bg-green-50"
                                    title="Update Order Status">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </button>

                            <button @click="order = { id: <?= $order['id'] ?> }; 
                                    shippingDetails = {
                                        courier_name: '',
                                        service_type: '',
                                        tracking_number: '',
                                        shipping_cost: '',
                                        estimated_delivery_date: '',
                                        notes: ''
                                    };
                                    showShippingForm = true"
                                    class="text-purple-600 hover:text-purple-900 p-1 rounded-full hover:bg-purple-50"
                                    title="Update Shipping Details">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                               class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing
                                <span class="font-medium"><?= $offset + 1 ?></span>
                                to
                                <span class="font-medium"><?= min($offset + $per_page, $total_orders) ?></span>
                                of
                                <span class="font-medium"><?= $total_orders ?></span>
                                results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                <?php endif; ?>

                                <?php
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);
                                
                                if ($start > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>
                                    <?php if ($start > 2): ?>
                                        <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $start; $i <= $end; $i++): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?= $i === $page ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-50' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($end < $total_pages): ?>
                                    <?php if ($end < $total_pages - 1): ?>
                                        <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>
                                    <?php endif; ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"><?= $total_pages ?></a>
                                <?php endif; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                       class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Next</span>
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal -->
    <div x-show="showModal" 
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <!-- Overlay -->
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>

        <!-- Modal Content -->
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="relative w-full max-w-3xl rounded-lg bg-white shadow-xl" @click.away="showModal = false">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b p-4">
                    <h3 class="text-xl font-semibold text-gray-900">
                        Order Details
                    </h3>
                    <button @click="showModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6">
                    <template x-if="order">
                        <div class="space-y-6">
                            <!-- Order Info -->
                            <div>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">Order Information</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Order Number</p>
                                        <p class="mt-1" x-text="order.order_number"></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Status</p>
                                        <p class="mt-1">
                                            <span x-text="order.status.replace('_', ' ')" 
                                                  class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                  :class="{
                                                      'bg-yellow-100 text-yellow-800': order.status === 'pending_payment',
                                                      'bg-blue-100 text-blue-800': order.status === 'payment_uploaded',
                                                      'bg-green-100 text-green-800': order.status === 'payment_verified',
                                                      'bg-blue-100 text-blue-800': order.status === 'processing',
                                                      'bg-purple-100 text-purple-800': order.status === 'shipped',
                                                      'bg-green-100 text-green-800': order.status === 'delivered',
                                                      'bg-red-100 text-red-800': order.status === 'cancelled',
                                                      'bg-gray-100 text-gray-800': order.status === 'refunded'
                                                  }">
                                            </span>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Date</p>
                                        <p class="mt-1" x-text="order.created_at ? new Date(order.created_at).toLocaleString('en-US', { 
                                            year: 'numeric', 
                                            month: 'long', 
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit'
                                        }) : ''"></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Total Amount</p>
                                        <p class="mt-1" x-text="order.total_amount ? 'Rp ' + Number(order.total_amount).toLocaleString('id-ID') : ''"></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Customer Info -->
                            <div>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">Customer Information</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Name</p>
                                        <p class="mt-1" x-text="order.full_name || order.user_name"></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Email</p>
                                        <p class="mt-1" x-text="order.user_email"></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Phone</p>
                                        <p class="mt-1" x-text="order.phone || '-'"></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Shipping Address -->
                            <div>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">Shipping Address</h4>
                                <template x-if="order.street_address">
                                    <div>
                                        <p x-text="order.street_address"></p>
                                        <p x-text="order.district"></p>
                                        <p x-text="order.city + ' ' + order.postal_code"></p>
                                        <p x-text="order.province"></p>
                                        <p x-text="'Phone: ' + order.phone"></p>
                                    </div>
                                </template>
                            </div>

                            <!-- Payment Info -->
                            <div>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">Payment Information</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Payment Method</p>
                                        <p class="mt-1" x-text="order.payment_method ? order.payment_method.replace('_', ' ') : ''"></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Payment Amount</p>
                                        <p class="mt-1" x-text="order.payment_amount ? 'Rp ' + Number(order.payment_amount).toLocaleString('id-ID') : '-'"></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Payment Date</p>
                                        <p class="mt-1" x-text="order.payment_date ? new Date(order.payment_date).toLocaleString('en-US', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit'
                                        }) : '-'"></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Verification Status</p>
                                        <p class="mt-1" x-text="order.verified_at ? 'Verified' : 'Not Verified'"></p>
                                    </div>
                                    <div x-show="order.verified_at">
                                        <p class="text-sm font-medium text-gray-500">Verified At</p>
                                        <p class="mt-1" x-text="new Date(order.verified_at).toLocaleString('en-US', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit'
                                        })"></p>
                                    </div>
                                    <div x-show="order.verified_by_username">
                                        <p class="text-sm font-medium text-gray-500">Verified By</p>
                                        <p class="mt-1">
                                            <span x-text="order.verified_by_name || order.verified_by_username"></span>
                                            <span class="text-gray-500" x-show="order.verified_by_name">
                                                (<span x-text="order.verified_by_username"></span>)
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                <!-- Payment Proof Image -->
                                <template x-if="order.transfer_proof_url">
                                    <div class="mt-4">
                                        <p class="text-sm font-medium text-gray-500 mb-2">Payment Proof</p>
                                        <div class="relative">
                                            <img :src="order.transfer_proof_url"
                                                 class="max-w-md rounded-lg shadow-sm"
                                                 @error="$event.target.src='/assets/images/placeholder.jpg'"
                                                 alt="Payment Proof">
                                            <a :href="order.transfer_proof_url"
                                               target="_blank"
                                               class="absolute top-2 right-2 bg-white p-2 rounded-full shadow hover:bg-gray-100">
                                               <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                         d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                               </svg>
                                            </a>
                                        </div>
                                    </div>
                                </template>

                                <!-- Payment Notes -->
                                <template x-if="order.notes">
                                    <div class="mt-4">
                                        <p class="text-sm font-medium text-gray-500 mb-2">Notes</p>
                                        <p class="text-sm text-gray-600" x-text="order.notes"></p>
                                    </div>
                                </template>
                            </div>

                            <!-- Order Items -->
                            <div>
                                <h4 class="text-lg font-medium text-gray-900 mb-4">Order Items</h4>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Price</th>
                                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <template x-for="item in order.items" :key="item.id">
                                            <tr>
                                                <td class="px-3 py-4">
                                                    <div class="flex items-center">
                                                        <div class="h-10 w-10 flex-shrink-0">
                                                            <img :src="item.product_image" 
                                                                 :alt="item.product_name"
                                                                 class="h-10 w-10 rounded object-cover"
                                                                 @error="$event.target.src='/assets/images/placeholder.jpg'">
                                                        </div>
                                                        <div class="ml-4">
                                                            <a :href="'/products/' + item.slug" 
                                                               class="font-medium text-gray-900 hover:text-blue-600"
                                                               x-text="item.product_name">
                                                            </a>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-4" x-text="item.sku"></td>
                                                <td class="px-3 py-4 text-right" 
                                                    x-text="'Rp ' + Number(item.price).toLocaleString('id-ID')"></td>
                                                <td class="px-3 py-4 text-right" 
                                                    x-text="Number(item.quantity).toLocaleString('id-ID')"></td>
                                                <td class="px-3 py-4 text-right" 
                                                    x-text="'Rp ' + Number(item.price * item.quantity).toLocaleString('id-ID')"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr>
                                            <td colspan="4" class="px-3 py-4 text-right font-medium">Total</td>
                                            <td class="px-3 py-4 text-right font-medium" 
                                                x-text="'Rp ' + Number(order.total_amount).toLocaleString('id-ID')"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Update Form -->
    <div x-show="showStatusForm" 
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="fixed inset-0 bg-black bg-opacity-50"></div>
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="relative w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Update Order Status</h3>
                    <form @submit.prevent="updateStatus">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">New Status</label>
                                <select name="status" 
                                        x-model="newStatus"
                                        @change="console.log('Selected status:', $event.target.value)"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <?php foreach ($statuses as $value => $label): ?>
                                        <option value="<?= $value ?>"><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Notes</label>
                                <textarea name="notes"
                                        x-model="statusNotes"
                                        rows="3"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" 
                                    @click="showStatusForm = false"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                    :disabled="isUpdating"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50">
                                <span x-show="!isUpdating">Update Status</span>
                                <span x-show="isUpdating">Updating...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Shipping Update Form -->
    <div x-show="showShippingForm" 
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="fixed inset-0 bg-black bg-opacity-50"></div>
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="relative w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Update Shipping Details</h3>
                    <form @submit.prevent="updateShipping">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Courier Name</label>
                                <input type="text" 
                                       x-model="shippingDetails.courier_name"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Service Type</label>
                                <input type="text" 
                                       x-model="shippingDetails.service_type"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Tracking Number</label>
                                <input type="text" 
                                       x-model="shippingDetails.tracking_number"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Shipping Cost</label>
                                <input type="number" 
                                       x-model="shippingDetails.shipping_cost"
                                       step="0.01"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Estimated Delivery Date</label>
                                <input type="date" 
                                       x-model="shippingDetails.estimated_delivery_date"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Notes</label>
                                <textarea x-model="shippingDetails.notes"
                                        rows="3"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" 
                                    @click="showShippingForm = false"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                    :disabled="isUpdating"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50">
                                <span x-show="!isUpdating">Save Shipping Details</span>
                                <span x-show="isUpdating">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div> 