<?php
require_once __DIR__ . '/../../includes/AdminAuth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

AdminAuth::requireAdmin();

header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Update product basic information
    $stmt = $conn->prepare("
        UPDATE products 
        SET name = ?, 
            description = ?, 
            category_id = ?, 
            price = ?, 
            stock = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $_POST['name'],
        $_POST['description'],
        $_POST['category_id'],
        $_POST['price'],
        $_POST['stock'],
        $_POST['product_id']
    ]);
    
    // Handle image uploads
    $upload_dir = __DIR__ . '/../../assets/images/';
    $web_path = '/assets/images/';
    
    // Get category name for the folder path
    $stmt = $conn->prepare("SELECT c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
    $stmt->execute([$_POST['product_id']]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        throw new Exception("Invalid category ID");
    }
    
    $category_folder = strtolower($category['category_name']);
    
    // Ensure category folder exists
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
            
            // Delete old image if exists
            $stmt = $conn->prepare("SELECT image_url FROM product_galleries WHERE product_id = ? AND is_primary = ?");
            $stmt->execute([$product_id, $is_primary ? 1 : 0]);
            $old_image = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($old_image && file_exists(__DIR__ . '/../../' . $old_image['image_url'])) {
                unlink(__DIR__ . '/../../' . $old_image['image_url']);
            }
            
            // Move uploaded file
            if (!move_uploaded_file($tmp_name, $upload_path)) {
                throw new Exception("Failed to move uploaded file to: " . $upload_path);
            }
            
            // Update or insert into product_galleries
            $stmt = $conn->prepare("
                INSERT INTO product_galleries (product_id, image_url, is_primary, sort_order, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    image_url = VALUES(image_url),
                    updated_at = NOW()
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
    
    // Handle primary image if uploaded
    if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if (!handleImageUpload($_FILES['primary_image'], $_POST['product_id'], true, $conn, $upload_dir, $web_path, $category_folder, $_POST['name'])) {
            throw new Exception('Error uploading primary image');
        }
    }
    
    // Handle hover image if uploaded
    if (isset($_FILES['hover_image']) && $_FILES['hover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if (!handleImageUpload($_FILES['hover_image'], $_POST['product_id'], false, $conn, $upload_dir, $web_path, $category_folder, $_POST['name'])) {
            throw new Exception('Error uploading hover image');
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error updating product: ' . $e->getMessage()
    ]);
} 