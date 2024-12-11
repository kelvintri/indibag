<?php
Auth::requireLogin();

$db = new Database();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];
$success = $error = '';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
        case 'edit':
            $address_id = $_POST['address_id'] ?? null;
            $data = [
                'recipient_name' => $_POST['recipient_name'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'street_address' => $_POST['street_address'] ?? '',
                'district' => $_POST['district'] ?? '',
                'city' => $_POST['city'] ?? '',
                'province' => $_POST['province'] ?? '',
                'postal_code' => $_POST['postal_code'] ?? '',
                'is_default' => isset($_POST['is_default']) ? 1 : 0,
                'additional_info' => $_POST['additional_info'] ?? ''
            ];

            try {
                $conn->beginTransaction();

                if ($data['is_default']) {
                    $updateQuery = "UPDATE addresses SET is_default = 0 WHERE user_id = :user_id";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bindParam(":user_id", $user_id);
                    $updateStmt->execute();
                }

                if ($action === 'add') {
                    $insertQuery = "INSERT INTO addresses (user_id, recipient_name, phone, street_address, 
                                                        district, city, province, postal_code, is_default, 
                                                        additional_info)
                                  VALUES (:user_id, :recipient_name, :phone, :street_address, 
                                          :district, :city, :province, :postal_code, :is_default, 
                                          :additional_info)";
                    $stmt = $conn->prepare($insertQuery);
                    $stmt->bindParam(":user_id", $user_id);
                } else {
                    $updateQuery = "UPDATE addresses 
                                  SET recipient_name = :recipient_name, phone = :phone, 
                                      street_address = :street_address, district = :district,
                                      city = :city, province = :province, postal_code = :postal_code,
                                      is_default = :is_default, additional_info = :additional_info
                                  WHERE id = :address_id AND user_id = :user_id";
                    $stmt = $conn->prepare($updateQuery);
                    $stmt->bindParam(":address_id", $address_id);
                    $stmt->bindParam(":user_id", $user_id);
                }

                foreach ($data as $key => $value) {
                    $stmt->bindParam(":{$key}", $data[$key]);
                }

                $stmt->execute();
                $conn->commit();
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Address ' . ($action === 'add' ? 'added' : 'updated') . ' successfully']);
                exit;
                
            } catch (PDOException $e) {
                $conn->rollBack();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error ' . ($action === 'add' ? 'adding' : 'updating') . ' address']);
                exit;
            }
            break;

        case 'delete':
            $address_id = $_POST['address_id'] ?? null;
            try {
                $deleteQuery = "DELETE FROM addresses WHERE id = :address_id AND user_id = :user_id AND is_default = 0";
                $stmt = $conn->prepare($deleteQuery);
                $stmt->bindParam(":address_id", $address_id);
                $stmt->bindParam(":user_id", $user_id);
                $stmt->execute();
                
                header('Content-Type: application/json');
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Address deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete default address']);
                }
                exit;
                
            } catch (PDOException $e) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error deleting address']);
                exit;
            }
            break;
    }
}

// Get user's addresses
$query = "SELECT * FROM addresses WHERE user_id = :user_id ORDER BY is_default DESC, created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="max-w-4xl mx-auto" x-data="addressManager">
    <!-- Profile Navigation -->
    <div class="mb-8 border-b">
        <nav class="flex space-x-8">
            <a href="/profile" 
               class="border-b-2 border-transparent px-1 pb-4 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                Profile
            </a>
            <a href="/profile/addresses" 
               class="border-b-2 border-blue-500 px-1 pb-4 text-sm font-medium text-blue-600">
                Addresses
            </a>
            <a href="/orders" 
               class="border-b-2 border-transparent px-1 pb-4 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                Orders
            </a>
        </nav>
    </div>

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Manage Addresses</h1>
        <button @click="openModal()" 
                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
            Add New Address
        </button>
    </div>

    <!-- Address List -->
    <div class="space-y-4">
        <?php foreach ($addresses as $address): ?>
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-semibold">
                                <?= htmlspecialchars($address['recipient_name']) ?>
                            </h3>
                            <?php if ($address['is_default']): ?>
                                <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Default</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-gray-600 mt-1"><?= htmlspecialchars($address['phone']) ?></p>
                        <p class="text-gray-600"><?= htmlspecialchars($address['street_address']) ?></p>
                        <p class="text-gray-600">
                            <?= htmlspecialchars($address['district']) ?>,
                            <?= htmlspecialchars($address['city']) ?>
                        </p>
                        <p class="text-gray-600">
                            <?= htmlspecialchars($address['province']) ?>,
                            <?= htmlspecialchars($address['postal_code']) ?>
                        </p>
                        <?php if ($address['additional_info']): ?>
                            <p class="text-gray-500 mt-2 text-sm">
                                Note: <?= htmlspecialchars($address['additional_info']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex gap-4">
                        <button @click="editAddress(<?= htmlspecialchars(json_encode($address)) ?>)"
                                class="text-blue-600 hover:text-blue-800">
                            Edit
                        </button>
                        <?php if (!$address['is_default']): ?>
                            <button @click="deleteAddress(<?= $address['id'] ?>)"
                                    class="text-red-600 hover:text-red-800">
                                Delete
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Address Modal -->
    <div x-show="showModal" 
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none"
         @keydown.escape.window="showModal = false">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" @click="showModal = false">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900" x-text="isEdit ? 'Edit Address' : 'Add New Address'"></h3>
                    <button @click="showModal = false" class="text-gray-400 hover:text-gray-500">
                        <span class="sr-only">Close</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="saveAddress" class="space-y-4">
                    <input type="hidden" x-model="currentAddress.id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Recipient Name</label>
                        <input type="text" x-model="currentAddress.recipient_name" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Phone</label>
                        <input type="tel" x-model="currentAddress.phone" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Street Address</label>
                        <textarea x-model="currentAddress.street_address" required rows="2"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">District</label>
                            <input type="text" x-model="currentAddress.district" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">City</label>
                            <input type="text" x-model="currentAddress.city" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Province</label>
                            <input type="text" x-model="currentAddress.province" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Postal Code</label>
                            <input type="text" x-model="currentAddress.postal_code" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Additional Info</label>
                        <textarea x-model="currentAddress.additional_info" rows="2"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" x-model="currentAddress.is_default"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <label class="ml-2 text-sm text-gray-700">
                            Set as default address
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" @click="showModal = false"
                                class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-500">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('addressManager', () => ({
        showModal: false,
        isEdit: false,
        currentAddress: {
            id: null,
            recipient_name: '',
            phone: '',
            street_address: '',
            district: '',
            city: '',
            province: '',
            postal_code: '',
            additional_info: '',
            is_default: false
        },

        openModal() {
            this.isEdit = false;
            this.currentAddress = {
                id: null,
                recipient_name: '',
                phone: '',
                street_address: '',
                district: '',
                city: '',
                province: '',
                postal_code: '',
                additional_info: '',
                is_default: false
            };
            this.showModal = true;
        },

        editAddress(address) {
            this.isEdit = true;
            this.currentAddress = {
                ...address,
                is_default: Boolean(parseInt(address.is_default))
            };
            this.showModal = true;
        },

        async saveAddress() {
            try {
                const formData = new FormData();
                formData.append('action', this.isEdit ? 'edit' : 'add');
                if (this.isEdit) {
                    formData.append('address_id', this.currentAddress.id);
                }
                formData.append('recipient_name', this.currentAddress.recipient_name);
                formData.append('phone', this.currentAddress.phone);
                formData.append('street_address', this.currentAddress.street_address);
                formData.append('district', this.currentAddress.district);
                formData.append('city', this.currentAddress.city);
                formData.append('province', this.currentAddress.province);
                formData.append('postal_code', this.currentAddress.postal_code);
                formData.append('additional_info', this.currentAddress.additional_info);
                if (this.currentAddress.is_default) {
                    formData.append('is_default', '1');
                }

                const response = await fetch('/profile/addresses', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (result.success) {
                    window.location.reload();
                }
            } catch (error) {
                console.error('Error saving address:', error);
            }
        },

        async deleteAddress(addressId) {
            if (!confirm('Are you sure you want to delete this address?')) return;
            
            try {
                const response = await fetch('/profile/addresses', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&address_id=${addressId}`
                });

                const result = await response.json();
                if (result.success) {
                    window.location.reload();
                }
            } catch (error) {
                console.error('Error deleting address:', error);
            }
        }
    }));
});
</script>