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
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <nav class="flex mb-8" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="/" class="text-gray-700 hover:text-blue-600">Home</a>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <a href="/categories/<?= $product['category_slug'] ?>" class="text-gray-700 hover:text-blue-600">
                        <?= htmlspecialchars($product['category_name']) ?>
                    </a>
                </div>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-gray-500"><?= htmlspecialchars($product['name']) ?></span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Product Images -->
        <div x-data="{ activeImage: '<?= getImageUrl($images[0]['image_url']) ?>' }">
            <div class="mb-4 aspect-w-1 aspect-h-1">
                <img :src="activeImage" 
                     alt="<?= htmlspecialchars($product['name']) ?>" 
                     class="w-full h-full object-cover rounded-lg">
            </div>
            <div class="grid grid-cols-4 gap-4">
                <?php foreach ($images as $image): ?>
                <button @mouseover="activeImage = '<?= getImageUrl($image['image_url']) ?>'"
                        class="aspect-w-1 aspect-h-1">
                    <img src="<?= getImageUrl($image['image_url']) ?>" 
                         alt="<?= htmlspecialchars($product['name']) ?>"
                         class="w-full h-full object-cover rounded-lg">
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Product Info -->
        <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-4">
                <?= htmlspecialchars($product['name']) ?>
            </h1>
            
            <div class="mb-4">
                <a href="/brands/<?= $product['brand_slug'] ?>" 
                   class="text-blue-600 hover:text-blue-800 font-medium">
                    <?= htmlspecialchars($product['brand_name']) ?>
                </a>
            </div>

            <div class="text-2xl font-bold text-gray-900 mb-6">
                Rp <?= number_format($product['price'], 0, ',', '.') ?>
            </div>

            <div class="prose prose-sm text-gray-500 mb-6">
                <?= nl2br(htmlspecialchars($product['description'])) ?>
            </div>

            <div class="mb-6">
                <h3 class="text-sm font-medium text-gray-900 mb-2">Product Details</h3>
                <div class="prose prose-sm text-gray-500">
                    <?php 
                    // Split details by pipe and create a list
                    $details = explode('|', $product['details']);
                    echo '<ul class="list-disc pl-4">';
                    foreach ($details as $detail) {
                        echo '<li>' . htmlspecialchars(trim($detail)) . '</li>';
                    }
                    echo '</ul>';
                    ?>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-sm font-medium text-gray-900 mb-2">SKU</h3>
                <p class="text-gray-500"><?= htmlspecialchars($product['sku']) ?></p>
            </div>

            <div class="mb-6">
                <h3 class="text-sm font-medium text-gray-900 mb-2">Condition</h3>
                <p class="text-gray-500"><?= htmlspecialchars($product['condition_status']) ?></p>
            </div>

            <?php if ($product['stock'] > 0): ?>
                <div x-data="cartForm">
                    <form @submit.prevent="addToCart" class="mb-6">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <div class="flex items-center mb-4">
                            <label for="quantity" class="mr-4 text-sm font-medium text-gray-900">Quantity</label>
                            <select name="quantity" id="quantity" 
                                    class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <?php for ($i = 1; $i <= min($product['stock'], 10); $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button type="submit" 
                                class="w-full bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Add to Cart
                        </button>
                    </form>

                    <!-- Success Notification -->
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

                <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('cartForm', () => ({
                        showNotification: false,
                        notificationType: 'success',
                        notificationMessage: '',

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
                                    // Update cart count in header
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
                    }));
                });
                </script>
            <?php else: ?>
                <button disabled 
                        class="w-full bg-gray-300 text-gray-500 px-6 py-3 rounded-md cursor-not-allowed">
                    Out of Stock
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Related Products -->
    <?php if ($relatedProducts): ?>
    <div class="mt-16">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Related Products</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <?php foreach ($relatedProducts as $related): ?>
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <img src="<?= getImageUrl($related['image_url']) ?>" 
                         alt="<?= htmlspecialchars($related['name']) ?>"
                         class="w-full h-64 object-cover">
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            <?= htmlspecialchars($related['name']) ?>
                        </h3>
                        <p class="text-gray-600 mb-2">
                            Rp <?= number_format($related['price'], 0, ',', '.') ?>
                        </p>
                        <a href="/products/<?= htmlspecialchars($related['slug']) ?>" 
                           class="block text-center bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                            View Details
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php
return true; // Indicate successful rendering
?> 