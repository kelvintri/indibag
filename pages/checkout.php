<?php
Auth::requireLogin();

$db = new Database();
$conn = $db->getConnection();
$cart = new Cart();

// Get cart items and total
$cartItems = $cart->getItems();
$total = $cart->getTotal();

// Get user's addresses
$query = "SELECT * FROM addresses WHERE user_id = :user_id ORDER BY is_default DESC, created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Redirect if no addresses
if (empty($addresses)) {
    header('Location: /profile/addresses');
    exit;
}

// Get user data
$userQuery = "SELECT * FROM users WHERE id = :user_id";
$userStmt = $conn->prepare($userQuery);
$userStmt->bindParam(":user_id", $_SESSION['user_id']);
$userStmt->execute();
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="checkoutForm">
    <h1 class="text-2xl font-bold text-gray-900 mb-8">Checkout</h1>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-8">
            <!-- Shipping Address -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Shipping Address</h2>
                <div class="space-y-4">
                    <?php foreach ($addresses as $address): ?>
                        <label class="block">
                            <div class="flex items-center">
                                <input type="radio" 
                                       name="shipping_address" 
                                       value="<?= $address['id'] ?>"
                                       x-model="selectedAddressId"
                                       class="rounded-full border-gray-300 text-blue-600 focus:ring-blue-500">
                                <div class="ml-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium"><?= htmlspecialchars($address['recipient_name']) ?></span>
                                        <?php if ($address['is_default']): ?>
                                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Default</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-gray-600 text-sm"><?= htmlspecialchars($address['phone']) ?></p>
                                    <p class="text-gray-600 text-sm"><?= htmlspecialchars($address['street_address']) ?></p>
                                    <p class="text-gray-600 text-sm">
                                        <?= htmlspecialchars($address['district']) ?>,
                                        <?= htmlspecialchars($address['city']) ?>
                                    </p>
                                    <p class="text-gray-600 text-sm">
                                        <?= htmlspecialchars($address['province']) ?>,
                                        <?= htmlspecialchars($address['postal_code']) ?>
                                    </p>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-4">
                    <a href="/profile/addresses" class="text-sm text-blue-600 hover:text-blue-800">
                        Add New Address
                    </a>
                </div>
            </div>

            <!-- Payment Method -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment Method</h2>
                <div class="space-y-4">
                    <label class="block">
                        <div class="flex items-center">
                            <input type="radio" 
                                   name="payment_method" 
                                   value="bank_transfer"
                                   x-model="paymentMethod"
                                   class="rounded-full border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-3">Bank Transfer</span>
                        </div>
                    </label>
                    
                    <label class="block">
                        <div class="flex items-center">
                            <input type="radio" 
                                   name="payment_method" 
                                   value="e-wallet"
                                   x-model="paymentMethod"
                                   class="rounded-full border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-3">E-Wallet</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Order Items -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Items</h2>
                <div class="space-y-4">
                    <?php foreach ($cartItems as $item): ?>
                        <div class="flex items-center">
                            <div class="w-20 h-20 flex-shrink-0">
                                <img src="<?= getImageUrl($item['image_url']) ?>" 
                                     alt="<?= htmlspecialchars($item['name']) ?>"
                                     class="w-full h-full object-contain rounded-md">
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($item['name']) ?>
                                </h3>
                                <p class="text-sm text-gray-500">
                                    Quantity: <?= $item['quantity'] ?>
                                </p>
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
                        <span class="text-gray-900">Rp <?= number_format($total, 0, ',', '.') ?></span>
                    </div>
                    
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Shipping</span>
                        <span class="text-gray-900">Rp 0</span>
                    </div>
                    
                    <div class="border-t border-gray-200 mt-4 pt-4">
                        <div class="flex justify-between">
                            <span class="text-base font-medium text-gray-900">Total</span>
                            <span class="text-base font-medium text-gray-900">
                                Rp <?= number_format($total, 0, ',', '.') ?>
                            </span>
                        </div>
                    </div>
                </div>

                <button @click="placeOrder()" 
                        :disabled="isProcessing"
                        class="w-full mt-6 bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
                    <span x-text="isProcessing ? 'Processing...' : 'Place Order'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('checkoutForm', () => ({
        selectedAddressId: <?= htmlspecialchars($addresses[0]['id']) ?>,
        paymentMethod: 'bank_transfer',
        isProcessing: false,

        async placeOrder() {
            if (this.isProcessing) return;
            
            try {
                this.isProcessing = true;
                
                const response = await fetch('/orders/create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        shipping_address_id: this.selectedAddressId,
                        payment_method: this.paymentMethod
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.location.href = `/orders/${result.order_id}`;
                } else {
                    alert(result.message || 'Error creating order');
                }
            } catch (error) {
                console.error('Error placing order:', error);
                alert('Error placing order. Please try again.');
            } finally {
                this.isProcessing = false;
            }
        }
    }));
});
</script> 