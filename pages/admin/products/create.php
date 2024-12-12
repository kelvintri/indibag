<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers early
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../includes/AdminAuth.php';
    require_once __DIR__ . '/../../../config/database.php';
    require_once __DIR__ . '/../../../includes/helpers.php';

    // Debug log
    error_log("Script path: " . __FILE__);
    error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));

    // Ensure we're getting a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    // Check admin authentication
    AdminAuth::requireAdmin();

    // Validate required fields
    $required_fields = ['name', 'description', 'category_id', 'brand_id', 'price', 'stock'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }

    // Validate files
    if (!isset($_FILES['primary_image']) || $_FILES['primary_image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Primary image is required");
    }
    if (!isset($_FILES['hover_image']) || $_FILES['hover_image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Hover image is required");
    }

    $db = new Database();
    $conn = $db->getConnection();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Insert product
    $stmt = $conn->prepare("
        INSERT INTO products (
            name, description, details, category_id, brand_id, 
            meta_title, meta_description, price, stock, 
            created_at, updated_at
        ) VALUES (
            :name, :description, :details, :category_id, :brand_id,
            :meta_title, :meta_description, :price, :stock,
            NOW(), NOW()
        )
    ");

    $stmt->execute([
        ':name' => $_POST['name'],
        ':description' => $_POST['description'],
        ':details' => $_POST['details'] ?? null,
        ':category_id' => $_POST['category_id'],
        ':brand_id' => $_POST['brand_id'],
        ':meta_title' => $_POST['meta_title'] ?? null,
        ':meta_description' => $_POST['meta_description'] ?? null,
        ':price' => $_POST['price'],
        ':stock' => $_POST['stock']
    ]);
    
    $product_id = $conn->lastInsertId();
    
    // Handle image uploads
    $upload_dir = __DIR__ . '/../../../assets/images/';
    $web_path = '/assets/images/';
    
    // Get category name for the folder path
    $stmt = $conn->prepare("SELECT name as category_name FROM categories WHERE id = ?");
    $stmt->execute([$_POST['category_id']]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        throw new Exception("Invalid category ID");
    }
    
    $category_folder = strtolower($category['category_name']);
    
    // Ensure upload directories exist
    $category_path = $upload_dir . $category_folder;
    foreach (['', '/primary', '/hover'] as $subfolder) {
        $dir_path = $category_path . $subfolder;
        if (!is_dir($dir_path)) {
            if (!mkdir($dir_path, 0777, true)) {
                throw new Exception("Failed to create directory: " . $dir_path);
            }
        }
    }
    
    // Function to handle image upload
    function handleImageUpload($file, $product_id, $is_primary, $conn, $upload_dir, $web_path, $category_folder, $product_name) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $file['tmp_name'];
            $extension = 'jpg'; // Force jpg extension as per database examples
            
            // Clean product name for filename (remove special characters, replace spaces with underscores)
            $clean_name = preg_replace('/[^a-zA-Z0-9\s]/', '', $product_name);
            $clean_name = str_replace(' ', '_', trim($clean_name));
            
            // Generate filename following the pattern from database
            $type = $is_primary ? 'primary' : 'hover';
            $new_filename = $clean_name . "_{$type}.{$extension}";
            
            // Set upload paths
            $subfolder = $is_primary ? 'primary' : 'hover';
            $upload_path = $upload_dir . $category_folder . '/' . $subfolder . '/' . $new_filename;
            $web_image_path = $web_path . $category_folder . '/' . $subfolder . '/' . $new_filename;
            
            // Move uploaded file
            if (!move_uploaded_file($tmp_name, $upload_path)) {
                throw new Exception("Failed to move uploaded file to: " . $upload_path);
            }
            
            // Insert into product_galleries
            $stmt = $conn->prepare("
                INSERT INTO product_galleries (product_id, image_url, is_primary, sort_order, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $product_id, 
                $web_image_path, 
                $is_primary ? 1 : 0,
                $is_primary ? 0 : 1 // Sort order: 0 for primary, 1 for hover
            ]);
            
            return true;
        }
        return false;
    }
    
    // Handle primary image
    if (!handleImageUpload($_FILES['primary_image'], $product_id, true, $conn, $upload_dir, $web_path, $category_folder, $_POST['name'])) {
        throw new Exception('Error uploading primary image');
    }
    
    // Handle hover image
    if (!handleImageUpload($_FILES['hover_image'], $product_id, false, $conn, $upload_dir, $web_path, $category_folder, $_POST['name'])) {
        throw new Exception('Error uploading hover image');
    }
    
    // Commit transaction
    $conn->commit();
    
    // Clear any output and send success response
    ob_clean();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Rollback transaction if exists
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    // Log error
    error_log("Product creation error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Clear any output and send error response
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    ob_end_flush();
}