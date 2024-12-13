<?php
Auth::requireLogin();

$db = new Database();
$conn = $db->getConnection();
$order_id = $matches[1];

// Get order details using Order class
$orderObj = new Order();
$order = $orderObj->getOrder($order_id);

// Handle 404 through routing instead of direct redirect
if (!$order) {
    $content = ROOT_PATH . '/pages/404.php';
    $pageTitle = '404 Not Found - Bananina';
    require_once ROOT_PATH . '/layouts/main.php';
    exit;
}

// Get order items
$items = $orderObj->getOrderItems($order_id);

// Helper function for status styling
function getStatusStyle($status) {
    return match($status) {
        'pending_payment' => 'bg-yellow-100 text-yellow-800',
        'payment_uploaded' => 'bg-blue-100 text-blue-800',
        'payment_verified', 'processing' => 'bg-indigo-100 text-indigo-800',
        'shipped' => 'bg-purple-100 text-purple-800',
        'delivered' => 'bg-green-100 text-green-800',
        'cancelled', 'refunded' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-800'
    };
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" 
     x-data="{ 
         showModal: false, 
         file: null, 
         preview: null, 
         dragover: false, 
         isUploading: false,

         handleFileSelect(event) {
             const file = event.target.files[0];
             this.setFile(file);
         },

         handleDrop(event) {
             this.dragover = false;
             const file = event.dataTransfer.files[0];
             this.setFile(file);
         },

         setFile(file) {
             if (!file || !file.type.startsWith('image/')) {
                 alert('Please upload an image file');
                 return;
             }

             if (file.size > 5 * 1024 * 1024) {
                 alert('File size should be less than 5MB');
                 return;
             }

             this.file = file;
             const reader = new FileReader();
             reader.onload = e => this.preview = e.target.result;
             reader.readAsDataURL(file);
         },

         removeFile() {
             this.file = null;
             this.preview = null;
         },

         async uploadPayment() {
             if (!this.file) return;
             
             try {
                 this.isUploading = true;
                 const formData = new FormData();
                 formData.append('order_id', <?= $order_id ?>);
                 formData.append('payment_proof', this.file);

                 const response = await fetch('/orders/upload-payment', {
                     method: 'POST',
                     body: formData
                 });

                 const result = await response.json();
                 
                 if (result.success) {
                     window.location.reload();
                 } else {
                     alert(result.message || 'Error uploading payment proof');
                 }
             } catch (error) {
                 console.error('Error:', error);
                 alert('Error uploading payment proof');
             } finally {
                 this.isUploading = false;
             }
         }
     }">
    <!-- Order Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Order #<?= $order_id ?></h1>
                <p class="text-gray-600">
                    Placed on <?= date('F j, Y', strtotime($order['created_at'])) ?>
                </p>
            </div>
            <a href="/orders" class="text-blue-600 hover:text-blue-800 flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Orders
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-8 space-y-6">
            <!-- Order Status -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Status</h2>
                <div class="flex items-center justify-between">
                    <span class="px-3 py-1 rounded-full text-sm <?= getStatusStyle($order['status']) ?>">
                        <?= ucwords(str_replace('_', ' ', $order['status'])) ?>
                    </span>
                    <?php if ($order['status'] === 'shipped'): ?>
                        <span class="text-gray-600">
                            Shipped on <?= date('F j, Y', strtotime($order['updated_at'])) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment Details -->
            <?php if ($order['status'] === 'pending_payment' || $order['status'] === 'payment_uploaded'): ?>
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment Details</h2>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Payment Method</p>
                        <p class="mt-1">
                            <?= $order['payment_method'] ? str_replace('_', ' ', ucwords($order['payment_method'])) : '-' ?>
                        </p>
                    </div>
                    <?php if ($order['payment_date']): ?>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Payment Date</p>
                        <p class="mt-1"><?= date('F j, Y H:i', strtotime($order['payment_date'])) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['transfer_proof_url']): ?>
                        <div class="mt-4">
                            <span class="text-gray-600 block mb-2">Payment Proof</span>
                            <div class="relative group">
                                <img src="<?= $order['transfer_proof_url'] ?>" 
                                     alt="Payment Proof" 
                                     class="max-w-xs rounded-lg shadow-sm">
                                <?php if ($orderObj->canReuploadPayment($order_id)): ?>
                                    <div class="mt-2">
                                        <button @click="showModal = true" 
                                                class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                            </svg>
                                            Reupload Payment Proof
                                        </button>
                                        <p class="text-xs text-gray-500 mt-1">
                                            You can reupload if the previous proof was incorrect
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($order['status'] === 'pending_payment'): ?>
                        <div class="mt-4">
                            <button @click="showModal = true" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                Upload Payment Proof
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Shipping Address -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Shipping Address</h2>
                <div class="space-y-1">
                    <p class="font-medium"><?= htmlspecialchars($order['recipient_name']) ?></p>
                    <p class="text-gray-600"><?= htmlspecialchars($order['phone']) ?></p>
                    <p class="text-gray-600"><?= htmlspecialchars($order['street_address']) ?></p>
                    <p class="text-gray-600">
                        <?= htmlspecialchars($order['district']) ?>,
                        <?= htmlspecialchars($order['city']) ?>
                    </p>
                    <p class="text-gray-600">
                        <?= htmlspecialchars($order['province']) ?>,
                        <?= htmlspecialchars($order['postal_code']) ?>
                    </p>
                </div>
            </div>

            <!-- Shipping Details -->
            <?php if ($order['status'] === 'shipped'): ?>
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Shipping Details</h2>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Courier</p>
                            <p class="mt-1"><?= htmlspecialchars($order['courier_name']) ?></p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Service Type</p>
                            <p class="mt-1"><?= htmlspecialchars($order['service_type'] ?? '-') ?></p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Tracking Number</p>
                            <p class="mt-1"><?= htmlspecialchars($order['tracking_number'] ?? '-') ?></p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Shipping Cost</p>
                            <p class="mt-1">Rp <?= number_format($order['shipping_cost'] ?? 0, 0, ',', '.') ?></p>
                        </div>
                    </div>
                    <?php if ($order['estimated_delivery_date']): ?>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Estimated Delivery</p>
                        <p class="mt-1"><?= date('F j, Y', strtotime($order['estimated_delivery_date'])) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['shipping_notes']): ?>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Notes</p>
                        <p class="mt-1 text-gray-600"><?= nl2br(htmlspecialchars($order['shipping_notes'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Order Items -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Items</h2>
                <div class="space-y-4">
                    <?php foreach ($items as $item): ?>
                        <div class="flex items-center">
                            <div class="w-20 h-20 flex-shrink-0">
                                <img src="<?= getImageUrl($item['image_url']) ?>" 
                                     alt="<?= htmlspecialchars($item['name']) ?>"
                                     class="w-full h-full object-contain rounded-md">
                            </div>
                            <div class="ml-4 flex-1">
                                <a href="/products/<?= htmlspecialchars($item['slug']) ?>" 
                                   class="text-sm font-medium text-gray-900 hover:text-blue-600">
                                    <?= htmlspecialchars($item['name']) ?>
                                </a>
                                <p class="text-sm text-gray-500">Quantity: <?= $item['quantity'] ?></p>
                                <p class="text-sm font-medium text-gray-900">
                                    Rp <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="lg:col-span-4">
            <div class="bg-white rounded-lg shadow-sm p-6 sticky top-4">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Summary</h2>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="text-gray-900">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Shipping</span>
                        <span class="text-gray-900">
                            Rp <?= number_format($order['shipping_cost'] ?? 0, 0, ',', '.') ?>
                        </span>
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
        </div>
    </div>

    <!-- Payment Upload Modal -->
    <template x-teleport="body">
        <div x-show="showModal" 
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             @keydown.escape.window="showModal = false">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" @click="showModal = false">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>

                <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            <?= $order['transfer_proof_url'] ? 'Reupload Payment Proof' : 'Upload Payment Proof' ?>
                        </h3>
                        <button @click="showModal = false" class="text-gray-400 hover:text-gray-500">
                            <span class="sr-only">Close</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="uploadPayment" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Payment Amount</label>
                            <p class="text-lg font-semibold text-gray-900">
                                Rp <?= number_format($order['total_amount'], 0, ',', '.') ?>
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Upload Proof</label>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md"
                                 @dragover.prevent="dragover = true"
                                 @dragleave.prevent="dragover = false"
                                 @drop.prevent="handleDrop($event)"
                                 :class="{'border-blue-500 bg-blue-50': dragover}">
                                <div class="space-y-1 text-center">
                                    <template x-if="!preview">
                                        <div>
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <div class="flex text-sm text-gray-600">
                                                <label class="relative cursor-pointer rounded-md font-medium text-blue-600 hover:text-blue-500">
                                                    <span>Upload a file</span>
                                                    <input type="file" 
                                                           @change="handleFileSelect"
                                                           accept="image/*"
                                                           class="sr-only">
                                                </label>
                                                <p class="pl-1">or drag and drop</p>
                                            </div>
                                            <p class="text-xs text-gray-500">PNG, JPG up to 5MB</p>
                                        </div>
                                    </template>
                                    <template x-if="preview">
                                        <div class="relative">
                                            <img :src="preview" class="max-h-48 mx-auto">
                                            <button @click.prevent="removeFile" 
                                                    class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button" @click="showModal = false"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-500">
                                Cancel
                            </button>
                            <button type="submit"
                                    :disabled="!file || isUploading"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md disabled:bg-gray-400 disabled:cursor-not-allowed">
                                <span x-text="isUploading ? 'Uploading...' : 'Upload Payment'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>