            <?php
            $db = new Database();
            $conn = $db->getConnection();
            
// Get featured products
            $query = "SELECT p.*, 
                      MAX(CASE WHEN pg.is_primary = 1 THEN pg.image_url END) as primary_image,
                      MAX(CASE WHEN pg.is_primary = 0 THEN pg.image_url END) as hover_image
                      FROM products p 
                      LEFT JOIN product_galleries pg ON p.id = pg.product_id 
                      GROUP BY p.id
                      ORDER BY p.id
                      LIMIT 8";
            
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $featuredProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get brands
$brandQuery = "SELECT * FROM brands ORDER BY name LIMIT 24";
$brandStmt = $conn->prepare($brandQuery);
$brandStmt->execute();
$brands = $brandStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all categories with product count
$categoryQuery = "SELECT c.*, COUNT(p.id) as product_count,
           MAX(CASE WHEN pg.is_primary = 1 THEN pg.image_url END) as primary_image
           FROM categories c 
           LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
           LEFT JOIN product_galleries pg ON p.id = pg.product_id
           GROUP BY c.id
           ORDER BY c.name
           LIMIT 8";

$categoryStmt = $conn->prepare($categoryQuery);
$categoryStmt->execute();
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Top Banner -->
<div class="bg-gray-900 text-white text-center text-sm py-2">
    Sign up and GET 20% OFF for your first order. <a href="#" class="underline">Sign up now</a>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Hero Slider -->
    <div class="relative mb-12">
        <div class="rounded-lg overflow-hidden">
            <div id="hero-carousel" class="relative" style="aspect-ratio: 1740/608;">
                <div class="absolute inset-0 w-full h-full transition-opacity duration-500">
                    <img src="/assets/images/hero/hero1.jpg" alt="Summer Collection" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-black bg-opacity-30 flex items-center justify-center">
                        <div class="text-center text-white">
                            <h1 class="text-4xl md:text-6xl font-bold mb-4">Level up your style with our<br>summer collections</h1>
                            <a href="/products" class="inline-block bg-white text-black px-8 py-3 rounded-full hover:bg-gray-100 transition">
                                Shop now
                            </a>
                        </div>
                    </div>
                </div>
                <div class="absolute inset-0 w-full h-full transition-opacity duration-500 opacity-0">
                    <img src="/assets/images/hero/hero2.jpg" alt="Summer Collection" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-black bg-opacity-30 flex items-center justify-center">
                        <div class="text-center text-white">
                            <h1 class="text-4xl md:text-6xl font-bold mb-4">Discover our latest<br>fashion trends</h1>
                            <a href="/products" class="inline-block bg-white text-black px-8 py-3 rounded-full hover:bg-gray-100 transition">
                                Shop now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Slider Navigation -->
            <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex space-x-2">
                <button onclick="setSlide(0)" class="w-2 h-2 rounded-full bg-white" id="slide-dot-0"></button>
                <button onclick="setSlide(1)" class="w-2 h-2 rounded-full bg-white bg-opacity-50" id="slide-dot-1"></button>
            </div>
            <!-- Slider Arrows -->
            <button onclick="prevSlide()" class="absolute left-4 top-1/2 transform -translate-y-1/2 w-10 h-10 bg-white bg-opacity-50 rounded-full flex items-center justify-center hover:bg-opacity-75">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
            <button onclick="nextSlide()" class="absolute right-4 top-1/2 transform -translate-y-1/2 w-10 h-10 bg-white bg-opacity-50 rounded-full flex items-center justify-center hover:bg-opacity-75">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Brands -->
    <div class="mb-12">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold">Brands</h2>
            <div class="flex gap-2">
                <button id="brands-prev" onclick="moveCarousel('brands', -1)" class="w-8 h-8 bg-white rounded-full shadow flex items-center justify-center hover:bg-gray-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button id="brands-next" onclick="moveCarousel('brands', 1)" class="w-8 h-8 bg-white rounded-full shadow flex items-center justify-center hover:bg-gray-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>
        <div class="relative overflow-hidden">
            <div id="brands-carousel" class="flex transition-transform duration-800 ease-in-out">
                <?php foreach (array_chunk($brands, 8) as $brandGroup): ?>
                    <div class="min-w-full">
                        <div class="grid grid-cols-8 gap-8">
                            <?php foreach ($brandGroup as $brand): ?>
                                <div class="flex items-center justify-center">
                                    <?php
                                    $brandName = strtolower($brand['name']);
                                    $logoUrl = "/assets/images/brands/" . $brand['slug'] . ".png";
                                    ?>
                                    <img src="<?= $logoUrl ?>" 
                                         alt="<?= htmlspecialchars($brand['name']) ?>"
                                         class="h-8 object-contain grayscale hover:grayscale-0 transition"
                                         onerror="this.src='/assets/images/brands/placeholder.png'">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Features -->
    <div class="mb-12">
        <h2 class="text-xl font-semibold mb-2">We provide best</h2>
        <p class="text-gray-500 mb-6">customer experiences</p>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="p-6 bg-white rounded-lg">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="font-semibold mb-2">Original Products</h3>
                <p class="text-sm text-gray-500">We provide money back guarantee if the product isn't original</p>
            </div>
            <div class="p-6 bg-white rounded-lg">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h3 class="font-semibold mb-2">Satisfaction Guarantee</h3>
                <p class="text-sm text-gray-500">Exchange the product you've purchased if it doesn't fit on you</p>
            </div>
            <div class="p-6 bg-white rounded-lg">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="font-semibold mb-2">New Arrival Everyday</h3>
                <p class="text-sm text-gray-500">We update our collections almost everyday</p>
            </div>
            <div class="p-6 bg-white rounded-lg">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                </div>
                <h3 class="font-semibold mb-2">Fast & Free Shipping</h3>
                <p class="text-sm text-gray-500">We offer fast and free shipping for our loyal customers</p>
            </div>
        </div>
    </div>

    <!-- Categories -->
    <div class="mb-12">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold">Categories</h2>
            <div class="flex items-center gap-4">
                <div class="flex gap-2">
                    <button id="categories-prev" onclick="moveCarousel('categories', -1)" class="w-8 h-8 bg-white rounded-full shadow flex items-center justify-center hover:bg-gray-50 transition-opacity">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button id="categories-next" onclick="moveCarousel('categories', 1)" class="w-8 h-8 bg-white rounded-full shadow flex items-center justify-center hover:bg-gray-50 transition-opacity">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
                <a href="/categories" class="text-sm text-gray-500 hover:text-black">View all →</a>
            </div>
        </div>
        <div class="relative overflow-hidden">
            <div id="categories-carousel" class="flex transition-transform duration-300 ease-in-out">
                <?php foreach ($categories as $category): ?>
                <div class="w-full md:w-1/4 flex-shrink-0 px-3">
                    <a href="/products?category[]=<?= $category['id'] ?>" class="relative aspect-square rounded-lg overflow-hidden group block">
                        <img src="<?= getImageUrl($category['primary_image']) ?>" 
                             alt="<?= htmlspecialchars($category['name']) ?>" 
                             class="w-full h-full object-cover"
                             onerror="this.src='<?= asset('images/placeholder.jpg') ?>'">
                        <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">
                            <div class="text-center">
                                <button class="bg-white text-black px-6 py-2 rounded-full opacity-0 group-hover:opacity-100 transition">
                                    <?= htmlspecialchars($category['name']) ?>
                                </button>
                                <p class="text-white text-sm mt-2"><?= $category['product_count'] ?> Products</p>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Featured Products -->
    <div class="mb-12">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold">Featured products</h2>
            <div class="flex gap-2">
                <button id="featured-prev" onclick="moveCarousel('featured', -1)" class="w-8 h-8 bg-white rounded-full shadow flex items-center justify-center hover:bg-gray-50 transition-opacity">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button id="featured-next" onclick="moveCarousel('featured', 1)" class="w-8 h-8 bg-white rounded-full shadow flex items-center justify-center hover:bg-gray-50 transition-opacity">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>
        <div class="relative overflow-hidden">
            <div id="featured-carousel" class="flex transition-transform duration-300 ease-in-out">
                <?php foreach ($featuredProducts as $product): ?>
                <div class="w-full md:w-1/3 flex-shrink-0 px-3">
                    <div class="bg-white rounded-lg overflow-hidden group">
                        <div class="relative aspect-[3/4]">
                            <img src="<?= getImageUrl($product['primary_image']) ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 class="w-full h-full object-cover">
                            <?php if ($product['hover_image']): ?>
                                <img src="<?= getImageUrl($product['hover_image']) ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?>"
                                     class="absolute inset-0 w-full h-full object-cover opacity-0 group-hover:opacity-100 transition">
                            <?php endif; ?>
                            <div class="absolute bottom-4 right-4">
                                <button class="w-10 h-10 bg-white rounded-full shadow-lg flex items-center justify-center hover:bg-gray-100">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="p-4">
                            <h3 class="font-medium"><?= htmlspecialchars($product['name']) ?></h3>
                            <p class="text-gray-500">Rp <?= number_format($product['price'], 0, ',', '.') ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Promo Banner -->
    <div class="mb-12">
        <div class="relative rounded-lg overflow-hidden">
            <img src="/assets/images/promo-banner.jpg" alt="Special Offer" class="w-full h-64 object-cover">
            <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center">
                <div class="px-8">
                    <p class="text-white text-sm mb-2">LIMITED OFFER</p>
                    <h3 class="text-white text-2xl font-bold mb-4">35% off only this friday<br>and get special gift</h3>
                    <button class="bg-white text-black px-6 py-2 rounded-full hover:bg-gray-100">
                        Grab it now
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Newsletter -->
    <div class="mb-12 text-center">
        <h2 class="text-xl font-semibold mb-2">Subscribe to our newsletter to get updates</h2>
        <p class="text-gray-500 mb-6">to our latest collections</p>
        <form class="max-w-md mx-auto">
            <div class="flex gap-4">
                <input type="email" 
                       placeholder="Enter your email" 
                       class="flex-1 px-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:border-black">
                <button type="submit" 
                        class="px-6 py-2 bg-black text-white rounded-full hover:bg-gray-800">
                    Subscribe
                </button>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                Get 25% off your first order just by subscribing to our newsletter
            </p>
        </form>
    </div>
</div> 

<script>
const carouselState = {
    categories: { currentIndex: 0, itemCount: <?= count($categories) ?>, itemsPerView: 4, scrollAmount: 1, infinite: true },
    featured: { currentIndex: 0, itemCount: <?= count($featuredProducts) ?>, itemsPerView: 3, scrollAmount: 1, infinite: true },
    brands: { currentIndex: 0, itemCount: <?= ceil(count($brands) / 8) ?>, itemsPerView: 1, scrollAmount: 1, infinite: true }
};

function moveCarousel(type, direction) {
    const state = carouselState[type];
    const carousel = document.getElementById(`${type}-carousel`);
    
    if (!carousel) return;

    // Calculate new index
    let newIndex = state.currentIndex + direction;
    
    // For infinite scroll
    if (newIndex < 0) {
        newIndex = state.itemCount - state.itemsPerView;
    } else if (newIndex >= state.itemCount - state.itemsPerView + 1) {
        newIndex = 0;
    }
    
    // Update state
    state.currentIndex = newIndex;
    
    // Calculate translation based on carousel type
    let translateX;
    if (type === 'brands') {
        translateX = -(newIndex * 100);
    } else {
        translateX = -(newIndex * (100 / state.itemsPerView));
    }
    
    // Apply smooth transition
    carousel.style.transition = 'transform 0.8s ease-in-out';
    carousel.style.transform = `translateX(${translateX}%)`;
}

// Handle responsive itemsPerView changes
function updateItemsPerView() {
    const width = window.innerWidth;
    if (width < 768) { // mobile
        carouselState.categories.itemsPerView = 1;
        carouselState.featured.itemsPerView = 1;
        carouselState.brands.itemsPerView = 1;
    } else { // desktop
        carouselState.categories.itemsPerView = 4;
        carouselState.featured.itemsPerView = 3;
        carouselState.brands.itemsPerView = 1;
    }
    
    // Reset positions and update
    Object.keys(carouselState).forEach(type => {
        const state = carouselState[type];
        state.currentIndex = 0;
        // Update maxIndex based on itemsPerView
        state.maxIndex = Math.max(0, state.itemCount - state.itemsPerView);
        
        const carousel = document.getElementById(`${type}-carousel`);
        if (carousel) {
            carousel.style.transition = 'none';
            carousel.style.transform = 'translateX(0)';
            carousel.offsetHeight; // Force reflow
            carousel.style.transition = 'transform 0.8s ease-in-out';
        }
    });
}

// Optional: Auto-play functionality with 10-second interval
function startAutoPlay() {
    setInterval(() => {
        ['categories', 'featured', 'brands'].forEach(type => {
            const state = carouselState[type];
            if (state.currentIndex >= state.maxIndex) {
                state.currentIndex = -1; // Will become 0 after moveCarousel adds 1
            }
            moveCarousel(type, 1);
        });
    }, 10000);
}

// Initialize carousel states with maxIndex
document.addEventListener('DOMContentLoaded', () => {
    // Initialize maxIndex for each carousel
    Object.keys(carouselState).forEach(type => {
        const state = carouselState[type];
        state.maxIndex = Math.max(0, state.itemCount - state.itemsPerView);
    });
    
    updateItemsPerView();
    startAutoPlay();
});

// Update on resize
window.addEventListener('resize', updateItemsPerView);

// Hero Slider functionality
let currentSlide = 0;
const totalSlides = 2;
const slideInterval = 5000; // Change slide every 5 seconds

function showSlide(index) {
    const slides = document.querySelectorAll('#hero-carousel > div');
    const dots = document.querySelectorAll('[id^="slide-dot-"]');
    
    slides.forEach((slide, i) => {
        slide.style.opacity = i === index ? '1' : '0';
        dots[i].classList.toggle('bg-opacity-50', i !== index);
    });
}

function nextSlide() {
    currentSlide = (currentSlide + 1) % totalSlides;
    showSlide(currentSlide);
}

function prevSlide() {
    currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
    showSlide(currentSlide);
}

function setSlide(index) {
    currentSlide = index;
    showSlide(currentSlide);
}

// Start the automatic slideshow
let slideTimer = setInterval(nextSlide, slideInterval);

// Reset timer when manually changing slides
document.querySelectorAll('[id^="slide-dot-"], button').forEach(button => {
    button.addEventListener('click', () => {
        clearInterval(slideTimer);
        slideTimer = setInterval(nextSlide, slideInterval);
    });
});

// Initialize the first slide
document.addEventListener('DOMContentLoaded', () => {
    showSlide(0);
});
</script>