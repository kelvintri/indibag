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
            updated_at = NOW()
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
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image = $_FILES['image'];
        $fileName = uniqid() . '_' . basename($image['name']);
        $uploadDir = __DIR__ . '/../../../public/uploads/products/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Move uploaded file
        if (move_uploaded_file($image['tmp_name'], $uploadDir . $fileName)) {
            // Update or insert into product_galleries
            $stmt = $conn->prepare("
                INSERT INTO product_galleries (product_id, image_url, is_primary, created_at)
                VALUES (:product_id, :image_url, 1, NOW())
                ON DUPLICATE KEY UPDATE 
                    image_url = VALUES(image_url),
                    updated_at = NOW()
            ");

            $stmt->execute([
                ':product_id' => $product_id,
                ':image_url' => '/uploads/products/' . $fileName
            ]);
        } else {
            throw new Exception('Failed to upload image');
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