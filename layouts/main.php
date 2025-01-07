<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Bananina' ?></title>
    
    <!-- Tailwind CSS -->
    <link href="/css/output.css" rel="stylesheet">
    
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body>
    <!-- Header -->
    <?php require_once ROOT_PATH . '/includes/header.php'; ?>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <?php 
        if (is_string($content)) {
            echo $content;
        } else {
            include $content;
        }
        ?>
    </main>

    <!-- Footer -->
    <?php require_once ROOT_PATH . '/includes/footer.php'; ?>

    <!-- Custom JS -->
    <script src="/assets/js/main.js"></script>
</body>
</html> 