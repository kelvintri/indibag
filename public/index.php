<?php
session_start();

// Define the root path
define('ROOT_PATH', dirname(__DIR__));

// Include database configuration and classes
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/Auth.php';
require_once ROOT_PATH . '/includes/AdminAuth.php';
require_once ROOT_PATH . '/includes/Cart.php';
require_once ROOT_PATH . '/includes/Order.php';
require_once ROOT_PATH . '/includes/helpers.php';

// Get the current URL path
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Check if path starts with /admin
if (strpos($path, '/admin') === 0) {
    // Admin routes
    switch ($path) {
        case '/admin':
        case '/admin/dashboard':
            AdminAuth::requireAdmin();
            $pageTitle = 'Admin Dashboard';
            $content = ROOT_PATH . '/pages/admin/dashboard.php';
            require_once ROOT_PATH . '/includes/admin-layout.php';
            exit;
            break;
            
        case '/admin/products':
            AdminAuth::requireAdmin();
            $pageTitle = 'Manage Products';
            $content = ROOT_PATH . '/pages/admin/products.php';
            require_once ROOT_PATH . '/includes/admin-layout.php';
            exit;
            break;
            
        case '/admin/products/create':
            AdminAuth::requireAdmin();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $db = new Database();
                    $conn = $db->getConnection();

                    // Get brand and category data for meta generation
                    $stmt = $conn->prepare("SELECT name FROM brands WHERE id = ?");
                    $stmt->execute([$_POST['brand_id']]);
                    $brand = $stmt->fetch(PDO::FETCH_ASSOC);

                    $stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
                    $stmt->execute([$_POST['category_id']]);
                    $category = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$brand || !$category) {
                        throw new Exception("Invalid brand or category ID");
                    }

                    // Generate slug from name
                    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['name'])));
                    
                    // Check if slug exists and make it unique if needed
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE slug = ?");
                    $stmt->execute([$slug]);
                    $count = $stmt->fetchColumn();
                    
                    if ($count > 0) {
                        $originalSlug = $slug;
                        $counter = 1;
                        do {
                            $slug = $originalSlug . '-' . $counter;
                            $stmt->execute([$slug]);
                            $count = $stmt->fetchColumn();
                            $counter++;
                        } while ($count > 0);
                    }

                    // Generate meta title and description
                    // Limit meta title to 100 characters
                    $meta_title = substr($_POST['name'] . ' | ' . strtoupper($brand['name']) . ' ' . ucfirst($category['name']), 0, 100);

                    // Limit meta description to 255 characters
                    $description_excerpt = substr($_POST['description'], 0, 150); // Shorter excerpt to allow for brand name and prefix
                    if (strlen($_POST['description']) > 150) {
                        $description_excerpt .= '...';
                    }
                    $meta_description = substr('Shop ' . strtoupper($brand['name']) . ' ' . $_POST['name'] . '. ' . $description_excerpt, 0, 255);

                    // Begin transaction
                    $conn->beginTransaction();

                    // Insert product
                    $stmt = $conn->prepare("
                        INSERT INTO products (
                            name,
                            slug,
                            category_id,
                            brand_id,
                            description,
                            details,
                            meta_title,
                            meta_description,
                            price,
                            stock,
                            sku,
                            condition_status,
                            is_active,
                            created_at,
                            updated_at
                        ) 
                        VALUES (
                            :name,
                            :slug,
                            :category_id,
                            :brand_id,
                            :description,
                            :details,
                            :meta_title,
                            :meta_description,
                            :price,
                            :stock,
                            :sku,
                            :condition_status,
                            :is_active,
                            NOW(),
                            NOW()
                        )
                    ");
                    
                    $stmt->execute([
                        ':name' => $_POST['name'],
                        ':slug' => $slug,
                        ':category_id' => $_POST['category_id'],
                        ':brand_id' => $_POST['brand_id'] ?? 3, // Default to brand_id 3 if not provided
                        ':description' => $_POST['description'],
                        ':details' => $_POST['details'] ?? 'Made in Indonesia | Gold tone Hardware | MK Logo Medallion Hang Charm | Michael Kors metal Logo Lettering',
                        ':meta_title' => $meta_title,
                        ':meta_description' => $meta_description,
                        ':price' => $_POST['price'],
                        ':stock' => $_POST['stock'],
                        ':sku' => $slug, // Using slug as SKU
                        ':condition_status' => 'Brand new | Completeness: Care card',
                        ':is_active' => 1
                    ]);

                    $product_id = $conn->lastInsertId();

                    // Handle primary image
                    if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] === UPLOAD_ERR_OK) {
                        $category_stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
                        $category_stmt->execute([$_POST['category_id']]);
                        $category = $category_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$category) {
                            throw new Exception("Invalid category ID");
                        }
                        
                        $category_folder = strtolower($category['name']);
                        
                        // Define paths first
                        $base_upload_dir = ROOT_PATH . '/public/assets/images/' . $category_folder;
                        $upload_dir = $base_upload_dir . '/primary/';
                        
                        // Clean filename
                        $clean_name = preg_replace('/[^a-zA-Z0-9\s]/', '', $_POST['name']);
                        $clean_name = str_replace(' ', '_', trim(strtolower($clean_name)));
                        
                        // Generate paths
                        $filename = $clean_name . '_primary.jpg';
                        $upload_path = $upload_dir . $filename;
                        $web_path = '/assets/images/' . $category_folder . '/primary/' . $filename;
                        
                        // Add debug logging
                        error_log("Upload attempt - Base directory: " . ROOT_PATH);
                        error_log("Category folder: " . $category_folder);
                        error_log("Upload directory: " . $upload_dir);
                        error_log("Upload path: " . $upload_path);
                        error_log("Web path: " . $web_path);

                        // Check if file exists and is readable
                        if (!file_exists($_FILES['primary_image']['tmp_name']) || !is_readable($_FILES['primary_image']['tmp_name'])) {
                            error_log("Temporary file does not exist or is not readable");
                            throw new Exception("Temporary file issue");
                        }

                        // Check directory permissions
                        error_log("Base directory exists: " . (is_dir($base_upload_dir) ? 'yes' : 'no'));
                        error_log("Base directory writable: " . (is_writable($base_upload_dir) ? 'yes' : 'no'));
                        error_log("Upload directory exists: " . (is_dir($upload_dir) ? 'yes' : 'no'));
                        error_log("Upload directory writable: " . (is_writable($upload_dir) ? 'yes' : 'no'));

                        // Create directories if they don't exist
                        if (!is_dir($base_upload_dir)) {
                            if (!mkdir($base_upload_dir, 0777, true)) {
                                error_log("Failed to create base directory: " . $base_upload_dir);
                                throw new Exception("Failed to create base category directory");
                            }
                        }

                        if (!is_dir($upload_dir)) {
                            if (!mkdir($upload_dir, 0777, true)) {
                                error_log("Failed to create upload directory: " . $upload_dir);
                                throw new Exception("Failed to create primary image directory");
                            }
                        }

                        // Set directory permissions
                        chmod($base_upload_dir, 0777);
                        chmod($upload_dir, 0777);

                        // Attempt file upload
                        if (!move_uploaded_file($_FILES['primary_image']['tmp_name'], $upload_path)) {
                            error_log("Failed to move file. PHP Error: " . error_get_last()['message']);
                            throw new Exception('Failed to move primary image. Upload path: ' . $upload_path);
                        }

                        // Log success
                        error_log("File successfully uploaded to: " . $upload_path);

                        // Insert into product_galleries
                        $stmt = $conn->prepare("
                            INSERT INTO product_galleries (product_id, image_url, is_primary, sort_order, created_at)
                            VALUES (?, ?, 1, 0, NOW())
                        ");
                        $stmt->execute([$product_id, $web_path]);
                    }

                    // Handle hover image similarly
                    if (isset($_FILES['hover_image']) && $_FILES['hover_image']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = $base_upload_dir . '/hover/';
                        
                        // Generate paths for hover image
                        $filename = $clean_name . '_hover.jpg';
                        $upload_path = $upload_dir . $filename;
                        $web_path = '/assets/images/' . $category_folder . '/hover/' . $filename;
                        
                        // Create hover directory if needed
                        if (!is_dir($upload_dir)) {
                            if (!mkdir($upload_dir, 0777, true)) {
                                error_log("Failed to create hover directory: " . $upload_dir);
                                throw new Exception("Failed to create hover image directory");
                            }
                        }
                        
                        // Set directory permissions
                        chmod($upload_dir, 0777);
                        
                        // Attempt file upload
                        if (!move_uploaded_file($_FILES['hover_image']['tmp_name'], $upload_path)) {
                            error_log("Failed to move hover file. PHP Error: " . error_get_last()['message']);
                            throw new Exception('Failed to move hover image. Upload path: ' . $upload_path);
                        }
                        
                        // Log success
                        error_log("Hover file successfully uploaded to: " . $upload_path);
                        
                        // Insert into product_galleries
                        $stmt = $conn->prepare("
                            INSERT INTO product_galleries (product_id, image_url, is_primary, sort_order, created_at)
                            VALUES (?, ?, 0, 1, NOW())
                        ");
                        $stmt->execute([$product_id, $web_path]);
                    }

                    $conn->commit();

                    // Return success response
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                    exit;
                } catch (Exception $e) {
                    if (isset($conn)) {
                        $conn->rollBack();
                    }
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                    exit;
                }
            }
            break;
            
        case (preg_match('/^\/admin\/products\/edit\/(\d+)$/', $path, $matches) ? true : false):
            AdminAuth::requireAdmin();
            $pageTitle = 'Edit Product';
            $content = ROOT_PATH . '/pages/admin/products/edit.php';
            require_once ROOT_PATH . '/includes/admin-layout.php';
            exit;
            break;
            
        case '/admin/orders':
            AdminAuth::requireAdmin();
            $pageTitle = 'Manage Orders';
            $content = ROOT_PATH . '/pages/admin/orders/index.php';
            require_once ROOT_PATH . '/includes/admin-layout.php';
            exit;
            break;
            
        case '/admin/users':
            AdminAuth::requireAdmin();
            $pageTitle = 'Manage Users';
            $content = ROOT_PATH . '/pages/admin/users/index.php';
            require_once ROOT_PATH . '/includes/admin-layout.php';
            exit;
            break;
            
        case '/admin/settings':
            AdminAuth::requireAdmin();
            $pageTitle = 'Settings';
            $content = ROOT_PATH . '/pages/admin/settings.php';
            require_once ROOT_PATH . '/includes/admin-layout.php';
            exit;
            break;
        case '/admin/products/update':
            AdminAuth::requireAdmin();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                require_once ROOT_PATH . '/pages/admin/update-product.php';
                exit;
            }
            break;
    }
}

// Regular routes
switch ($path) {
    case '/':
        $pageTitle = 'Home - Bananina';
        $content = ROOT_PATH . '/pages/home.php';
        break;
    case '/products':
        $pageTitle = 'Products - Bananina';
        $content = ROOT_PATH . '/pages/products.php';
        break;
    case '/categories':
        $pageTitle = 'Categories - Bananina';
        $content = ROOT_PATH . '/pages/categories.php';
        break;
    case '/cart':
        $pageTitle = 'Shopping Cart - Bananina';
        $content = ROOT_PATH . '/pages/cart.php';
        break;
    case '/cart/add':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $cart = new Cart();
            $result = $cart->add($_POST['product_id'], $_POST['quantity'] ?? 1);
            // Return JSON response instead of redirecting
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }
        header('Location: /');
        break;
    case '/login':
        $pageTitle = 'Login - Bananina';
        $content = ROOT_PATH . '/pages/login.php';
        break;
    case '/register':
        $pageTitle = 'Register - Bananina';
        $content = ROOT_PATH . '/pages/register.php';
        break;
    case '/logout':
        $auth = new Auth();
        $auth->logout();
        header('Location: /login');
        exit;
        break;
    case '/debug-images':
        $pageTitle = 'Debug Images';
        $content = ROOT_PATH . '/pages/debug-images.php';
        break;
    case (preg_match('/^\/products\/[\w-]+$/', $path) ? true : false):
        $slug = basename($path);
        $pageTitle = 'Product Details - Bananina';
        $content = ROOT_PATH . '/pages/product-detail.php';
        break;
    case '/cart/count':
        $cart = new Cart();
        echo $cart->getCount();
        exit;
        break;
    case '/cart/items':
        $cart = new Cart();
        $items = $cart->getItems();
        $total = $cart->getTotal();
        header('Content-Type: application/json');
        echo json_encode([
            'items' => $items,
            'total' => $total
        ]);
        exit;
        break;
    case '/profile':
        Auth::requireLogin();
        $pageTitle = 'Profile - Bananina';
        $content = ROOT_PATH . '/pages/profile/index.php';
        break;
    case '/profile/addresses':
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Handle AJAX requests
            $action = $_POST['action'] ?? '';
            if (in_array($action, ['add', 'edit', 'delete'])) {
                require_once ROOT_PATH . '/pages/profile/addresses.php';
                exit; // Important: exit after handling AJAX request
            }
        }
        $pageTitle = 'Manage Addresses - Bananina';
        $content = ROOT_PATH . '/pages/profile/addresses.php';
        break;
    case '/checkout':
        Auth::requireLogin();
        // Redirect if cart is empty
        $cart = new Cart();
        if (empty($cart->getItems())) {
            header('Location: /cart');
            exit;
        }
        $pageTitle = 'Checkout - Bananina';
        $content = ROOT_PATH . '/pages/checkout.php';
        break;
    case '/orders/create':
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_once ROOT_PATH . '/includes/Order.php';
            
            $data = json_decode(file_get_contents('php://input'), true);
            $cart = new Cart();
            $cartItems = $cart->getItems();
            $total = $cart->getTotal();
            
            if (empty($cartItems)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Cart is empty']);
                exit;
            }
            
            $orderData = [
                'shipping_address_id' => $data['shipping_address_id'],
                'payment_method' => $data['payment_method'],
                'total_amount' => $total,
                'items' => $cartItems
            ];
            
            $order = new Order();
            $result = $order->create($orderData);
            
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }
        break;
    case (preg_match('/^\/orders\/(\d+)$/', $path, $matches) ? true : false):
        Auth::requireLogin();
        $order_id = $matches[1];
        
        // Include Order class
        require_once ROOT_PATH . '/includes/Order.php';
        
        // Pre-check order existence
        $orderObj = new Order();
        $order = $orderObj->getOrder($order_id);
        
        if (!$order) {
            $pageTitle = '404 Not Found - Bananina';
            $content = ROOT_PATH . '/pages/404.php';
        } else {
            $pageTitle = 'Order Details - Bananina';
            $content = ROOT_PATH . '/pages/orders/detail.php';
        }
        break;
    case '/orders':
        Auth::requireLogin();
        $pageTitle = 'My Orders - Bananina';
        $content = ROOT_PATH . '/pages/orders/index.php';
        break;
    case '/orders/upload-payment':
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Prevent any PHP errors from being output
            error_reporting(0);
            
            // Set headers early
            header('Content-Type: application/json');
            
            // Start output buffering
            ob_start();
            
            try {
                if (!isset($_FILES['payment_proof']) || !isset($_POST['order_id'])) {
                    throw new Exception('Missing required data');
                }

                require_once ROOT_PATH . '/includes/Order.php';
                $order = new Order();
                $result = $order->uploadPaymentProof($_POST['order_id'], $_FILES['payment_proof']);
                
                // Clean any output before sending JSON
                ob_clean();
                echo json_encode($result);
                
            } catch (Exception $e) {
                ob_clean();
                error_log("Upload error: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            exit;
        }
        break;
    default:
        $pageTitle = '404 Not Found - Bananina';
        $content = ROOT_PATH . '/pages/404.php';
        break;
}

// Include the main layout for non-admin routes
require_once ROOT_PATH . '/layouts/main.php'; 