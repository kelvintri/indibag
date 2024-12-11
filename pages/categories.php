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

// Get one sample product image for each category
$imageQuery = "SELECT c.id as category_id, pg.image_url
               FROM categories c
               LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
               LEFT JOIN product_galleries pg ON p.id = pg.product_id AND pg.is_primary = 1
               WHERE p.id IN (
                   SELECT MIN(products.id)
                   FROM products
                   WHERE category_id = c.id AND is_active = 1
                   GROUP BY category_id
               )";

$imageStmt = $conn->prepare($imageQuery);
$imageStmt->execute();
$categoryImages = $imageStmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Shop by Category</h1>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($categories as $category): ?>
            <a href="/products?category[]=<?= $category['id'] ?>" 
               class="group relative bg-white rounded-lg shadow-sm overflow-hidden hover:shadow-md transition">
                <!-- Category Image -->
                <div class="aspect-[4/3] w-full overflow-hidden bg-gray-200">
                    <?php if (isset($categoryImages[$category['id']])): ?>
                        <img src="<?= getImageUrl($categoryImages[$category['id']]) ?>" 
                             alt="<?= htmlspecialchars($category['name']) ?>"
                             class="w-full h-full object-cover object-center group-hover:opacity-75 transition"
                             onerror="this.src='<?= asset('images/placeholder.jpg') ?>'">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-gray-500">
                            <span class="text-lg">No image available</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Category Info -->
                <div class="p-4">
                    <h2 class="text-xl font-semibold text-gray-900 group-hover:text-blue-600 transition">
                        <?= htmlspecialchars($category['name']) ?>
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        <?= $category['product_count'] ?> Products
                    </p>
                </div>

                <!-- Hover Overlay -->
                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-opacity"></div>
            </a>
        <?php endforeach; ?>
    </div>
</div> 