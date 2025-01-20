<?php
require_once __DIR__ . '/../../../includes/AdminAuth.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';

AdminAuth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        // Validate category ID
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        if (!$id) {
            throw new Exception('Invalid category ID');
        }

        // Check if category has associated products
        $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE category_id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $productCount = $stmt->fetchColumn();

        if ($productCount > 0) {
            throw new Exception('Cannot delete category: it has associated products');
        }

        // Delete category
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);

        header('Location: /admin/categories?success=Category deleted successfully');
        exit;
    } catch (Exception $e) {
        header('Location: /admin/categories?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: /admin/categories');
    exit;
} 