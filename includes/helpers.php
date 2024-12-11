<?php
function asset($path) {
    // Remove leading slash if present
    $path = ltrim($path, '/');
    return '/assets/' . $path;
}

function getImageUrl($path) {
    if (empty($path)) {
        return asset('images/placeholder.jpg');
    }
    
    // If path already starts with /assets, return as is
    if (strpos($path, '/assets/') === 0) {
        return $path;
    }
    
    // Otherwise, prepend /assets/
    return asset($path);
} 