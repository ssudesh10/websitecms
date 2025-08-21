<?php
require_once '../config.php';
require_once '../function.php';
require_once '../upload-functions.php';
requireLogin();

$images = getUploadedImages();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Gallery - Admin</title>
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
     <link href="../public/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Custom Opacity Classes */
        .opacity-0 {
            opacity: 0;
        }
        
        .opacity-50 {
            opacity: 0.5;
        }
        
        .opacity-75 {
            opacity: 0.75;
        }
        
        .opacity-90 {
            opacity: 0.9;
        }
        
        .opacity-100 {
            opacity: 1;
        }
        
        /* Hover effects */
        .hover-opacity-90:hover {
            opacity: 0.9;
        }
        
        .group:hover .group-hover-opacity-75 {
            opacity: 0.75;
        }
        
        .group:hover .group-hover-opacity-100 {
            opacity: 1;
        }

        /* Enhanced Hover Button Styling */
        .hover-overlay {
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(2px);
            transition: all 0.3s ease;
        }

        .hover-buttons-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            border: 2px solid rgba(255, 255, 255, 0.8);
        }

        .hover-button {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            border: 3px solid rgba(255, 255, 255, 0.9);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .hover-button:hover {
            transform: scale(1.15);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5);
            border-color: white;
        }

        .hover-button i {
            color: white;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        }

        /* Color specific hover effects */
        .btn-blue {
            background: linear-gradient(135deg, #3b82f6, #1e40af);
        }

        .btn-blue:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }

        .btn-green {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .btn-green:hover {
            background: linear-gradient(135deg, #059669, #047857);
        }

        .btn-red {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .btn-red:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        /* Animation for smooth appearance */
        .hover-buttons-container {
            transform: scale(0.8);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .group:hover .hover-buttons-container {
            transform: scale(1);
        }

        /* Modal Overlay Custom Opacity */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-width: 28rem;
            width: 100%;
            margin: 1rem;
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }

        .modal-overlay.show .modal-content {
            transform: scale(1);
        }

        /* Success Message Custom Opacity */
        .success-message {
            position: fixed;
            top: 1rem;
            right: 1rem;
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            z-index: 50;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            visibility: hidden;
        }

        .success-message.show {
            opacity: 1;
            transform: translateY(0);
            visibility: visible;
        }

        /* Hide class for elements */
        .hidden {
            display: none !important;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Admin Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900">Image Gallery</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="openUploadModal()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-upload mr-2"></i>Upload Image
                    </button>
                    <a href="logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if (empty($images)): ?>
            <div class="bg-white rounded-lg shadow p-8 text-center">
                <i class="fas fa-images text-gray-400 text-6xl mb-4"></i>
                <h3 class="text-xl font-medium text-gray-900 mb-2">No images uploaded yet</h3>
                <p class="text-gray-600 mb-6">Start building your image library by uploading your first image.</p>
                <button onclick="openUploadModal()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-upload mr-2"></i>Upload First Image
                </button>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Uploaded Images</h2>
                    <p class="text-gray-600 mt-1">Manage your uploaded images. Hover over any image to see action buttons.</p>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        <?php foreach ($images as $image): ?>
                            <div class="relative group bg-gray-100 rounded-lg overflow-hidden aspect-square">
                                <img src="<?= htmlspecialchars($image['file_path']) ?>" 
                                     alt="<?= htmlspecialchars($image['original_name']) ?>"
                                     class="w-full h-full object-cover cursor-pointer hover-opacity-90 transition duration-200"
                                     onclick="copyImageUrl('<?= htmlspecialchars($image['file_path']) ?>')">
                                
                                <!-- Enhanced Hover Overlay -->
                                <div class="absolute inset-0 opacity-0 group-hover-opacity-100 transition-all duration-300 flex items-center justify-center hover-overlay">
                                    <div class="hover-buttons-container flex space-x-4">
                                        <button onclick="copyImageUrl('<?= htmlspecialchars($image['file_path']) ?>')" 
                                                class="hover-button btn-blue"
                                                title="Copy URL">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <button onclick="viewImageDetails(<?= $image['id'] ?>)" 
                                                class="hover-button btn-green"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="deleteImage(<?= $image['id'] ?>)" 
                                                class="hover-button btn-red"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-2">
                                    <p class="text-white text-xs truncate"><?= htmlspecialchars($image['original_name']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Upload Modal with Custom Opacity -->
    <div id="uploadModal" class="modal-overlay">
        <div class="modal-content">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Upload Image</h3>
            </div>
            <div class="p-6">
                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Image</label>
                        <input type="file" id="imageInput" name="image" accept="image/*" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-sm text-gray-500 mt-1">Supported formats: JPG, PNG, GIF, WebP, SVG (max 5MB)</p>
                    </div>
                    
                    <div id="imagePreview" class="hidden mb-4">
                        <img id="previewImg" class="w-full h-48 object-cover rounded-lg border">
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeUploadModal()" 
                                class="px-4 py-2 text-gray-700 bg-gray-300 rounded hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" id="uploadBtn"
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-upload mr-2"></i>Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Message with Custom Opacity -->
    <div id="successMessage" class="success-message">
        <i class="fas fa-check-circle mr-2"></i><span id="successText"></span>
    </div>

    <script>
        function openUploadModal() {
            const modal = document.getElementById('uploadModal');
            modal.classList.add('show');
        }
        
        function closeUploadModal() {
            const modal = document.getElementById('uploadModal');
            modal.classList.remove('show');
            document.getElementById('uploadForm').reset();
            document.getElementById('imagePreview').classList.add('hidden');
        }
        
        function copyImageUrl(url) {
            navigator.clipboard.writeText(url).then(() => {
                showSuccess('Image URL copied to clipboard!');
            });
        }
        
        function deleteImage(imageId) {
            if (confirm('Are you sure you want to delete this image?')) {
                fetch('delete-image.php?id=' + imageId, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting image: ' + data.error);
                    }
                });
            }
        }
        
        function viewImageDetails(imageId) {
            // Implement image details view if needed
            alert('Image details - ID: ' + imageId);
        }
        
        function showSuccess(message) {
            document.getElementById('successText').textContent = message;
            const successDiv = document.getElementById('successMessage');
            successDiv.classList.add('show');
            setTimeout(() => {
                successDiv.classList.remove('show');
            }, 3000);
        }
        
        // Image preview
        document.getElementById('imageInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Upload form submission
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const uploadBtn = document.getElementById('uploadBtn');
            
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...';
            
            fetch('upload-image.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Image uploaded successfully!');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert('Upload failed: ' + data.error);
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload';
                }
            })
            .catch(error => {
                alert('Upload failed: ' + error);
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload';
            });
        });

        // Close modal when clicking outside
        document.getElementById('uploadModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUploadModal();
            }
        });
    </script>
</body>
</html>