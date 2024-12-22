            <?php
            $db = new Database();
            $conn = $db->getConnection();
            
// Get featured products
            $query = "SELECT p.*, 
                      MAX(CASE WHEN pg.is_primary = 1 THEN pg.image_url END) as primary_image,
                      MAX(CASE WHEN pg.is_primary = 0 THEN pg.image_url END) as hover_image
                      FROM products p 
                      LEFT JOIN product_galleries pg ON p.id = pg.product_id 
                      GROUP BY p.id
          LIMIT 3";
            
            $stmt = $conn->prepare($query);
            $stmt->execute();
$featuredProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get brands
$brandQuery = "SELECT * FROM brands ORDER BY name LIMIT 8";
$brandStmt = $conn->prepare($brandQuery);
$brandStmt->execute();
$brands = $brandStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Top Banner -->
<div class="bg-gray-900 text-white text-center text-sm py-2">
    Sign up and GET 20% OFF for your first order. <a href="#" class="underline">Sign up now</a>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Hero Slider -->
    <div class="relative mb-12">
        <div class="rounded-lg overflow-hidden">
            <div class="relative aspect-[16/9]">
                <img src="/assets/images/hero.jpg" alt="Summer Collection" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-black bg-opacity-30 flex items-center justify-center">
                    <div class="text-center text-white">
                        <h1 class="text-4xl md:text-6xl font-bold mb-4">Level up your style with our<br>summer collections</h1>
                        <a href="/products" class="inline-block bg-white text-black px-8 py-3 rounded-full hover:bg-gray-100 transition">
                            Shop now
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <!-- Slider Navigation -->
        <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex space-x-2">
            <button class="w-2 h-2 rounded-full bg-white bg-opacity-50"></button>
            <button class="w-2 h-2 rounded-full bg-white"></button>
            <button class="w-2 h-2 rounded-full bg-white bg-opacity-50"></button>
            <button class="w-2 h-2 rounded-full bg-white bg-opacity-50"></button>
        </div>
        <!-- Slider Arrows -->
        <button class="absolute left-4 top-1/2 transform -translate-y-1/2 w-10 h-10 bg-white bg-opacity-50 rounded-full flex items-center justify-center hover:bg-opacity-75">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </button>
        <button class="absolute right-4 top-1/2 transform -translate-y-1/2 w-10 h-10 bg-white bg-opacity-50 rounded-full flex items-center justify-center hover:bg-opacity-75">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button>
    </div>

    <!-- Brands -->
    <div class="mb-12">
        <h2 class="text-xl font-semibold mb-6">Brands</h2>
        <div class="grid grid-cols-4 md:grid-cols-8 gap-8">
            <?php foreach ($brands as $brand): ?>
                <div class="flex items-center justify-center">
                    <?php
                    $brandName = strtolower($brand['name']);
                    $logoUrl = "/assets/images/brands/" . $brand['slug'] . ".png";
                    ?>
                    <img src="<?= $logoUrl ?>" 
                         alt="<?= htmlspecialchars($brand['name']) ?>"
                         class="h-8 object-contain grayscale hover:grayscale-0 transition"
                         onerror="this.src='/assets/images/brands/placeholder.png'">
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Features -->
    <div class="mb-12">
        <h2 class="text-xl font-semibold mb-2">We provide best</h2>
        <p class="text-gray-500 mb-6">customer experiences</p>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="p-6 bg-white rounded-lg">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="font-semibold mb-2">Original Products</h3>
                <p class="text-sm text-gray-500">We provide money back guarantee if the product isn't original</p>
            </div>
            <div class="p-6 bg-white rounded-lg">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h3 class="font-semibold mb-2">Satisfaction Guarantee</h3>
                <p class="text-sm text-gray-500">Exchange the product you've purchased if it doesn't fit on you</p>
            </div>
            <div class="p-6 bg-white rounded-lg">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="font-semibold mb-2">New Arrival Everyday</h3>
                <p class="text-sm text-gray-500">We update our collections almost everyday</p>
            </div>
            <div class="p-6 bg-white rounded-lg">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                </div>
                <h3 class="font-semibold mb-2">Fast & Free Shipping</h3>
                <p class="text-sm text-gray-500">We offer fast and free shipping for our loyal customers</p>
            </div>
        </div>
    </div>

    <!-- Curated Picks -->
    <div class="mb-12">
        <h2 class="text-xl font-semibold mb-6">Curated picks</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <a href="/products?category=best-seller" class="relative aspect-square rounded-lg overflow-hidden group">
                <img src="/assets/images/categories/best-seller.jpg" alt="Best Seller" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">
                    <button class="bg-white text-black px-6 py-2 rounded-full opacity-0 group-hover:opacity-100 transition">
                        Best Seller
                    </button>
                </div>
            </a>
            <a href="/products?category=men" class="relative aspect-square rounded-lg overflow-hidden group">
                <img src="/assets/images/categories/men.jpg" alt="Shop Men" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">
                    <button class="bg-white text-black px-6 py-2 rounded-full opacity-0 group-hover:opacity-100 transition">
                        Shop Men
                    </button>
                </div>
            </a>
            <a href="/products?category=women" class="relative aspect-square rounded-lg overflow-hidden group">
                <img src="/assets/images/categories/women.jpg" alt="Shop Women" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">
                    <button class="bg-white text-black px-6 py-2 rounded-full opacity-0 group-hover:opacity-100 transition">
                        Shop Women
                    </button>
                </div>
            </a>
            <a href="/products?category=casual" class="relative aspect-square rounded-lg overflow-hidden group">
                <img src="/assets/images/categories/casual.jpg" alt="Shop Casual" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">
                    <button class="bg-white text-black px-6 py-2 rounded-full opacity-0 group-hover:opacity-100 transition">
                        Shop Casual
                    </button>
                </div>
            </a>
        </div>
    </div>

    <!-- Featured Products -->
    <div class="mb-12">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold">Featured products</h2>
            <div class="flex gap-2">
                <button class="w-8 h-8 bg-white rounded-full shadow flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button class="w-8 h-8 bg-white rounded-full shadow flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php foreach ($featuredProducts as $product): ?>
                <div class="bg-white rounded-lg overflow-hidden group">
                    <div class="relative aspect-[3/4]">
                        <img src="<?= getImageUrl($product['primary_image']) ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             class="w-full h-full object-cover">
                        <?php if ($product['hover_image']): ?>
                            <img src="<?= getImageUrl($product['hover_image']) ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 class="absolute inset-0 w-full h-full object-cover opacity-0 group-hover:opacity-100 transition">
                        <?php endif; ?>
                        <div class="absolute bottom-4 right-4">
                            <button class="w-10 h-10 bg-white rounded-full shadow-lg flex items-center justify-center hover:bg-gray-100">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="font-medium"><?= htmlspecialchars($product['name']) ?></h3>
                        <p class="text-gray-500">Rp <?= number_format($product['price'], 0, ',', '.') ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Promo Banner -->
    <div class="mb-12">
        <div class="relative rounded-lg overflow-hidden">
            <img src="/assets/images/promo-banner.jpg" alt="Special Offer" class="w-full h-64 object-cover">
            <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center">
                <div class="px-8">
                    <p class="text-white text-sm mb-2">LIMITED OFFER</p>
                    <h3 class="text-white text-2xl font-bold mb-4">35% off only this friday<br>and get special gift</h3>
                    <button class="bg-white text-black px-6 py-2 rounded-full hover:bg-gray-100">
                        Grab it now
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Newsletter -->
    <div class="mb-12 text-center">
        <h2 class="text-xl font-semibold mb-2">Subscribe to our newsletter to get updates</h2>
        <p class="text-gray-500 mb-6">to our latest collections</p>
        <form class="max-w-md mx-auto">
            <div class="flex gap-4">
                <input type="email" 
                       placeholder="Enter your email" 
                       class="flex-1 px-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:border-black">
                <button type="submit" 
                        class="px-6 py-2 bg-black text-white rounded-full hover:bg-gray-800">
                    Subscribe
                </button>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                Get 25% off your first order just by subscribing to our newsletter
            </p>
        </form>
    </div>
</div> 