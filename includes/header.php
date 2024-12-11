<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bag E-commerce</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <header class="bg-white shadow-sm" x-data="{ mobileMenuOpen: false }">
        <nav class="container mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <a href="/" class="flex items-center">
                    <span class="text-xl font-bold text-gray-800">Bananina</span>
                </a>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-4">
                    <a href="/" class="text-gray-600 hover:text-gray-900">Home</a>
                    <a href="/products" class="text-gray-600 hover:text-gray-900">Products</a>
                    <a href="/categories" class="text-gray-600 hover:text-gray-900">Categories</a>
                    
                    <!-- Cart Icon -->
                    <a href="/cart" class="relative text-gray-600 hover:text-gray-900">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
                            <?php
                                $cart = new Cart();
                                echo $cart->getCount();
                            ?>
                        </span>
                    </a>

                    <?php if (Auth::isLoggedIn()): ?>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center text-gray-600 hover:text-gray-900">
                                <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1">
                                <a href="/profile" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                                <a href="/orders" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Orders</a>
                                <a href="/wishlist" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Wishlist</a>
                                <?php if (Auth::hasRole('admin')): ?>
                                    <a href="/admin" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Admin Panel</a>
                                <?php endif; ?>
                                <a href="/logout" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="/login" class="text-gray-600 hover:text-gray-900">Login</a>
                        <a href="/register" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">Register</a>
                    <?php endif; ?>
                </div>

                <!-- Mobile menu button -->
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>

            <!-- Mobile Navigation -->
            <div x-show="mobileMenuOpen" class="md:hidden py-4">
                <a href="/" class="block py-2 text-gray-600 hover:text-gray-900">Home</a>
                <a href="/products" class="block py-2 text-gray-600 hover:text-gray-900">Products</a>
                <a href="/categories" class="block py-2 text-gray-600 hover:text-gray-900">Categories</a>
                <a href="/cart" class="block py-2 text-gray-600 hover:text-gray-900">Cart</a>
                <?php if (Auth::isLoggedIn()): ?>
                    <a href="/profile" class="block py-2 text-gray-600 hover:text-gray-900">Profile</a>
                    <a href="/orders" class="block py-2 text-gray-600 hover:text-gray-900">Orders</a>
                    <a href="/wishlist" class="block py-2 text-gray-600 hover:text-gray-900">Wishlist</a>
                    <a href="/logout" class="block py-2 text-gray-600 hover:text-gray-900">Logout</a>
                <?php else: ?>
                    <a href="/login" class="block py-2 text-gray-600 hover:text-gray-900">Login</a>
                    <a href="/register" class="block py-2 text-gray-600 hover:text-gray-900">Register</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>
</body>
</html> 