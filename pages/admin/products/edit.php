<?php
require_once __DIR__ . '/../../../includes/AdminAuth.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';

AdminAuth::requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get product ID and validate it exists
    $product_id = $_POST['product_id'] ?? null;
    if (!$product_id) {
        throw new Exception('Product ID is required');
    }

    // Start transaction
    $conn->beginTransaction();

    // Update product details
    $stmt = $conn->prepare("
        UPDATE products 
        SET name = :name,
            description = :description,
            category_id = :category_id,
            brand_id = :brand_id,
            price = :price,
            stock = :stock,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id AND deleted_at IS NULL
    ");

    $stmt->execute([
        ':name' => $_POST['name'],
        ':description' => $_POST['description'],
        ':category_id' => $_POST['category_id'],
        ':brand_id' => $_POST['brand_id'],
        ':price' => $_POST['price'],
        ':stock' => $_POST['stock'],
        ':id' => $product_id
    ]);

    // Handle image upload if provided
    if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] === UPLOAD_ERR_OK) {
        $image = $_FILES['primary_image'];
        
        // Get existing primary image
        $stmt = $conn->prepare("SELECT image_url FROM product_galleries WHERE product_id = ? AND is_primary = 1");
        $stmt->execute([$product_id]);
        $existingImage = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingImage && $existingImage['image_url']) {
            // Use the existing path but replace the file
            $oldFile = __DIR__ . '/../../../public' . $existingImage['image_url'];
            $uploadDir = dirname($oldFile);
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Move uploaded file to replace the existing one
            if (move_uploaded_file($image['tmp_name'], $oldFile)) {
                // Update timestamp in database
                $stmt = $conn->prepare("
                    UPDATE product_galleries 
                    SET updated_at = CURRENT_TIMESTAMP
                    WHERE product_id = :product_id AND is_primary = 1
                ");
                $stmt->execute([':product_id' => $product_id]);
            } else {
                throw new Exception('Failed to upload primary image');
            }
        } else {
            // No existing image, create new one in default location
            $fileName = uniqid() . '_' . basename($image['name']);
            $uploadDir = __DIR__ . '/../../../public/assets/images/backpacks/primary/';
            
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            if (move_uploaded_file($image['tmp_name'], $uploadDir . $fileName)) {
                $stmt = $conn->prepare("
                    INSERT INTO product_galleries (product_id, image_url, is_primary, created_at, updated_at)
                    VALUES (:product_id, :image_url, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");

                $stmt->execute([
                    ':product_id' => $product_id,
                    ':image_url' => '/assets/images/backpacks/primary/' . $fileName
                ]);
            } else {
                throw new Exception('Failed to upload primary image');
            }
        }
    }

    // Handle hover image upload if provided
    if (isset($_FILES['hover_image']) && $_FILES['hover_image']['error'] === UPLOAD_ERR_OK) {
        $image = $_FILES['hover_image'];
        
        // Get existing hover image
        $stmt = $conn->prepare("SELECT image_url FROM product_galleries WHERE product_id = ? AND is_primary = 0");
        $stmt->execute([$product_id]);
        $existingImage = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingImage && $existingImage['image_url']) {
            // Use the existing path but replace the file
            $oldFile = __DIR__ . '/../../../public' . $existingImage['image_url'];
            $uploadDir = dirname($oldFile);
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Move uploaded file to replace the existing one
            if (move_uploaded_file($image['tmp_name'], $oldFile)) {
                // Update timestamp in database
                $stmt = $conn->prepare("
                    UPDATE product_galleries 
                    SET updated_at = CURRENT_TIMESTAMP
                    WHERE product_id = :product_id AND is_primary = 0
                ");
                $stmt->execute([':product_id' => $product_id]);
            } else {
                throw new Exception('Failed to upload hover image');
            }
        } else {
            // No existing image, create new one in default location
            $fileName = uniqid() . '_' . basename($image['name']);
            $uploadDir = __DIR__ . '/../../../public/assets/images/backpacks/hover/';
            
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            if (move_uploaded_file($image['tmp_name'], $uploadDir . $fileName)) {
                $stmt = $conn->prepare("
                    INSERT INTO product_galleries (product_id, image_url, is_primary, created_at, updated_at)
                    VALUES (:product_id, :image_url, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");

                $stmt->execute([
                    ':product_id' => $product_id,
                    ':image_url' => '/assets/images/backpacks/hover/' . $fileName
                ]);
            } else {
                throw new Exception('Failed to upload hover image');
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    error_log("Error updating product: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 