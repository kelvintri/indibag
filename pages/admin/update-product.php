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
    
    // Get brand and category names for meta data
    $stmt = $conn->prepare("
        SELECT b.name as brand_name, c.name as category_name 
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$_POST['product_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Generate meta title and description if not provided
    $meta_title = $_POST['meta_title'] ?? $_POST['name'] . ' | ' . strtoupper($result['brand_name']) . ' ' . ucfirst($result['category_name']);
    $meta_description = $_POST['meta_description'] ?? 'Shop ' . strtoupper($result['brand_name']) . ' ' . $_POST['name'] . '. ' . $_POST['description'];
    
    // Update product basic information
    $stmt = $conn->prepare("
        UPDATE products 
        SET name = ?, 
            description = ?, 
            category_id = ?, 
            brand_id = ?,
            price = ?, 
            stock = ?,
            meta_title = ?,
            meta_description = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $_POST['name'],
        $_POST['description'],
        $_POST['category_id'],
        $_POST['brand_id'],
        $_POST['price'],
        $_POST['stock'],
        $meta_title,
        $meta_description,
        $_POST['product_id']
    ]);
    
    // Handle image uploads
    $upload_dir = __DIR__ . '/../../public/assets/images/';
    $web_path = '/assets/images/';
    
    // Get category name for the folder path
    $stmt = $conn->prepare("SELECT c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
    $stmt->execute([$_POST['product_id']]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        throw new Exception("Invalid category ID");
    }
    
    $category_folder = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]/', '-', $category['category_name'])));
    
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
            // Get file extension from uploaded file
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Clean product name for filename (remove special characters, replace spaces with underscores)
            $clean_name = preg_replace('/[^a-zA-Z0-9\s]/', '', $product_name);
            $clean_name = str_replace(' ', '_', trim(strtolower($clean_name)));
            
            // Generate filename following the pattern from database
            $type = $is_primary ? 'primary' : 'hover';
            $new_filename = $clean_name . "_{$type}.{$extension}";
            
            // Set upload paths
            $subfolder = $is_primary ? 'primary' : 'hover';
            $category_path = $category_folder;
            $upload_path = $upload_dir . $category_path . '/' . $subfolder . '/' . $new_filename;
            $web_image_path = $web_path . $category_path . '/' . $subfolder . '/' . $new_filename;
            
            // Delete old image if exists
            $stmt = $conn->prepare("SELECT image_url FROM product_galleries WHERE product_id = ? AND is_primary = ?");
            $stmt->execute([$product_id, $is_primary ? 1 : 0]);
            $old_image = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($old_image) {
                $old_file_path = __DIR__ . '/../../public' . $old_image['image_url'];
                error_log("Attempting to delete old file: " . $old_file_path);
                if (file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
            }
            
            // Ensure directory exists
            $upload_folder = dirname($upload_path);
            if (!is_dir($upload_folder)) {
                if (!mkdir($upload_folder, 0777, true)) {
                    throw new Exception("Failed to create directory: " . $upload_folder);
                }
            }
            
            // Move uploaded file
            if (!move_uploaded_file($tmp_name, $upload_path)) {
                error_log("Failed to move file from {$tmp_name} to {$upload_path}");
                error_log("Upload path exists: " . (file_exists(dirname($upload_path)) ? 'yes' : 'no'));
                error_log("Upload path writable: " . (is_writable(dirname($upload_path)) ? 'yes' : 'no'));
                throw new Exception("Failed to move uploaded file to: " . $upload_path);
            }
            
            // Update or insert into product_galleries
            $stmt = $conn->prepare("
                UPDATE product_galleries 
                SET image_url = ?
                WHERE product_id = ? AND is_primary = ?
            ");
            
            $stmt->execute([
                $web_image_path,
                $product_id,
                $is_primary ? 1 : 0
            ]);
            
            // If no rows were updated, insert a new record
            if ($stmt->rowCount() === 0) {
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
            }
            
            return true;
        }
        return false;
    }
    
    // Handle primary image if uploaded
    if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        error_log("Primary image upload attempt - Error code: " . $_FILES['primary_image']['error']);
        error_log("Primary image temp name: " . $_FILES['primary_image']['tmp_name']);
        if (!handleImageUpload($_FILES['primary_image'], $_POST['product_id'], true, $conn, $upload_dir, $web_path, $category_folder, $_POST['name'])) {
            throw new Exception('Error uploading primary image');
        }
    }
    
    // Handle hover image if uploaded
    if (isset($_FILES['hover_image']) && $_FILES['hover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        error_log("Hover image upload attempt - Error code: " . $_FILES['hover_image']['error']);
        error_log("Hover image temp name: " . $_FILES['hover_image']['tmp_name']);
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