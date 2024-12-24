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
    $query .= " AND (p.name LIKE :search OR p.description LIKE :search OR b.name LIKE :search)";
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
$countQuery = "SELECT COUNT(DISTINCT p.id) as total 
               FROM products p 
               LEFT JOIN brands b ON p.brand_id = b.id
               LEFT JOIN categories c ON p.category_id = c.id
               WHERE p.is_active = 1";

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
    $countQuery .= " AND (p.name LIKE :search OR p.description LIKE :search OR b.name LIKE :search)";
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
    <title>Bag Collection</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
</head>
<body class="bg-white" x-data="{ 
    showNotification: false,
    notificationType: 'success',
    notificationMessage: '',
    
    showNotificationMessage(type, message) {
        this.notificationType = type;
        this.notificationMessage = message;
        this.showNotification = true;
        setTimeout(() => {
            this.showNotification = false;
        }, 2000);
    }
}">
    <!-- Notification -->
    <div x-show="showNotification" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-x-full"
         x-transition:enter-end="opacity-100 transform translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform translate-x-0"
         x-transition:leave-end="opacity-0 transform translate-x-full"
         class="fixed bottom-4 right-4 z-50"
         @click="showNotification = false">
        <div :class="{
            'bg-green-500': notificationType === 'success',
            'bg-red-500': notificationType === 'error'
        }" class="text-white px-6 py-3 rounded-lg shadow-lg">
            <span x-text="notificationMessage"></span>
        </div>
    </div>

    <!-- Breadcrumb -->
    <div class="container mx-auto px-4 py-4">
        <div class="flex items-center space-x-2 text-sm text-gray-500">
            <a href="/" class="hover:text-gray-900">Home</a>
            <span>/</span>
            <span class="text-gray-900">Browse Products</span>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Filters Sidebar -->
            <div class="w-full lg:w-64 flex-shrink-0" x-data="{ isOpen: false }">
                <button @click="isOpen = !isOpen" 
                        class="lg:hidden w-full mb-4 px-4 py-2 bg-white border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <span x-text="isOpen ? 'Hide Filters' : 'Show Filters'"></span>
                </button>

                <div :class="{'hidden': !isOpen}" class="lg:block space-y-6 bg-white p-6 rounded-lg shadow-sm">
                    <form method="GET" class="space-y-6">
                        <!-- Category Filter -->
                        <div class="border-b pb-6" x-data="{ isExpanded: true }">
                            <button type="button" 
                                    @click="isExpanded = !isExpanded"
                                    class="flex items-center justify-between w-full">
                                <h3 class="text-lg font-medium text-gray-900">CATEGORY</h3>
                                <svg class="w-5 h-5 transform transition-transform" 
                                     :class="{'rotate-180': !isExpanded}"
                                     fill="none" 
                                     stroke="currentColor" 
                                     viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div class="space-y-2 mt-4" x-show="isExpanded">
                                <?php foreach ($categories as $cat): ?>
                                <div class="flex items-center justify-between">
                                    <label class="flex items-center">
                                        <input type="checkbox" 
                                               name="category[]" 
                                               value="<?= $cat['id'] ?>"
                                               <?= (is_array($category_id) && in_array($cat['id'], $category_id)) ? 'checked' : '' ?>
                                               class="h-4 w-4 rounded border-gray-300 text-blue-600">
                                        <span class="ml-2 text-sm text-gray-600"><?= htmlspecialchars($cat['name']) ?></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Price Filter -->
                        <div class="border-b pb-6" x-data="{ isExpanded: false }">
                            <button type="button" 
                                    @click="isExpanded = !isExpanded"
                                    class="flex items-center justify-between w-full">
                                <h3 class="text-lg font-medium text-gray-900">PRICE</h3>
                                <svg class="w-5 h-5 transform transition-transform" 
                                     :class="{'rotate-180': !isExpanded}"
                                     fill="none" 
                                     stroke="currentColor" 
                                     viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div class="space-y-2 mt-4" x-show="isExpanded">
                                <?php
                                $price_ranges = [
                                    '0-3000000' => 'Under IDR 3M',
                                    '3000000-5000000' => 'IDR 3M - 5M',
                                    '5000000-10000000' => 'IDR 5M - 10M',
                                    '10000000-20000000' => 'IDR 10M - 20M',
                                    '20000000-999999999' => 'Above IDR 20M'
                                ];
                                foreach ($price_ranges as $range => $label):
                                ?>
                                <div class="flex items-center">
                                    <label class="flex items-center">
                                        <input type="checkbox" 
                                               name="price_range[]" 
                                               value="<?= $range ?>"
                                               <?= (is_array($price_range) && in_array($range, $price_range)) ? 'checked' : '' ?>
                                               class="h-4 w-4 rounded border-gray-300 text-blue-600">
                                        <span class="ml-2 text-sm text-gray-600"><?= $label ?></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Brands Filter -->
                        <div class="border-b pb-6" x-data="{ isExpanded: false }">
                            <button type="button" 
                                    @click="isExpanded = !isExpanded"
                                    class="flex items-center justify-between w-full">
                                <h3 class="text-lg font-medium text-gray-900">BRANDS</h3>
                                <svg class="w-5 h-5 transform transition-transform" 
                                     :class="{'rotate-180': !isExpanded}"
                                     fill="none" 
                                     stroke="currentColor" 
                                     viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div class="space-y-2 mt-4" x-show="isExpanded">
                                <?php foreach ($brands as $brand): ?>
                                <div class="flex items-center justify-between">
                                    <label class="flex items-center">
                                        <input type="checkbox" 
                                               name="brand[]" 
                                               value="<?= $brand['id'] ?>"
                                               <?= (is_array($brand_id) && in_array($brand['id'], $brand_id)) ? 'checked' : '' ?>
                                               class="h-4 w-4 rounded border-gray-300 text-blue-600">
                                        <span class="ml-2 text-sm text-gray-600"><?= htmlspecialchars($brand['name']) ?></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button type="submit" 
                                class="w-full bg-gray-900 text-white px-4 py-2 rounded-md hover:bg-gray-800 transition duration-150">
                            Apply Filters
                        </button>
                    </form>
                </div>
            </div>

            <!-- Product Grid -->
            <div class="flex-1">
                <!-- Sort and Results Info -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <p class="text-sm text-gray-500">
                            Showing <span class="font-medium"><?= count($products) ?></span> results
                            <?php if ($search): ?>
                                for "<?= htmlspecialchars($search) ?>"
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($category_id) || !empty($brand_id) || !empty($price_range)): ?>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <?php 
                            // Get selected category names
                            if (!empty($category_id)) {
                                foreach ($categories as $cat) {
                                    if (in_array($cat['id'], $category_id)) {
                                        ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-gray-100">
                                            <?= htmlspecialchars($cat['name']) ?>
                                            <button onclick="removeFilter('category', <?= $cat['id'] ?>)" class="ml-1">&times;</button>
                                        </span>
                                        <?php
                                    }
                                }
                            }

                            // Get selected brand names
                            if (!empty($brand_id)) {
                                foreach ($brands as $brand) {
                                    if (in_array($brand['id'], $brand_id)) {
                                        ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-gray-100">
                                            <?= htmlspecialchars($brand['name']) ?>
                                            <button onclick="removeFilter('brand', <?= $brand['id'] ?>)" class="ml-1">&times;</button>
                                        </span>
                                        <?php
                                    }
                                }
                            }

                            // Show price range filters
                            if (!empty($price_range)) {
                                foreach ($price_range as $range) {
                                    $label = $price_ranges[$range] ?? $range;
                                    ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-gray-100">
                                        <?= htmlspecialchars($label) ?>
                                        <button onclick="removeFilter('price_range', '<?= $range ?>')" class="ml-1">&times;</button>
                                    </span>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <select name="sort" 
                            onchange="window.location.href=this.value"
                            class="block w-48 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'newest'])) ?>" 
                                <?= $sort === 'newest' ? 'selected' : '' ?>>
                            Newest
                        </option>
                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'price_asc'])) ?>"
                                <?= $sort === 'price_asc' ? 'selected' : '' ?>>
                            Price: Low to High
                        </option>
                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'price_desc'])) ?>"
                                <?= $sort === 'price_desc' ? 'selected' : '' ?>>
                            Price: High to Low
                        </option>
                    </select>
                </div>

                <!-- Products Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($products as $product): ?>
                    <div class="group relative flex flex-col h-full">
                        <div class="relative aspect-[3/4] mb-4 bg-gray-100">
                            <a href="/products/<?= htmlspecialchars($product['slug']) ?>" class="block w-full h-full">
                                <img src="<?= str_replace(['.jpg', '.jpeg'], '.webp', getImageUrl($product['primary_image'])) ?>"
                                     data-hover-src="<?= str_replace(['.jpg', '.jpeg'], '.webp', getImageUrl($product['hover_image'])) ?>"
                                     alt="<?= htmlspecialchars($product['name']) ?>"
                                     class="w-full h-full object-cover transition-opacity duration-300"
                                     onmouseover="this.src=this.dataset.hoverSrc"
                                     onmouseout="this.src='<?= str_replace(['.jpg', '.jpeg'], '.webp', getImageUrl($product['primary_image'])) ?>'"
                                     onerror="this.src='<?= str_replace(['.jpg', '.jpeg'], '.webp', asset('images/placeholder.jpg')) ?>'">
                            </a>
                        </div>
                        <div class="flex-grow">
                            <h3 class="font-medium mb-1">
                                <a href="/products/<?= htmlspecialchars($product['slug']) ?>">
                                    <?= htmlspecialchars($product['name']) ?>
                                </a>
                            </h3>
                            <p class="text-sm text-gray-500 mb-2"><?= htmlspecialchars($product['brand_name']) ?></p>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">
                                    IDR <?= number_format($product['price'], 0, ',', '.') ?>
                                </span>
                            </div>
                        </div>
                        <?php if ($product['stock'] > 0): ?>
                        <button onclick="addToCart(<?= $product['id'] ?>)"
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
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="mt-8">
                    <!-- Page info -->
                    <p class="text-sm text-gray-500 text-center mb-4">
                        Page <?= $page ?> of <?= $total_pages ?>
                    </p>
                    
                    <!-- Pagination buttons -->
                    <nav class="flex justify-center items-center gap-1" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&<?= http_build_query(array_filter([
                            'category' => $category_id,
                            'brand' => $brand_id,
                            'price_range' => $price_range,
                            'search' => $search,
                            'sort' => $sort
                        ])) ?>" 
                           class="px-3 py-2 rounded-md bg-white border hover:bg-gray-50">
                            Previous
                        </a>
                        <?php endif; ?>

                        <?php
                        $range = 2;
                        $start = max(1, $page - $range);
                        $end = min($total_pages, $page + $range);

                        if ($start > 1): ?>
                            <a href="?page=1&<?= http_build_query(array_filter([
                                'category' => $category_id,
                                'brand' => $brand_id,
                                'price_range' => $price_range,
                                'search' => $search,
                                'sort' => $sort
                            ])) ?>" 
                               class="px-3 py-2 rounded-md bg-white border hover:bg-gray-50">
                                1
                            </a>
                            <?php if ($start > 2): ?>
                                <span class="px-3 py-2">...</span>
                            <?php endif;
                        endif;

                        for ($i = $start; $i <= $end; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="px-3 py-2 rounded-md bg-gray-900 text-white font-semibold">
                                    <?= $i ?>
                                </span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>&<?= http_build_query(array_filter([
                                    'category' => $category_id,
                                    'brand' => $brand_id,
                                    'price_range' => $price_range,
                                    'search' => $search,
                                    'sort' => $sort
                                ])) ?>" 
                                   class="px-3 py-2 rounded-md bg-white border hover:bg-gray-50">
                                    <?= $i ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor;

                        if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?>
                                <span class="px-3 py-2">...</span>
                            <?php endif; ?>
                            <a href="?page=<?= $total_pages ?>&<?= http_build_query(array_filter([
                                'category' => $category_id,
                                'brand' => $brand_id,
                                'price_range' => $price_range,
                                'search' => $search,
                                'sort' => $sort
                            ])) ?>" 
                               class="px-3 py-2 rounded-md bg-white border hover:bg-gray-50">
                                <?= $total_pages ?>
                            </a>
                        <?php endif;

                        if ($page < $total_pages): ?>
                            <a href="?page=<?= $page+1 ?>&<?= http_build_query(array_filter([
                                'category' => $category_id,
                                'brand' => $brand_id,
                                'price_range' => $price_range,
                                'search' => $search,
                                'sort' => $sort
                            ])) ?>" 
                               class="px-3 py-2 rounded-md bg-white border hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <script>
    function removeFilter(filterType, value) {
        const urlParams = new URLSearchParams(window.location.search);
        
        // Get current values as array
        let currentValues = urlParams.getAll(filterType + '[]');
        
        // Remove the specific value
        currentValues = currentValues.filter(v => v != value);
        
        // Remove all instances of this filter
        urlParams.delete(filterType + '[]');
        
        // Add back remaining values
        currentValues.forEach(v => {
            urlParams.append(filterType + '[]', v);
        });
        
        // Redirect with updated filters
        window.location.search = urlParams.toString();
    }

    // Keep the existing Alpine.js and cart functionality
    async function addToCart(productId) {
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('quantity', 1);

        try {
            const response = await fetch('/cart/add', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            // Show notification using Alpine.js
            const alpine = document.querySelector('body').__x.$data;
            alpine.showNotificationMessage(data.success ? 'success' : 'error', data.message);

            // Update cart count if success
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
            const alpine = document.querySelector('body').__x.$data;
            alpine.showNotificationMessage('error', 'Error adding to cart');
        }
    }
    </script>
</body>
</html>