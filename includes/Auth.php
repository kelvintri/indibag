<?php
class Auth {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function login($email, $password) {
        try {
            $query = "SELECT u.*, GROUP_CONCAT(r.name) as roles 
                     FROM users u 
                     LEFT JOIN user_roles ur ON u.id = ur.user_id 
                     LEFT JOIN roles r ON ur.role_id = r.id 
                     WHERE u.email = :email AND u.is_active = 1 
                     GROUP BY u.id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Store user data in session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['roles'] = explode(',', $user['roles']);
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    
    public function register($data) {
        try {
            $this->db->beginTransaction();
            
            // Check if email or username already exists
            $checkQuery = "SELECT id FROM users WHERE email = :email OR username = :username";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(":email", $data['email']);
            $checkStmt->bindParam(":username", $data['username']);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Email or username already exists'];
            }
            
            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Insert user
            $query = "INSERT INTO users (username, email, password, full_name, phone) 
                     VALUES (:username, :email, :password, :full_name, :phone)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":username", $data['username']);
            $stmt->bindParam(":email", $data['email']);
            $stmt->bindParam(":password", $hashedPassword);
            $stmt->bindParam(":full_name", $data['full_name']);
            $stmt->bindParam(":phone", $data['phone']);
            $stmt->execute();
            
            $userId = $this->db->lastInsertId();
            
            // Assign default customer role
            $roleQuery = "INSERT INTO user_roles (user_id, role_id) 
                         SELECT :user_id, id FROM roles WHERE name = 'customer'";
            $roleStmt = $this->db->prepare($roleQuery);
            $roleStmt->bindParam(":user_id", $userId);
            $roleStmt->execute();
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Registration successful'];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }
    
    public function logout() {
        session_destroy();
        return true;
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public static function hasRole($role) {
        return isset($_SESSION['roles']) && in_array($role, $_SESSION['roles']);
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: /login');
            exit;
        }
    }
    
    public static function requireRole($role) {
        self::requireLogin();
        if (!self::hasRole($role)) {
            header('Location: /403');
            exit;
        }
    }
} 