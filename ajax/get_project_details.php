<?php
// File: ajax/get_project_details.php (FIXED VERSION)

// Prevent any HTML output before JSON
ob_start();

// Set proper headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Clear any previous output
ob_clean();

// Don't display PHP errors as HTML - log them instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit;
    }
    
    $projectId = $input['project_id'] ?? '';
    $sectionId = $input['section_id'] ?? '';

    if (empty($projectId) || empty($sectionId)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Missing required parameters',
            'received' => ['project_id' => $projectId, 'section_id' => $sectionId]
        ]);
        exit;
    }

    // Get the specific project details
    $project = getProjectDetailsById($projectId, $sectionId);
    
    if (!$project) {
        echo json_encode(['success' => false, 'message' => 'Project not found']);
        exit;
    }
    
    // Generate the HTML for modal content
    $html = generateProjectModalHTML($project);
    
    echo json_encode([
        'success' => true,
        'project' => [
            'id' => $project['id'],
            'name' => $project['name']
        ],
        'html' => $html
    ]);
    
} catch (Exception $e) {
    // Log error instead of displaying
    error_log("AJAX Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred'
    ]);
}

function getProjectDetailsById($projectId, $sectionId) {
    // You need to replace this with YOUR actual method of loading sections
    $sections = loadSectionsData();
    
    foreach ($sections as $section) {
        if ($section['id'] == $sectionId && !empty($section['content'])) {
            $projects = json_decode($section['content'], true);
            
            if (is_array($projects)) {
                foreach ($projects as $project) {
                    if ($project['id'] == $projectId) {
                        return $project;
                    }
                }
            }
        }
    }
    
    return null;
}

// IMPORTANT: Replace this function with how YOU load your sections data
function loadSectionsData() {
    // METHOD 1: If you store sections in a file relative to your main page
    $sectionsFile = '../sections.json'; // Adjust path
    if (file_exists($sectionsFile)) {
        $content = file_get_contents($sectionsFile);
        return json_decode($content, true) ?: [];
    }
    
    // METHOD 2: If you use sessions
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['sections'])) {
        return $_SESSION['sections'];
    }
    
    // METHOD 3: If you have a config file or database connection
    /*
    require_once '../config.php';
    // Your database or file loading code here
    */
    
    // METHOD 4: If you store data in a specific way, adapt this:
    /*
    // Example: If you have a CMS file that contains the sections
    $cmsFile = '../your-cms-data.php';
    if (file_exists($cmsFile)) {
        include $cmsFile;
        return $sectionsData ?? []; // Replace with your variable name
    }
    */
    
    return [];
}

function generateProjectModalHTML($project) {
    // Start output buffering to capture HTML
    ob_start();
    ?>
    
    <!-- Project Images Gallery -->
    <?php if (!empty($project['images'])): ?>
        <div class="mb-4">
            <h4 class="text-sm font-semibold mb-2 text-gray-700">Project Gallery</h4>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                <?php foreach (array_slice($project['images'], 0, 6) as $index => $image): ?>
                    <div class="relative group cursor-pointer" onclick="openImageLightbox('<?= htmlspecialchars(getProjectImageUrl($image)) ?>')">
                        <img src="<?= htmlspecialchars(getProjectImageUrl($image)) ?>" 
                             alt="Project image <?= $index + 1 ?>" 
                             class="w-full h-20 object-cover rounded shadow-sm hover:shadow-md transition-shadow"
                             loading="lazy">
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 rounded flex items-center justify-center">
                            <i class="fas fa-search-plus text-white text-sm opacity-0 group-hover:opacity-100 transition-opacity"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (count($project['images']) > 6): ?>
                    <div class="flex items-center justify-center h-20 bg-gray-100 rounded text-gray-500 text-xs">
                        +<?= count($project['images']) - 6 ?> more
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="space-y-4">
        <!-- Description -->
        <?php if (!empty($project['description'])): ?>
            <div>
                <h4 class="text-sm font-semibold mb-2 flex items-center text-blue-600">
                    <i class="fas fa-file-alt mr-2"></i>Description
                </h4>
                <p class="text-sm text-gray-700 leading-relaxed bg-gray-50 p-3 rounded">
                    <?= htmlspecialchars($project['description']) ?>
                </p>
            </div>
        <?php endif; ?>
        
        <!-- Technologies -->
        <?php if (!empty($project['technologies'])): ?>
            <div>
                <h4 class="text-sm font-semibold mb-2 flex items-center text-purple-600">
                    <i class="fas fa-cogs mr-2"></i>Technologies
                </h4>
                <div class="flex flex-wrap gap-1">
                    <?php foreach (explode(',', $project['technologies']) as $tech): ?>
                        <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs font-medium">
                            <?= htmlspecialchars(trim($tech)) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Project Information -->
        <div>
            <h4 class="text-sm font-semibold mb-2 flex items-center text-gray-600">
                <i class="fas fa-info-circle mr-2"></i>Project Information
            </h4>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <?php
                $projectInfo = [
                    ['label' => 'Status', 'value' => $project['status'] ?? '', 'icon' => 'fas fa-flag'],
                    ['label' => 'Category', 'value' => $project['model'] ?? '', 'icon' => 'fas fa-tag'],
                    ['label' => 'Client', 'value' => $project['client'] ?? '', 'icon' => 'fas fa-building'],
                    ['label' => 'Location', 'value' => $project['location'] ?? '', 'icon' => 'fas fa-map-marker-alt'],
                    ['label' => 'Value', 'value' => $project['value'] ?? '', 'icon' => 'fas fa-dollar-sign'],
                    ['label' => 'Architects', 'value' => $project['architects'] ?? '', 'icon' => 'fas fa-drafting-compass']
                ];
                
                foreach ($projectInfo as $info):
                    if (!empty($info['value'])):
                ?>
                    <div class="bg-gray-50 p-2 rounded">
                        <div class="flex items-center text-gray-500 mb-1">
                            <i class="<?= $info['icon'] ?> mr-1"></i>
                            <span class="font-medium"><?= $info['label'] ?></span>
                        </div>
                        <div class="text-gray-900 font-medium"><?= htmlspecialchars($info['value']) ?></div>
                    </div>
                <?php
                    endif;
                endforeach;
                ?>
            </div>
        </div>
        
        <!-- Our Role -->
        <?php if (!empty($project['role'])): ?>
            <div>
                <h4 class="text-sm font-semibold mb-2 flex items-center text-green-600">
                    <i class="fas fa-user-tie mr-2"></i>Our Role
                </h4>
                <p class="text-sm text-green-700 bg-green-50 p-2 rounded font-medium">
                    <?= htmlspecialchars($project['role']) ?>
                </p>
            </div>
        <?php endif; ?>
        
        <!-- External Links -->
        <?php if (!empty($project['githubUrl']) || !empty($project['demoUrl'])): ?>
            <div>
                <h4 class="text-sm font-semibold mb-2 flex items-center text-gray-600">
                    <i class="fas fa-external-link-alt mr-2"></i>Links
                </h4>
                <div class="flex gap-2">
                    <?php if (!empty($project['githubUrl'])): ?>
                        <a href="<?= htmlspecialchars($project['githubUrl']) ?>" target="_blank" 
                           class="flex-1 flex items-center justify-center px-3 py-2 bg-gray-800 text-white rounded text-xs hover:bg-gray-700 transition-colors">
                            <i class="fab fa-github mr-1"></i>GitHub
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['demoUrl'])): ?>
                        <a href="<?= htmlspecialchars($project['demoUrl']) ?>" target="_blank" 
                           class="flex-1 flex items-center justify-center px-3 py-2 bg-blue-600 text-white rounded text-xs hover:bg-blue-700 transition-colors">
                            <i class="fas fa-globe mr-1"></i>Live Demo
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    function openImageLightbox(imageUrl) {
        let lightbox = document.getElementById('imageLightbox');
        if (!lightbox) {
            lightbox = document.createElement('div');
            lightbox.id = 'imageLightbox';
            lightbox.className = 'fixed inset-0 bg-black bg-opacity-95 z-[70] hidden flex items-center justify-center p-4';
            lightbox.innerHTML = `
                <div class="relative max-w-full max-h-full">
                    <img id="lightboxImage" src="" alt="Project image" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl">
                    <button onclick="closeLightbox()" class="absolute -top-12 right-0 text-white bg-black bg-opacity-50 rounded-full w-10 h-10 flex items-center justify-center hover:bg-opacity-75 transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            document.body.appendChild(lightbox);
        }
        
        document.getElementById('lightboxImage').src = imageUrl;
        lightbox.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    function closeLightbox() {
        const lightbox = document.getElementById('imageLightbox');
        if (lightbox) {
            lightbox.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }
    </script>
    
    <?php
    return ob_get_clean();
}

function getProjectImageUrl($imagePath) {
    if (empty($imagePath)) return '';
    
    if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
        return $imagePath;
    }
    
    if (strpos($imagePath, 'data:') === 0) {
        return $imagePath;
    }
    
    // Build the correct URL - adjust as needed for your setup
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . '://' . $host;
    
    if (strpos($imagePath, 'uploads/') === 0) {
        return $baseUrl . '/' . $imagePath;
    }
    
    return $baseUrl . '/uploads/' . $imagePath;
}
?>