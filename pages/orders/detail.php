<?php
// This file now serves as a template, actual data comes from the parent component
?>

<!-- Order Detail Modal Template -->
<template x-teleport="body">
    <div x-show="showOrderDetail" 
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="showOrderDetail = false">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 transition-opacity" @click="showOrderDetail = false">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <!-- Modal panel -->
            <div class="inline-block w-full max-w-7xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg">
                <!-- Modal header with close button -->
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Order #<span x-text="currentOrderId"></span></h1>
                    <button @click="showOrderDetail = false" class="text-gray-400 hover:text-gray-500">
                        <span class="sr-only">Close</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <template x-if="orderData">
                    <!-- Modal content -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 max-h-[calc(100vh-200px)] overflow-y-auto">
                        <!-- Main Content -->
                        <div class="lg:col-span-8 space-y-6">
                            <!-- Order Status -->
                            <div class="bg-white rounded-lg shadow-sm p-6">
                                <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Status</h2>
                                <div class="flex items-center justify-between">
                                    <span class="px-3 py-1 rounded-full text-sm" 
                                          :class="getStatusStyle(orderData.status)"
                                          x-text="formatStatus(orderData.status)"></span>
                                    <template x-if="orderData.status === 'shipped'">
                                        <span class="text-gray-600" x-text="'Shipped on ' + formatDate(orderData.updated_at)"></span>
                                    </template>
                                </div>
                            </div>

                            <!-- Payment Details -->
                            <template x-if="['pending_payment', 'payment_uploaded'].includes(orderData.status)">
                                <div class="bg-white rounded-lg shadow-sm p-6">
                                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment Details</h2>
                                    <div class="space-y-4">
                                        <div>
                                            <p class="text-sm font-medium text-gray-500">Payment Method</p>
                                            <p class="mt-1" x-text="formatPaymentMethod(orderData.payment_method)"></p>
                                        </div>
                                        <template x-if="orderData.payment_date">
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Payment Date</p>
                                                <p class="mt-1" x-text="formatDate(orderData.payment_date, true)"></p>
                                            </div>
                                        </template>
                                        <template x-if="orderData.transfer_proof_url">
                                            <div class="mt-4">
                                                <span class="text-gray-600 block mb-2">Payment Proof</span>
                                                <div class="relative group">
                                                    <img :src="orderData.transfer_proof_url" 
                                                         alt="Payment Proof" 
                                                         class="max-w-xs rounded-lg shadow-sm">
                                                    <template x-if="orderData.can_reupload_payment">
                                                        <div class="mt-2">
                                                            <button @click="showPaymentModal = true" 
                                                                    class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                                                </svg>
                                                                Reupload Payment Proof
                                                            </button>
                                                            <p class="text-xs text-gray-500 mt-1">
                                                                You can reupload if the previous proof was incorrect
                                                            </p>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="orderData.status === 'pending_payment'">
                                            <div class="mt-4">
                                                <button @click="showPaymentModal = true" 
                                                        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                                    Upload Payment Proof
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Shipping Address -->
                            <div class="bg-white rounded-lg shadow-sm p-6">
                                <h2 class="text-lg font-semibold text-gray-900 mb-4">Shipping Address</h2>
                                <div class="space-y-1">
                                    <p class="font-medium" x-text="orderData.recipient_name"></p>
                                    <p class="text-gray-600" x-text="orderData.phone"></p>
                                    <p class="text-gray-600" x-text="orderData.street_address"></p>
                                    <p class="text-gray-600">
                                        <span x-text="orderData.district"></span>,
                                        <span x-text="orderData.city"></span>
                                    </p>
                                    <p class="text-gray-600">
                                        <span x-text="orderData.province"></span>,
                                        <span x-text="orderData.postal_code"></span>
                                    </p>
                                </div>
                            </div>

                            <!-- Shipping Details -->
                            <template x-if="orderData.status === 'shipped'">
                                <div class="bg-white rounded-lg shadow-sm p-6">
                                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Shipping Details</h2>
                                    <div class="space-y-4">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Courier</p>
                                                <p class="mt-1" x-text="orderData.courier_name"></p>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Service Type</p>
                                                <p class="mt-1" x-text="orderData.service_type || '-'"></p>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Tracking Number</p>
                                                <p class="mt-1" x-text="orderData.tracking_number || '-'"></p>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Shipping Cost</p>
                                                <p class="mt-1" x-text="formatPrice(orderData.shipping_cost || 0)"></p>
                                            </div>
                                        </div>
                                        <template x-if="orderData.estimated_delivery_date">
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Estimated Delivery</p>
                                                <p class="mt-1" x-text="formatDate(orderData.estimated_delivery_date)"></p>
                                            </div>
                                        </template>
                                        <template x-if="orderData.shipping_notes">
                                            <div>
                                                <p class="text-sm font-medium text-gray-500">Notes</p>
                                                <p class="mt-1 text-gray-600" x-text="orderData.shipping_notes"></p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Order Items -->
                            <div class="bg-white rounded-lg shadow-sm p-6">
                                <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Items</h2>
                                <div class="space-y-4">
                                    <template x-for="item in orderData.items" :key="item.id">
                                        <div class="flex items-center">
                                            <div class="w-20 h-20 flex-shrink-0">
                                                <img :src="item.image_url" 
                                                     :alt="item.name"
                                                     class="w-full h-full object-contain rounded-md">
                                            </div>
                                            <div class="ml-4 flex-1">
                                                <a :href="'/products/' + item.slug" 
                                                   class="text-sm font-medium text-gray-900 hover:text-blue-600"
                                                   x-text="item.name"></a>
                                                <p class="text-sm text-gray-500">
                                                    Quantity: <span x-text="item.quantity"></span>
                                                </p>
                                                <p class="text-sm font-medium text-gray-900" 
                                                   x-text="formatPrice(item.price * item.quantity)"></p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Order Summary -->
                        <div class="lg:col-span-4">
                            <div class="bg-white rounded-lg shadow-sm p-6">
                                <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Summary</h2>
                                <div class="space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Subtotal</span>
                                        <span class="text-gray-900" x-text="formatPrice(orderData.total_amount)"></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Shipping</span>
                                        <span class="text-gray-900" x-text="formatPrice(orderData.shipping_cost || 0)"></span>
                                    </div>
                                    <div class="border-t border-gray-200 mt-4 pt-4">
                                        <div class="flex justify-between">
                                            <span class="text-base font-medium text-gray-900">Total</span>
                                            <span class="text-base font-medium text-gray-900" 
                                                  x-text="formatPrice(parseFloat(orderData.total_amount) + parseFloat(orderData.shipping_cost || 0))"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<!-- Payment Upload Modal -->
<template x-teleport="body">
    <div x-show="showPaymentModal" 
         x-cloak
         class="fixed inset-0 z-[60] overflow-y-auto"
         @keydown.escape.window="showPaymentModal = false">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" @click="showPaymentModal = false">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">
                        Upload Payment Proof
                    </h3>
                    <button @click="showPaymentModal = false" class="text-gray-400 hover:text-gray-500">
                        <span class="sr-only">Close</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <template x-if="orderData">
                    <form @submit.prevent="uploadPayment" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Payment Amount</label>
                            <p class="text-lg font-semibold text-gray-900" x-text="formatPrice(parseFloat(orderData.total_amount) + parseFloat(orderData.shipping_cost || 0))"></p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Upload Proof</label>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md"
                                 @dragover.prevent="dragover = true"
                                 @dragleave.prevent="dragover = false"
                                 @drop.prevent="handleDrop($event)"
                                 :class="{'border-blue-500 bg-blue-50': dragover}">
                                <div class="space-y-1 text-center">
                                    <template x-if="!preview">
                                        <div>
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <div class="flex text-sm text-gray-600">
                                                <label class="relative cursor-pointer rounded-md font-medium text-blue-600 hover:text-blue-500">
                                                    <span>Upload a file</span>
                                                    <input type="file" 
                                                           @change="handleFileSelect"
                                                           accept="image/*"
                                                           class="sr-only">
                                                </label>
                                                <p class="pl-1">or drag and drop</p>
                                            </div>
                                            <p class="text-xs text-gray-500">PNG, JPG up to 5MB</p>
                                        </div>
                                    </template>
                                    <template x-if="preview">
                                        <div class="relative">
                                            <img :src="preview" class="max-h-48 mx-auto">
                                            <button @click.prevent="removeFile" 
                                                    class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button" @click="showPaymentModal = false"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-500">
                                Cancel
                            </button>
                            <button type="submit"
                                    :disabled="!file || isUploading"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md disabled:bg-gray-400 disabled:cursor-not-allowed">
                                <span x-text="isUploading ? 'Uploading...' : 'Upload Payment'"></span>
                            </button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>
</template>