<?php
$db = new Database();
$conn = $db->getConnection();

$query = "SELECT image_url FROM product_galleries";
$stmt = $conn->prepare($query);
$stmt->execute();

echo "<h1>Image Path Check</h1>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . getImageUrl($row['image_url']);
    echo "<div>";
    echo "DB Path: " . htmlspecialchars($row['image_url']) . "<br>";
    echo "Full Path: " . htmlspecialchars($fullPath) . "<br>";
    echo "Exists: " . (file_exists($fullPath) ? "Yes" : "No") . "<br>";
    echo "</div><hr>";
} 