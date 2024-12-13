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

// Regular routes
switch ($path) {
    case '/':
        $pageTitle = 'Home - Bananina';
        $content = ROOT_PATH . '/pages/home.php';
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
        if (!include($content)) {
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
    case (preg_match('/^\/admin\/orders\/(\d+)$/', $path, $matches) ? true : false):
        AdminAuth::requireAdmin();
        header('Content-Type: application/json');
        require_once ROOT_PATH . '/pages/admin/orders/get-order.php';
        exit;
        break;
    default:
        $pageTitle = '404 Not Found - Bananina';
        $content = ROOT_PATH . '/pages/404.php';
        break;
}

// Include the main layout for non-admin routes
require_once ROOT_PATH . '/layouts/main.php'; 