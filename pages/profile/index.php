<?php
Auth::requireLogin();

$db = new Database();
$conn = $db->getConnection();

// Get user data
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        try {
            $updateQuery = "UPDATE users 
                          SET full_name = :full_name,
                              phone = :phone
                          WHERE id = :user_id";
            
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bindParam(":full_name", $_POST['full_name']);
            $updateStmt->bindParam(":phone", $_POST['phone']);
            $updateStmt->bindParam(":user_id", $_SESSION['user_id']);
            $updateStmt->execute();
            
            $success = "Profile updated successfully";
            
            // Refresh user data
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $error = "Error updating profile";
            error_log($e->getMessage());
        }
    }
}
?>

<div class="max-w-4xl mx-auto">
    <!-- Profile Navigation -->
    <div class="mb-8 border-b">
        <nav class="flex space-x-8">
            <a href="/profile" 
               class="border-b-2 border-blue-500 px-1 pb-4 text-sm font-medium text-blue-600">
                Profile
            </a>
            <a href="/profile/addresses" 
               class="border-b-2 border-transparent px-1 pb-4 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                Addresses
            </a>
            <a href="/orders" 
               class="border-b-2 border-transparent px-1 pb-4 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                Orders
            </a>
        </nav>
    </div>

    <?php if ($success): ?>
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Profile Information -->
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-6">Profile Information</h2>
        
        <form method="POST" class="space-y-6">
            <input type="hidden" name="action" value="update_profile">
            
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                <input type="text" id="username" value="<?= htmlspecialchars($user['username']) ?>" 
                       class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50" disabled>
            </div>
            
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" 
                       class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50" disabled>
            </div>
            
            <div>
                <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                <input type="text" id="full_name" name="full_name" 
                       value="<?= htmlspecialchars($user['full_name']) ?>"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                <input type="tel" id="phone" name="phone" 
                       value="<?= htmlspecialchars($user['phone']) ?>"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            
            <div class="flex justify-end">
                <button type="submit" 
                        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    Save Changes
                </button>
            </div>
        </form>
    </div>

    <!-- Change Password -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-6">Change Password</h2>
        
        <form method="POST" class="space-y-6">
            <input type="hidden" name="action" value="change_password">
            
            <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                <input type="password" id="current_password" name="current_password" required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            
            <div>
                <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                <input type="password" id="new_password" name="new_password" required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            
            <div class="flex justify-end">
                <button type="submit" 
                        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    Change Password
                </button>
            </div>
        </form>
    </div>
</div> 