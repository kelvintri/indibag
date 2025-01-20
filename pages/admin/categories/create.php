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
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            throw new Exception('Category name is required');
        }

        // Generate slug from name
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));

        // Check if slug already exists
        $stmt = $conn->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            throw new Exception('A category with this name already exists');
        }

        // Insert new category
        $stmt = $conn->prepare("INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)");
        $stmt->execute([$name, $slug, $description]);

        header('Location: /admin/categories?success=Category created successfully');
        exit;
    } catch (Exception $e) {
        header('Location: /admin/categories?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: /admin/categories');
    exit;
} 