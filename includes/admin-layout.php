<?php
ob_start(); // Start output buffering
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="/css/output.css" rel="stylesheet">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-100">
    <div x-data="{ sidebarOpen: localStorage.getItem('sidebarOpen') === 'true' }" 
         @resize.window="window.innerWidth >= 1024 ? null : sidebarOpen = false"
         x-init="$watch('sidebarOpen', value => localStorage.setItem('sidebarOpen', value))"
         class="min-h-screen flex">
        <!-- Overlay -->
        <div x-show="sidebarOpen" 
             @click="sidebarOpen = false"
             class="fixed inset-0 z-20 bg-black bg-opacity-50 transition-opacity lg:hidden"></div>

        <!-- Sidebar -->
        <div class="fixed inset-y-0 left-0 z-30 w-64 transform bg-[#1B2537] transition duration-200 ease-in-out"
             :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen}">
            <div class="flex h-16 items-center px-6">
                <h2 class="text-xl font-semibold text-white">Admin Panel</h2>
                <button @click="sidebarOpen = false" class="ml-auto text-white">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <nav class="mt-6 px-6 space-y-2">
                <a href="/admin/dashboard" class="flex items-center py-3 text-white hover:bg-gray-800 rounded-lg px-3">
                    <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                    </svg>
                    <span>Dashboard</span>
                </a>
                <a href="/admin/products" class="flex items-center py-3 text-white hover:bg-gray-800 rounded-lg px-3">
                    <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <span>Products</span>
                </a>
                <a href="/admin/orders" class="flex items-center py-3 text-white hover:bg-gray-800 rounded-lg px-3">
                    <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <span>Orders</span>
                </a>
                <a href="/admin/users" class="flex items-center py-3 text-white hover:bg-gray-800 rounded-lg px-3">
                    <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <span>Users</span>
                </a>
                <a href="/admin/settings" class="flex items-center py-3 text-white hover:bg-gray-800 rounded-lg px-3">
                    <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span>Settings</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1" :class="{'ml-64': sidebarOpen}">
            <!-- Top Navigation -->
            <div class="bg-white shadow-sm">
                <div class="flex h-16 items-center justify-between px-4">
                    <button @click="sidebarOpen = !sidebarOpen" class="text-gray-500 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <div class="flex items-center">
                        <span class="text-gray-800 mr-4">
                            <?= htmlspecialchars($_SESSION['username'] ?? 'Admin User') ?>
                        </span>
                        <a href="/logout" class="text-red-600 hover:text-red-800 text-sm">Logout</a>
                    </div>
                </div>
            </div>

            <!-- Page Content -->
            <main class="p-6">
                <?php include $content; ?>
            </main>
        </div>
    </div>
</body>
</html> 