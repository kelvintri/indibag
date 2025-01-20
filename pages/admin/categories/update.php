<?php
require_once __DIR__ . '/../../../includes/AdminAuth.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';

AdminAuth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        // Validate and sanitize input
        $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');

        if (!$id) {
            throw new Exception('ID kategori tidak valid');
        }

        if (empty($name)) {
            throw new Exception('Nama kategori wajib diisi');
        }

        // Generate slug from name
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));

        // Check if slug already exists for other categories
        $stmt = $conn->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $id]);
        if ($stmt->fetch()) {
            throw new Exception('Kategori dengan nama ini sudah ada');
        }

        // Update category
        $stmt = $conn->prepare("UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $description, $id]);

        header('Location: /admin/categories?success=Kategori berhasil diubah');
        exit;
    } catch (Exception $e) {
        header('Location: /admin/categories?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: /admin/categories');
    exit;
} 