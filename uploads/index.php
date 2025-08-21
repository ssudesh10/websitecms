<?php
// Define config file path
$config_path = '../config.php';

// Check if config file exists and load it
if (file_exists($config_path)) {
    require_once $config_path;
    $base_url = defined('BASE_URL') ? BASE_URL : '/';
} else {
    $base_url = '/';
}

// Define the directory to scan for images
$image_dir = '../uploads/'; // Adjust path as needed
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

// Get all image files
$images = [];
if (is_dir($image_dir)) {
    $files = scandir($image_dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed_extensions)) {
                $images[] = $file;
            }
        }
    }
}

// Sort images by name
sort($images);

// Get statistics
$totalImages = count($images);
$totalSize = 0;
foreach ($images as $image) {
    $image_path = $image_dir . $image;
    if (file_exists($image_path)) {
        $totalSize += filesize($image_path);
    }
}
$totalSizeFormatted = number_format($totalSize / (1024 * 1024), 2) . ' MB';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Gallery - Admin Dashboard</title>
    <link href="../public/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Modal for full-size image view */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
        }

        .modal-content {
            position: relative;
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
            margin-top: 5%;
        }

        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #fff;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #ccc;
        }

        .image-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .image-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .image-wrapper {
            position: relative;
            height: 200px;
            overflow: hidden;
            border-radius: 8px;
        }

        .image-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .image-card:hover img {
            transform: scale(1.05);
        }

        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
            border-radius: 8px;
        }

        .image-card:hover .image-overlay {
            opacity: 1;
        }

        .overlay-text {
            color: white;
            font-size: 16px;
            font-weight: 600;
        }

        /* Animation for scroll reveal */
        .image-card {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .image-card.animate-in {
            opacity: 1;
            transform: translateY(0);
        }

        /* Stagger animation delay */
        .image-card:nth-child(1) { transition-delay: 0.1s; }
        .image-card:nth-child(2) { transition-delay: 0.2s; }
        .image-card:nth-child(3) { transition-delay: 0.3s; }
        .image-card:nth-child(4) { transition-delay: 0.4s; }
        .image-card:nth-child(5) { transition-delay: 0.5s; }
        .image-card:nth-child(6) { transition-delay: 0.6s; }
        .image-card:nth-child(7) { transition-delay: 0.7s; }
        .image-card:nth-child(8) { transition-delay: 0.8s; }
        .image-card:nth-child(9) { transition-delay: 0.9s; }
        .image-card:nth-child(10) { transition-delay: 1.0s; }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Admin Header -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="javascript:history.back()" class="text-gray-600 hover:text-gray-800 mr-4">
                        <i class="fas fa-arrow-left mr-2"></i>Back
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900">Image Gallery</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="<?= $base_url ?>" target="_blank" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-external-link-alt mr-1"></i>View Site
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-images text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Images</h3>
                        <p class="text-2xl font-semibold text-gray-900"><?= $totalImages ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-hdd text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Size</h3>
                        <p class="text-2xl font-semibold text-gray-900"><?= $totalSizeFormatted ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-folder text-purple-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Directory</h3>
                        <p class="text-lg font-semibold text-gray-900">uploads/</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-file-image text-orange-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Formats</h3>
                        <p class="text-sm font-semibold text-gray-900">JPG, PNG, GIF, WEBP</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Images Gallery -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900">All Images (<?= $totalImages ?>)</h2>
            </div>
            
            <?php if (!empty($images)): ?>
                <div class="p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                        <?php foreach ($images as $index => $image): 
                            $image_path = $image_dir . $image;
                            $image_size = file_exists($image_path) ? filesize($image_path) : 0;
                            $image_size_formatted = $image_size > 0 ? number_format($image_size / 1024, 1) . ' KB' : 'Unknown';
                            
                            // Clean up filename for display
                            $display_name = preg_replace('/^\d+[a-f0-9]*_\d+\./', '', $image);
                            $display_name = str_replace(['_', '-'], ' ', pathinfo($display_name, PATHINFO_FILENAME));
                            $display_name = ucwords($display_name);
                        ?>
                            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden image-card" 
                                 onclick="openModal('<?= $image_path; ?>', '<?= htmlspecialchars($display_name); ?>')">
                                <div class="image-wrapper">
                                    <img src="<?= $image_path; ?>" alt="<?= htmlspecialchars($display_name); ?>" loading="lazy">
                                    <div class="image-overlay">
                                        <div class="overlay-text">Click to view</div>
                                    </div>
                                </div>
                                <div class="p-4">
                                    <h3 class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($display_name); ?></h3>
                                    <p class="text-xs text-gray-500 mt-1"><?= strtoupper(pathinfo($image, PATHINFO_EXTENSION)); ?> • <?= $image_size_formatted; ?></p>
                                    <div class="mt-3 flex justify-center">
                                        <button class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-12 text-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-images text-gray-400 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Images Found</h3>
                    <p class="text-gray-500 mb-6">No images were found in the uploads directory.</p>
                    <button class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-upload mr-2"></i>Upload Your First Image
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for full-size image view -->
    <div id="imageModal" class="modal">
        <span class="close" onclick="closeModal()">&times;</span>
        <img class="modal-content" id="modalImage">
        <div id="caption" style="text-align: center; color: white; padding: 20px; font-size: 18px;"></div>
    </div>

    <script>
        // Scroll animation for image cards
        function initScrollAnimation() {
            const imageCards = document.querySelectorAll('.image-card');
            
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-in');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            imageCards.forEach(card => {
                observer.observe(card);
            });
        }

        // Initialize animations when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initScrollAnimation();
        });

        // Modal functionality
        function openModal(imageSrc, imageName) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            const caption = document.getElementById('caption');
            
            modal.style.display = 'block';
            modalImg.src = imageSrc;
            caption.innerHTML = imageName;
        }

        function closeModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        // Close modal when clicking outside the image
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }

        // Keyboard navigation for modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>