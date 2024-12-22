<?php
$slug = basename($_SERVER['REQUEST_URI']);
$db = new Database();
$conn = $db->getConnection();

// Get product details with brand and category
$query = "SELECT p.*, b.name as brand_name, c.name as category_name,
                 b.slug as brand_slug, c.slug as category_slug
          FROM products p 
          LEFT JOIN brands b ON p.brand_id = b.id 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.slug = :slug AND p.is_active = 1 AND p.deleted_at IS NULL";

$stmt = $conn->prepare($query);
$stmt->bindParam(":slug", $slug);
$stmt->execute();
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    return false;
}

// Get product images
$imageQuery = "SELECT * FROM product_galleries 
              WHERE product_id = :product_id 
              ORDER BY is_primary DESC, sort_order ASC";
$imageStmt = $conn->prepare($imageQuery);
$imageStmt->bindParam(":product_id", $product['id']);
$imageStmt->execute();
$images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

// Get related products
$relatedQuery = "SELECT p.*, pg.image_url 
                FROM products p 
                LEFT JOIN product_galleries pg ON p.id = pg.product_id 
                WHERE p.category_id = :category_id 
                AND p.id != :product_id 
                AND p.is_active = 1 
                AND pg.is_primary = 1 
                LIMIT 4";
$relatedStmt = $conn->prepare($relatedQuery);
$relatedStmt->bindParam(":category_id", $product['category_id']);
$relatedStmt->bindParam(":product_id", $product['id']);
$relatedStmt->execute();
$relatedProducts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

// Output the HTML directly, no need for ob_start/ob_get_clean
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .star-rating {
            color: #FFD700;
        }
        .thumbnail:hover {
            opacity: 0.75;
            transition: opacity 0.2s ease-in-out;
        }
    </style>
</head>
<body class="bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm mb-8">
            <a href="/" class="text-gray-600 hover:text-gray-900">Browse Products</a>
            <span class="text-gray-400">/</span>
            <a href="/categories/<?= $product['category_slug'] ?>" class="text-gray-600 hover:text-gray-900">
                <?= htmlspecialchars($product['category_name']) ?>
            </a>
            <span class="text-gray-400">/</span>
            <span class="text-gray-900"><?= htmlspecialchars($product['name']) ?></span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Product Images -->
            <div x-data="{ activeImage: '<?= getImageUrl($images[0]['image_url']) ?>' }">
                <!-- Main Image -->
                <div class="relative max-w-lg mx-auto aspect-[3/4] mb-4 bg-gray-100 rounded-lg overflow-hidden">
                    <img :src="activeImage" 
                         alt="<?= htmlspecialchars($product['name']) ?>" 
                         class="w-full h-full object-cover">
                    
                    <!-- Navigation arrows -->
                    <button class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <!-- Thumbnails -->
                <div class="max-w-lg mx-auto grid grid-cols-5 gap-2">
                    <?php foreach ($images as $image): ?>
                    <button @mouseover="activeImage = '<?= getImageUrl($image['image_url']) ?>'"
                            class="thumbnail aspect-square bg-gray-100 rounded-md overflow-hidden">
                        <img src="<?= getImageUrl($image['image_url']) ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             class="w-full h-full object-cover">
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Product Info -->
            <div class="flex flex-col lg:pl-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-4">
                    <?= htmlspecialchars($product['name']) ?>
                </h1>

                <!-- Rating -->
                <div class="flex items-center gap-2 mb-6">
                    <div class="flex star-rating">
                        <?php for($i = 0; $i < 5; $i++): ?>
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        <?php endfor; ?>
                    </div>
                    <span class="text-gray-600">(4.9)</span>
                </div>

                <!-- Price -->
                <div class="text-3xl font-bold mb-8">
                    Rp <?= number_format($product['price'], 0, ',', '.') ?>
                </div>

                <!-- Description -->
                <div class="prose prose-sm text-gray-600 mb-8">
                    <?= nl2br(htmlspecialchars($product['description'])) ?>
                </div>

                <!-- Product Details -->
                <div class="mb-8">
                    <h3 class="font-medium text-gray-900 mb-4">Product Details</h3>
                    <div class="prose prose-sm text-gray-600">
                        <?php 
                        $details = explode('|', $product['details']);
                        echo '<ul class="list-disc pl-4 space-y-2">';
                        foreach ($details as $detail) {
                            echo '<li>' . htmlspecialchars(trim($detail)) . '</li>';
                        }
                        echo '</ul>';
                        ?>
                    </div>
                </div>

                <!-- Stock Status -->
                <?php if ($product['stock'] > 0): ?>
                    <div class="mb-8">
                        <p class="text-lg">
                            Last <span class="font-semibold"><?= $product['stock'] ?></span> left
                            <span class="text-gray-600">- make it yours!</span>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Additional Info -->
                <div class="grid grid-cols-2 gap-6 mb-8">
                    <div>
                        <h3 class="font-medium text-gray-900 mb-2">SKU</h3>
                        <p class="text-gray-600"><?= htmlspecialchars($product['sku']) ?></p>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900 mb-2">Condition</h3>
                        <p class="text-gray-600"><?= htmlspecialchars($product['condition_status']) ?></p>
                    </div>
                </div>

                <!-- Add to Cart Form -->
                <div x-data="{ 
                    quantity: 1,
                    maxQuantity: <?= min($product['stock'], 10) ?>,
                    showNotification: false,
                    notificationType: 'success',
                    notificationMessage: '',
                    
                    decrementQuantity() {
                        if (this.quantity > 1) this.quantity--;
                    },
                    incrementQuantity() {
                        if (this.quantity < this.maxQuantity) this.quantity++;
                    },
                    
                    async addToCart(e) {
                        const form = e.target;
                        const formData = new FormData(form);

                        try {
                            const response = await fetch('/cart/add', {
                                method: 'POST',
                                body: formData
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
                }" class="mt-auto">
                    <form @submit.prevent="addToCart" class="space-y-6">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        
                        <!-- Quantity -->
                        <div class="flex items-center gap-4">
                            <button type="button" 
                                    @click="decrementQuantity"
                                    class="w-10 h-10 rounded-lg border flex items-center justify-center hover:bg-gray-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                                </svg>
                            </button>
                            <input type="number" 
                                   name="quantity" 
                                   x-model="quantity"
                                   min="1" 
                                   :max="maxQuantity"
                                   class="w-20 h-10 rounded-lg border text-center">
                            <button type="button" 
                                    @click="incrementQuantity"
                                    class="w-10 h-10 rounded-lg border flex items-center justify-center hover:bg-gray-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Add to Cart Button -->
                        <?php if ($product['stock'] > 0): ?>
                            <button type="submit" 
                                    class="w-full bg-gray-900 text-white h-12 rounded-lg hover:bg-gray-800 transition-colors">
                                Add to cart
                            </button>
                        <?php else: ?>
                            <button disabled 
                                    class="w-full bg-gray-200 text-gray-500 h-12 rounded-lg cursor-not-allowed">
                                Out of Stock
                            </button>
                        <?php endif; ?>
                    </form>

                    <!-- Notification -->
                    <div x-show="showNotification" 
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 transform translate-x-full"
                         x-transition:enter-end="opacity-100 transform translate-x-0"
                         x-transition:leave="transition ease-in duration-300"
                         x-transition:leave-start="opacity-100 transform translate-x-0"
                         x-transition:leave-end="opacity-0 transform translate-x-full"
                         :class="notificationType === 'success' ? 'bg-green-500' : 'bg-red-500'"
                         class="fixed top-4 right-4 text-white px-6 py-3 rounded-lg shadow-lg">
                        <span x-text="notificationMessage"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products Section -->
        <?php if ($relatedProducts): ?>
        <div class="mt-16">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-2xl font-bold text-gray-900">Related Products</h2>
                <div class="flex gap-2">
                    <button class="w-10 h-10 rounded-full border flex items-center justify-center hover:bg-gray-50">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button class="w-10 h-10 rounded-full border flex items-center justify-center hover:bg-gray-50">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                <?php foreach ($relatedProducts as $related): ?>
                <div class="group relative">
                    <div class="relative aspect-[3/4] mb-4 bg-gray-100 rounded-lg overflow-hidden">
                        <a href="/products/<?= htmlspecialchars($related['slug']) ?>">
                            <img src="<?= getImageUrl($related['image_url']) ?>" 
                                 alt="<?= htmlspecialchars($related['name']) ?>"
                                 class="w-full h-full object-cover group-hover:opacity-75 transition-opacity">
                        </a>
                    </div>
                    
                    <div class="flex flex-col">
                        <h3 class="font-medium mb-1">
                            <a href="/products/<?= htmlspecialchars($related['slug']) ?>">
                                <?= htmlspecialchars($related['name']) ?>
                            </a>
                        </h3>
                        
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">
                                Rp <?= number_format($related['price'], 0, ',', '.') ?>
                            </span>
                        </div>

                        <?php if ($related['stock'] > 0): ?>
                        <button onclick="addToCart(<?= $related['id'] ?>)"
                                class="mt-4 w-full bg-gray-900 text-white px-4 py-2 rounded-md hover:bg-gray-800 transition duration-150">
                            Add to Cart
                        </button>
                        <?php else: ?>
                        <button disabled 
                                class="mt-4 w-full bg-gray-200 text-gray-500 px-4 py-2 rounded-md cursor-not-allowed">
                            Out of Stock
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Alpine.js initialization is now handled in the x-data attribute above
        
        // Add to cart function for related products
        function addToCart(productId) {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('quantity', 1);

            fetch('/cart/add', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Show notification
                const notification = document.createElement('div');
                notification.className = `fixed top-4 right-4 text-white px-6 py-3 rounded-lg shadow-lg ${data.success ? 'bg-green-500' : 'bg-red-500'}`;
                notification.textContent = data.message;
                document.body.appendChild(notification);

                // Update cart count if success
                if (data.success) {
                    const cartCountEl = document.querySelector('.cart-count');
                    if (cartCountEl) {
                        fetch('/cart/count')
                            .then(response => response.text())
                            .then(count => {
                                cartCountEl.textContent = count;
                            });
                    }
                }

                // Remove notification after 2 seconds
                setTimeout(() => {
                    notification.remove();
                }, 2000);
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                // Show error notification
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 text-white px-6 py-3 rounded-lg shadow-lg bg-red-500';
                notification.textContent = 'Error adding to cart';
                document.body.appendChild(notification);
                setTimeout(() => {
                    notification.remove();
                }, 2000);
            });
        }
    </script>
</body>
</html>


<?php
return true; // Indicate successful rendering
?> 