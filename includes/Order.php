<?php
class Order {
    private $db;
    private $user_id;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
    
    public function create($data) {
        try {
            $this->db->beginTransaction();
            
            // Create order
            $orderQuery = "INSERT INTO orders (
                user_id, 
                shipping_address_id, 
                total_amount, 
                payment_method, 
                status
            ) VALUES (
                :user_id, 
                :shipping_address_id, 
                :total_amount, 
                :payment_method,
                'pending_payment'
            )";
            
            $orderStmt = $this->db->prepare($orderQuery);
            $orderStmt->bindParam(":user_id", $this->user_id);
            $orderStmt->bindParam(":shipping_address_id", $data['shipping_address_id']);
            $orderStmt->bindParam(":total_amount", $data['total_amount']);
            $orderStmt->bindParam(":payment_method", $data['payment_method']);
            $orderStmt->execute();
            
            $order_id = $this->db->lastInsertId();
            
            // Create order items
            $itemQuery = "INSERT INTO order_items (
                order_id, 
                product_id, 
                quantity, 
                price
            ) VALUES (
                :order_id, 
                :product_id, 
                :quantity, 
                :price
            )";
            
            $itemStmt = $this->db->prepare($itemQuery);
            
            foreach ($data['items'] as $item) {
                $itemStmt->bindParam(":order_id", $order_id);
                $itemStmt->bindParam(":product_id", $item['id']);
                $itemStmt->bindParam(":quantity", $item['quantity']);
                $itemStmt->bindParam(":price", $item['price']);
                $itemStmt->execute();
            }
            
            // Create payment details
            $paymentQuery = "INSERT INTO payment_details (
                order_id, 
                payment_method, 
                payment_amount
            ) VALUES (
                :order_id, 
                :payment_method, 
                :payment_amount
            )";
            
            $paymentStmt = $this->db->prepare($paymentQuery);
            $paymentStmt->bindParam(":order_id", $order_id);
            $paymentStmt->bindParam(":payment_method", $data['payment_method']);
            $paymentStmt->bindParam(":payment_amount", $data['total_amount']);
            $paymentStmt->execute();
            
            // Clear cart after successful order
            $cart = new Cart();
            $cart->clear();
            
            $this->db->commit();
            return ['success' => true, 'order_id' => $order_id];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Order creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error creating order'];
        }
    }

    public function uploadPaymentProof($order_id, $file) {
        try {
            ob_start();
            
            // Validate file
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                throw new Exception('No file uploaded');
            }

            // Check file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception('Invalid file type. Only JPG and PNG are allowed');
            }

            // Check file size (5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('File too large. Maximum size is 5MB');
            }

            // Get current payment details
            $checkQuery = "SELECT id, transfer_proof_url FROM payment_details WHERE order_id = :order_id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(":order_id", $order_id);
            $checkStmt->execute();
            
            $paymentDetails = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$paymentDetails) {
                throw new Exception('Payment details not found');
            }

            // Store old file path if exists
            $oldFilePath = null;
            if ($paymentDetails['transfer_proof_url']) {
                $oldFilePath = ROOT_PATH . '/public' . $paymentDetails['transfer_proof_url'];
            }

            // Verify order belongs to user and is in correct status
            $query = "SELECT o.id, o.status 
                     FROM orders o 
                     WHERE o.id = :order_id 
                     AND o.user_id = :user_id 
                     AND o.status IN ('pending_payment', 'payment_uploaded')";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":order_id", $order_id);
            $stmt->bindParam(":user_id", $this->user_id);
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                throw new Exception('Invalid order or payment already verified');
            }

            // Handle new file upload
            $upload_dir = ROOT_PATH . '/public/assets/uploads/payments/';
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception('Error creating upload directory');
                }
            }

            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $file_name = 'payment_' . $order_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                throw new Exception('Error saving uploaded file');
            }

            $this->db->beginTransaction();

            try {
                // Update payment details
                $proof_url = '/assets/uploads/payments/' . $file_name;
                $updateQuery = "UPDATE payment_details 
                              SET transfer_proof_url = :proof_url,
                                  payment_date = CURRENT_TIMESTAMP
                              WHERE order_id = :order_id";
                
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->bindValue(":proof_url", $proof_url);
                $updateStmt->bindValue(":order_id", $order_id);
                $updateStmt->execute();

                // Update order status if needed
                $orderQuery = "UPDATE orders 
                              SET status = 'payment_uploaded'
                              WHERE id = :order_id 
                              AND status = 'pending_payment'";
                
                $orderStmt = $this->db->prepare($orderQuery);
                $orderStmt->bindValue(":order_id", $order_id);
                $orderStmt->execute();

                $this->db->commit();

                // Delete old file after successful database update
                if ($oldFilePath && file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                    error_log("Deleted old payment proof: " . $oldFilePath);
                }

                return ['success' => true, 'message' => 'Payment proof uploaded successfully'];

            } catch (Exception $e) {
                $this->db->rollBack();
                // Delete the new file if database update failed
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                throw $e;
            }
            
        } catch (Exception $e) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            if (isset($this->db) && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Payment upload error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getOrder($order_id) {
        try {
            $query = "SELECT o.*, 
                            a.recipient_name, a.phone, a.street_address, 
                            a.district, a.city, a.province, a.postal_code,
                            pd.payment_method, pd.transfer_proof_url, 
                            pd.payment_date, pd.verified_at, pd.notes,
                            u.email
                     FROM orders o 
                     LEFT JOIN addresses a ON o.shipping_address_id = a.id
                     LEFT JOIN payment_details pd ON o.id = pd.order_id
                     LEFT JOIN users u ON o.user_id = u.id
                     WHERE o.id = :order_id AND o.user_id = :user_id";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":order_id", $order_id);
            $stmt->bindParam(":user_id", $this->user_id);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting order: " . $e->getMessage());
            return null;
        }
    }

    public function getOrderItems($order_id) {
        try {
            $query = "SELECT oi.*, p.name, p.slug, pg.image_url
                     FROM order_items oi
                     JOIN products p ON oi.product_id = p.id
                     LEFT JOIN product_galleries pg ON p.id = pg.product_id 
                     AND pg.is_primary = 1
                     WHERE oi.order_id = :order_id";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":order_id", $order_id);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting order items: " . $e->getMessage());
            return [];
        }
    }

    public function getUserOrders() {
        try {
            $query = "SELECT o.*, pd.payment_method, pd.payment_date, 
                            pd.verified_at,
                            (SELECT COUNT(*) FROM order_items 
                             WHERE order_id = o.id) as item_count
                     FROM orders o
                     LEFT JOIN payment_details pd ON o.id = pd.order_id
                     WHERE o.user_id = :user_id
                     ORDER BY o.created_at DESC";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":user_id", $this->user_id);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting user orders: " . $e->getMessage());
            return [];
        }
    }

    public function canReuploadPayment($order_id) {
        try {
            $query = "SELECT o.status 
                     FROM orders o 
                     WHERE o.id = :order_id 
                     AND o.user_id = :user_id 
                     AND o.status IN ('pending_payment', 'payment_uploaded')";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":order_id", $order_id);
            $stmt->bindParam(":user_id", $this->user_id);
            $stmt->execute();
            
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("Error checking reupload status: " . $e->getMessage());
            return false;
        }
    }
}