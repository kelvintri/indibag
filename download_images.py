import pandas as pd
import os
import requests
from urllib.parse import urlparse
import time
import concurrent.futures
from pathlib import Path

# Define the base directory for images
BASE_DIR = Path("C:/laragon/www/Bananina/public/assets/images")

# Create directories if they don't exist
(BASE_DIR / "backpacks" / "primary").mkdir(parents=True, exist_ok=True)
(BASE_DIR / "backpacks" / "hover").mkdir(parents=True, exist_ok=True)

def get_headers():
    return {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.6778.109 Safari/537.36',
        'Accept': 'image/webp,image/*,*/*;q=0.8',
        'Accept-Language': 'en-US,en;q=0.5',
        'Connection': 'keep-alive',
    }

def download_image(url, folder_path, filename):
    """Download image from URL and save it to appropriate folder"""
    try:
        if url == "N/A" or not url.startswith('http'):
            return None
            
        # Create folder if it doesn't exist
        Path(folder_path).mkdir(parents=True, exist_ok=True)
        
        # Create filename
        filepath = Path(folder_path) / filename
        
        # Skip if file already exists
        if filepath.exists():
            print(f"Image already exists: {filepath}")
            return str(filepath)
        
        # Download image with session
        with requests.get(url, headers=get_headers(), stream=True, timeout=10) as response:
            response.raise_for_status()
            with open(filepath, 'wb') as f:
                for chunk in response.iter_content(chunk_size=8192):
                    if chunk:
                        f.write(chunk)
                        
        print(f"Downloaded: {filepath}")
        return str(filepath)
        
    except Exception as e:
        print(f"Error downloading image {url}: {str(e)}")
        return None

def process_row(args):
    """Process a single row of data"""
    index, row, category = args
    results = {'primary': None, 'hover': None}
    
    for image_type in ['primary', 'hover']:
        url = row[f'{image_type.title()} Image']
        if url != 'N/A':
            folder_path = os.path.join('images', category, image_type)
            # Clean filename
            clean_name = "".join(c for c in row['Name'] if c.isalnum() or c in (' ', '-', '_')).rstrip()
            ext = os.path.splitext(urlparse(url).path)[1] or '.jpg'
            filename = f"{clean_name}_{image_type}{ext}"
            
            local_path = download_image(url, folder_path, filename)
            results[image_type] = local_path
            
    return index, results

def process_csv_file(csv_file):
    """Process a single CSV file and download its images"""
    try:
        # Get category name from filename
        category = csv_file.replace('_bags.csv', '')
        print(f"\nProcessing {category} category...")
        
        # Read CSV file
        df = pd.read_csv(csv_file)
        total_products = len(df)
        print(f"Found {total_products} products")
        
        # Create arguments for parallel processing
        args_list = [(index, row, category) for index, row in df.iterrows()]
        
        # Process in parallel with ThreadPoolExecutor
        with concurrent.futures.ThreadPoolExecutor(max_workers=10) as executor:
            futures = [executor.submit(process_row, args) for args in args_list]
            
            # Process results as they complete
            for future in concurrent.futures.as_completed(futures):
                try:
                    index, results = future.result()
                    # Update DataFrame with local paths
                    if results['primary']:
                        df.at[index, 'Primary Image Local Path'] = results['primary']
                    if results['hover']:
                        df.at[index, 'Hover Image Local Path'] = results['hover']
                    
                    print(f"\rProcessed {index + 1}/{total_products} products...", end='', flush=True)
                except Exception as e:
                    print(f"\nError processing row: {str(e)}")
        
        # Save updated CSV with local paths
        df.to_csv(csv_file, index=False)
        print(f"\nCompleted processing {category} category")
        return True
        
    except Exception as e:
        print(f"Error processing CSV file {csv_file}: {str(e)}")
        return False

def main():
    # Get all CSV files in current directory
    csv_files = [f for f in os.listdir('.') if f.endswith('_bags.csv')]
    
    if not csv_files:
        print("No CSV files found!")
        return
    
    print(f"Found {len(csv_files)} CSV files to process")
    successful = 0
    failed = 0
    
    start_time = time.time()
    
    for csv_file in csv_files:
        print(f"\n{'='*50}")
        print(f"Processing {csv_file}")
        print(f"{'='*50}")
        
        if process_csv_file(csv_file):
            successful += 1
        else:
            failed += 1
    
    end_time = time.time()
    duration = end_time - start_time
    
    print(f"\n{'='*50}")
    print(f"Processing completed in {duration:.2f} seconds!")
    print(f"Successfully processed: {successful} files")
    print(f"Failed to process: {failed} files")
    print(f"{'='*50}")

if __name__ == "__main__":
    main()