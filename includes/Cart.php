<?php
class Cart {
    private $db;
    private $user_id;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        // Initialize session cart if not exists
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
    }
    
    public function add($product_id, $quantity = 1) {
        try {
            // Check product exists and is active
            $query = "SELECT id, stock, price FROM products WHERE id = :product_id AND is_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":product_id", $product_id);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                return ['success' => false, 'message' => 'Product not found'];
            }

            // Check if item already exists in cart
            if ($this->user_id) {
                // For logged-in users, check database
                $checkQuery = "SELECT quantity FROM cart 
                             WHERE user_id = :user_id AND product_id = :product_id";
                $checkStmt = $this->db->prepare($checkQuery);
                $checkStmt->bindParam(":user_id", $this->user_id);
                $checkStmt->bindParam(":product_id", $product_id);
                $checkStmt->execute();
                $existingItem = $checkStmt->fetch(PDO::FETCH_ASSOC);

                $newQuantity = ($existingItem ? $existingItem['quantity'] : 0) + $quantity;
                
                // Check maximum quantity limit
                if ($newQuantity > 10) {
                    return ['success' => false, 'message' => 'Maximum quantity limit is 10 items'];
                }

                if ($newQuantity > $product['stock']) {
                    return ['success' => false, 'message' => 'Not enough stock'];
                }

                if ($existingItem) {
                    // Update existing cart item
                    $updateQuery = "UPDATE cart 
                                  SET quantity = :quantity 
                                  WHERE user_id = :user_id AND product_id = :product_id";
                    $updateStmt = $this->db->prepare($updateQuery);
                    $updateStmt->bindParam(":quantity", $newQuantity);
                    $updateStmt->bindParam(":user_id", $this->user_id);
                    $updateStmt->bindParam(":product_id", $product_id);
                    $updateStmt->execute();
                } else {
                    // Insert new cart item
                    $insertQuery = "INSERT INTO cart (user_id, product_id, quantity) 
                                  VALUES (:user_id, :product_id, :quantity)";
                    $insertStmt = $this->db->prepare($insertQuery);
                    $insertStmt->bindParam(":user_id", $this->user_id);
                    $insertStmt->bindParam(":product_id", $product_id);
                    $insertStmt->bindParam(":quantity", $quantity);
                    $insertStmt->execute();
                }
            } else {
                // For guests, check session
                $newQuantity = (isset($_SESSION['cart'][$product_id]) ? $_SESSION['cart'][$product_id] : 0) + $quantity;
                
                if ($newQuantity > $product['stock']) {
                    return ['success' => false, 'message' => 'Not enough stock'];
                }
                
                $_SESSION['cart'][$product_id] = $newQuantity;
            }
            
            return ['success' => true, 'message' => 'Product added to cart'];
            
        } catch (PDOException $e) {
            error_log("Cart add error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error adding to cart'];
        }
    }
    
    public function update($product_id, $quantity) {
        try {
            if ($quantity <= 0) {
                return $this->remove($product_id);
            }
            
            // Check stock
            $query = "SELECT stock FROM products WHERE id = :product_id AND is_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":product_id", $product_id);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product || $product['stock'] < $quantity) {
                return ['success' => false, 'message' => 'Invalid quantity'];
            }
            
            if ($this->user_id) {
                $updateQuery = "UPDATE cart 
                               SET quantity = :quantity 
                               WHERE user_id = :user_id AND product_id = :product_id";
                               
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->bindParam(":quantity", $quantity);
                $updateStmt->bindParam(":user_id", $this->user_id);
                $updateStmt->bindParam(":product_id", $product_id);
                $updateStmt->execute();
            } else {
                $_SESSION['cart'][$product_id] = $quantity;
            }
            
            return ['success' => true, 'message' => 'Cart updated'];
            
        } catch (PDOException $e) {
            error_log("Cart update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating cart'];
        }
    }
    
    public function remove($product_id) {
        try {
            if ($this->user_id) {
                $query = "DELETE FROM cart 
                         WHERE user_id = :user_id AND product_id = :product_id";
                         
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(":user_id", $this->user_id);
                $stmt->bindParam(":product_id", $product_id);
                $stmt->execute();
            } else {
                unset($_SESSION['cart'][$product_id]);
            }
            
            return ['success' => true, 'message' => 'Product removed from cart'];
            
        } catch (PDOException $e) {
            error_log("Cart remove error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error removing from cart'];
        }
    }
    
    public function getItems() {
        try {
            if ($this->user_id) {
                $query = "SELECT p.*, c.quantity, pg.image_url, 
                                LEAST(p.stock, 10) as max_quantity,  /* Limit max quantity to 10 */
                                p.slug  /* Add slug to the query */
                         FROM cart c
                         JOIN products p ON c.product_id = p.id
                         LEFT JOIN product_galleries pg ON p.id = pg.product_id AND pg.is_primary = 1
                         WHERE c.user_id = :user_id AND p.is_active = 1";
                         
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(":user_id", $this->user_id);
                $stmt->execute();
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                if (empty($_SESSION['cart'])) {
                    return [];
                }
                
                $productIds = array_keys($_SESSION['cart']);
                $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
                
                $query = "SELECT p.*, pg.image_url,
                                LEAST(p.stock, 10) as max_quantity,  /* Limit max quantity to 10 */
                                p.slug  /* Add slug to the query */
                         FROM products p
                         LEFT JOIN product_galleries pg ON p.id = pg.product_id AND pg.is_primary = 1
                         WHERE p.id IN ($placeholders) AND p.is_active = 1";
                         
                $stmt = $this->db->prepare($query);
                $stmt->execute($productIds);
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Add quantities from session
                foreach ($products as &$product) {
                    $product['quantity'] = $_SESSION['cart'][$product['id']];
                }
                
                return $products;
            }
            
        } catch (PDOException $e) {
            error_log("Cart getItems error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getTotal() {
        $items = $this->getItems();
        $total = 0;
        
        foreach ($items as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        
        return $total;
    }
    
    public function getCount() {
        $items = $this->getItems();
        $count = 0;
        
        foreach ($items as $item) {
            $count += $item['quantity'];
        }
        
        return $count;
    }
    
    public function clear() {
        if ($this->user_id) {
            $query = "DELETE FROM cart WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":user_id", $this->user_id);
            $stmt->execute();
        }
        
        $_SESSION['cart'] = [];
        return true;
    }
} 