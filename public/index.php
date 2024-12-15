<?php
error_log('=== New Request ===');
error_log('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
error_log('SCRIPT_NAME: ' . $_SERVER['SCRIPT_NAME']);
error_log('PHP_SELF: ' . $_SERVER['PHP_SELF']);

session_start();

// Define the root path
define('ROOT_PATH', dirname(__DIR__));

// Add this debug line
error_log('ROOT_PATH: ' . ROOT_PATH);

try {
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

    error_log('Processing request for path: ' . $path);

    // Test API route
    if ($path === '/api/test') {
        error_log('Matched /api/test route');
        header('Content-Type: application/json');
        echo json_encode(['message' => 'API route working']);
        exit;
    }

    // Handle API routes
    if (strpos($path, '/api/v1/') === 0) {
        error_log('Matched API v1 route prefix');
        $api_path = ltrim(substr($path, 7), '/'); // Remove /api/v1 and any leading slash
        error_log('API path after prefix removal: ' . $api_path);

        switch ($api_path) {
            case 'auth/login':
                $api_file = ROOT_PATH . '/api/v1/auth/login.php';
                error_log('Looking for login file at: ' . $api_file);
                if (!file_exists($api_file)) {
                    error_log('Login file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;
                
            case 'auth/register':
                $api_file = ROOT_PATH . '/api/v1/auth/register.php';
                if (!file_exists($api_file)) {
                    error_log('Register file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;

            case 'products':
                $api_file = ROOT_PATH . '/api/v1/products/index.php';
                if (!file_exists($api_file)) {
                    error_log('Products file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;
                
            case (preg_match('/^products\/([^\/]+)$/', $api_path, $matches) ? true : false):
                $api_file = ROOT_PATH . '/api/v1/products/detail.php';
                if (!file_exists($api_file)) {
                    error_log('Product detail file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                error_log('Product detail route matched. Slug: ' . $matches[1]);
                $_GET['slug'] = urldecode($matches[1]); // URL decode the slug
                require_once $api_file;
                exit;
                
            case 'categories':
                $api_file = ROOT_PATH . '/api/v1/categories/index.php';
                if (!file_exists($api_file)) {
                    error_log('Categories file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;

            case 'brands':
                $api_file = ROOT_PATH . '/api/v1/brands/index.php';
                if (!file_exists($api_file)) {
                    error_log('Brands file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;

            case 'cart':
                $api_file = ROOT_PATH . '/api/v1/cart/index.php';
                if (!file_exists($api_file)) {
                    error_log('Cart file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;

            case 'cart/add':
                $api_file = ROOT_PATH . '/api/v1/cart/add.php';
                if (!file_exists($api_file)) {
                    error_log('Cart add file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;

            case 'cart/update':
                $api_file = ROOT_PATH . '/api/v1/cart/update.php';
                if (!file_exists($api_file)) {
                    error_log('Cart update file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;

            case 'cart/remove':
                $api_file = ROOT_PATH . '/api/v1/cart/remove.php';
                if (!file_exists($api_file)) {
                    error_log('Cart remove file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;

            case 'orders/create':
                $api_file = ROOT_PATH . '/api/v1/orders/create.php';
                if (!file_exists($api_file)) {
                    error_log('Orders create file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;

            case 'user/addresses':
                $api_file = ROOT_PATH . '/api/v1/user/addresses.php';
                if (!file_exists($api_file)) {
                    error_log('User addresses file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;

            case 'user/addresses/create':
                $api_file = ROOT_PATH . '/api/v1/user/addresses/create.php';
                if (!file_exists($api_file)) {
                    error_log('Address create file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;

            case 'user/addresses/update':
                $api_file = ROOT_PATH . '/api/v1/user/addresses/update.php';
                if (!file_exists($api_file)) {
                    error_log('Address update file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;

            case 'user/addresses/delete':
                $api_file = ROOT_PATH . '/api/v1/user/addresses/delete.php';
                if (!file_exists($api_file)) {
                    error_log('Address delete file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;

            case 'orders':
                $api_file = ROOT_PATH . '/api/v1/orders/index.php';
                if (!file_exists($api_file)) {
                    error_log('Orders list file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;

            case (preg_match('/^orders\/(\d+)$/', $api_path, $matches) ? true : false):
                $api_file = ROOT_PATH . '/api/v1/orders/detail.php';
                if (!file_exists($api_file)) {
                    error_log('Order detail file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                $_GET['id'] = $matches[1];
                require_once $api_file;
                exit;

            case (preg_match('/^orders\/(\d+)\/upload-payment$/', $api_path, $matches) ? true : false):
                $api_file = ROOT_PATH . '/api/v1/orders/upload-payment.php';
                if (!file_exists($api_file)) {
                    error_log('Order upload payment file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                $_GET['id'] = $matches[1];
                require_once $api_file;
                exit;

            case 'user/profile':
                $api_file = ROOT_PATH . '/api/v1/user/profile.php';
                if (!file_exists($api_file)) {
                    error_log('User profile file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;

            case 'user/profile/update':
                $api_file = ROOT_PATH . '/api/v1/user/profile/update.php';
                if (!file_exists($api_file)) {
                    error_log('Profile update file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;

            case 'user/profile/password':
                $api_file = ROOT_PATH . '/api/v1/user/profile/password.php';
                if (!file_exists($api_file)) {
                    error_log('Password update file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                require_once $api_file;
                exit;

            case (preg_match('/^orders\/(\d+)\/cancel$/', $api_path, $matches) ? true : false):
                $api_file = ROOT_PATH . '/api/v1/orders/cancel.php';
                if (!file_exists($api_file)) {
                    error_log('Order cancel file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                $_GET['id'] = $matches[1];
                require_once $api_file;
                exit;

            case (preg_match('/^orders\/(\d+)\/refund$/', $api_path, $matches) ? true : false):
                $api_file = ROOT_PATH . '/api/v1/orders/refund.php';
                if (!file_exists($api_file)) {
                    error_log('Order refund file not found at: ' . $api_file);
                    throw new Exception('API endpoint file not found');
                }
                $_GET['id'] = $matches[1];
                require_once $api_file;
                exit;

            default:
                header('Content-Type: application/json');
                http_response_code(404);
                echo json_encode([
                    'error' => 'API endpoint not found', 
                    'path' => $api_path,
                    'full_path' => ROOT_PATH . '/api/v1/' . $api_path . '.php'
                ]);
                exit;
        }
    }

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
                    require_once ROOT_PATH . '/pages/admin/products/create.php';
                    exit;
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
            case '/admin/orders/update-status':
                AdminAuth::requireAdmin();
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    require_once ROOT_PATH . '/pages/admin/orders/update-status.php';
                    exit;
                }
                break;
            case '/admin/orders/update-shipping':
                AdminAuth::requireAdmin();
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    require_once ROOT_PATH . '/pages/admin/orders/update-shipping.php';
                    exit;
                }
                break;
            case (preg_match('/^\/admin\/orders\/(\d+)$/', $path, $matches) ? true : false):
                AdminAuth::requireAdmin();
                header('Content-Type: application/json');
                require_once ROOT_PATH . '/pages/admin/orders/get-order.php';
                exit;
                break;
        }
    }

    // At the top of the file, add a helper function
    function renderPage($filePath) {
        ob_start();
        $result = include($filePath);
        if ($result === false) {
            ob_end_clean();
            return false;
        }
        return ob_get_clean();
    }

    // Regular routes
    switch ($path) {
        case '/':
            $pageTitle = 'Home - Bananina';
            $content = renderPage(ROOT_PATH . '/pages/home.php');
            break;
        case '/orders/create':
            Auth::requireLogin();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                require_once ROOT_PATH . '/includes/Cart.php';
                require_once ROOT_PATH . '/includes/Order.php';
                require_once ROOT_PATH . '/pages/orders/create.php';
                exit;
            }
            break;
        case '/products':
            $pageTitle = 'Products - Bananina';
            $content = renderPage(ROOT_PATH . '/pages/products.php');
            break;
        case '/categories':
            $pageTitle = 'Categories - Bananina';
            $content = renderPage(ROOT_PATH . '/pages/categories.php');
            break;
        case '/cart':
            $pageTitle = 'Shopping Cart - Bananina';
            $content = renderPage(ROOT_PATH . '/pages/cart.php');
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
            $content = renderPage(ROOT_PATH . '/pages/login.php');
            break;
        case '/register':
            $pageTitle = 'Register - Bananina';
            $content = renderPage(ROOT_PATH . '/pages/register.php');
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
            $content = renderPage(ROOT_PATH . '/pages/product-detail.php');
            if ($content === false) {
                header('Location: /404');
                exit;
            }
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
            $content = renderPage(ROOT_PATH . '/pages/profile/index.php');
            break;
        case '/profile/addresses':
            Auth::requireLogin();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Handle AJAX requests
                $action = $_POST['action'] ?? '';
                if (in_array($action, ['add', 'edit', 'delete'])) {
                    require_once ROOT_PATH . '/pages/profile/addresses.php';
                    exit;
                }
            }
            $pageTitle = 'Manage Addresses - Bananina';
            $content = renderPage(ROOT_PATH . '/pages/profile/addresses.php');
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
            $content = renderPage(ROOT_PATH . '/pages/checkout.php');
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
                $content = renderPage(ROOT_PATH . '/pages/404.php');
            } else {
                $pageTitle = 'Order Details - Bananina';
                $content = renderPage(ROOT_PATH . '/pages/orders/detail.php');
            }
            break;
        case '/orders':
            Auth::requireLogin();
            $pageTitle = 'My Orders - Bananina';
            $content = renderPage(ROOT_PATH . '/pages/orders/index.php');
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
        case (preg_match('/^\/admin\/orders\/(\d+)$/', $path, $matches) ? true : false):
            AdminAuth::requireAdmin();
            header('Content-Type: application/json');
            require_once ROOT_PATH . '/pages/admin/orders/get-order.php';
            exit;
            break;
        default:
            $pageTitle = '404 Not Found - Bananina';
            $content = renderPage(ROOT_PATH . '/pages/404.php');
            break;
    }

    // Include the main layout if content is available
    if ($content !== false) {
        require_once ROOT_PATH . '/layouts/main.php';
    } else {
        header('Location: /404');
        exit;
    }

} catch (Exception $e) {
    error_log('Error in index.php: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
} 