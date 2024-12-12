<?php
function asset($path) {
    // Remove leading slash if present
    $path = ltrim($path, '/');
    return '/assets/' . $path;
}

function getImageUrl($path) {
    // Remove leading slash if present
    $path = ltrim($path, '/');
    return '/' . $path;
} 