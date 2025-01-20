<?php
function asset($path) {
    // Remove leading slash if present
    $path = ltrim($path, '/');
    return '/assets/' . $path;
}

function getImageUrl($path) {
    // Remove leading slash if present
    $path = ltrim($path, '/');
    
    // Only convert to webp if the path doesn't already end in .webp
    if (!preg_match('/\.webp$/i', $path)) {
        $path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $path);
    }
    
    return '/' . $path;
} 