<?php
$cart = new Cart();
$cartItems = $cart->getItems();
$total = $cart->getTotal();

// Get user's addresses if logged in
$addresses = [];
if (Auth::isLoggedIn()) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $query = "SELECT * FROM addresses 
              WHERE user_id = :user_id 
              ORDER BY is_default DESC, created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":user_id", $_SESSION['user_id']);
    $stmt->execute();
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $product_id = $_POST['product_id'] ?? 0;
    
    switch ($action) {
        case 'update':
            $quantity = (int)$_POST['quantity'];
            $result = $cart->update($product_id, $quantity);
            echo json_encode($result);
            break;
            
        case 'remove':
            $result = $cart->remove($product_id);
            echo json_encode($result);
            break;
    }
    exit;
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" 
     x-data="cartPage"
     x-init="initCart(<?= htmlspecialchars(json_encode($cartItems)) ?>, <?= $total ?>)">
    <h1 class="text-2xl font-bold text-gray-900 mb-8">Shopping Cart</h1>
    
    <template x-if="items.length === 0">
        <div class="bg-white rounded-lg shadow-sm p-6 text-center">
            <p class="text-gray-500 mb-4">Your cart is empty</p>
            <a href="/products" class="inline-block bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600">
                Continue Shopping
            </a>
        </div>
    </template>

    <template x-if="items.length > 0">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Cart Items -->
            <div class="lg:col-span-8">
                <template x-for="item in items" :key="item.id">
                    <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6 mb-4">
                        <!-- Mobile Layout -->
                        <div class="flex flex-col sm:flex-row sm:items-center">
                            <!-- Image and Basic Info -->
                            <div class="flex items-start flex-1">
                                <div class="w-20 h-28 sm:w-24 sm:h-32 flex-shrink-0">
                                    <img :src="item.image_url" 
                                         :alt="item.name"
                                         class="w-full h-full object-contain rounded-md">
                                </div>
                                
                                <div class="ml-4 flex-1">
                                    <a :href="'/products/' + item.slug">
                                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 hover:text-blue-600 transition" x-text="item.name"></h3>
                                    </a>
                                    <p class="text-sm text-gray-500 mt-1">
                                        SKU: <span x-text="item.sku"></span>
                                    </p>
                                    
                                    <!-- Mobile Price -->
                                    <p class="text-base font-semibold text-gray-900 mt-2 sm:hidden" 
                                       x-text="formatPrice(item.price)">
                                    </p>
                                </div>
                            </div>

                            <!-- Actions and Price -->
                            <div class="mt-4 sm:mt-0 sm:ml-6 flex flex-col sm:items-end gap-3">
                                <!-- Desktop Price -->
                                <div class="hidden sm:block text-right">
                                    <p class="text-lg font-semibold text-gray-900" 
                                       x-text="formatPrice(item.price * item.quantity)">
                                    </p>
                                    <p class="text-sm text-gray-500" 
                                       x-text="formatPrice(item.price) + ' each'">
                                    </p>
                                </div>

                                <!-- Quantity and Remove -->
                                <div class="flex items-center justify-between sm:justify-end w-full">
                                    <div class="flex items-center gap-3">
                                        <label class="text-sm text-gray-600">Qty:</label>
                                        <select @change="updateQuantity($event, item.id)" 
                                                class="w-20 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <template x-for="n in item.max_quantity" :key="n">
                                                <option :value="n" 
                                                        :selected="n === item.quantity" 
                                                        x-text="n">
                                                </option>
                                            </template>
                                        </select>
                                    </div>
                                    
                                    <button @click="removeItem(item.id)" 
                                            class="text-sm text-red-600 hover:text-red-800 ml-4">
                                        Remove
                                    </button>
                                </div>

                                <!-- Mobile Total -->
                                <p class="text-base font-semibold text-gray-900 sm:hidden" 
                                   x-text="'Total: ' + formatPrice(item.price * item.quantity)">
                                </p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            
            <!-- Order Summary -->
            <div class="lg:col-span-4">
                <div class="bg-white rounded-lg shadow-sm p-6 sticky top-4">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Summary</h2>
                    
                    <!-- Shipping Address Section -->
                    <div class="mb-6">
                        <h3 class="text-sm font-medium text-gray-900 mb-2">Shipping Address</h3>
                        <?php if (Auth::isLoggedIn()): ?>
                            <?php if ($addresses): ?>
                                <div x-data="{ showAddresses: false, selectedAddress: <?= htmlspecialchars(json_encode($addresses[0])) ?> }">
                                    <!-- Selected Address Display -->
                                    <div class="border rounded-md p-3 mb-2 text-sm">
                                        <template x-if="selectedAddress">
                                            <div>
                                                <p class="font-medium" x-text="selectedAddress.recipient_name"></p>
                                                <p class="text-gray-600" x-text="selectedAddress.phone"></p>
                                                <p class="text-gray-600" x-text="selectedAddress.street_address"></p>
                                                <p class="text-gray-600">
                                                    <span x-text="selectedAddress.district"></span>,
                                                    <span x-text="selectedAddress.city"></span>
                                                </p>
                                                <p class="text-gray-600">
                                                    <span x-text="selectedAddress.province"></span>,
                                                    <span x-text="selectedAddress.postal_code"></span>
                                                </p>
                                            </div>
                                        </template>
                                    </div>

                                    <!-- Change Address Button -->
                                    <button @click="showAddresses = !showAddresses"
                                            class="text-sm text-blue-600 hover:text-blue-800">
                                        Change Address
                                    </button>

                                    <!-- Address Selection Modal -->
                                    <div x-show="showAddresses" 
                                         class="fixed inset-0 z-50 overflow-y-auto"
                                         @click.away="showAddresses = false">
                                        <div class="flex items-center justify-center min-h-screen px-4">
                                            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                                            
                                            <div class="relative bg-white rounded-lg max-w-md w-full p-6">
                                                <h3 class="text-lg font-medium mb-4">Select Shipping Address</h3>
                                                
                                                <div class="space-y-4 max-h-96 overflow-y-auto">
                                                    <?php foreach ($addresses as $address): ?>
                                                        <div class="border rounded-md p-3 cursor-pointer hover:border-blue-500"
                                                             @click="selectedAddress = <?= htmlspecialchars(json_encode($address)) ?>; showAddresses = false">
                                                            <p class="font-medium"><?= htmlspecialchars($address['recipient_name']) ?></p>
                                                            <p class="text-gray-600"><?= htmlspecialchars($address['phone']) ?></p>
                                                            <p class="text-gray-600"><?= htmlspecialchars($address['street_address']) ?></p>
                                                            <p class="text-gray-600">
                                                                <?= htmlspecialchars($address['district']) ?>,
                                                                <?= htmlspecialchars($address['city']) ?>
                                                            </p>
                                                            <p class="text-gray-600">
                                                                <?= htmlspecialchars($address['province']) ?>,
                                                                <?= htmlspecialchars($address['postal_code']) ?>
                                                            </p>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                
                                                <div class="mt-4 flex justify-between">
                                                    <button @click="showAddresses = false"
                                                            class="text-gray-600 hover:text-gray-800">
                                                        Cancel
                                                    </button>
                                                    <a href="/profile/addresses" 
                                                       class="text-blue-600 hover:text-blue-800">
                                                        Add New Address
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-sm text-gray-600 mb-2">
                                    No shipping address found.
                                    <a href="/profile/addresses" class="text-blue-600 hover:text-blue-800">
                                        Add an address
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-sm text-gray-600 mb-2">
                                Please <a href="/login" class="text-blue-600 hover:text-blue-800">login</a> 
                                to add a shipping address.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Rest of your order summary -->
                    <div class="border-t border-gray-200 pt-4">
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="text-gray-900" x-text="formatPrice(total)"></span>
                        </div>
                        
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-600">Shipping</span>
                            <span class="text-gray-900" x-text="formatPrice(calculateShipping())"></span>
                        </div>
                        
                        <div class="border-t border-gray-200 mt-4 pt-4">
                            <div class="flex justify-between mb-4">
                                <span class="text-lg font-semibold text-gray-900">Total</span>
                                <span class="text-lg font-semibold text-gray-900" x-text="formatPrice(total + calculateShipping())"></span>
                            </div>
                            
                            <?php if (Auth::isLoggedIn() && $addresses): ?>
                                <a href="/checkout" 
                                   class="block w-full bg-blue-600 text-white text-center px-6 py-3 rounded-md hover:bg-blue-700">
                                    Proceed to Checkout
                                </a>
                            <?php else: ?>
                                <button disabled 
                                        class="w-full bg-gray-300 text-gray-500 px-6 py-3 rounded-md cursor-not-allowed">
                                    <?= Auth::isLoggedIn() ? 'Add shipping address to continue' : 'Login to checkout' ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('cartPage', () => ({
        items: [],
        total: 0,

        calculateShipping() {
            const totalQty = this.items.reduce((sum, item) => sum + item.quantity, 0);
            return totalQty * 50000; // Rp 50.000 per item
        },

        initCart(items, total) {
            this.items = items;
            this.total = total;
        },

        async updateQuantity(event, productId) {
            const quantity = event.target.value;
            
            try {
                const response = await fetch('/cart', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=update&product_id=${productId}&quantity=${quantity}`
                });
                
                const result = await response.json();
                if (result.success) {
                    await this.refreshCart();
                }
            } catch (error) {
                console.error('Error updating cart:', error);
            }
        },
        
        async removeItem(productId) {
            if (!confirm('Are you sure you want to remove this item?')) return;
            
            try {
                const response = await fetch('/cart', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=remove&product_id=${productId}`
                });
                
                const result = await response.json();
                if (result.success) {
                    // Remove item from local array
                    this.items = this.items.filter(item => item.id !== productId);
                    await this.refreshCart();
                    
                    // Update cart count in header
                    const cartCountEl = document.querySelector('.cart-count');
                    if (cartCountEl) {
                        const countResponse = await fetch('/cart/count');
                        const count = await countResponse.text();
                        cartCountEl.textContent = count;
                    }
                }
            } catch (error) {
                console.error('Error removing item:', error);
            }
        },

        async refreshCart() {
            try {
                const response = await fetch('/cart/items');
                const data = await response.json();
                this.items = data.items;
                this.total = data.total;
            } catch (error) {
                console.error('Error refreshing cart:', error);
            }
        },
        
        formatPrice(price) {
            return `Rp ${price.toLocaleString('id-ID')}`;
        }
    }));
});
</script> 