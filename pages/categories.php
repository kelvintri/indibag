<?php
$db = new Database();
$conn = $db->getConnection();

// Get all categories with product count
$query = "SELECT c.*, COUNT(p.id) as product_count 
          FROM categories c 
          LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
          GROUP BY c.id 
          ORDER BY c.name";

$stmt = $conn->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Header Section -->
    <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">Browse Categories</h1>
        <p class="text-lg text-gray-600 max-w-2xl mx-auto">
            Explore our carefully curated collection of products across different categories
        </p>
    </div>

    <!-- Categories Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php foreach ($categories as $category): ?>
            <a href="/products?category[]=<?= $category['id'] ?>" 
               class="group relative bg-white rounded-lg overflow-hidden hover:shadow-xl transition duration-300 ease-in-out transform hover:-translate-y-1">
                <!-- Category Image -->
                <div class="w-full h-48 overflow-hidden bg-gray-50">
                    <?php 
                    $imagePath = "/assets/images/categories/{$category['slug']}.webp";
                    $fallbackPath = "/assets/images/categories/{$category['slug']}.png";
                    ?>
                    <img src="<?= $imagePath ?>" 
                         alt="<?= htmlspecialchars($category['name']) ?>"
                         class="w-full h-full object-cover object-center transform group-hover:scale-110 transition duration-500"
                         onerror="this.onerror=null; this.src='<?= $fallbackPath ?>'; this.onerror=function(){this.src='<?= asset('images/categories/placeholder.png') ?>';}">
                </div>

                <!-- Category Info -->
                <div class="p-6">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-xl font-bold text-gray-900 group-hover:text-blue-600 transition">
                            <?= htmlspecialchars($category['name']) ?>
                        </h2>
                        <span class="inline-flex items-center justify-center px-3 py-1 text-sm font-medium rounded-full bg-gray-100 text-gray-800">
                            <?= $category['product_count'] ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($category['description'])): ?>
                    <p class="text-sm text-gray-600 line-clamp-2">
                        <?= htmlspecialchars($category['description']) ?>
                    </p>
                    <?php endif; ?>

                    <!-- View Category Button -->
                    <div class="mt-4 flex items-center text-blue-600 text-sm font-medium">
                        Browse Category
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
    <?php if (empty($categories)): ?>
    <div class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">No categories found</h3>
        <p class="mt-1 text-sm text-gray-500">Check back soon for new categories.</p>
    </div>
    <?php endif; ?>
</div> 