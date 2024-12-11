<?php
function connectDB() {
    $db = new Database();
    return $db->getConnection();
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    if (!isLoggedIn()) return null;
    
    $db = connectDB();
    $query = "SELECT r.name FROM roles r 
              JOIN user_roles ur ON r.id = ur.role_id 
              WHERE ur.user_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function isAdmin() {
    $roles = getUserRole();
    return in_array('admin', $roles);
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit();
    }
}

function redirectIfNotAdmin() {
    if (!isAdmin()) {
        header('Location: /');
        exit();
    }
}
?> 