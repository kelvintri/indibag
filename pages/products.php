<?php
$db = new Database();
$conn = $db->getConnection();

// Get filter parameters
$category_id = $_GET['category'] ?? [];
$brand_id = $_GET['brand'] ?? [];
$price_range = $_GET['price_range'] ?? [];
$condition = $_GET['condition'] ?? [];
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, $_GET['page'] ?? 1);
$per_page = 12;

// Base query
$query = "SELECT p.*, 
          pg_primary.image_url as primary_image,
          pg_hover.image_url as hover_image,
          b.name as brand_name, 
          c.name as category_name
          FROM products p 
          LEFT JOIN (
              SELECT product_id, MIN(image_url) as image_url 
              FROM product_galleries 
              WHERE is_primary = 1
              GROUP BY product_id
          ) pg_primary ON p.id = pg_primary.product_id
          LEFT JOIN (
              SELECT product_id, MIN(image_url) as image_url 
              FROM product_galleries 
              WHERE is_primary = 0
              GROUP BY product_id
          ) pg_hover ON p.id = pg_hover.product_id
          LEFT JOIN brands b ON p.brand_id = b.id
          LEFT JOIN categories c ON p.category_id = c.id
          WHERE p.is_active = 1";

$params = [];

// Update filter conditions to handle arrays
if (!empty($category_id)) {
    $placeholders = array_map(function($i) { return ":category_id_$i"; }, array_keys($category_id));
    $query .= " AND p.category_id IN (" . implode(',', $placeholders) . ")";
    foreach ($category_id as $i => $id) {
        $params[":category_id_$i"] = $id;
    }
}

if (!empty($brand_id)) {
    $placeholders = array_map(function($i) { return ":brand_id_$i"; }, array_keys($brand_id));
    $query .= " AND p.brand_id IN (" . implode(',', $placeholders) . ")";
    foreach ($brand_id as $i => $id) {
        $params[":brand_id_$i"] = $id;
    }
}

// Add price range filter
if (!empty($price_range)) {
    $priceConditions = [];
    foreach ($price_range as $i => $range) {
        list($min, $max) = explode('-', $range);
        $priceConditions[] = "(p.price BETWEEN :price_min_$i AND :price_max_$i)";
        $params[":price_min_$i"] = $min;
        $params[":price_max_$i"] = $max;
    }
    if (!empty($priceConditions)) {
        $query .= " AND (" . implode(' OR ', $priceConditions) . ")";
    }
}

// Add condition filter
if (!empty($condition)) {
    $placeholders = array_map(function($i) { return ":condition_$i"; }, array_keys($condition));
    $query .= " AND p.condition_status IN (" . implode(',', $placeholders) . ")";
    foreach ($condition as $i => $cond) {
        $params[":condition_$i"] = $cond;
    }
}

if ($search) {
    $query .= " AND (p.name LIKE :search OR p.description LIKE :search)";
    $params[':search'] = "%$search%";
}

// Add sorting
switch ($sort) {
    case 'price_asc':
        $query .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY p.price DESC";
        break;
    default: // newest
        $query .= " ORDER BY p.created_at DESC";
}

// Get total count for pagination
$countQuery = "SELECT COUNT(DISTINCT p.id) as total FROM products p WHERE p.is_active = 1";

if (!empty($category_id)) {
    $countQuery .= " AND p.category_id IN (" . implode(',', $placeholders) . ")";
}

if (!empty($brand_id)) {
    $countQuery .= " AND p.brand_id IN (" . implode(',', $placeholders) . ")";
}

if (!empty($price_range)) {
    $countQuery .= " AND (" . implode(' OR ', $priceConditions) . ")";
}

if (!empty($condition)) {
    $countQuery .= " AND p.condition_status IN (" . implode(',', $placeholders) . ")";
}

if ($search) {
    $countQuery .= " AND (p.name LIKE :search OR p.description LIKE :search)";
}

$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$total_products = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_products / $per_page);

// Add pagination
$offset = ($page - 1) * $per_page;
$query .= " LIMIT :offset, :per_page";

// Execute main query
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

// Bind the pagination parameters separately
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', (int)$per_page, PDO::PARAM_INT);

$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all categories and brands for filters
$categoriesQuery = "SELECT id, name, slug FROM categories ORDER BY name";
$categoriesStmt = $conn->prepare($categoriesQuery);
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$brandsQuery = "SELECT id, name, slug FROM brands ORDER BY name";
$brandsStmt = $conn->prepare($brandsQuery);
$brandsStmt->execute();
$brands = $brandsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Listing</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Filters Sidebar -->
            <div class="w-full lg:w-64 flex-shrink-0" x-data="{ isOpen: false }">
                <button @click="isOpen = !isOpen" class="lg:hidden w-full mb-4 px-4 py-2 bg-white border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <span x-text="isOpen ? 'Hide Filters' : 'Show Filters'"></span>
                </button>
                <div :class="{'hidden': !isOpen}" class="lg:block space-y-6 bg-white p-6 rounded-lg shadow-sm">
                    <form method="GET" class="space-y-6">
                        <!-- Category Filter -->
                        <div class="border-b pb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">CATEGORY</h3>
                            <div class="space-y-2">
                                <?php foreach ($categories as $cat): ?>
                                    <div class="flex items-center">
                                        <input type="checkbox" 
                                               id="category_<?= $cat['id'] ?>" 
                                               name="category[]" 
                                               value="<?= $cat['id'] ?>"
                                               <?= (is_array($category_id) && in_array($cat['id'], $category_id)) ? 'checked' : '' ?>
                                               class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <label for="category_<?= $cat['id'] ?>" 
                                               class="ml-3 text-sm text-gray-600">
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Price Filter -->
                        <div class="border-b pb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">PRICE</h3>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           name="price_range[]" 
                                           value="0-3000000" 
                                           id="price_under_3m"
                                           <?= (is_array($price_range) && in_array('0-3000000', $price_range)) ? 'checked' : '' ?>
                                           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <label for="price_under_3m" class="ml-3 text-sm text-gray-600">
                                        UNDER IDR 3 M
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           name="price_range[]" 
                                           value="3000000-5000000" 
                                           id="price_3m_5m"
                                           <?= (is_array($price_range) && in_array('3000000-5000000', $price_range)) ? 'checked' : '' ?>
                                           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <label for="price_3m_5m" class="ml-3 text-sm text-gray-600">
                                        IDR 3 M - IDR 5 M
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           name="price_range[]" 
                                           value="5000000-10000000" 
                                           id="price_5m_10m"
                                           <?= (is_array($price_range) && in_array('5000000-10000000', $price_range)) ? 'checked' : '' ?>
                                           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <label for="price_5m_10m" class="ml-3 text-sm text-gray-600">
                                        IDR 5 M - IDR 10 M
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           name="price_range[]" 
                                           value="10000000-20000000" 
                                           id="price_10m_20m"
                                           <?= (is_array($price_range) && in_array('10000000-20000000', $price_range)) ? 'checked' : '' ?>
                                           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <label for="price_10m_20m" class="ml-3 text-sm text-gray-600">
                                        IDR 10 M - IDR 20 M
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           name="price_range[]" 
                                           value="20000000-999999999" 
                                           id="price_above_20m"
                                           <?= (is_array($price_range) && in_array('20000000-999999999', $price_range)) ? 'checked' : '' ?>
                                           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <label for="price_above_20m" class="ml-3 text-sm text-gray-600">
                                        ABOVE IDR 20 M
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Brands Filter -->
                        <div class="border-b pb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">BRANDS</h3>
                            <div class="space-y-2">
                                <?php foreach ($brands as $brand): ?>
                                    <div class="flex items-center">
                                        <input type="checkbox" 
                                               id="brand_<?= $brand['id'] ?>" 
                                               name="brand[]" 
                                               value="<?= $brand['id'] ?>"
                                               <?= (is_array($brand_id) && in_array($brand['id'], $brand_id)) ? 'checked' : '' ?>
                                               class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <label for="brand_<?= $brand['id'] ?>" 
                                               class="ml-3 text-sm text-gray-600">
                                            <?= htmlspecialchars($brand['name']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Apply Filters Button -->
                        <button type="submit" 
                                class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-150 ease-in-out">
                            Apply Filters
                        </button>
                    </form>
                </div>
            </div>

            <!-- Main Content -->
            <div class="flex-1">
                <!-- Sort and Search -->
                <div class="mb-6">
                    <form method="GET" class="flex flex-col sm:flex-row gap-4">
                        <!-- Preserve filter values -->
                        <?php if (!empty($category_id)): ?>
                            <?php foreach ($category_id as $id): ?>
                                <input type="hidden" name="category[]" value="<?= htmlspecialchars($id) ?>">
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (!empty($brand_id)): ?>
                            <?php foreach ($brand_id as $id): ?>
                                <input type="hidden" name="brand[]" value="<?= htmlspecialchars($id) ?>">
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (!empty($price_range)): ?>
                            <?php foreach ($price_range as $range): ?>
                                <input type="hidden" name="price_range[]" value="<?= htmlspecialchars($range) ?>">
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="w-full sm:w-48">
                            <select name="sort" id="sort" 
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    onchange="this.form.submit()">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                            </select>
                        </div>
                        <div class="flex-1">
                            <div class="flex gap-2">
                                <input type="text" name="search" 
                                       value="<?= htmlspecialchars($search) ?>"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       placeholder="Search products...">
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-150 ease-in-out">
                                    Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Products Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($products as $product): ?>
                        <div class="bg-white rounded-lg shadow-sm overflow-hidden hover:shadow-md transition duration-300 ease-in-out"
                             x-data="{ isHovered: false }">
                            <!-- Product Image -->
                            <div class="relative pt-[125%]">
                                <div class="absolute inset-0 overflow-hidden bg-gray-100"
                                     @mouseenter="isHovered = true" 
                                     @mouseleave="isHovered = false">
                                    <a href="/products/<?= htmlspecialchars($product['slug']) ?>" class="block w-full h-full">
                                        <img :src="isHovered && '<?= getImageUrl($product['hover_image']) ?>' ? '<?= getImageUrl($product['hover_image']) ?>' : '<?= getImageUrl($product['primary_image']) ?>'"
                                             alt="<?= htmlspecialchars($product['name']) ?>"
                                             class="w-full h-full object-cover object-center transition-opacity duration-300"
                                             onerror="this.src='<?= asset('images/placeholder.jpg') ?>'">
                                    </a>
                                </div>
                            </div>

                            <!-- Product Info -->
                            <div class="p-4">
                                <a href="/products/<?= htmlspecialchars($product['slug']) ?>" class="block mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900 hover:text-blue-600 transition duration-150 ease-in-out">
                                        <?= htmlspecialchars($product['name']) ?>
                                    </h3>
                                </a>
                                <p class="text-sm text-gray-600 mb-2">
                                    <?= htmlspecialchars($product['brand_name']) ?>
                                </p>
                                <p class="text-gray-900 font-medium mb-4">
                                    IDR <?= number_format($product['price'], 0, ',', '.') ?>
                                </p>
                                <?php if ($product['stock'] > 0): ?>
                                    <button @click="addToCart(<?= $product['id'] ?>)" 
                                            class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-150 ease-in-out">
                                        Add to Cart
                                    </button>
                                <?php else: ?>
                                    <button disabled 
                                            class="w-full bg-gray-300 text-gray-500 px-4 py-2 rounded-md cursor-not-allowed">
                                        Out of Stock
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-8 flex justify-center">
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php
                            // Previous page
                            if ($page > 1): ?>
                                <a href="?page=<?= $page-1 ?>&<?= http_build_query(array_filter([
                                    'category' => $category_id,
                                    'brand' => $brand_id,
                                    'price_range' => $price_range,
                                    'search' => $search,
                                    'sort' => $sort
                                ])) ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>

                            <?php
                            // Calculate range of pages to show
                            $range = 2;
                            $start = max(1, $page - $range);
                            $end = min($total_pages, $page + $range);

                            // Show first page if not in range
                            if ($start > 1): ?>
                                <a href="?page=1&<?= http_build_query(array_filter([
                                    'category' => $category_id,
                                    'brand' => $brand_id,
                                    'price_range' => $price_range,
                                    'search' => $search,
                                    'sort' => $sort
                                ])) ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    1
                                </a>
                                <?php if ($start > 2): ?>
                                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                        ...
                                    </span>
                                <?php endif;
                            endif;

                            // Page numbers
                            for ($i = $start; $i <= $end; $i++): ?>
                                <a href="?page=<?= $i ?>&<?= http_build_query(array_filter([
                                    'category' => $category_id,
                                    'brand' => $brand_id,
                                    'price_range' => $price_range,
                                    'search' => $search,
                                    'sort' => $sort
                                ])) ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border <?= $page === $i ? 'border-blue-500 bg-blue-50 text-blue-600' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor;

                            // Show last page if not in range
                            if ($end < $total_pages): ?>
                                <?php if ($end < $total_pages - 1): ?>
                                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                        ...
                                    </span>
                                <?php endif; ?>
                                <a href="?page=<?= $total_pages ?>&<?= http_build_query(array_filter([
                                    'category' => $category_id,
                                    'brand' => $brand_id,
                                    'price_range' => $price_range,
                                    'search' => $search,
                                    'sort' => $sort
                                ])) ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    <?= $total_pages ?>
                                </a>
                            <?php endif; ?>

                            <?php 
                            // Next page
                            if ($page < $total_pages): ?>
                                <a href="?page=<?= $page+1 ?>&<?= http_build_query(array_filter([
                                    'category' => $category_id,
                                    'brand' => $brand_id,
                                    'price_range' => $price_range,
                                    'search' => $search,
                                    'sort' => $sort
                                ])) ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    async function addToCart(productId) {
        try {
            const response = await fetch('/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=1`
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
    </script>
</body>
</html>