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

    // Helper function to render pages
    function renderPage($filePath) {
        ob_start();
        $result = include($filePath);
        if ($result === false) {
            ob_end_clean();
            return false;
        }
        return ob_get_clean();
    }

    // Check if path starts with /api
    if (strpos($path, '/api') === 0) {
        // API routes
        if (preg_match('/^\/api\/orders\/(\d+)$/', $path, $matches)) {
            require_once ROOT_PATH . '/api/orders/[id].php';
            exit;
        }
        // Add more API routes here if needed
        
        // If no API route matches, return 404
        http_response_code(404);
        echo json_encode(['error' => 'API endpoint not found']);
        exit;
    }

    // Check if path starts with /admin
    if (strpos($path, '/admin') === 0) {
        // Admin routes
        switch (true) {
            case '/admin' === $path:
            case '/admin/dashboard' === $path:
                AdminAuth::requireAdmin();
                $pageTitle = 'Admin Dashboard';
                $content = ROOT_PATH . '/pages/admin/dashboard.php';
                require_once ROOT_PATH . '/includes/admin-layout.php';
                exit;
                break;

            case '/admin/orders' === $path:
                AdminAuth::requireAdmin();
                $pageTitle = 'Manage Orders';
                $content = ROOT_PATH . '/pages/admin/orders/index.php';
                require_once ROOT_PATH . '/includes/admin-layout.php';
                exit;
                break;

            case '/admin/orders/update-status' === $path:
                AdminAuth::requireAdmin();
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    require_once ROOT_PATH . '/pages/admin/orders/update-status.php';
                    exit;
                }
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
                break;

            case '/admin/orders/update-shipping' === $path:
                AdminAuth::requireAdmin();
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    require_once ROOT_PATH . '/pages/admin/orders/update-shipping.php';
                    exit;
                }
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
                break;

            case (preg_match('/^\/admin\/orders\/(\d+)$/', $path, $matches) === 1):
                error_log('=== Admin Order Details Route ===');
                error_log('Matches: ' . print_r($matches, true));
                error_log('Request Method: ' . $_SERVER['REQUEST_METHOD']);
                
                AdminAuth::requireAdmin();
                if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                    error_log('Processing GET request for order details');
                    header('Content-Type: application/json');
                    require_once ROOT_PATH . '/pages/admin/orders/get-order.php';
                    exit;
                }
                error_log('Method not allowed: ' . $_SERVER['REQUEST_METHOD']);
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
                break;
                
            case '/admin/products' === $path:
                AdminAuth::requireAdmin();
                $pageTitle = 'Manage Products';
                $content = ROOT_PATH . '/pages/admin/products.php';
                require_once ROOT_PATH . '/includes/admin-layout.php';
                exit;
                break;

            case '/admin/products/create' === $path:
                AdminAuth::requireAdmin();
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    require_once ROOT_PATH . '/pages/admin/products/create.php';
                    exit;
                }
                $pageTitle = 'Create Product';
                $content = ROOT_PATH . '/pages/admin/products/create-form.php';
                require_once ROOT_PATH . '/includes/admin-layout.php';
                exit;
                break;

            case '/admin/products/update' === $path:
                AdminAuth::requireAdmin();
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    require_once ROOT_PATH . '/pages/admin/products/edit.php';
                    exit;
                }
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
                break;

            case '/admin/users' === $path:
                AdminAuth::requireAdmin();
                $pageTitle = 'Manage Users';
                $content = ROOT_PATH . '/pages/admin/users/index.php';
                require_once ROOT_PATH . '/includes/admin-layout.php';
                exit;
                break;
                
            case '/admin/settings' === $path:
                AdminAuth::requireAdmin();
                $pageTitle = 'Settings';
                $content = ROOT_PATH . '/pages/admin/settings.php';
                require_once ROOT_PATH . '/includes/admin-layout.php';
                exit;
                break;
        }
    }

    // Regular routes
    switch ($path) {
        case '/':
            $pageTitle = 'Home - Bananina';
            $content = renderPage(ROOT_PATH . '/pages/home.php');
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
                error_log('=== Cart Add Request ===');
                error_log('POST data: ' . print_r($_POST, true));
                error_log('Request headers: ' . print_r(getallheaders(), true));
                
                $cart = new Cart();
                $result = $cart->add($_POST['product_id'], $_POST['quantity'] ?? 1);
                
                error_log('Cart add result: ' . print_r($result, true));
                
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
            }
            header('Location: /');
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

        case (preg_match('/^\/products\/[\w-]+$/', $path) ? true : false):
            $slug = basename($path);
            $pageTitle = 'Product Details - Bananina';
            $content = renderPage(ROOT_PATH . '/pages/product-detail.php');
            if ($content === false) {
                header('Location: /404');
                exit;
            }
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

        case '/orders/create':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                Auth::requireLogin();
                header('Content-Type: application/json');
                
                // Get JSON input
                $json = file_get_contents('php://input');
                $data = json_decode($json, true);
                
                // Get cart items
                $cart = new Cart();
                $items = $cart->getItems();
                
                if (empty($items)) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Cart is empty'
                    ]);
                    exit;
                }
                
                // Add items to order data
                $data['items'] = $items;
                
                // Create order
                $order = new Order();
                $result = $order->create($data);
                
                // Clear cart if order was created successfully
                if ($result['success']) {
                    $cart->clear();
                    $order_id = $order->getLastInsertId();
                    $response = [
                        'success' => true,
                        'order_id' => $order_id,
                        'redirect_url' => "/orders/{$order_id}/confirmation"
                    ];
                    error_log('Order creation response: ' . json_encode($response));
                    echo json_encode($response);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => $result['error']
                    ]);
                }
                exit;
            }
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
            break;

        case (preg_match('/^\/orders\/(\d+)\/confirmation$/', $path, $matches) ? true : false):
            Auth::requireLogin();
            $pageTitle = 'Order Confirmation - Bananina';
            
            // Get order ID from URL
            $order_id = $matches[1];
            
            // Include Order class and get order details
            require_once ROOT_PATH . '/includes/Order.php';
            $orderObj = new Order();
            $order = $orderObj->getOrder($order_id);
            
            // Verify order belongs to user
            if (!$order || $order['user_id'] != $_SESSION['user_id']) {
                header('Location: /orders');
                exit;
            }
            
            // Get shipping address details
            $db = new Database();
            $conn = $db->getConnection();
            $addressQuery = "SELECT * FROM addresses WHERE id = :address_id";
            $addressStmt = $conn->prepare($addressQuery);
            $addressStmt->bindParam(":address_id", $order['shipping_address_id']);
            $addressStmt->execute();
            $shippingAddress = $addressStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get shipping details
            $shippingQuery = "SELECT * FROM shipping_details WHERE order_id = :order_id";
            $shippingStmt = $conn->prepare($shippingQuery);
            $shippingStmt->bindParam(":order_id", $order_id);
            $shippingStmt->execute();
            $shippingDetails = $shippingStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get payment details
            $paymentQuery = "SELECT payment_method, payment_date, verified_at, transfer_proof_url 
                           FROM payment_details 
                           WHERE order_id = :order_id";
            $paymentStmt = $conn->prepare($paymentQuery);
            $paymentStmt->bindParam(":order_id", $order_id);
            $paymentStmt->execute();
            $paymentDetails = $paymentStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get order items
            $items = $orderObj->getOrderItems($order_id);
            
            // Make all data available to the confirmation page
            $orderData = [
                'order' => $order,
                'items' => $items,
                'shippingAddress' => $shippingAddress,
                'shippingDetails' => $shippingDetails,
                'paymentDetails' => $paymentDetails
            ];
            extract($orderData);
            
            ob_start();
            require ROOT_PATH . '/pages/orders/confirmation.php';
            $content = ob_get_clean();
            
            if (empty($content)) {
                error_log('Failed to render confirmation page for order ' . $order_id);
                header('Location: /404');
                exit;
            }
            
            require_once ROOT_PATH . '/layouts/main.php';
            exit;
            break;

        case '/orders':
            Auth::requireLogin();
            $pageTitle = 'My Orders - Bananina';
            $content = renderPage(ROOT_PATH . '/pages/orders/index.php');
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
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Order not found']);
                exit;
            }

            // Get order items
            $items = $orderObj->getOrderItems($order_id);
            $order['items'] = $items;
            
            // Get payment details
            $db = new Database();
            $conn = $db->getConnection();
            $query = "SELECT payment_method, payment_date, verified_at, transfer_proof_url 
                    FROM payment_details 
                    WHERE order_id = :order_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(":order_id", $order_id);
            $stmt->execute();
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($payment) {
                $order['payment_method'] = $payment['payment_method'];
                $order['payment_date'] = $payment['payment_date'];
                $order['verified_at'] = $payment['verified_at'];
                $order['transfer_proof_url'] = $payment['transfer_proof_url'];
            }

            // Add can_reupload_payment flag
            $order['can_reupload_payment'] = $orderObj->canReuploadPayment($order_id);

            header('Content-Type: application/json');
            echo json_encode($order);
            exit;
            break;

        case '/orders/upload-payment':
            Auth::requireLogin();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                require_once ROOT_PATH . '/orders/upload-payment.php';
                exit;
            }
            header('Location: /orders');
            break;

        case (preg_match('/^\/orders\/(\d+)\/cancel$/', $path, $matches) ? true : false):
            Auth::requireLogin();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                require_once ROOT_PATH . '/pages/orders/cancel.php';
                exit;
            }
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
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