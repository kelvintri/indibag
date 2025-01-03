<?php
require_once __DIR__ . '/../../../includes/AdminAuth.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';

AdminAuth::requireAdmin();
header('Content-Type: application/json');

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
    $meta_title = substr($_POST['name'] . ' | ' . strtoupper($brand['name']) . ' ' . ucfirst($category['name']), 0, 100);
    $description_excerpt = substr($_POST['description'], 0, 150);
    if (strlen($_POST['description']) > 150) {
        $description_excerpt .= '...';
    }
    $meta_description = substr('Shop ' . strtoupper($brand['name']) . ' ' . $_POST['name'] . '. ' . $description_excerpt, 0, 255);

    // Begin transaction
    $conn->beginTransaction();

    // Insert product
    $stmt = $conn->prepare("
        INSERT INTO products (
            name, slug, category_id, brand_id, description, details,
            meta_title, meta_description, price, stock, sku,
            condition_status, is_active, created_at
        ) 
        VALUES (
            :name, :slug, :category_id, :brand_id, :description, :details,
            :meta_title, :meta_description, :price, :stock, :sku,
            :condition_status, :is_active, NOW()
        )
    ");
    
    $stmt->execute([
        ':name' => $_POST['name'],
        ':slug' => $slug,
        ':category_id' => $_POST['category_id'],
        ':brand_id' => $_POST['brand_id'] ?? 3,
        ':description' => $_POST['description'],
        ':details' => $_POST['details'] ?? 'Made in Indonesia | Gold tone Hardware | MK Logo Medallion Hang Charm | Michael Kors metal Logo Lettering',
        ':meta_title' => $meta_title,
        ':meta_description' => $meta_description,
        ':price' => $_POST['price'],
        ':stock' => $_POST['stock'],
        ':sku' => $slug,
        ':condition_status' => 'Brand new | Completeness: Care card',
        ':is_active' => 1
    ]);

    $product_id = $conn->lastInsertId();

    // Handle image uploads
    foreach (['primary_image' => true, 'hover_image' => false] as $field => $is_primary) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $category_folder = strtolower($category['name']);
            $base_upload_dir = ROOT_PATH . '/public/assets/images/' . $category_folder;
            $upload_dir = $base_upload_dir . ($is_primary ? '/primary/' : '/hover/');
            
            // Clean filename
            $clean_name = preg_replace('/[^a-zA-Z0-9\s]/', '', $_POST['name']);
            $clean_name = str_replace(' ', '_', trim(strtolower($clean_name)));
            
            // Generate paths with WebP extension
            $filename = $clean_name . ($is_primary ? '_primary.webp' : '_hover.webp');
            $upload_path = $upload_dir . $filename;
            $web_path = '/assets/images/' . $category_folder . ($is_primary ? '/primary/' : '/hover/') . $filename;

            // Create directories if needed
            if (!is_dir($base_upload_dir)) {
                mkdir($base_upload_dir, 0777, true);
            }
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Create image resource based on uploaded file type
            $source_image = null;
            $image_type = exif_imagetype($_FILES[$field]['tmp_name']);
            
            switch ($image_type) {
                case IMAGETYPE_JPEG:
                    $source_image = imagecreatefromjpeg($_FILES[$field]['tmp_name']);
                    break;
                case IMAGETYPE_PNG:
                    $source_image = imagecreatefrompng($_FILES[$field]['tmp_name']);
                    break;
                case IMAGETYPE_WEBP:
                    $source_image = imagecreatefromwebp($_FILES[$field]['tmp_name']);
                    break;
                default:
                    throw new Exception('Unsupported image format. Please upload JPG, PNG, or WebP');
            }

            if (!$source_image) {
                throw new Exception('Failed to create image resource');
            }

            // Get original image dimensions
            $width = imagesx($source_image);
            $height = imagesy($source_image);

            // Set maximum dimensions while maintaining aspect ratio
            $max_width = 1200;
            $max_height = 1200;

            if ($width > $max_width || $height > $max_height) {
                $ratio = min($max_width / $width, $max_height / $height);
                $new_width = round($width * $ratio);
                $new_height = round($height * $ratio);

                $resized_image = imagecreatetruecolor($new_width, $new_height);
                
                // Preserve transparency
                imagepalettetotruecolor($resized_image);
                imagealphablending($resized_image, false);
                imagesavealpha($resized_image, true);
                
                imagecopyresampled(
                    $resized_image, $source_image,
                    0, 0, 0, 0,
                    $new_width, $new_height, $width, $height
                );
                
                $source_image = $resized_image;
            }

            // Save as WebP with quality setting
            if (!imagewebp($source_image, $upload_path, 85)) { // 85 is a good balance between quality and file size
                throw new Exception('Failed to save WebP image');
            }

            // Clean up
            imagedestroy($source_image);

            // Insert into product_galleries
            $stmt = $conn->prepare("
                INSERT INTO product_galleries (product_id, image_url, is_primary, sort_order, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$product_id, $web_path, $is_primary ? 1 : 0, $is_primary ? 0 : 1]);
        }
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}