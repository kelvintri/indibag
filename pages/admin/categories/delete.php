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
            throw new Exception('ID kategori tidak valid');
        }

        // Check if category has any active products
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM products 
            WHERE category_id = ? 
            AND is_active = 1 
            AND deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        $activeProductCount = $stmt->fetchColumn();

        if ($activeProductCount > 0) {
            throw new Exception('Tidak dapat menghapus kategori: masih ada produk aktif dalam kategori ini');
        }

        // If no active products, delete the category
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);

        header('Location: /admin/categories?success=Kategori berhasil dihapus');
        exit;
    } catch (Exception $e) {
        header('Location: /admin/categories?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: /admin/categories');
    exit;
} 