<?php
$imagePaths = [
    'backpacks/primary/SHB3088421 Megan Small Backpack Misty Blue_primary.jpg',
    'backpacks/primary/Chelsea Medium Backpack Deep Cornflower_primary.jpg',
    'backpacks/hover/Chelsea Medium Backpack Deep Cornflower_hover.jpg',
    'backpacks/primary/Signature Maisie Xs 2 In 1 Backpack Brown_primary.jpg',
    'backpacks/hover/Signature Maisie Xs 2 In 1 Backpack Brown_hover.jpg',
    'backpacks/primary/Valerie Signature Medium Backpack Brown Acorn_primary.jpg',
    'backpacks/hover/Valerie Signature Medium Backpack Brown Acorn_hover.jpg',
    'backpacks/primary/Madison Saffiano Flap Backpack Parisian Navy_primary.jpg',
    'backpacks/hover/Madison Saffiano Flap Backpack Parisian Navy_hover.jpg',
    'backpacks/primary/SHB3088844 Megan Small Backpack Berry Stripe_primary.jpg',
    'backpacks/hover/SHB3088844 Megan Small Backpack Berry Stripe_hover.jpg'
];

$baseDir = 'C:/laragon/www/Bananina/public/assets/images/';

// Create a simple colored image for testing
foreach ($imagePaths as $path) {
    $fullPath = $baseDir . $path;
    $dir = dirname($fullPath);
    
    // Create directory if it doesn't exist
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    // Create a 400x400 image
    $image = imagecreatetruecolor(400, 400);
    
    // Random background color
    $color = imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255));
    imagefill($image, 0, 0, $color);
    
    // Save the image
    imagejpeg($image, $fullPath);
    imagedestroy($image);
    
    echo "Created: $fullPath\n";
}

// Create placeholder image
$placeholder = imagecreatetruecolor(400, 400);
$gray = imagecolorallocate($placeholder, 200, 200, 200);
imagefill($placeholder, 0, 0, $gray);
imagejpeg($placeholder, $baseDir . 'placeholder.jpg');
imagedestroy($placeholder);

echo "Created placeholder image\n"; 