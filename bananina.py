from bs4 import BeautifulSoup
import csv
import time
from urllib.parse import urljoin, urlparse
from playwright.sync_api import sync_playwright, TimeoutError
import concurrent.futures
from functools import partial
import os

def get_headers():
    return {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.6778.109 Safari/537.36',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language': 'en-US,en;q=0.5',
        'Connection': 'keep-alive',
        'Upgrade-Insecure-Requests': '1',
    }

def scrape_with_playwright(base_url):
    """Attempt to scrape using Playwright"""
    browser = None
    try:
        print("Trying with Playwright...")
        with sync_playwright() as p:
            browser = p.chromium.launch(
                headless=True,
                args=['--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage']
            )
            
            context = browser.new_context(
                user_agent='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.6778.109 Safari/537.36',
                viewport={'width': 1920, 'height': 1080}
            )
            
            page = context.new_page()
            page.set_default_timeout(60000)
            page.set_default_navigation_timeout(60000)
            
            all_products = []
            current_page = 1
            
            while True:
                # Construct URL with page number
                url = f"{base_url}?p={current_page}"
                print(f"\nScraping page {current_page}...")
                
                # Navigate to the page
                page.goto(url)
                page.wait_for_load_state('domcontentloaded')
                
                try:
                    # Wait for products to be visible
                    page.wait_for_selector('.category-products', state='visible', timeout=60000)
                    
                    # Scroll down the page
                    for _ in range(3):
                        page.evaluate('window.scrollTo(0, document.body.scrollHeight)')
                        page.wait_for_timeout(2000)
                    
                    # Get page content
                    content = page.content()
                    soup = BeautifulSoup(content, 'html.parser')
                    
                    # Find products on current page
                    products = soup.find_all('div', class_='product-box')
                    
                    if not products:
                        print(f"No products found on page {current_page}")
                        break
                    
                    print(f"Found {len(products)} products on page {current_page}")
                    all_products.extend(products)
                    
                    # Check if there's a next page
                    pagination = soup.find('div', class_='pages')
                    if not pagination:
                        print("No pagination found")
                        break
                        
                    next_link = pagination.find('a', class_='next')
                    if not next_link:
                        print("No next page link found")
                        break
                    
                    # Check if we're on the last page by looking at the active page number
                    current_span = pagination.find('span', class_='current')
                    if current_span:
                        total_pages = max([
                            int(a.get_text()) 
                            for a in pagination.find_all(['a', 'span']) 
                            if a.get_text().strip().isdigit()
                        ])
                        if current_page >= total_pages:
                            print(f"Reached last page ({total_pages})")
                            break
                    
                    current_page += 1
                    
                except Exception as e:
                    print(f"Error processing page {current_page}: {str(e)}")
                    break
            
            print(f"\nTotal products found across all pages: {len(all_products)}")
            return all_products if all_products else None
                
    except Exception as e:
        print(f"Playwright setup error: {str(e)}")
        return None
    finally:
        if browser:
            try:
                browser.close()
            except:
                pass  # Ignore any errors during browser closing

def get_product_details(page, url):
    """Get additional product details from product page"""
    try:
        page.set_default_timeout(15000)
        page.goto(url)
        
        try:
            page.wait_for_selector('.product-description', timeout=5000)
        except:
            pass
            
        content = page.content()
        soup = BeautifulSoup(content, 'html.parser')
        
        # Get product quality
        quality = "N/A"
        quality_elem = soup.find('div', id='product-quality')
        if quality_elem:
            quality = quality_elem.get_text(strip=True)
            
        # Get product description
        description = "N/A"
        desc_elem = soup.find('div', id='product-description')
        if desc_elem:
            description = desc_elem.get_text(strip=True)
            
        # Get product details and remove measurement note
        details = "N/A"
        details_elem = soup.find('div', id='product-details')
        if details_elem:
            details_list = []
            for li in details_elem.find_all('li'):
                text = li.get_text(strip=True)
                # Remove the measurement note if present
                if "Product size is measured based on BANANANINA" not in text:
                    details_list.append(text)
            details = ' | '.join(details_list)
            
        # Get product condition
        condition = "N/A"
        condition_elem = soup.find('div', id='product-condition')
        if condition_elem:
            condition = ' | '.join([li.get_text(strip=True) for li in condition_elem.find_all('li')])
            
        # Get completeness (if exists)
        completeness = "N/A"
        completeness_elem = soup.find('div', id='product-completeness')
        if completeness_elem:
            completeness = ' | '.join([li.get_text(strip=True) for li in completeness_elem.find_all('li')])
            # Add completeness to condition if it exists
            if completeness != "N/A":
                condition = f"{condition} | Completeness: {completeness}"
                
        return {
            'quality': quality,
            'description': description,
            'details': details,
            'condition': condition
        }
        
    except Exception as e:
        print(f"Error getting product details from {url}: {str(e)}")
        return {
            'quality': "N/A",
            'description': "N/A",
            'details': "N/A",
            'condition': "N/A"
        }

def process_products(products, category):
    """Process and save product data"""
    filename = f"{category}_bags.csv"
    
    with open(filename, 'w', newline='', encoding='utf-8') as file:
        writer = csv.writer(file)
        writer.writerow([
            "Brand",
            "Name", 
            "Price", 
            "Original Price", 
            "Discount", 
            "Product Link", 
            "Primary Image",
            "Hover Image",
            "SKU",
            "Quality",
            "Description",
            "Details",
            "Condition"
        ])
        
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            context = browser.new_context(
                user_agent='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.6778.109 Safari/537.36'
            )
            page = context.new_page()
            
            for index, product in enumerate(products, 1):
                try:
                    # Extract brand name
                    brand_elem = product.find('p', class_='brand')
                    brand = brand_elem.get_text(strip=True) if brand_elem else "N/A"
                    
                    # Extract product name
                    name_elem = product.find('p', class_='name')
                    name = name_elem.get_text(strip=True) if name_elem else "N/A"
                    
                    # Extract prices and discount
                    price_box = product.find('div', class_='price-box')
                    price = "N/A"
                    original_price = "N/A"
                    discount = "No discount"
                    
                    if price_box:
                        # Try to get special price first (discounted price)
                        special_price = price_box.find('p', class_='special-price')
                        if special_price:
                            price_span = special_price.find('span', class_='price')
                            if price_span:
                                price = price_span.get_text(strip=True)
                                
                            # Get original price
                            old_price = price_box.find('p', class_='old-price')
                            if old_price:
                                orig_span = old_price.find('span', class_='price')
                                if orig_span:
                                    original_price = orig_span.get_text(strip=True)
                                    
                            # Get discount percentage
                            discount_elem = price_box.find('p', class_='yoursaving')
                            if discount_elem:
                                discount_span = discount_elem.find('span', class_='price')
                                if discount_span:
                                    discount = discount_span.get_text(strip=True)
                        else:
                            # If no special price, get regular price
                            regular_price = price_box.find('span', class_='regular-price')
                            if regular_price:
                                price_span = regular_price.find('span', class_='price')
                                if price_span:
                                    price = price_span.get_text(strip=True)
                    
                    # Extract product link
                    product_link = "N/A"
                    link_elem = product.find('a', href=True)
                    if link_elem:
                        product_link = link_elem['href']
                    
                    # Extract image links
                    primary_image = "N/A"
                    hover_image = "N/A"
                    images_div = product.find('div', class_='images')
                    if images_div:
                        # Get primary image
                        primary_img = images_div.find('img', class_='img-primary')
                        if primary_img:
                            primary_image = primary_img.get('data-src') or primary_img.get('src', 'N/A')
                            if 'blank.jpg' in primary_image:
                                real_file = primary_img.get('realfile')
                                if real_file:
                                    primary_image = f"https://media.banananina.id/catalog/product/{real_file}"
                        
                        # Get hover/secondary image
                        hover_img = images_div.find('img', class_='img-secondary')
                        if hover_img:
                            hover_image = hover_img.get('data-src') or hover_img.get('src', 'N/A')
                            if 'blank.jpg' in hover_image:
                                real_file = hover_img.get('realfile')
                                if real_file:
                                    hover_image = f"https://media.banananina.id/catalog/product/{real_file}"
                    
                    # Try to extract SKU from product link
                    sku = "N/A"
                    if product_link != "N/A":
                        sku_match = product_link.split('/')[-1].split('.')[0]
                        if sku_match:
                            sku = sku_match
                    
                    # Get additional details if we have a valid product link
                    product_details = {
                        'quality': "N/A",
                        'description': "N/A",
                        'details': "N/A",
                        'condition': "N/A"
                    }
                    
                    if product_link != "N/A":
                        print(f"\nGetting details for product {index}...")
                        product_details = get_product_details(page, product_link)
                    
                    # Save the image URLs directly without downloading
                    primary_image = primary_image if primary_image != "N/A" else "N/A"
                    hover_image = hover_image if hover_image != "N/A" else "N/A"
                    
                    # Write row to CSV
                    writer.writerow([
                        brand,
                        name,
                        price,
                        original_price,
                        discount,
                        product_link,
                        primary_image,
                        hover_image,
                        sku,
                        product_details['quality'],
                        product_details['description'],
                        product_details['details'],
                        product_details['condition']
                    ])
                    
                    print(f"\rProcessed {index}/{len(products)} products...", end='', flush=True)
                    time.sleep(0.1)
                    
                except Exception as e:
                    print(f"\nError processing product {index}: {str(e)}")
                    continue
            
            browser.close()
    
    print(f"\nData has been successfully scraped and saved to {filename}")

def get_category_urls():
    """Return a dictionary of category names and their URLs"""
    return {
        'backpacks': 'https://www.banananina.co.id/bags/backpacks.html',
        'clutches': 'https://www.banananina.co.id/bags/clutches.html',
        'crossbody': 'https://www.banananina.co.id/bags/crossbody-bags.html',
        'laptop': 'https://www.banananina.co.id/bags/laptop-bags.html',
        'satchels': 'https://www.banananina.co.id/bags/satchels.html',
        'shoulder': 'https://www.banananina.co.id/bags/shoulder-bags.html',
        'totes': 'https://www.banananina.co.id/bags/totes.html',
        'travel': 'https://www.banananina.co.id/bags/travel-bags.html'
    }

def scrape_category(url, category):
    """Scrape products from a specific category"""
    print(f"\nStarting to scrape {category} bags from {url}")
    
    products = scrape_with_playwright(url)
    
    if products:
        print(f"Found {len(products)} {category} bags. Starting to extract data...")
        process_products(products, category)
        return True
    else:
        print(f"Failed to scrape {category} bags. Please check the website structure or try again later.")
        return False

def scrape_main():
    """Main function for scraping products"""
    categories = get_category_urls()
    successful = 0
    failed = 0
    
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as executor:
        future_to_category = {
            executor.submit(scrape_category, url, category): category 
            for category, url in categories.items()
        }
        
        for future in concurrent.futures.as_completed(future_to_category):
            category = future_to_category[future]
            try:
                success = future.result()
                if success:
                    successful += 1
                else:
                    failed += 1
            except Exception as e:
                print(f"\nError processing {category}: {str(e)}")
                failed += 1
            
    print(f"\n{'='*50}")
    print(f"Scraping completed!")
    print(f"Successfully scraped: {successful} categories")
    print(f"Failed to scrape: {failed} categories")
    print(f"{'='*50}")

if __name__ == "__main__":
    scrape_main()  # Only keep the scraping functionality
