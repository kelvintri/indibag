<?php
$db = new Database();
$conn = $db->getConnection();

// Get all brands with product count
$query = "SELECT b.*, COUNT(p.id) as product_count 
          FROM brands b 
          LEFT JOIN products p ON b.id = p.brand_id AND p.is_active = 1
          GROUP BY b.id 
          ORDER BY b.name";

$stmt = $conn->prepare($query);
$stmt->execute();
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Header Section -->
    <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">Our Brands</h1>
        <p class="text-lg text-gray-600 max-w-2xl mx-auto">
            Discover our collection of premium and luxury brands
        </p>
    </div>

    <!-- Brands Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php foreach ($brands as $brand): ?>
            <a href="/products?brand[]=<?= $brand['id'] ?>" 
               class="group relative bg-white rounded-lg overflow-hidden hover:shadow-xl transition duration-300 ease-in-out transform hover:-translate-y-1">
                <!-- Brand Image -->
                <div class="w-full h-48 overflow-hidden bg-gray-50 flex items-center justify-center p-6">
                    <?php 
                    $logoPath = "/assets/images/Brand/{$brand['slug']}.webp";
                    $fallbackPath = "/assets/images/Brand/{$brand['slug']}.png";
                    ?>
                    <img src="<?= $logoPath ?>" 
                         alt="<?= htmlspecialchars($brand['name']) ?>"
                         class="max-h-full max-w-full object-contain filter group-hover:brightness-110 transition duration-500"
                         onerror="this.onerror=null; this.src='<?= $fallbackPath ?>'; this.onerror=function(){this.src='<?= asset('images/Brand/placeholder.png') ?>';}">
                </div>

                <!-- Brand Info -->
                <div class="p-6">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-xl font-bold text-gray-900 group-hover:text-blue-600 transition">
                            <?= htmlspecialchars($brand['name']) ?>
                        </h2>
                        <span class="inline-flex items-center justify-center px-3 py-1 text-sm font-medium rounded-full bg-gray-100 text-gray-800">
                            <?= $brand['product_count'] ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($brand['description'])): ?>
                    <p class="text-sm text-gray-600 line-clamp-2">
                        <?= htmlspecialchars($brand['description']) ?>
                    </p>
                    <?php endif; ?>

                    <!-- View Brand Button -->
                    <div class="mt-4 flex items-center text-blue-600 text-sm font-medium">
                        Browse Products
                        <svg class="w-4 h-4 ml-1 transform group-hover:translate-x-1 transition" 
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Empty State -->
    <?php if (empty($brands)): ?>
    <div class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 2H9a1 1 0 00-1 1v2a1 1 0 001 1h6a1 1 0 001-1V3a1 1 0 00-1-1z" />
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">No brands found</h3>
        <p class="mt-1 text-sm text-gray-500">Check back soon for new brands.</p>
    </div>
    <?php endif; ?>
</div>  