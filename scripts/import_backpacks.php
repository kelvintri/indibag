<?php
require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Define categories and their files
$category_files = [
    'backpacks' => __DIR__ . '/../data/backpacks_bags.csv',
    'totes' => __DIR__ . '/../data/totes_bags.csv'
];

// Begin transaction
$conn->beginTransaction();

try {
    // Prepare statements
    $categoryStmt = $conn->prepare("
        INSERT IGNORE INTO categories (name, slug) 
        VALUES (:name, :slug)
    ");

    $brandStmt = $conn->prepare("
        INSERT IGNORE INTO brands (name, slug) 
        VALUES (:name, :slug)
    ");

    $checkProductStmt = $conn->prepare("
        SELECT id FROM products WHERE slug = :slug
    ");

    $productStmt = $conn->prepare("
        INSERT IGNORE INTO products (
            category_id, brand_id, name, slug, description, details,
            meta_title, meta_description, price, stock, sku, 
            condition_status, is_active
        ) VALUES (
            :category_id, :brand_id, :name, :slug, :description, :details,
            :meta_title, :meta_description, :price, :stock, :sku, 
            :condition_status, 1
        )
    ");

    $galleryStmt = $conn->prepare("
        INSERT IGNORE INTO product_galleries (
            product_id, image_url, is_primary, sort_order
        ) VALUES (
            :product_id, :image_url, :is_primary, :sort_order
        )
    ");

    $total_imported = 0;
    $total_skipped = 0;

    // Process each category file
    foreach ($category_files as $category_name => $file_path) {
        // Create or get category
        $categoryStmt->execute([
            ':name' => ucfirst($category_name),
            ':slug' => $category_name
        ]);
        $categoryId = $conn->lastInsertId() ?: $conn->query("SELECT id FROM categories WHERE slug = '$category_name'")->fetchColumn();

        // Read CSV file
        if (!file_exists($file_path)) {
            echo "Warning: File not found - $file_path\n";
            continue;
        }

        $csv = array_map('str_getcsv', file($file_path));
        $headers = array_shift($csv); // Remove header row

        $imported = 0;
        $skipped = 0;

        foreach ($csv as $index => $row) {
            // Skip empty rows or rows with missing essential data
            if (empty($row[0]) || empty($row[1])) {
                echo "Skipping row " . ($index + 2) . ": Missing brand or name\n";
                continue;
            }

            // Validate required fields
            $required_fields = [
                0 => 'Brand',
                1 => 'Name',
                2 => 'Price',
                8 => 'SKU',
                10 => 'Description',
                11 => 'Details',
                12 => 'Condition',
                13 => 'Primary Image',
                14 => 'Hover Image'
            ];

            $missing_fields = [];
            foreach ($required_fields as $index => $field) {
                if (!isset($row[$index]) || trim($row[$index]) === '') {
                    $missing_fields[] = $field;
                }
            }

            if (!empty($missing_fields)) {
                echo "Skipping product '{$row[1]}': Missing required fields: " . implode(', ', $missing_fields) . "\n";
                $skipped++;
                continue;
            }

            // Clean and prepare data
            $brand = $row[0];
            $brandSlug = strtolower(str_replace(' ', '-', $brand));
            
            // Insert brand
            $brandStmt->execute([
                ':name' => $brand,
                ':slug' => $brandSlug
            ]);
            
            // Get brand ID
            $brandId = $conn->lastInsertId() ?: $conn->query("SELECT id FROM brands WHERE slug = '$brandSlug'")->fetchColumn();

            // Clean price
            $price = (int) str_replace(['IDR', ',', '.', ' '], '', $row[2]);
            
            // Generate product slug
            $productSlug = strtolower(str_replace(' ', '-', $row[1]));
            
            // Check if product already exists
            $checkProductStmt->execute([':slug' => $productSlug]);
            $existingProduct = $checkProductStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingProduct) {
                $skipped++;
                continue; // Skip this product
            }

            // Generate meta information
            $metaTitle = $row[1] . ' | ' . $brand . ' ' . ucfirst($category_name);
            $metaDescription = 'Shop ' . $brand . ' ' . $row[1] . '. ' . ($row[10] ?? 'No description available.');

            // Insert product with default values for missing fields
            $productStmt->execute([
                ':category_id' => $categoryId,
                ':brand_id' => $brandId,
                ':name' => $row[1],
                ':slug' => $productSlug,
                ':description' => $row[10] ?? '',
                ':details' => $row[11] ?? '',
                ':meta_title' => $metaTitle,
                ':meta_description' => $metaDescription,
                ':price' => $price,
                ':stock' => 10, // Default stock
                ':sku' => $row[8] ?? '',
                ':condition_status' => $row[12] ?? 'New'
            ]);
            
            $productId = $conn->lastInsertId();

            if ($productId) {
                // Only process images if they exist
                if (!empty($row[13])) {
                    $primaryImagePath = '/assets/images/' . $category_name . '/' . str_replace(['images\\' . $category_name . '\\', 'images/' . $category_name . '/'], '', $row[13]);
                    $primaryImagePath = str_replace('\\', '/', $primaryImagePath);
                    
                    $galleryStmt->execute([
                        ':product_id' => $productId,
                        ':image_url' => $primaryImagePath,
                        ':is_primary' => 1,
                        ':sort_order' => 0
                    ]);
                }

                if (!empty($row[14])) {
                    $hoverImagePath = '/assets/images/' . $category_name . '/' . str_replace(['images\\' . $category_name . '\\', 'images/' . $category_name . '/'], '', $row[14]);
                    $hoverImagePath = str_replace('\\', '/', $hoverImagePath);
                    
                    $galleryStmt->execute([
                        ':product_id' => $productId,
                        ':image_url' => $hoverImagePath,
                        ':is_primary' => 0,
                        ':sort_order' => 1
                    ]);
                }
                $imported++;
            }
        }

        echo "Category: " . ucfirst($category_name) . "\n";
        echo "- Imported: $imported products\n";
        echo "- Skipped: $skipped products\n\n";

        $total_imported += $imported;
        $total_skipped += $skipped;
    }

    // Commit transaction
    $conn->commit();
    echo "Total Import completed:\n";
    echo "- Total Imported: $total_imported products\n";
    echo "- Total Skipped: $total_skipped products\n";

} catch (Exception $e) {
    // Rollback on error
    $conn->rollBack();
    echo "Error importing data: " . $e->getMessage() . "\n";
} 