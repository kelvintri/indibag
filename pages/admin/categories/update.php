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
            throw new Exception('Invalid category ID');
        }

        if (empty($name)) {
            throw new Exception('Category name is required');
        }

        // Generate slug from name
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));

        // Check if slug already exists for other categories
        $stmt = $conn->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $id]);
        if ($stmt->fetch()) {
            throw new Exception('A category with this name already exists');
        }

        // Update category
        $stmt = $conn->prepare("UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $description, $id]);

        header('Location: /admin/categories?success=Category updated successfully');
        exit;
    } catch (Exception $e) {
        header('Location: /admin/categories?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: /admin/categories');
    exit;
} 