<?php
// Remove the HTML structure and keep only the navigation content
?>
<!-- Top Banner -->


<header class="bg-white relative z-50" x-data="{ mobileMenuOpen: false }">
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
             
            <a href="/" class="flex items-center">
            <img src="/assets/images/logo.png" alt="Bananina" class="w-16 h-16">
            </a>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="/products" class="text-gray-600 hover:text-gray-900">All Products</a>
                <a href="/categories" class="text-gray-600 hover:text-gray-900">Categories</a>
                <a href="/brands" class="text-gray-600 hover:text-gray-900">Brand</a>
            </div>

            <!-- Search, Cart, Account -->
            <div class="hidden md:flex items-center space-x-6">
                <!-- Search -->
                <div class="relative">
                    <form action="/products" method="GET" class="relative">
                        <input type="text" 
                               name="search"
                               placeholder="Search products" 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                               class="w-40 pl-8 pr-4 py-1 border border-gray-300 rounded-full text-sm focus:outline-none focus:border-gray-400">
                        <button type="submit" class="absolute left-2.5 top-1/2 transform -translate-y-1/2 text-gray-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </button>
                    </form>
                </div>

                <!-- Cart -->
                <a href="/cart" class="relative text-gray-600 hover:text-gray-900">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs cart-count">
                        <?php
                            $cart = new Cart();
                            echo $cart->getCount();
                        ?>
                    </span>
                </a>

                <!-- Account -->
                <?php if (Auth::isLoggedIn()): ?>
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="text-gray-600 hover:text-gray-900">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-[9999]">
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
                    <a href="/login" class="text-gray-600 hover:text-gray-900">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </a>
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
            <form action="/products" method="GET" class="relative mb-4">
                <input type="text" 
                       name="search"
                       placeholder="Search products" 
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                       class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-full text-sm focus:outline-none focus:border-gray-400">
                <button type="submit" class="absolute left-2.5 top-1/2 transform -translate-y-1/2 text-gray-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>
            </form>
            <a href="/products" class="block py-2 text-gray-600 hover:text-gray-900">All Products</a>
            <a href="/categories" class="block py-2 text-gray-600 hover:text-gray-900">Categories</a>
            <a href="/brands" class="block py-2 text-gray-600 hover:text-gray-900">Brand</a>
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