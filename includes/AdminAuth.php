<?php
require_once 'Auth.php';

class AdminAuth {
    public static function requireAdmin() {
        Auth::requireRole('admin');
    }

    public static function updateUserAdminStatus($userId) {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Update is_admin flag in users table based on role
        $stmt = $conn->prepare("
            UPDATE users u 
            SET u.is_admin = EXISTS (
                SELECT 1 
                FROM user_roles ur 
                JOIN roles r ON ur.role_id = r.id 
                WHERE ur.user_id = u.id 
                AND r.name = 'admin'
            )
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
    }

    public static function assignAdminRole($userId) {
        $db = new Database();
        $conn = $db->getConnection();
        
        try {
            $conn->beginTransaction();
            
            // Get admin role ID
            $stmt = $conn->prepare("SELECT id FROM roles WHERE name = 'admin'");
            $stmt->execute();
            $adminRoleId = $stmt->fetchColumn();
            
            if (!$adminRoleId) {
                throw new Exception('Admin role not found');
            }
            
            // Check if user already has admin role
            $stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM user_roles 
                WHERE user_id = ? AND role_id = ?
            ");
            $stmt->execute([$userId, $adminRoleId]);
            $hasRole = $stmt->fetchColumn() > 0;
            
            if (!$hasRole) {
                // Assign admin role
                $stmt = $conn->prepare("
                    INSERT INTO user_roles (user_id, role_id) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$userId, $adminRoleId]);
                
                // Update is_admin flag
                self::updateUserAdminStatus($userId);
            }
            
            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Error assigning admin role: " . $e->getMessage());
            return false;
        }
    }

    public static function removeAdminRole($userId) {
        $db = new Database();
        $conn = $db->getConnection();
        
        try {
            $conn->beginTransaction();
            
            // Get admin role ID
            $stmt = $conn->prepare("SELECT id FROM roles WHERE name = 'admin'");
            $stmt->execute();
            $adminRoleId = $stmt->fetchColumn();
            
            if ($adminRoleId) {
                // Remove admin role
                $stmt = $conn->prepare("
                    DELETE FROM user_roles 
                    WHERE user_id = ? AND role_id = ?
                ");
                $stmt->execute([$userId, $adminRoleId]);
                
                // Update is_admin flag
                self::updateUserAdminStatus($userId);
            }
            
            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Error removing admin role: " . $e->getMessage());
            return false;
        }
    }
} 