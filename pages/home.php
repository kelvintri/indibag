<div class="max-w-7xl mx-auto">
    <!-- Hero Section -->
    <div class="bg-white rounded-lg shadow-sm p-8 mb-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">Welcome to Bananina</h1>
        <p class="text-gray-600 mb-6">Discover our collection of premium bags and accessories.</p>
        <a href="/products" class="bg-blue-500 text-white px-6 py-3 rounded-md hover:bg-blue-600">
            Shop Now
        </a>
    </div>

    <!-- Featured Products -->
    <div class="mb-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Featured Products</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php
            $db = new Database();
            $conn = $db->getConnection();
            
            $query = "SELECT p.*, 
                      MAX(CASE WHEN pg.is_primary = 1 THEN pg.image_url END) as primary_image,
                      MAX(CASE WHEN pg.is_primary = 0 THEN pg.image_url END) as hover_image
                      FROM products p 
                      LEFT JOIN product_galleries pg ON p.id = pg.product_id 
                      WHERE p.is_active = 1 
                      GROUP BY p.id
                      LIMIT 8";
            
            $stmt = $conn->prepare($query);
            $stmt->execute();
            
            while ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
            ?>
                <div class="bg-white rounded-lg shadow-sm overflow-hidden" 
                     x-data="{ 
                        showNotification: false,
                        notificationType: 'success',
                        notificationMessage: '',
                        isHovered: false,
                        primaryImage: '<?= getImageUrl($product['primary_image']) ?>',
                        hoverImage: '<?= getImageUrl($product['hover_image']) ?>',
                        
                        async addToCart() {
                            try {
                                const response = await fetch('/cart/add', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: `product_id=<?= $product['id'] ?>&quantity=1`
                                });
                                
                                const data = await response.json();
                                
                                this.notificationType = data.success ? 'success' : 'error';
                                this.notificationMessage = data.message;
                                this.showNotification = true;
                                
                                setTimeout(() => {
                                    this.showNotification = false;
                                }, 2000);

                                if (data.success) {
                                    const cartCountEl = document.querySelector('.cart-count');
                                    if (cartCountEl) {
                                        const countResponse = await fetch('/cart/count');
                                        const count = await countResponse.text();
                                        cartCountEl.textContent = count;
                                    }
                                }
                            } catch (error) {
                                console.error('Error adding to cart:', error);
                                this.notificationType = 'error';
                                this.notificationMessage = 'Error adding to cart';
                                this.showNotification = true;
                                setTimeout(() => {
                                    this.showNotification = false;
                                }, 2000);
                            }
                        }
                    }">
                    <div class="aspect-[3/4] w-full relative" 
                         @mouseenter="isHovered = true" 
                         @mouseleave="isHovered = false">
                        <img :src="isHovered && hoverImage ? hoverImage : primaryImage" 
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             class="w-full h-full object-contain transition-opacity duration-300"
                             onerror="this.src='<?= asset('images/placeholder.jpg') ?>'">
                    </div>
                    <div class="p-4">
                        <a href="/products/<?= htmlspecialchars($product['slug']) ?>" 
                           class="block mb-2">
                            <h3 class="text-lg font-semibold text-gray-900 hover:text-blue-600 transition">
                                <?= htmlspecialchars($product['name']) ?>
                            </h3>
                        </a>
                        <p class="text-gray-600 mb-4">
                            Rp <?= number_format($product['price'], 0, ',', '.') ?>
                        </p>
                        <?php if ($product['stock'] > 0): ?>
                            <button @click="addToCart()" 
                                    class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                                Add to Cart
                            </button>
                        <?php else: ?>
                            <button disabled 
                                    class="w-full bg-gray-300 text-gray-500 px-4 py-2 rounded-md cursor-not-allowed">
                                Out of Stock
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Notification -->
                    <div x-show="showNotification" 
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 transform translate-x-full"
                         x-transition:enter-end="opacity-100 transform translate-x-0"
                         x-transition:leave="transition ease-in duration-300"
                         x-transition:leave-start="opacity-100 transform translate-x-0"
                         x-transition:leave-end="opacity-0 transform translate-x-full"
                         :class="notificationType === 'success' ? 'bg-green-500' : 'bg-red-500'"
                         class="fixed top-4 right-4 text-white px-6 py-3 rounded-md shadow-lg">
                        <span x-text="notificationMessage"></span>
                    </div>
                </div>
            <?php
            }
            ?>
        </div>
    </div>
</div> 