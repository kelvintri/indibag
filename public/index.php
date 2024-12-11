<?php
session_start();

// Define the root path
define('ROOT_PATH', dirname(__DIR__));

// Include database configuration and classes
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/Auth.php';
require_once ROOT_PATH . '/includes/Cart.php';
require_once ROOT_PATH . '/includes/Order.php';
require_once ROOT_PATH . '/includes/helpers.php';

// Get the current URL path
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Basic routing
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

// Include the main layout
require_once ROOT_PATH . '/layouts/main.php'; 