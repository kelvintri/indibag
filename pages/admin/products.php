<?php
require_once __DIR__ . '/../../includes/AdminAuth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
AdminAuth::requireAdmin();

$db = new Database();
$conn = $db->getConnection();

// Handle AJAX requests first, before any output
if (isset($_GET['edit_id'])) {
    $stmt = $conn->prepare("
        SELECT p.*, 
               pg_primary.image_url as primary_image,
               pg_hover.image_url as hover_image,
               b.name as brand_name,
               b.id as brand_id
        FROM products p
        LEFT JOIN product_galleries pg_primary ON p.id = pg_primary.product_id AND pg_primary.is_primary = 1
        LEFT JOIN product_galleries pg_hover ON p.id = pg_hover.product_id AND pg_hover.is_primary = 0
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.id = ? AND p.is_active = 1 AND p.deleted_at IS NULL
    ");
    $stmt->execute([$_GET['edit_id']]);
    $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($editProduct) {
        ob_clean(); // Clear any output buffers
        header('Content-Type: application/json');
        echo json_encode($editProduct);
        exit;
    }
    exit;
}

// Handle delete action
if (isset($_POST['delete']) && isset($_POST['product_id'])) {
    $stmt = $conn->prepare("
        UPDATE products 
        SET is_active = 0, 
            deleted_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$_POST['product_id']]);
    
    ob_clean(); // Clear any output buffers
    header("Location: /admin/products");
    exit;
}

// Fetch categories for the product form
$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch brands for the product form
$brands = $conn->query("SELECT * FROM brands ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get search and sort parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

// Validate sort parameters
$allowed_sort_fields = ['name', 'price', 'stock', 'created_at'];
$sort_by = in_array($sort_by, $allowed_sort_fields) ? $sort_by : 'created_at';
$sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

// Build search condition
$search_condition = '';
$params = [];
if ($search !== '') {
    $search_condition = "AND (p.name LIKE :search_name OR c.name LIKE :search_category)";
    $params[':search_name'] = "%$search%";
    $params[':search_category'] = "%$search%";
}

// Get total products count with search
$total_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT p.id)
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1 AND p.deleted_at IS NULL
    $search_condition
");
$total_stmt->execute($params);
$total_products = $total_stmt->fetchColumn();

$total_pages = ceil($total_products / $per_page);

// Fetch paginated products with search and sort
$stmt = $conn->prepare("
    SELECT p.*, 
           c.name as category_name, 
           b.name as brand_name, 
           (SELECT image_url 
            FROM product_galleries 
            WHERE product_id = p.id 
            AND is_primary = 1 
            LIMIT 1) as image_url
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE p.is_active = 1 AND p.deleted_at IS NULL
    $search_condition
    ORDER BY p.$sort_by $sort_order
    LIMIT :limit OFFSET :offset
");

// Bind all parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Create Product Modal -->
<div x-data="{ 
    showModal: false, 
    showEditModal: false, 
    isSubmitting: false,
    editProduct: {
        id: '',
        name: '',
        description: '',
        category_id: '',
        brand_id: '',
        price: '',
        stock: '',
        primary_image: '',
        hover_image: ''
    },
    fetchProduct(id) {
        fetch(`products?edit_id=${id}`)
            .then(response => response.json())
            .then(data => {
                this.editProduct = data;
                this.showEditModal = true;
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error fetching product details');
            });
    }
}" @keydown.escape="showModal = false; showEditModal = false">
    <!-- Trigger Button -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Products</h1>
            <button @click="showModal = true" 
                    class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                Add New Product
            </button>
        </div>

        <!-- Search and Sort Controls -->
        <div class="mb-6 flex flex-col sm:flex-row gap-4">
            <form class="flex-1" method="GET">
                <div class="flex gap-2">
                    <input type="text" 
                           name="search" 
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search by product or category name..."
                           class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <button type="submit" 
                            class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                        Search
                    </button>
                    <?php if ($search): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['search' => ''])) ?>" 
                           class="bg-gray-100 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-200">
                            Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
            <div class="flex gap-2">
                <select name="sort_by" 
                        onchange="window.location.href='?' + new URLSearchParams(Object.assign(Object.fromEntries(new URLSearchParams(window.location.search)), {sort_by: this.value})).toString()"
                        class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Sort by: Latest</option>
                    <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>Sort by: Name</option>
                    <option value="price" <?= $sort_by === 'price' ? 'selected' : '' ?>>Sort by: Price</option>
                    <option value="stock" <?= $sort_by === 'stock' ? 'selected' : '' ?>>Sort by: Stock</option>
                </select>
                <button onclick="window.location.href='?' + new URLSearchParams(Object.assign(Object.fromEntries(new URLSearchParams(window.location.search)), {sort_order: '<?= $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>'})).toString()"
                        class="bg-gray-100 text-gray-600 px-3 py-2 rounded-lg hover:bg-gray-200">
                    <?php if ($sort_order === 'ASC'): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 10.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-6-6a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l4.293-4.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <!-- Modal -->
        <div x-show="showModal" 
             class="fixed inset-0 z-50 overflow-y-auto" 
             style="display: none;">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>

            <!-- Modal Content -->
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="relative w-full max-w-2xl rounded-lg bg-white shadow-xl" @click.away="showModal = false">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between border-b p-4">
                        <h3 class="text-xl font-semibold text-gray-900">Create New Product</h3>
                        <button @click="showModal = false" class="text-gray-400 hover:text-gray-500">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6">
                        <form id="createProductForm" 
                              action="/admin/products/create" 
                              method="POST" 
                              enctype="multipart/form-data"
                              @submit.prevent="submitForm" 
                              class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" name="name" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea name="description" rows="3" required
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Details</label>
                                <textarea name="details" rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                          placeholder="Made in Indonesia | Gold tone Hardware | MK Logo Medallion Hang Charm | Michael Kors metal Logo Lettering"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Category</label>
                                <select name="category_id" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>">
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Brand</label>
                                <select name="brand_id" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Select a brand</option>
                                    <?php foreach ($brands as $brand): ?>
                                        <option value="<?= $brand['id'] ?>">
                                            <?= htmlspecialchars($brand['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Price (Rp)</label>
                                <input type="number" name="price" required min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Stock</label>
                                <input type="number" name="stock" required min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Primary Image</label>
                                    <input type="file" name="primary_image" accept="image/*" required
                                           class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Hover Image</label>
                                    <input type="file" name="hover_image" accept="image/*" required
                                           class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex justify-end space-x-3 border-t p-4">
                        <button @click="showModal = false" 
                                class="rounded-md bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200">
                            Cancel
                        </button>
                        <button type="submit" form="createProductForm"
                                class="rounded-md bg-blue-500 px-4 py-2 text-white hover:bg-blue-600"
                                :disabled="isSubmitting">
                            <span x-show="!isSubmitting">Create Product</span>
                            <span x-show="isSubmitting">Creating...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Product Modal -->
        <div x-show="showEditModal" 
             class="fixed inset-0 z-50 overflow-y-auto" 
             x-cloak>
            <!-- Overlay -->
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>

            <!-- Modal Content -->
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="relative w-full max-w-2xl rounded-lg bg-white shadow-xl" @click.away="showEditModal = false">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between border-b p-4">
                        <h3 class="text-xl font-semibold text-gray-900">Edit Product</h3>
                        <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-500">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6">
                        <form id="editProductForm" 
                              enctype="multipart/form-data"
                              @submit.prevent="submitEditForm()" 
                              class="space-y-4">
                            <input type="hidden" name="product_id" x-model="editProduct.id">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" name="name" required x-model="editProduct.name"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea name="description" rows="3" required x-model="editProduct.description"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Category</label>
                                <select name="category_id" required x-model="editProduct.category_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>">
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Brand</label>
                                <select name="brand_id" required x-model="editProduct.brand_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Select a brand</option>
                                    <?php foreach ($brands as $brand): ?>
                                        <option value="<?= $brand['id'] ?>">
                                            <?= htmlspecialchars($brand['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Price (Rp)</label>
                                <input type="number" name="price" required min="0" x-model="editProduct.price"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Stock</label>
                                <input type="number" name="stock" required min="0" x-model="editProduct.stock"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Primary Image</label>
                                    <div class="mt-1 flex items-center space-x-4">
                                        <img x-show="editProduct.primary_image" :src="editProduct.primary_image" 
                                             class="h-20 w-20 object-cover rounded-md">
                                        <input type="file" 
                                               name="primary_image" 
                                               accept="image/*"
                                               @change="console.log('Primary image selected:', $event.target.files[0])"
                                               class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Hover Image</label>
                                    <div class="mt-1 flex items-center space-x-4">
                                        <img x-show="editProduct.hover_image" :src="editProduct.hover_image" 
                                             class="h-20 w-20 object-cover rounded-md">
                                        <input type="file" 
                                               name="hover_image" 
                                               accept="image/*"
                                               @change="console.log('Hover image selected:', $event.target.files[0])"
                                               class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex justify-end space-x-3 border-t p-4">
                        <button @click="showEditModal = false" 
                                class="rounded-md bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200">
                            Cancel
                        </button>
                        <button type="submit" form="editProductForm"
                                class="rounded-md bg-blue-500 px-4 py-2 text-white hover:bg-blue-600"
                                :disabled="isSubmitting">
                            <span x-show="!isSubmitting">Save Changes</span>
                            <span x-show="isSubmitting">Saving...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Image</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Brand</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($products as $product): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($product['image_url']): ?>
                            <img src="<?= getImageUrl($product['image_url']) ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>" 
                                 class="h-12 w-12 object-cover rounded">
                        <?php else: ?>
                            <div class="h-12 w-12 bg-gray-200 rounded flex items-center justify-center">
                                <span class="text-gray-500 text-xs">No image</span>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <?= htmlspecialchars($product['name']) ?>
                    </td>
                    <td class="px-6 py-4">
                        <?= htmlspecialchars($product['category_name']) ?>
                    </td>
                    <td class="px-6 py-4">
                        <?= htmlspecialchars($product['brand_name']) ?>
                    </td>
                    <td class="px-6 py-4">
                        Rp <?= number_format($product['price'], 0, ',', '.') ?>
                    </td>
                    <td class="px-6 py-4">
                        <?= number_format($product['stock']) ?>
                    </td>
                    <td class="px-6 py-4 flex space-x-2">
                        <button @click="fetchProduct(<?= $product['id'] ?>)" 
                                class="text-blue-600 hover:text-blue-900">Edit</button>
                        <form method="POST" class="inline" 
                              onsubmit="return confirm('Are you sure you want to delete this product?');">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <button type="submit" name="delete" 
                                    class="text-red-600 hover:text-red-900 ml-2">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                           class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing
                            <span class="font-medium"><?= $offset + 1 ?></span>
                            to
                            <span class="font-medium"><?= min($offset + $per_page, $total_products) ?></span>
                            of
                            <span class="font-medium"><?= $total_products ?></span>
                            results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            if ($start > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>
                                <?php if ($start > 2): ?>
                                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?= $i === $page ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-50' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($end < $total_pages): ?>
                                <?php if ($end < $total_pages - 1): ?>
                                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>
                                <?php endif; ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"><?= $total_pages ?></a>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function submitForm(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    this.isSubmitting = true;
    
    fetch('/admin/products/create', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        console.log('Raw response:', text); // Debug log
        try {
            const data = JSON.parse(text);
            if (data.success) {
                window.location.reload();
            } else {
                console.error('Server error:', data.message);
                alert(data.message || 'Error creating product');
            }
        } catch (e) {
            console.error('Error parsing JSON:', e);
            console.error('Raw response:', text);
            throw new Error(`Invalid JSON response: ${text.substring(0, 100)}...`);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error creating product: ' + error.message);
    })
    .finally(() => {
        this.isSubmitting = false;
    });
}

function fetchProduct(id) {
    fetch(`products?edit_id=${id}`)
        .then(response => response.json())
        .then(data => {
            this.editProduct = data;
            this.showEditModal = true;
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error fetching product details');
        });
}

function submitEditForm() {
    const form = document.getElementById('editProductForm');
    const formData = new FormData(form);
    
    this.isSubmitting = true;
    
    // Log the FormData contents for debugging
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }
    
    fetch('/admin/products/update', {
        method: 'POST',
        body: formData,
        headers: {
            // Do not set Content-Type here, let the browser handle it
        }
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Server response:', text);
            throw new Error('Invalid JSON response from server');
        }
    })
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Error updating product');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating product: ' + error.message);
    })
    .finally(() => {
        this.isSubmitting = false;
    });
}
</script> 