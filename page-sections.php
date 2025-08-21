<?php
// Enhanced section renderer for all section types

function renderSection($section, $isAdmin = false) {
    // Get theme configuration
    $themeConfig = getThemeConfig();
    
    $bgColor = $section['background_color'] ?? $themeConfig['primary_color'] ?? '#3b82f6';
    $textColor = $section['text_color'] ?? '#ffffff';
    
    // Admin edit button wrapper
    if ($isAdmin) {
        echo '<div class="relative group">';
        echo '<div class="absolute top-4 right-4 z-40 opacity-80 hover:opacity-100 transition-opacity duration-200">';
        echo '<a href="' . BASE_URL . '/admin/edit-section.php?id=' . $section['id'] . '" ';
        echo 'class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 transition duration-200 pointer-events-auto shadow-md admin-edit-btn" style="background-color: ' . $themeConfig['primary_color'] . ';">';
        echo '<i class="fas fa-edit mr-1"></i>Edit</a></div>';
    }
    
    switch ($section['section_type']) {
        case 'hero':
            renderHeroSection($section, $bgColor, $textColor, $themeConfig);
            break;
        case 'content':
            renderContentSection($section, $bgColor, $textColor, $themeConfig);
            break;
        case 'features':
            renderFeaturesSection($section, $bgColor, $textColor, $themeConfig);
            break;
        case 'stats':
            renderStatsSection($section, $bgColor, $textColor, $themeConfig);
            break;
        case 'testimonials':
            renderTestimonialsSection($section, $bgColor, $textColor, $themeConfig);
            break;
        case 'pricing':
            renderPricingSection($section, $bgColor, $textColor, $themeConfig);
            break;
        case 'team':
            renderTeamSection($section, $bgColor, $textColor, $themeConfig);
            break;
        case 'slider':
        case 'image_slider':
            renderEnhancedSliderSection($section, $bgColor, $textColor, $themeConfig);
            break;
        case 'banner':
            renderBannerSection($section, $themeConfig);
            break;
        case 'gallery':
            renderGallerySection($section, $bgColor, $textColor, $themeConfig);
            break;
        case 'video':
            renderVideoSection($section, $bgColor, $textColor, $themeConfig);
            break;
        case 'timeline':
            renderTimelineSection($section, $bgColor, $textColor, $themeConfig);
            break;
        case 'faq':
            renderFaqSection($section, $bgColor, $textColor, $themeConfig);
            break;
        case 'newsletter':
            renderNewsletterSection($section, $bgColor, $textColor, $themeConfig);
            break;
        case 'cta':
            renderCtaSection($section, $themeConfig);
            break;
        case 'contact_form':
            renderContactFormSection($section, $bgColor, $textColor, $themeConfig);
            break;
        case 'projects':
            renderProjectSection($section, $bgColor, $textColor, $themeConfig);
            break;
        case 'textwithimage':
            renderTextWithImageSection($section, $bgColor, $textColor, $themeConfig);
            break;            
        case 'custom':
            renderCustomSection($section, $bgColor, $textColor, $themeConfig);
            break;
    }
    
    if ($isAdmin) {
        echo '</div>';
    }
}


function renderHeroSection($section) {
    // Get styling configuration
    $backgroundType = $section['background_type'] ?? 'gradient';
    $bgColor = $section['background_color'] ?? '#60a48e';
    $textColor = $section['text_color'] ?? '#ffffff';
    
    // FIXED: Get background styling with iOS-safe modifications
    $backgroundStyle = getIOSSafeBackgroundStyle($section);
    $sectionTextColor = getSectionTextColor($section);
    
    // FIXED: Better text color handling for image backgrounds
    $finalTextColor = $textColor; // Start with the specified text color
    
    // Only use getSectionTextColor if no text_color is explicitly set
    if (!isset($section['text_color']) && function_exists('getSectionTextColor')) {
        $finalTextColor = $sectionTextColor;
    } elseif (isset($section['text_color'])) {
        // Use the explicitly set text color
        $finalTextColor = $section['text_color'];
    }
    
    // For image backgrounds, provide better contrast options
    if ($backgroundType === 'image') {
        // If no text color is set, use white as default for image backgrounds
        if (!isset($section['text_color'])) {
            $finalTextColor = '#ffffff';
        }
        // But allow override if explicitly set
        if (isset($section['text_color'])) {
            $finalTextColor = $section['text_color'];
        }
    }
    
    // Generate unique section ID to scope CSS
    $sectionId = 'hero-section-' . uniqid();
    
    // Default gradient fallback if no background is set
    $defaultGradient = '';
    if ($backgroundType === 'gradient' && empty($backgroundStyle)) {
        $defaultGradient = 'bg-gradient-to-r from-blue-600 to-purple-600';
    }    
    ?>
    
    <section id="<?= $sectionId ?>" class="hero-section <?= $defaultGradient ?> py-20 relative min-h-[600px] flex items-center overflow-hidden transition-all duration-500" 
             style="<?= $backgroundStyle ?>">
        
        <!-- Enhanced Background Overlay for Images -->
        <?php if ($backgroundType === 'image'): ?>
            <!-- Adjustable overlay for better text readability on images -->
            <?php 
            $overlayOpacity = $section['overlay_opacity'] ?? 0.4;
            $overlayColor = $section['overlay_color'] ?? '#000000';
            ?>
            <div class="absolute inset-0 pointer-events-none" 
                 style="background-color: <?= htmlspecialchars($overlayColor) ?>; opacity: <?= $overlayOpacity ?>;"></div>
            
            <!-- Optional Pattern Overlay for Images -->
            <div class="absolute inset-0 opacity-5 pointer-events-none">
                <div style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.8) 1px, transparent 0); background-size: 30px 30px; width: 100%; height: 100%;"></div>
            </div>
        <?php else: ?>
            <!-- Standard Pattern Overlay for Solid/Gradient -->
            <div class="absolute inset-0 opacity-10 pointer-events-none">
                <div style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.3) 1px, transparent 0); background-size: 20px 20px; width: 100%; height: 100%;"></div>
            </div>
        <?php endif; ?>
        
        <!-- Content Container -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10 w-full">
            <!-- Title -->
            <h1 class="hero-title text-4xl md:text-6xl font-bold mb-6 transition-colors duration-300 <?= $backgroundType === 'image' ? 'drop-shadow-lg' : '' ?>" 
                style="color: <?= htmlspecialchars($finalTextColor) ?>;">
                <?= htmlspecialchars($section['title']) ?>
            </h1>
            
            <!-- Subtitle -->
            <?php if (!empty($section['subtitle'])): ?>
                <p class="hero-subtitle text-xl md:text-2xl mb-4 opacity-90 transition-colors duration-300 <?= $backgroundType === 'image' ? 'drop-shadow-md' : '' ?>" 
                   style="color: <?= htmlspecialchars($finalTextColor) ?>;">
                    <?= htmlspecialchars($section['subtitle']) ?>
                </p>
            <?php endif; ?>
            
            <!-- Content -->
            <p class="hero-content text-lg md:text-xl mb-8 opacity-80 transition-colors duration-300 <?= $backgroundType === 'image' ? 'drop-shadow-md' : '' ?> max-w-3xl mx-auto" 
               style="color: <?= htmlspecialchars($finalTextColor) ?>;">
                <?= htmlspecialchars($section['content']) ?>
            </p>
            
            <!-- CTA Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                <!-- Primary Button -->
                <?php if (!empty($section['button_text']) && !empty($section['button_url'])): ?>
                    <?php
                    $primaryBgColor = $section['button_bg_color'] ?? '#ffffff';
                    $primaryTextColor = $section['button_text_color'] ?? '#3b82f6';
                    ?>
                    <a href="<?= htmlspecialchars($section['button_url']) ?>" 
                       class="inline-block px-8 py-3 rounded-lg font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl hero-primary-button group relative overflow-hidden <?= $backgroundType === 'image' ? 'shadow-2xl' : '' ?>"
                       style="background-color: <?= htmlspecialchars($primaryBgColor) ?>; color: <?= htmlspecialchars($primaryTextColor) ?>;">
                        <!-- Button shine effect -->
                        <span class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-0 group-hover:opacity-20 transition-opacity duration-500 transform -skew-x-12 -translate-x-full group-hover:translate-x-full"></span>
                        <span class="relative z-10"><?= htmlspecialchars($section['button_text']) ?></span>
                    </a>
                <?php else: ?>
                    <button class="inline-block px-8 py-3 rounded-lg font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl hero-primary-button group relative overflow-hidden <?= $backgroundType === 'image' ? 'shadow-2xl' : '' ?>"
                            style="background-color: #ffffff; color: #3b82f6;">
                        <!-- Button shine effect -->
                        <span class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-0 group-hover:opacity-20 transition-opacity duration-500 transform -skew-x-12 -translate-x-full group-hover:translate-x-full"></span>
                        <span class="relative z-10">Get Started</span>
                    </button>
                <?php endif; ?>
                
                <!-- Secondary Button -->
                <?php if (!empty($section['button_text_2'])): ?>
                    <?php
                    $secondaryBgColor = $section['button_bg_color_2'] ?? 'transparent';
                    $secondaryTextColor = $section['button_text_color_2'] ?? $finalTextColor;
                    $buttonStyle2 = $section['button_style_2'] ?? 'outline';
                    $secondaryButtonUrl = $section['button_url_2'] ?? '#';
                    
                    // Apply button style
                    $secondaryStyles = '';
                    $secondaryClasses = 'inline-block px-8 py-3 rounded-lg font-semibold transition-all duration-300 transform hover:scale-105 hero-secondary-button group relative overflow-hidden';
                    
                    // Add extra shadow for image backgrounds
                    if ($backgroundType === 'image') {
                        $secondaryClasses .= ' shadow-2xl';
                    }
                    
                    switch ($buttonStyle2) {
                        case 'outline':
                            $secondaryStyles = "background-color: transparent; color: " . htmlspecialchars($finalTextColor) . "; border: 2px solid " . htmlspecialchars($finalTextColor) . ";";
                            break;
                        case 'ghost':
                            $secondaryStyles = "background-color: transparent; color: " . htmlspecialchars($finalTextColor) . "; border: none;";
                            break;
                        default: // solid
                            $secondaryStyles = "background-color: " . htmlspecialchars($secondaryBgColor) . "; color: " . htmlspecialchars($secondaryTextColor) . ";";
                            break;
                    }
                    ?>
                    <a href="<?= htmlspecialchars($secondaryButtonUrl) ?>" 
                       class="<?= $secondaryClasses ?>"
                       style="<?= $secondaryStyles ?>"
                       onmouseover="handleHeroSecondaryButtonHover(this, '<?= $buttonStyle2 ?>', '<?= htmlspecialchars($finalTextColor) ?>', '<?= htmlspecialchars($secondaryTextColor) ?>')"
                       onmouseout="handleHeroSecondaryButtonOut(this, '<?= $buttonStyle2 ?>', '<?= htmlspecialchars($finalTextColor) ?>', '<?= htmlspecialchars($secondaryTextColor) ?>')">
                        <!-- Button shine effect -->
                        <span class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-0 group-hover:opacity-20 transition-opacity duration-500 transform -skew-x-12 -translate-x-full group-hover:translate-x-full"></span>
                        <span class="relative z-10"><?= htmlspecialchars($section['button_text_2']) ?></span>
                    </a>
                <?php else: ?>
                    <!-- Default secondary button -->
                    <button class="border-2 px-8 py-3 rounded-lg font-semibold transition-all duration-300 transform hover:scale-105 hero-secondary-button group relative overflow-hidden <?= $backgroundType === 'image' ? 'shadow-2xl' : '' ?>"
                            style="border-color: <?= htmlspecialchars($finalTextColor) ?>; color: <?= htmlspecialchars($finalTextColor) ?>; background-color: transparent;"
                            onmouseover="this.style.backgroundColor='<?= htmlspecialchars($finalTextColor) ?>'; this.style.color='<?= $backgroundType === 'image' ? '#000000' : $bgColor ?>';"
                            onmouseout="this.style.backgroundColor='transparent'; this.style.color='<?= htmlspecialchars($finalTextColor) ?>';">
                        <!-- Button shine effect -->
                        <span class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-0 group-hover:opacity-20 transition-opacity duration-500 transform -skew-x-12 -translate-x-full group-hover:translate-x-full"></span>
                        <span class="relative z-10">Learn More</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Scroll indicator (optional) -->
        <?php if ($backgroundType === 'image'): ?>
            <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce" style="color: <?= htmlspecialchars($finalTextColor) ?>;">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                </svg>
            </div>
        <?php endif; ?>
    </section>
    
    <!-- Preload background image for better performance -->
    <?php if ($backgroundType === 'image' && !empty($section['image_url'])): ?>
        <link rel="preload" as="image" href="<?= htmlspecialchars($section['image_url']) ?>">
    <?php endif; ?>
    
    <!-- SCOPED CSS - Only affects this Hero section with iOS fixes -->
    <style>
        /* iOS-specific fixes */
        #<?= $sectionId ?> {
            <?php if ($backgroundType === 'image'): ?>
            /* Force iOS-safe background properties */
            background-attachment: scroll !important;
            -webkit-background-size: cover !important;
            background-size: cover !important;
            background-position: center !important;
            background-repeat: no-repeat !important;
            
            /* iOS performance optimizations */
            -webkit-transform: translateZ(0);
            transform: translateZ(0);
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
            will-change: transform;
            <?php endif; ?>
        }
        
        /* iOS Safari specific fixes */
        @supports (-webkit-touch-callout: none) {
            #<?= $sectionId ?> {
                background-attachment: scroll !important;
                -webkit-background-size: cover !important;
            }
        }
        
        /* Scope all styles to the specific Hero section */
        #<?= $sectionId ?> .hero-primary-button {
            position: relative;
            background-image: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            background-size: 200% 100%;
            background-position: 200% 0;
            transition: all 0.3s ease;
        }
        
        #<?= $sectionId ?> .hero-primary-button:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            background-position: -200% 0;
        }
        
        #<?= $sectionId ?> .hero-primary-button:active {
            transform: translateY(0) scale(1.02);
            transition: transform 0.1s ease;
        }
        
        /* Secondary Button Animations */
        #<?= $sectionId ?> .hero-secondary-button {
            position: relative;
            transition: all 0.3s ease;
        }
        
        #<?= $sectionId ?> .hero-secondary-button:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        #<?= $sectionId ?> .hero-secondary-button:active {
            transform: translateY(0) scale(1.02);
            transition: transform 0.1s ease;
        }
        
        /* Enhanced shadow effects for image backgrounds - SCOPED */
        <?php if ($backgroundType === 'image'): ?>
        #<?= $sectionId ?> .hero-primary-button,
        #<?= $sectionId ?> .hero-secondary-button {
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        
        #<?= $sectionId ?> .hero-primary-button:hover,
        #<?= $sectionId ?> .hero-secondary-button:hover {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }
        
        /* Text shadow for better readability on images - SCOPED */
        #<?= $sectionId ?> .hero-title,
        #<?= $sectionId ?> .hero-subtitle,
        #<?= $sectionId ?> .hero-content {
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
        }
        <?php endif; ?>
        
        /* Pulse animation for Hero section */
        @keyframes heroPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        
        #<?= $sectionId ?> .hero-primary-button:hover {
            animation: heroPulse 2s infinite;
        }
        
        /* Responsive adjustments - SCOPED */
        @media (max-width: 768px) {
            #<?= $sectionId ?> .hero-primary-button,
            #<?= $sectionId ?> .hero-secondary-button {
                width: 100%;
                text-align: center;
                margin-bottom: 1rem;
            }
            
            /* Adjust text sizes on mobile - SCOPED */
            #<?= $sectionId ?> .hero-title {
                font-size: 2.5rem !important;
            }
            
            #<?= $sectionId ?> .hero-subtitle,
            #<?= $sectionId ?> .hero-content {
                font-size: 1rem !important;
            }
        }
        
        /* Gradient animation for gradient backgrounds - SCOPED */
        <?php if ($backgroundType === 'gradient'): ?>
        #<?= $sectionId ?> {
            background-size: 400% 400%;
            animation: gradientShift 8s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        <?php endif; ?>
        
        /* Dark mode adjustments - SCOPED */
        @media (prefers-color-scheme: dark) {
            #<?= $sectionId ?> .hero-primary-button,
            #<?= $sectionId ?> .hero-secondary-button {
                box-shadow: 0 4px 15px rgba(255, 255, 255, 0.1);
            }
            
            #<?= $sectionId ?> .hero-primary-button:hover,
            #<?= $sectionId ?> .hero-secondary-button:hover {
                box-shadow: 0 8px 25px rgba(255, 255, 255, 0.2);
            }
        }
        
        /* Animation for content reveal */
        #<?= $sectionId ?> .animate-fade-in {
            animation: fadeInUp 1s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
    
    <!-- JavaScript for Enhanced Interactions -->
    <script>
        // Enhanced secondary button hover effects
        function handleHeroSecondaryButtonHover(button, style, sectionTextColor, textColor) {
            switch(style) {
                case 'outline':
                    button.style.backgroundColor = sectionTextColor;
                    button.style.color = textColor;
                    button.style.borderColor = sectionTextColor;
                    break;
                case 'ghost':
                    button.style.backgroundColor = sectionTextColor;
                    button.style.color = textColor;
                    break;
                default: // solid
                    button.style.opacity = '0.9';
                    button.style.filter = 'brightness(1.1)';
                    break;
            }
        }
        
        function handleHeroSecondaryButtonOut(button, style, sectionTextColor, textColor) {
            switch(style) {
                case 'outline':
                    button.style.backgroundColor = 'transparent';
                    button.style.color = sectionTextColor;
                    button.style.borderColor = sectionTextColor;
                    break;
                case 'ghost':
                    button.style.backgroundColor = 'transparent';
                    button.style.color = sectionTextColor;
                    break;
                default: // solid
                    button.style.opacity = '1';
                    button.style.filter = 'brightness(1)';
                    break;
            }
        }
        
        // Add click ripple effect
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('#<?= $sectionId ?> .hero-primary-button, #<?= $sectionId ?> .hero-secondary-button');
            
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                        background: rgba(255, 255, 255, 0.3);
                        border-radius: 50%;
                        transform: scale(0);
                        animation: ripple 0.6s ease-out;
                        pointer-events: none;
                        z-index: 1;
                    `;
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
        });
    </script>
    
    <!-- Add ripple animation CSS -->
    <style>
        @keyframes ripple {
            to {
                transform: scale(2);
                opacity: 0;
            }
        }
    </style>
    
    <?php
}

// iOS-Safe Background Style Function - Add this function
function getIOSSafeBackgroundStyle($section) {
    $backgroundType = $section['background_type'] ?? 'solid';
    
    switch ($backgroundType) {
        case 'gradient':
            $direction = $section['gradient_direction'] ?? 'to right';
            $start = $section['gradient_start'] ?? '#3b82f6';
            $end = $section['gradient_end'] ?? '#8b5cf6';
            return "background: linear-gradient($direction, $start, $end);";
            
        case 'image':
            $imageUrl = $section['image_url'] ?? '';
            if (!$imageUrl) {
                return "background-color: #f3f4f6;";
            }
            
            $position = $section['image_position'] ?? 'center';
            $size = $section['image_size'] ?? 'cover';
            $overlay = $section['overlay_color'] ?? '';
            
            $style = "background-image: url('" . htmlspecialchars($imageUrl) . "'); ";
            $style .= "background-position: $position; ";
            $style .= "background-size: $size; ";
            $style .= "background-repeat: no-repeat; ";
            // REMOVED: background-attachment: fixed; (causes iOS issues)
            $style .= "background-attachment: scroll; "; // iOS-safe
            
            if ($overlay) {
                if (strpos($overlay, 'rgba') === 0 || strpos($overlay, 'rgb') === 0) {
                    $style .= "background-color: $overlay; ";
                    $style .= "background-blend-mode: overlay; ";
                } else if (strpos($overlay, '#') === 0) {
                    $style .= "background-color: $overlay; ";
                    $style .= "background-blend-mode: overlay; ";
                }
            }
            
            return $style;
            
        default: // solid
            $bgColor = $section['background_color'] ?? '#60a48e';
            return "background-color: $bgColor;";
    }
}

function renderContentSection($section, $bgColor, $textColor) {
    // Get styling configuration
    $backgroundType = $section['background_type'] ?? 'solid';
    
    // Get background styling using the same helper functions as CTA and Hero
    $backgroundStyle = getSectionBackgroundStyle($section);
    
    // Modified text color logic - use provided textColor or section-specific color
    $sectionTextColor = $textColor; // Use the passed textColor parameter first
    
    // If no textColor provided, check if section has custom text color
    if (!$sectionTextColor && isset($section['text_color'])) {
        $sectionTextColor = $section['text_color'];
    }
    
    // If still no color, use getSectionTextColor() as fallback
    if (!$sectionTextColor) {
        $sectionTextColor = getSectionTextColor($section);
    }
    
    // Override: if background type is image and no custom color specified, 
    // allow manual override instead of forcing white
    if ($backgroundType === 'image' && !isset($section['text_color']) && !$textColor) {
        // You can customize this default behavior
        $sectionTextColor = $section['image_text_color'] ?? '#ffffff'; // Allow override via image_text_color
    }
    
    // Check if content needs to be split (250+ words)
    $content = $section['content'];
    $wordCount = str_word_count($content);
    $shouldSplit = $wordCount >= 250;
    
    if ($shouldSplit) {
        // Split content into two roughly equal parts for side-by-side layout
        $words = explode(' ', $content);
        $midPoint = ceil(count($words) / 2);
        
        // Find the best break point (end of sentence near midpoint)
        $breakPoint = $midPoint;
        for ($i = $midPoint - 10; $i <= $midPoint + 10; $i++) {
            if ($i < count($words) && isset($words[$i]) && 
                (substr(trim($words[$i]), -1) === '.' || 
                 substr(trim($words[$i]), -1) === '!' || 
                 substr(trim($words[$i]), -1) === '?')) {
                $breakPoint = $i + 1;
                break;
            }
        }
        
        $firstPart = implode(' ', array_slice($words, 0, $breakPoint));
        $secondPart = implode(' ', array_slice($words, $breakPoint));
        
        // Render single section with two-column layout
        renderSingleContentSection($section, $sectionTextColor, $backgroundStyle, $backgroundType, $content, 1, true, $firstPart, $secondPart);
        
    } else {
        // Render as single section with centered content
        renderSingleContentSection($section, $sectionTextColor, $backgroundStyle, $backgroundType, $content, 1, false);
    }
}

function renderSingleContentSection($section, $sectionTextColor, $backgroundStyle, $backgroundType, $content, $partNumber, $isLongContent = false, $firstPart = '', $secondPart = '') {
    // Generate unique section ID to scope CSS
    $sectionId = 'content-section-' . uniqid();
    
    // Default gradient fallback if no background is set
    $defaultGradient = '';
    if ($backgroundType === 'gradient' && empty($backgroundStyle)) {
        $defaultGradient = 'bg-gradient-to-r from-blue-600 to-purple-600';
    }
    
    ?>
    
    <section id="<?= $sectionId ?>" class="content-section <?= $defaultGradient ?> py-16 relative overflow-hidden transition-all duration-500" 
             style="<?= $backgroundStyle ?>">
        
        <!-- Enhanced Background Overlay for Images -->
        <?php if ($backgroundType === 'image'): ?>
            <!-- Dark overlay for better text readability on images -->
            <div class="absolute inset-0 bg-black bg-opacity-30 pointer-events-none"></div>
            
            <!-- Optional Pattern Overlay for Images -->
            <div class="absolute inset-0 opacity-5 pointer-events-none">
                <div style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.8) 1px, transparent 0); background-size: 30px 30px; width: 100%; height: 100%;"></div>
            </div>
        <?php else: ?>
            <!-- Standard Pattern Overlay for Solid/Gradient -->
            <div class="absolute inset-0 opacity-5 pointer-events-none">
                <div style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.3) 1px, transparent 0); background-size: 20px 20px; width: 100%; height: 100%;"></div>
            </div>
        <?php endif; ?>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            
            <!-- Enhanced Header Section -->
            <div class="text-center mb-10">
                <div class="inline-flex items-center px-6 py-2 backdrop-blur-lg rounded-full text-sm font-semibold uppercase tracking-wider mb-6 border" 
                     style="background-color: <?= htmlspecialchars($sectionTextColor) ?>20; color: <?= htmlspecialchars($sectionTextColor) ?>; border-color: <?= htmlspecialchars($sectionTextColor) ?>20;">
                    <span class="w-2 h-2 rounded-full mr-2 animate-pulse" 
                          style="background-color: <?= htmlspecialchars($sectionTextColor) ?>;"></span>
                    <?= $partNumber > 1 ? 'Continued Story' : 'Our Journey' ?>
                </div>
                
                <div class="text-center mb-6">
                    <!-- Title with enhanced styling -->
                    <h2 class="content-title text-4xl md:text-5xl font-bold mb-2 transition-colors duration-300 <?= $backgroundType === 'image' ? 'drop-shadow-lg' : '' ?>" 
                        style="color: <?= htmlspecialchars($sectionTextColor) ?>;">
                        <?= htmlspecialchars($section['title']) ?>
                    </h2>
                    
                    <!-- Subtitle (only show for first part or if it exists) -->
                    <?php if ($section['subtitle'] && $partNumber === 1): ?>
                        <p class="content-subtitle text-lg mt-2 opacity-80 max-w-2xl mx-auto transition-colors duration-300 <?= $backgroundType === 'image' ? 'drop-shadow-md' : '' ?>" 
                           style="color: <?= htmlspecialchars($sectionTextColor) ?>;">
                            <?= htmlspecialchars($section['subtitle']) ?>
                        </p>
                    <?php endif; ?>
                    
                    <!-- Decorative line -->
                    <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto rounded-full mt-4 opacity-70"></div>
                </div>
            </div>
            
            <!-- Content Section -->
            <div class="text-center">
                <?php if ($isLongContent && $firstPart && $secondPart): ?>
                    <!-- Two Column Layout for Long Content -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-6xl mx-auto">
                        <!-- First Column -->
                        <div class="content-text text-lg opacity-90 transition-colors duration-300 leading-relaxed text-left <?= $backgroundType === 'image' ? 'drop-shadow-md' : '' ?>" 
                             style="color: <?= htmlspecialchars($sectionTextColor) ?>;">
                            <?= nl2br(htmlspecialchars($firstPart)) ?>
                        </div>
                        
                        <!-- Second Column -->
                        <div class="content-text text-lg opacity-90 transition-colors duration-300 leading-relaxed text-left <?= $backgroundType === 'image' ? 'drop-shadow-md' : '' ?>" 
                             style="color: <?= htmlspecialchars($sectionTextColor) ?>;">
                            <?= nl2br(htmlspecialchars($secondPart)) ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Single Column Layout for Short Content -->
                    <div class="content-text text-lg max-w-4xl mx-auto opacity-90 transition-colors duration-300 leading-relaxed text-center <?= $backgroundType === 'image' ? 'drop-shadow-md' : '' ?>" 
                         style="color: <?= htmlspecialchars($sectionTextColor) ?>;">
                        <?= nl2br(htmlspecialchars($content)) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Optional decorative element at bottom -->
                <div class="mt-12">
                    <div class="w-16 h-1 mx-auto rounded-full opacity-40" 
                         style="background-color: <?= htmlspecialchars($sectionTextColor) ?>;"></div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Preload background image for better performance -->
    <?php if ($backgroundType === 'image' && !empty($section['image_url'])): ?>
        <link rel="preload" as="image" href="<?= htmlspecialchars($section['image_url']) ?>">
    <?php endif; ?>
    
    <!-- SCOPED CSS - Only affects this Content section -->
    <style>
        /* Enhanced shadow effects for image backgrounds - SCOPED */
        <?php if ($backgroundType === 'image'): ?>
        #<?= $sectionId ?> .content-title,
        #<?= $sectionId ?> .content-subtitle,
        #<?= $sectionId ?> .content-text {
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
        }
        <?php endif; ?>
        
        /* Parallax effect for image backgrounds - SCOPED */
        <?php if ($backgroundType === 'image'): ?>
        #<?= $sectionId ?> {
            background-attachment: scroll; /* Prevents background disappearing */
        }
        <?php endif; ?>
        
        /* Gradient animation for gradient backgrounds - SCOPED */
        <?php if ($backgroundType === 'gradient'): ?>
        #<?= $sectionId ?> {
            background-size: 400% 400%;
            animation: contentGradientShift 8s ease infinite;
        }
        
        @keyframes contentGradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        <?php endif; ?>
        
        /* Enhanced content text animations */
        #<?= $sectionId ?> .content-title {
            animation: fadeInUp 0.8s ease-out;
        }
        
        #<?= $sectionId ?> .content-subtitle {
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }
        
        #<?= $sectionId ?> .content-text {
            animation: fadeInUp 0.8s ease-out 0.4s both;
            line-height: 1.8;
        }
        
        /* Badge animation */
        #<?= $sectionId ?> .inline-flex {
            animation: fadeInDown 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive adjustments - SCOPED */
        @media (max-width: 768px) {
            #<?= $sectionId ?> .content-title {
                font-size: 2.5rem !important;
            }
            
            #<?= $sectionId ?> .content-subtitle {
                font-size: 1.1rem !important;
            }
            
            #<?= $sectionId ?> .content-text {
                font-size: 1rem !important;
            }
            
            #<?= $sectionId ?> .grid-cols-2 {
                grid-template-columns: 1fr !important;
            }
            
            #<?= $sectionId ?> .inline-flex {
                font-size: 0.75rem !important;
                px: 4px !important;
                py: 1px !important;
            }
        }
        
        /* Dark mode adjustments - SCOPED */
        @media (prefers-color-scheme: dark) {
            #<?= $sectionId ?> {
                box-shadow: inset 0 0 50px rgba(255, 255, 255, 0.05);
            }
        }
        
        /* Enhanced hover effects for better interactivity */
        #<?= $sectionId ?>:hover .content-title {
            transform: translateY(-3px) scale(1.02);
            transition: all 0.4s ease;
        }
        
        #<?= $sectionId ?>:hover .inline-flex {
            transform: scale(1.05);
            transition: transform 0.3s ease;
        }
        
        /* Add subtle glow effect for gradient backgrounds */
        <?php if ($backgroundType === 'gradient'): ?>
        #<?= $sectionId ?>::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: inherit;
            filter: blur(20px);
            opacity: 0.3;
            z-index: -1;
        }
        <?php endif; ?>
        
        /* Enhanced readability for long content */
        <?php if ($isLongContent): ?>
        #<?= $sectionId ?> .content-text p {
            margin-bottom: 1.5rem;
        }
        
        #<?= $sectionId ?> .content-text:first-letter {
            font-size: 1.2em;
            font-weight: bold;
        }
        <?php endif; ?>
        
        /* Smooth scrolling between sections */
        #<?= $sectionId ?> {
            scroll-margin-top: 2rem;
        }
    </style>
    
    <?php
}

// Alternative version with more layout options


function renderFeaturesSection($section, $bgColor = null, $textColor = null) {
    $content = trim($section['content']);
    if (empty($content)) {
        return; // Don't render anything if no content
    }
    
    $features = [];
    
    // Check if content is JSON or pipe-separated string
    if (substr($content, 0, 1) === '{' || substr($content, 0, 1) === '[') {
        // Handle JSON format
        $jsonData = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            // If it's an object with 'features' key
            if (isset($jsonData['features']) && is_array($jsonData['features'])) {
                $featuresData = $jsonData['features'];
            } 
            // If it's a direct array of features
            elseif (is_array($jsonData)) {
                $featuresData = $jsonData;
            } else {
                return; // Invalid JSON structure
            }
            
            // Convert JSON features to our format
            foreach ($featuresData as $feature) {
                if (isset($feature['title']) && isset($feature['description'])) {
                    $features[] = [
                        'title' => $feature['title'],
                        'description' => $feature['description'],
                        'icon' => $feature['icon'] ?? 'fas fa-star',
                        'image' => $feature['image'] ?? '',
                        'iconBg' => $feature['iconBg'] ?? '#3b82f6',
                        'iconColor' => $feature['iconColor'] ?? '#ffffff',
                        'featureurl' => $feature['featureurl'] ?? '',
                        'badge' => $feature['badge'] ?? ''
                    ];
                }
            }
        } else {
            // JSON parsing failed, treat as string
            return;
        }
    } else {
        // Handle pipe-separated string format (original logic)
        $featuresArray = explode('||', $content);
        
        foreach ($featuresArray as $featureString) {
            $featureString = trim($featureString);
            if (empty($featureString)) continue;
            
            $parts = explode('|', $featureString);
            
            if (count($parts) >= 2 && !empty(trim($parts[0])) && !empty(trim($parts[1]))) {
                $features[] = [
                    'title' => trim($parts[0]),
                    'description' => trim($parts[1]),
                    'icon' => isset($parts[2]) && !empty(trim($parts[2])) ? trim($parts[2]) : 'fas fa-star',
                    'image' => isset($parts[3]) && !empty(trim($parts[3])) ? trim($parts[3]) : '',
                    'iconBg' => isset($parts[4]) && !empty(trim($parts[4])) ? trim($parts[4]) : '#3b82f6',
                    'iconColor' => isset($parts[5]) && !empty(trim($parts[5])) ? trim($parts[5]) : '#ffffff',
                    'featureurl' => isset($parts[6]) && !empty(trim($parts[6])) ? trim($parts[6]) : '',
                    'badge' => isset($parts[7]) && !empty(trim($parts[7])) ? trim($parts[7]) : ''
                ];
            }
        }
    }
    
    // Don't render if no valid features
    if (empty($features)) {
        return;
    }
    
    // Get styling configuration with fallbacks
    $backgroundType = $section['background_type'] ?? 'solid';
    $bgColor = $bgColor ?? $section['background_color'] ?? '#ffffff';
    $textColor = $textColor ?? $section['text_color'] ?? '#000000';
    
    // Get background styling using helper functions
    $backgroundStyle = getSectionBackgroundStyle($section);
    $sectionTextColor = getSectionTextColor($section);
    
    // Generate unique section ID for scoped CSS
    $sectionId = 'features-section-' . uniqid();
    
    ?>
    <section id="<?= $sectionId ?>" class="py-20" style="<?= $backgroundStyle ?>">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Section Header -->
            <div class="text-center mb-16">
                <div class="inline-flex items-center px-6 py-2 backdrop-blur-lg rounded-full text-sm font-semibold uppercase tracking-wider mb-6 border" 
                     style="background-color: <?= htmlspecialchars($sectionTextColor) ?>20; color: <?= htmlspecialchars($sectionTextColor) ?>; border-color: <?= htmlspecialchars($sectionTextColor) ?>20;">
                    <span class="w-2 h-2 bg-indigo-400 rounded-full mr-2 animate-pulse"></span>
                    Features
                </div>
                <h2 class="text-4xl md:text-5xl font-bold mb-2" style="color: <?= htmlspecialchars($sectionTextColor) ?>;">
                    <?= htmlspecialchars($section['title']) ?>
                </h2>
                <?php if ($section['subtitle']): ?>
                    <p class="text-lg mt-2 opacity-80 max-w-2xl mx-auto" style="color: <?= htmlspecialchars($sectionTextColor) ?>;">
                        <?= htmlspecialchars($section['subtitle']) ?>
                    </p>
                <?php endif; ?>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto rounded-full mt-2"></div>
            </div>

            <!-- Features Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($features as $feature): ?>
                    <div class="feature-card bg-white rounded-xl p-6 shadow-lg border border-gray-200 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                        <!-- Icon and Content Container -->
                        <div class="flex items-start space-x-4">
                            <!-- Icon -->
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 rounded-lg flex items-center justify-center shadow-sm" 
                                     style="background-color: <?= htmlspecialchars($feature['iconBg']) ?>;">
                                    <i class="<?= htmlspecialchars($feature['icon']) ?> text-lg" 
                                       style="color: <?= htmlspecialchars($feature['iconColor']) ?>;"></i>
                                </div>
                            </div>
                            
                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <!-- Title and Badge Container -->
                                <div class="flex items-center flex-wrap gap-2 mb-3">
                                    <h3 class="font-semibold text-gray-900 text-xl leading-tight">
                                        <?= htmlspecialchars($feature['title']) ?>
                                    </h3>
                                    <?php if (isset($feature['badge']) && !empty($feature['badge'])): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 border border-orange-200">
                                            <?= htmlspecialchars($feature['badge']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($feature['image'])): ?>
                                    <!-- Feature Image -->
                                    <div class="mb-3">
                                        <img src="<?= htmlspecialchars($feature['image']) ?>" 
                                             alt="<?= htmlspecialchars($feature['title']) ?>" 
                                             class="w-full h-24 object-cover rounded-lg border border-gray-100"
                                             onerror="this.style.display='none';">
                                    </div>
                                <?php endif; ?>
                                
                                <p class="text-gray-600 text-base leading-relaxed mb-4">
                                    <?= htmlspecialchars($feature['description']) ?>
                                </p>
                                
                                <!-- Learn More Link -->
                                <?php if (!empty($feature['featureurl'])): ?>
                                    <?php 
                                    // Ensure URL has proper protocol
                                    $url = $feature['featureurl'];
                                    if (!preg_match('/^https?:\/\//', $url)) {
                                        $url = 'https://' . $url;
                                    }
                                    ?>
                                    <a href="<?= htmlspecialchars($url) ?>" 
                                       target="_blank" 
                                       rel="noopener noreferrer"
                                       class="inline-flex items-center text-blue-600 hover:text-blue-700 text-sm font-medium transition-colors duration-200">
                                        Learn More
                                        <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <!-- SCOPED CSS -->
    <style>
        /* Feature card hover effects */
        #<?= $sectionId ?> .feature-card {
            border: 1px solid rgba(229, 231, 235, 1); /* Solid gray-200 border */
            transition: all 0.3s ease;
        }
        
        #<?= $sectionId ?> .feature-card:hover {
            border-color: rgba(59, 130, 246, 0.2); /* Blue border on hover */
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* Gradient animation for gradient backgrounds */
        <?php if ($backgroundType === 'gradient'): ?>
        #<?= $sectionId ?> {
            background-size: 400% 400%;
            animation: featuresGradientShift 8s ease infinite;
        }
        
        @keyframes featuresGradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        <?php endif; ?>
    </style>
    
    <?php
}

// Helper function to get section background style (if not already defined)
if (!function_exists('getSectionBackgroundStyle')) {
    function getSectionBackgroundStyle($section) {
        $backgroundType = $section['background_type'] ?? 'solid';
        $bgColor = $section['background_color'] ?? '#ffffff';
        
        switch ($backgroundType) {
            case 'gradient':
                $gradientType = $section['gradient_type'] ?? 'linear';
                $gradientDirection = $section['gradient_direction'] ?? '45deg';
                $gradientColor1 = $section['gradient_color_1'] ?? $bgColor;
                $gradientColor2 = $section['gradient_color_2'] ?? '#e0e0e0';
                
                if ($gradientType === 'linear') {
                    return "background: linear-gradient({$gradientDirection}, {$gradientColor1}, {$gradientColor2});";
                } else {
                    return "background: radial-gradient(circle, {$gradientColor1}, {$gradientColor2});";
                }
                
            case 'image':
                $backgroundImage = $section['background_image'] ?? '';
                $backgroundPosition = $section['background_position'] ?? 'center center';
                $backgroundSize = $section['background_size'] ?? 'cover';
                $backgroundRepeat = $section['background_repeat'] ?? 'no-repeat';
                
                return "background-image: url('{$backgroundImage}'); background-position: {$backgroundPosition}; background-size: {$backgroundSize}; background-repeat: {$backgroundRepeat};";
                
            default: // solid
                return "background-color: {$bgColor};";
        }
    }
}

// Helper function to get section text color (if not already defined)
if (!function_exists('getSectionTextColor')) {
    function getSectionTextColor($section) {
        return $section['text_color'] ?? '#000000';
    }
}


function renderFeaturesSection_Enhanced($section, $bgColor, $textColor) {
    // Parse the new format correctly
    $content = trim($section['content']);
    if (empty($content)) {
        return;
    }
    
    // Split by || to get individual features
    $featuresArray = explode('||', $content);
    $features = [];
    
    // Parse each feature
    foreach ($featuresArray as $featureString) {
        $featureString = trim($featureString);
        if (empty($featureString)) continue;
        
        $parts = explode('|', $featureString);
        
        if (count($parts) >= 2 && !empty(trim($parts[0])) && !empty(trim($parts[1]))) {
            $features[] = [
                'title' => trim($parts[0]),
                'description' => trim($parts[1]),
                'icon' => isset($parts[2]) && !empty(trim($parts[2])) ? trim($parts[2]) : 'fas fa-star',
                'image' => isset($parts[3]) && !empty(trim($parts[3])) ? trim($parts[3]) : '',
                'iconBg' => isset($parts[4]) && !empty(trim($parts[4])) ? trim($parts[4]) : '#3b82f6',
                'iconColor' => isset($parts[5]) && !empty(trim($parts[5])) ? trim($parts[5]) : '#ffffff'
            ];
        }
    }
    
    if (empty($features)) {
        return;
    }
    ?>
    <section class="py-16" style="background-color: <?= $bgColor ?>; color: <?= $textColor ?>;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Section Header -->
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold mb-4"><?= htmlspecialchars($section['title']) ?></h2>
                <?php if ($section['subtitle']): ?>
                    <p class="text-xl opacity-80 max-w-3xl mx-auto"><?= htmlspecialchars($section['subtitle']) ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Features Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($features as $index => $feature): ?>
                    <div class="group">
                        <div class="bg-white p-8 rounded-xl shadow-lg text-center hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 h-full flex flex-col">
                            <?php if (!empty($feature['image'])): ?>
                                <!-- Feature Image -->
                                <div class="mb-6 overflow-hidden rounded-lg">
                                    <img src="<?= htmlspecialchars($feature['image']) ?>" 
                                         alt="<?= htmlspecialchars($feature['title']) ?>" 
                                         class="w-full h-40 object-cover group-hover:scale-105 transition-transform duration-300"
                                         onerror="this.parentElement.style.display='none';">
                                </div>
                            <?php endif; ?>
                            
                            <!-- Feature Icon -->
                            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6 shadow-md group-hover:scale-110 transition-transform duration-300" 
                                 style="background: linear-gradient(135deg, <?= htmlspecialchars($feature['iconBg']) ?>, <?= adjustBrightness($feature['iconBg'], -20) ?>);">
                                <i class="<?= htmlspecialchars($feature['icon']) ?> text-xl" 
                                   style="color: <?= htmlspecialchars($feature['iconColor']) ?>;"></i>
                            </div>
                            
                            <!-- Feature Content -->
                            <div class="flex-1 flex flex-col">
                                <h3 class="text-xl font-bold text-gray-900 mb-3"><?= htmlspecialchars($feature['title']) ?></h3>
                                <p class="text-gray-600 leading-relaxed flex-1"><?= htmlspecialchars($feature['description']) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}

// Helper function to adjust color brightness
function adjustBrightness($hex, $percent) {
    // Remove # if present
    $hex = ltrim($hex, '#');
    
    // Convert hex to RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Adjust brightness
    $r = max(0, min(255, $r + ($r * $percent / 100)));
    $g = max(0, min(255, $g + ($g * $percent / 100)));
    $b = max(0, min(255, $b + ($b * $percent / 100)));
    
    // Convert back to hex
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

function renderStatsSection($section, $bgColor, $textColor) {
    $stats = explode('|', $section['content']);
    $textColor = $section['text_color'] ?? '#ffffff';
    $sectionTextColor = getSectionTextColor($section);
    
    // Parse stats into structured data (backward compatible)
    $parsedStats = [];
    for ($i = 0; $i < count($stats); $i += 2) {
        if (isset($stats[$i]) && isset($stats[$i + 1])) {
            $parsedStats[] = [
                'number' => trim($stats[$i]),
                'label' => trim($stats[$i + 1]),
                'icon' => '', // Default empty, can be enhanced later
                'color' => $textColor, // Use section text color as default
                'animation' => 'countup' // Default animation
            ];
        }
    }
    
    // Generate unique section ID for animations
    $sectionId = 'stats-section-' . uniqid();
    ?>
    
    <!-- Enhanced Stats Section with Animations -->
    <section id="<?= $sectionId ?>" class="py-16 relative overflow-hidden" 
             style="background-color: <?= $bgColor ?>; color: <?= $textColor ?>;">
        
        <!-- Background Effects -->
        <div class="absolute inset-0 opacity-5">
            <div class="absolute top-10 left-10 w-32 h-32 bg-white rounded-full blur-3xl"></div>
            <div class="absolute bottom-10 right-10 w-48 h-48 bg-white rounded-full blur-3xl"></div>
            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-white rounded-full blur-3xl"></div>
        </div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <!-- Section Header -->
            <div class="text-center mb-16">
                <div class="inline-flex items-center px-6 py-2 backdrop-blur-lg rounded-full text-sm font-semibold uppercase tracking-wider mb-6 border" 
                     style="background-color: <?= htmlspecialchars($sectionTextColor) ?>20; color: <?= htmlspecialchars($sectionTextColor) ?>; border-color: <?= htmlspecialchars($sectionTextColor) ?>20;">
                    <span class="w-2 h-2 bg-indigo-400 rounded-full mr-2 animate-pulse"></span>
                    Our Stats
                </div>
                <h2 class="text-4xl md:text-5xl font-bold text-black mb-2">
                    <?= htmlspecialchars($section['title']) ?>
                </h2>
                <?php if ($section['subtitle']): ?>
                    <p class="text-lg mt-2 opacity-80 max-w-2xl mx-auto" style="color: <?= htmlspecialchars($sectionTextColor) ?>;">
                        <?= htmlspecialchars($section['subtitle']) ?>
                    </p>
                <?php endif; ?>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto rounded-full mt-2"></div>
            </div>
            
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-<?= min(count($parsedStats), 4) ?> gap-8 lg:gap-12">
                <?php foreach ($parsedStats as $index => $stat): ?>
                    <div class="stats-card text-center group" 
                         data-index="<?= $index ?>"
                         style="animation-delay: <?= ($index * 150) ?>ms;">
                        
                        <!-- Icon (if available) -->
                        <?php if (!empty($stat['icon'])): ?>
                            <div class="mb-4">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white bg-opacity-20 backdrop-blur-sm group-hover:scale-110 transition-transform duration-300">
                                    <i class="<?= htmlspecialchars($stat['icon']) ?> text-2xl" 
                                       style="color: <?= $stat['color'] ?>;"></i>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Animated Number -->
                        <div class="stat-number text-4xl sm:text-5xl lg:text-6xl font-bold mb-3 relative"
                             data-target="<?= htmlspecialchars($stat['number']) ?>"
                             data-animation="<?= $stat['animation'] ?>"
                             style="color: <?= $stat['color'] ?>;">
                            
                            <!-- Number will be animated via JavaScript -->
                            <span class="counter-value">0</span>
                            
                            <!-- Glow Effect -->
                            <div class="absolute inset-0 opacity-0 group-hover:opacity-30 transition-opacity duration-500"
                                 style="background: linear-gradient(45deg, <?= $stat['color'] ?>40, transparent); 
                                        filter: blur(20px); transform: scale(1.2);"></div>
                        </div>
                        
                        <!-- Label -->
                        <div class="stat-label text-lg sm:text-xl font-medium opacity-90 group-hover:opacity-100 transition-opacity duration-300">
                            <?= htmlspecialchars($stat['label']) ?>
                        </div>
                        
                        <!-- Decorative Line -->
                        <div class="mx-auto mt-4 h-1 w-12 bg-current opacity-30 group-hover:w-16 group-hover:opacity-60 transition-all duration-300 rounded-full"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Additional Visual Elements -->
            <div class="mt-16 text-center opacity-60">
                <div class="inline-flex items-center space-x-2 text-sm">
                    <div class="w-2 h-2 bg-current rounded-full animate-pulse"></div>
                    <span>Real-time statistics</span>
                    <div class="w-2 h-2 bg-current rounded-full animate-pulse animation-delay-500"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Enhanced CSS Styles -->
    <style>
        /* Stats Section Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes countUp {
            from {
                opacity: 0;
                transform: scale(0.8) translateY(20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.3) translateY(50px);
            }
            50% {
                opacity: 1;
                transform: scale(1.1) translateY(-10px);
            }
            70% {
                transform: scale(0.95) translateY(5px);
            }
            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }

        /* Base animations */
        .animate-fade-in-up {
            animation: fadeInUp 0.8s ease-out forwards;
        }

        .animation-delay-200 {
            animation-delay: 200ms;
        }

        .animation-delay-500 {
            animation-delay: 500ms;
        }

        /* Stats Cards */
        .stats-card {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stats-card.animate {
            opacity: 1;
            transform: translateY(0);
            animation: slideInUp 0.8s ease-out forwards;
        }

        .stats-card:hover {
            transform: translateY(-5px) scale(1.02);
        }

        /* Number styling */
        .stat-number {
            position: relative;
            display: inline-block;
        }

        .stat-number .counter-value {
            display: inline-block;
            font-variant-numeric: tabular-nums;
            transition: all 0.3s ease;
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .stat-number {
                font-size: 2.5rem;
            }
            
            .stats-card {
                margin-bottom: 2rem;
            }
        }

        /* Modern glassmorphism effect */
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }

        .stats-card:hover::before {
            opacity: 1;
        }

        .stats-card {
            position: relative;
            padding: 2rem 1rem;
            border-radius: 1rem;
        }

        /* Loading placeholder */
        .counter-value.loading {
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            background-size: 200% 100%;
            animation: loading 1.5s ease-in-out infinite;
        }

        @keyframes loading {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }
    </style>

    <!-- Enhanced JavaScript for Animations -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize stats section
            initializeStatsSection('<?= $sectionId ?>');
        });

        function initializeStatsSection(sectionId) {
            const section = document.getElementById(sectionId);
            if (!section) return;

            // Set up intersection observer for scroll-triggered animations
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animateStatsSection(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.3,
                rootMargin: '0px 0px -100px 0px'
            });

            observer.observe(section);
        }

        function animateStatsSection(section) {
            const statsCards = section.querySelectorAll('.stats-card');
            
            statsCards.forEach((card, index) => {
                // Animate card entrance
                setTimeout(() => {
                    card.classList.add('animate');
                    
                    // Animate counter
                    const numberElement = card.querySelector('.stat-number');
                    const counterValue = card.querySelector('.counter-value');
                    const target = numberElement.getAttribute('data-target');
                    const animationType = numberElement.getAttribute('data-animation');
                    
                    // Start counter animation
                    animateCounter(counterValue, target, animationType);
                    
                }, index * 150);
            });
        }

        function animateCounter(element, target, animationType = 'countup') {
            // Extract number and suffix from target
            const numericPart = target.match(/[\d,\.]+/) ? target.match(/[\d,\.]+/)[0] : '0';
            const prefix = target.substring(0, target.indexOf(numericPart));
            const suffix = target.substring(target.indexOf(numericPart) + numericPart.length);
            const targetNumber = parseFloat(numericPart.replace(/,/g, '')) || 0;
            
            if (targetNumber === 0 || animationType !== 'countup') {
                // Just show the target immediately for non-numeric or non-countup animations
                element.textContent = target;
                return;
            }
            
            // Animate the counter
            let startTime = null;
            const duration = Math.min(2000 + (targetNumber * 2), 4000); // Dynamic duration based on number size
            
            function animate(currentTime) {
                if (!startTime) startTime = currentTime;
                const progress = Math.min((currentTime - startTime) / duration, 1);
                
                // Easing function for smooth animation
                const easeOutQuart = 1 - Math.pow(1 - progress, 4);
                const currentNumber = Math.floor(easeOutQuart * targetNumber);
                
                // Format number with commas if original had them
                let displayNumber = currentNumber.toString();
                if (numericPart.includes(',')) {
                    displayNumber = displayNumber.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }
                
                element.textContent = prefix + displayNumber + suffix;
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    element.textContent = target; // Ensure final value is exact
                }
            }
            
            requestAnimationFrame(animate);
        }

        // Add hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const statsCards = document.querySelectorAll('.stats-card');
            
            statsCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    // Add subtle shake animation to number
                    const number = this.querySelector('.stat-number');
                    number.style.animation = 'pulse 0.6s ease-in-out';
                });
                
                card.addEventListener('mouseleave', function() {
                    const number = this.querySelector('.stat-number');
                    number.style.animation = '';
                });
            });
        });

        // Parallax effect for background elements
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const parallaxElements = document.querySelectorAll('#<?= $sectionId ?> .absolute');
            
            parallaxElements.forEach((element, index) => {
                const speed = 0.5 + (index * 0.1);
                element.style.transform = `translateY(${scrolled * speed}px)`;
            });
        });
    </script>

    <?php
}

function renderTestimonialsSection($section, $bgColor, $textColor) {
    // Parse JSON content instead of pipe-separated format
    $testimonialsData = [];
    $content = trim($section['content']);
    
    if ($content) {
        try {
            $parsed = json_decode($content, true);
            
            // Handle different JSON structures
            if (isset($parsed['testimonials']) && is_array($parsed['testimonials'])) {
                // New JSON format with testimonials array
                $testimonialsData = $parsed['testimonials'];
            } elseif (is_array($parsed)) {
                // Direct array of testimonials
                $testimonialsData = $parsed;
            }
        } catch (Exception $e) {
            // Fallback to old pipe-separated format for backward compatibility
            $testimonials = explode('||', $content);
            foreach ($testimonials as $testimonial) {
                $parts = explode('|', $testimonial);
                if (count($parts) >= 3) {
                    $testimonialsData[] = [
                        'name' => trim($parts[0]),
                        'quote' => trim($parts[1]),
                        'rating' => trim($parts[2]),
                        'image' => isset($parts[3]) ? trim($parts[3]) : '',
                        'verified' => true
                    ];
                }
            }
        }
    }
    
    $carouselId = 'testimonials-' . uniqid();
    $validTestimonials = array_filter($testimonialsData, function($t) { 
        return isset($t['name']) && isset($t['quote']) && isset($t['rating']); 
    });
    ?>
    <section class="py-16" style="background-color: <?= $bgColor ?>; color: <?= $textColor ?>;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <div class="inline-flex items-center px-6 py-2 backdrop-blur-lg rounded-full text-sm font-semibold uppercase tracking-wider mb-6 border" 
                     style="background-color: <?= htmlspecialchars($textColor) ?>20; color: <?= htmlspecialchars($textColor) ?>; border-color: <?= htmlspecialchars($textColor) ?>20;">
                    <span class="w-2 h-2 bg-indigo-400 rounded-full mr-2 animate-pulse"></span>
                    Testimonials
                </div>
                <h2 class="text-4xl md:text-5xl font-bold text-black mb-2" style="color: <?= htmlspecialchars($textColor) ?>;">
                    <?= htmlspecialchars($section['title']) ?>
                </h2>
                <?php if ($section['subtitle']): ?>
                    <p class="text-lg mt-2 opacity-80 max-w-2xl mx-auto" style="color: <?= htmlspecialchars($textColor) ?>;">
                        <?= htmlspecialchars($section['subtitle']) ?>
                    </p>
                <?php endif; ?>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto rounded-full mt-2"></div>
            </div>
            
            <!-- Carousel Container -->
            <div class="relative max-w-7xl mx-auto">
                <div class="carousel-container overflow-hidden">
                    <div class="carousel-track flex transition-transform duration-300 ease-in-out" id="<?= $carouselId ?>">
                        <?php 
                        $slideIndex = 0;
                        for ($i = 0; $i < count($validTestimonials); $i += 3): 
                            $slideTestimonials = array_slice($validTestimonials, $i, 3);
                        ?>
                            <div class="carousel-slide w-full flex-shrink-0">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 px-4">
                                    <?php foreach ($slideTestimonials as $testimonial): ?>
                                        <?php 
                                        $testimonialId = 'testimonial-' . uniqid();
                                        $testimonialText = $testimonial['quote'];
                                        $isLongText = strlen($testimonialText) > 120;
                                        $shortText = $isLongText ? substr($testimonialText, 0, 120) . '...' : $testimonialText;
                                        $rating = intval($testimonial['rating']);
                                        $customerName = $testimonial['name'];
                                        $customerImage = isset($testimonial['image']) ? $testimonial['image'] : '';
                                        $isVerified = isset($testimonial['verified']) ? $testimonial['verified'] : true;
                                        
                                        // Handle image URL
                                        $imageUrl = '';
                                        if ($customerImage) {
                                            if (strpos($customerImage, 'http') === 0 || strpos($customerImage, 'data:') === 0) {
                                                $imageUrl = $customerImage;
                                            } else {
                                                // Use BASE_URL from config.php
                                                $baseUrl = defined('BASE_URL') ? BASE_URL : '';
                                                if (empty($baseUrl)) {
                                                    // Fallback to server detection if BASE_URL not defined
                                                    $baseUrl = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] : '';
                                                }
                                                $imageUrl = rtrim($baseUrl, '/') . '/' . ltrim($customerImage, '/');
                                            }
                                        }
                                        ?>
                                        <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100 testimonial-card relative overflow-hidden">
                                            <!-- Quote Icon -->
                                            <div class="absolute top-4 left-4 text-blue-100">
                                                <i class="fas fa-quote-left text-2xl"></i>
                                            </div>
                                            
                                            <!-- Stars -->
                                            <div class="flex justify-end mb-3">
                                                <?php for ($j = 0; $j < $rating; $j++): ?>
                                                    <i class="fas fa-star text-yellow-400 text-sm ml-1"></i>
                                                <?php endfor; ?>
                                                <?php for ($j = $rating; $j < 5; $j++): ?>
                                                    <i class="far fa-star text-gray-300 text-sm ml-1"></i>
                                                <?php endfor; ?>
                                            </div>
                                            
                                            <!-- Testimonial Text -->
                                            <div class="testimonial-text-container relative mb-4">
                                                <div class="testimonial-text-wrapper">
                                                    <p class="text-gray-700 text-base leading-relaxed font-medium testimonial-text">
                                                        "<?= htmlspecialchars($isLongText ? $shortText : $testimonialText) ?>"
                                                    </p>
                                                    <?php if ($isLongText): ?>
                                                        <button 
                                                            class="read-more-btn text-blue-600 text-sm font-medium mt-3 flex items-center group"
                                                            data-text="<?= htmlspecialchars($testimonialText, ENT_QUOTES) ?>"
                                                            data-author="<?= htmlspecialchars($customerName, ENT_QUOTES) ?>"
                                                            data-avatar="<?= htmlspecialchars($imageUrl, ENT_QUOTES) ?>"
                                                            data-stars="<?= $rating ?>"
                                                            onclick="openTestimonialModal(this)"
                                                        >
                                                            <span>Read More</span>
                                                            <i class="fas fa-chevron-right ml-1 text-xs"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Author Info -->
                                            <div class="flex items-center pt-4 border-t border-gray-100">
                                                <?php if ($imageUrl): ?>
                                                    <img src="<?= htmlspecialchars($imageUrl) ?>" 
                                                         alt="<?= htmlspecialchars($customerName) ?>" 
                                                         class="w-12 h-12 rounded-full mr-4 object-cover border-2 border-gray-200"
                                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full mr-4 flex items-center justify-center" style="display: none;">
                                                        <i class="fas fa-user text-white"></i>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full mr-4 flex items-center justify-center">
                                                        <i class="fas fa-user text-white"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="flex-1">
                                                    <h4 class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($customerName) ?></h4>
                                                    <div class="flex items-center mt-1">
                                                        <?php if ($isVerified): ?>
                                                            <div class="w-2 h-2 bg-green-400 rounded-full mr-2"></div>
                                                            <span class="text-xs text-gray-500">Verified Customer</span>
                                                        <?php else: ?>
                                                            <div class="w-2 h-2 bg-gray-400 rounded-full mr-2"></div>
                                                            <span class="text-xs text-gray-500">Customer</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php 
                            $slideIndex++;
                        endfor; 
                        ?>
                    </div>
                </div>
                
                <!-- Navigation Buttons -->
                <?php if (count($validTestimonials) > 3): ?>
                    <button class="carousel-btn carousel-prev absolute left-0 top-1/2 transform -translate-y-1/2 bg-white rounded-full p-3 shadow-lg hover:bg-gray-50 transition-colors" onclick="moveCarousel('<?= $carouselId ?>', -1)">
                        <i class="fas fa-chevron-left text-gray-600"></i>
                    </button>
                    <button class="carousel-btn carousel-next absolute right-0 top-1/2 transform -translate-y-1/2 bg-white rounded-full p-3 shadow-lg hover:bg-gray-50 transition-colors" onclick="moveCarousel('<?= $carouselId ?>', 1)">
                        <i class="fas fa-chevron-right text-gray-600"></i>
                    </button>
                    
                    <!-- Dots Indicator -->
                    <div class="flex justify-center mt-8 space-x-2">
                        <?php for ($i = 0; $i < ceil(count($validTestimonials) / 3); $i++): ?>
                            <button class="carousel-dot w-3 h-3 rounded-full bg-gray-300 transition-colors hover:bg-gray-400 <?= $i === 0 ? 'active bg-gray-600' : '' ?>" onclick="goToSlide('<?= $carouselId ?>', <?= $i ?>)"></button>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Testimonial Modal -->
    <div id="testimonialModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 opacity-0 invisible transition-all duration-300">
        <div class="bg-white rounded-2xl p-8 max-w-2xl mx-4 max-h-[90vh] overflow-y-auto testimonial-modal-content transform scale-95 transition-all duration-300 relative">
            <!-- Close Button -->
            <button onclick="closeTestimonialModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors text-xl">
                <i class="fas fa-times"></i>
            </button>
            
            <!-- Modal Content -->
            <div class="pr-8">
                <!-- Quote Icon -->
                <div class="text-blue-500 mb-4">
                    <i class="fas fa-quote-left text-3xl"></i>
                </div>
                
                <!-- Stars -->
                <div id="modalStars" class="flex mb-6"></div>
                
                <!-- Full Text -->
                <div class="mb-8">
                    <p id="modalText" class="text-gray-700 text-lg leading-relaxed font-medium"></p>
                </div>
                
                <!-- Author Info -->
                <div class="flex items-center pt-6 border-t border-gray-200">
                    <div id="modalAvatar" class="w-16 h-16 rounded-full mr-4 flex items-center justify-center"></div>
                    <div>
                        <h4 id="modalAuthor" class="font-semibold text-gray-900 text-lg"></h4>
                        <div class="flex items-center mt-2">
                            <div class="w-2 h-2 bg-green-400 rounded-full mr-2"></div>
                            <span class="text-sm text-gray-500">Verified Customer</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .carousel-container {
            position: relative;
        }
        
        .carousel-btn {
            z-index: 10;
            opacity: 1;
        }
        
        .carousel-dot.active {
            background-color: #4B5563 !important;
        }
        
        .carousel-slide {
            min-height: 320px;
        }
        
        .testimonial-card {
            height: auto;
            min-height: 280px;
            display: flex;
            flex-direction: column;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        
        .testimonial-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .testimonial-text-container {
            flex: 1;
            position: relative;
        }
        
        .testimonial-text-wrapper {
            position: relative;
        }
        
        .read-more-btn {
            position: relative;
            z-index: 5;
            transition: color 0.2s ease-in-out;
        }
        
        .read-more-btn:hover {
            color: #2563eb;
        }
        
        /* Modal Styles */
        .testimonial-modal-content {
            position: relative;
        }
        
        #testimonialModal.show {
            opacity: 1;
            visibility: visible;
        }
        
        #testimonialModal.show .testimonial-modal-content {
            transform: scale(1);
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @media (max-width: 768px) {
            .carousel-btn {
                display: none;
            }
            .carousel-slide .grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (min-width: 769px) and (max-width: 1023px) {
            .carousel-slide .grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>

    <script>
        let currentSlide = 0;
        const totalSlides = <?= ceil(count($validTestimonials) / 3) ?>;

        function moveCarousel(carouselId, direction) {
            if (totalSlides <= 1) return;
            
            const track = document.getElementById(carouselId);
            const dots = track.closest('.relative').querySelectorAll('.carousel-dot');
            
            currentSlide += direction;
            
            if (currentSlide >= totalSlides) {
                currentSlide = 0;
            } else if (currentSlide < 0) {
                currentSlide = totalSlides - 1;
            }
            
            const translateX = -currentSlide * 100;
            track.style.transform = `translateX(${translateX}%)`;
            
            // Update dots
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === currentSlide);
                dot.style.backgroundColor = index === currentSlide ? '#4B5563' : '#D1D5DB';
            });
        }

        function goToSlide(carouselId, slideIndex) {
            if (totalSlides <= 1) return;
            
            const track = document.getElementById(carouselId);
            const dots = track.closest('.relative').querySelectorAll('.carousel-dot');
            
            currentSlide = slideIndex;
            const translateX = -currentSlide * 100;
            track.style.transform = `translateX(${translateX}%)`;
            
            // Update dots
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === currentSlide);
                dot.style.backgroundColor = index === currentSlide ? '#4B5563' : '#D1D5DB';
            });
        }

        function openTestimonialModal(button) {
            const modal = document.getElementById('testimonialModal');
            const modalText = document.getElementById('modalText');
            const modalAuthor = document.getElementById('modalAuthor');
            const modalAvatar = document.getElementById('modalAvatar');
            const modalStars = document.getElementById('modalStars');
            
            // Get data from button attributes
            const text = button.getAttribute('data-text');
            const author = button.getAttribute('data-author');
            const avatar = button.getAttribute('data-avatar');
            const stars = parseInt(button.getAttribute('data-stars'));
            
            // Set text content
            modalText.textContent = '"' + text + '"';
            modalAuthor.textContent = author;
            
            // Set stars
            modalStars.innerHTML = '';
            for (let i = 0; i < stars; i++) {
                modalStars.innerHTML += '<i class="fas fa-star text-yellow-400 text-lg mr-1"></i>';
            }
            for (let i = stars; i < 5; i++) {
                modalStars.innerHTML += '<i class="far fa-star text-gray-300 text-lg mr-1"></i>';
            }
            
            // Set avatar
            if (avatar) {
                modalAvatar.innerHTML = `<img src="${avatar}" alt="${author}" class="w-16 h-16 rounded-full object-cover border-2 border-gray-200" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center" style="display: none;">
                        <i class="fas fa-user text-white text-xl"></i>
                    </div>`;
            } else {
                modalAvatar.innerHTML = `<div class="w-16 h-16 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center">
                    <i class="fas fa-user text-white text-xl"></i>
                </div>`;
            }
            
            // Show modal
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        function closeTestimonialModal() {
            const modal = document.getElementById('testimonialModal');
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside
        document.getElementById('testimonialModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeTestimonialModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeTestimonialModal();
            }
        });
    </script>
    <?php
}

function renderPricingSection($section, $bgColor, $textColor) {
    $plans = explode('||', $section['content']);
    
    ?>
    <section class="py-16" style="background-color: <?= $bgColor ?>; color: <?= $textColor ?>;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <div class="inline-flex items-center px-6 py-2 backdrop-blur-lg rounded-full text-sm font-semibold uppercase tracking-wider mb-6 border" 
                     style="background-color: <?= htmlspecialchars($textColor) ?>20; color: <?= htmlspecialchars($textColor) ?>; border-color: <?= htmlspecialchars($textColor) ?>20;">
                    <span class="w-2 h-2 bg-indigo-400 rounded-full mr-2 animate-pulse"></span>
                    Pricing
                </div>
                <h2 class="text-4xl md:text-5xl font-bold text-black mb-2">
                    <?= htmlspecialchars($section['title']) ?>
                </h2>
                <?php if ($section['subtitle']): ?>
                    <p class="text-lg mt-2 opacity-80 max-w-2xl mx-auto" style="color: <?= htmlspecialchars($textColor) ?>;">
                        <?= htmlspecialchars($section['subtitle']) ?>
                    </p>
                <?php endif; ?>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto rounded-full mt-2"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php foreach ($plans as $plan): ?>
                    <?php 
                    $parts = explode('|', $plan);
                    if (count($parts) >= 4):
                        // Extract plan data
                        $planName = trim($parts[0]);
                        $planPrice = trim($parts[1]);
                        $planPeriod = trim($parts[2]);
                        $planDescription = trim($parts[3]);
                        
                        // Check if popular field exists (5th element)
                        $isPopular = false;
                        $featuresStartIndex = 4;
                        
                        if (count($parts) >= 5) {
                            // Check if 5th element is '1' or '0' (popular indicator)
                            if ($parts[4] === '1' || $parts[4] === '0') {
                                $isPopular = ($parts[4] === '1');
                                $featuresStartIndex = 5; // Features start after popular field
                            }
                        }
                        
                        // Format price display
                        $displayPrice = $planPrice;
                        if (!empty($planPrice) && !str_contains($planPrice, '$') && !str_contains($planPrice, '€') && !str_contains($planPrice, '£') && strtolower($planPrice) !== 'free' && strtolower($planPrice) !== 'custom') {
                            $displayPrice = '$' . $planPrice;
                        }
                        
                        // Format period display
                        $displayPeriod = $planPeriod;
                        if (!empty($planPeriod) && !str_starts_with($planPeriod, '/')) {
                            $displayPeriod = '/' . $planPeriod;
                        }
                    ?>
                    <div class="bg-white rounded-lg shadow-md p-8 text-center relative <?= $isPopular ? 'ring-2 ring-blue-500 border-blue-200' : '' ?>">
                        
                        <?php if ($isPopular): ?>
                            <!-- Most Popular Badge -->
                            <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-1 rounded-full text-xs font-medium shadow-lg">
                                Most Popular
                            </div>
                        <?php endif; ?>
                        
                        <h3 class="text-2xl font-bold text-gray-900 mb-4"><?= htmlspecialchars($planName) ?></h3>
                        
                        <div class="mb-6">
                            <span class="text-4xl font-bold text-blue-600"><?= htmlspecialchars($displayPrice) ?></span>
                            <span class="text-gray-600"><?= htmlspecialchars($displayPeriod) ?></span>
                        </div>
                        
                        <?php if (!empty($planDescription)): ?>
                            <p class="text-gray-600 mb-6 italic"><?= htmlspecialchars($planDescription) ?></p>
                        <?php endif; ?>
                        
                        <!-- Features List -->
                        <ul class="space-y-3 mb-8 min-h-[120px]">
                            <?php 
                            $hasFeatures = false;
                            for ($i = $featuresStartIndex; $i < count($parts); $i++): 
                                if (trim($parts[$i])): 
                                    $hasFeatures = true;
                            ?>
                                <li class="text-gray-700 flex items-start text-sm">
                                    <svg class="w-4 h-4 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span><?= htmlspecialchars(trim($parts[$i])) ?></span>
                                </li>
                            <?php 
                                endif; 
                            endfor; 
                            
                            // Show placeholder if no features
                            if (!$hasFeatures):
                            ?>
                                <li class="text-gray-400 text-sm flex items-center justify-center h-full">
                                    No features listed
                                </li>
                            <?php endif; ?>
                        </ul>
                        
                        <!-- Choose Plan Button -->
                        <button class="w-full <?= $isPopular ? 'bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700' : 'bg-blue-600 hover:bg-blue-700' ?> text-white py-3 rounded-lg font-semibold transition duration-200">
                            Choose Plan
                        </button>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}


function renderTeamSection($section, $bgColor, $textColor) {
    $members = explode('||', $section['content']);
    ?>
    <section class="py-16" style="background-color: <?= $bgColor ?>; color: <?= $textColor ?>;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <div class="inline-flex items-center px-6 py-2 backdrop-blur-lg rounded-full text-sm font-semibold uppercase tracking-wider mb-6 border" 
                     style="background-color: <?= htmlspecialchars($textColor) ?>20; color: <?= htmlspecialchars($textColor) ?>; border-color: <?= htmlspecialchars($textColor) ?>20;">
                    <span class="w-2 h-2 bg-indigo-400 rounded-full mr-2 animate-pulse"></span>
                    Team
                </div>
                <h2 class="text-4xl md:text-5xl font-bold text-black mb-2">
                    <?= htmlspecialchars($section['title']) ?>
                </h2>
                <?php if ($section['subtitle']): ?>
                    <p class="text-lg mt-2 opacity-80 max-w-2xl mx-auto" style="color: <?= htmlspecialchars($textColor) ?>;">
                        <?= htmlspecialchars($section['subtitle']) ?>
                    </p>
                <?php endif; ?>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto rounded-full mt-2"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <?php foreach ($members as $member): ?>
                    <?php 
                    $parts = explode('|', $member);
                    if (count($parts) >= 3):
                    ?>
                    <div class="text-center">
                        <?php if (isset($parts[3]) && $parts[3]): ?>
                            <img src="<?= htmlspecialchars(trim($parts[3])) ?>" alt="" class="w-32 h-32 rounded-full mx-auto mb-4 object-cover">
                        <?php else: ?>
                            <div class="w-32 h-32 bg-gray-300 rounded-full mx-auto mb-4 flex items-center justify-center">
                                <i class="fas fa-user text-gray-600 text-3xl"></i>
                            </div>
                        <?php endif; ?>
                        <h3 class="text-xl font-semibold mb-2"><?= htmlspecialchars(trim($parts[0])) ?></h3>
                        <p class="text-blue-600 font-medium mb-2"><?= htmlspecialchars(trim($parts[1])) ?></p>
                        <p class="text-gray-600 text-sm"><?= htmlspecialchars(trim($parts[2])) ?></p>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}

// ENHANCED SLIDER SECTION - FIXED VERSION
function renderEnhancedSliderSection($section, $bgColor, $textColor) {
    $slides = [];
    
    if (!empty($section['content'])) {
        $slideData = explode('||', $section['content']);
        foreach ($slideData as $slide) {
            $parts = explode(';', $slide);
            if (!empty($parts[0])) {
                $slides[] = [
                    'url' => $parts[0],
                    'title' => $parts[1] ?? '',
                    'description' => $parts[2] ?? '',
                    'buttonText' => $parts[3] ?? '',
                    'buttonUrl' => $parts[4] ?? ''
                ];
            }
        }
    }
    
    // Fallback to old format
    if (empty($slides) && !empty($section['content'])) {
        $oldFormat = explode('|', $section['content']);
        foreach ($oldFormat as $imageUrl) {
            if (!empty(trim($imageUrl))) {
                $slides[] = [
                    'url' => trim($imageUrl),
                    'title' => '',
                    'description' => '',
                    'buttonText' => '',
                    'buttonUrl' => ''
                ];
            }
        }
    }
    
    if (empty($slides)) {
        echo '<div class="flex items-center justify-center py-16 text-gray-400">
                <div class="text-center">
                    <svg class="w-12 h-12 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-sm font-medium">No slides configured</p>
                </div>
              </div>';
        return;
    }
    
    $sliderId = 'elegant-slider-' . $section['id'];
    $safeId = str_replace('-', '_', $sliderId);
    ?>
    
    <section class="relative w-full overflow-hidden mb-12" style="background-color: <?= htmlspecialchars($bgColor) ?>;">
        <!-- Main Slider Container -->
        <div id="<?= $sliderId ?>" class="relative group">
            <div class="slider-wrapper flex transition-all duration-700 ease-out">
                <?php foreach ($slides as $index => $slide): ?>
                    <div class="slide w-full flex-shrink-0 relative">
                        <div class="relative h-[70vh] min-h-[500px] max-h-[800px] overflow-hidden">
                            <!-- Image with Ken Burns Effect -->
                            <img src="<?= htmlspecialchars($slide['url']) ?>" 
                                 alt="<?= htmlspecialchars($slide['title']) ?>"
                                 class="w-full h-full object-cover transition-transform duration-[20s] ease-out transform hover:scale-105">
                            
                            <!-- Elegant Gradient Overlay -->
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>
                            
                            <!-- Centered Content Container -->
                            <?php if (!empty($slide['title']) || !empty($slide['description']) || !empty($slide['buttonText'])): ?>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="text-center px-8 md:px-12 lg:px-16 max-w-5xl mx-auto">
                                        <?php if (!empty($slide['title'])): ?>
                                            <h2 class="text-4xl md:text-5xl lg:text-6xl font-light text-white mb-6 leading-tight tracking-wide slide-title opacity-0 translate-y-8">
                                                <?= htmlspecialchars($slide['title']) ?>
                                            </h2>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($slide['description'])): ?>
                                            <p class="text-lg md:text-xl text-white/90 mb-8 font-light leading-relaxed max-w-3xl mx-auto slide-description opacity-0 translate-y-8">
                                                <?= nl2br(htmlspecialchars($slide['description'])) ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($slide['buttonText']) && !empty($slide['buttonUrl'])): ?>
                                            <div class="flex justify-center">
                                                <a href="<?= htmlspecialchars($slide['buttonUrl']) ?>" 
                                                   class="inline-flex items-center px-8 py-4 bg-white/10 backdrop-blur-sm border border-white/20 text-white font-medium rounded-full transition-all duration-300 hover:bg-white/20 hover:scale-105 slide-button opacity-0 translate-y-8 group/btn">
                                                    <span class="mr-2"><?= htmlspecialchars($slide['buttonText']) ?></span>
                                                    <svg class="w-4 h-4 transition-transform duration-300 group-hover/btn:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                                    </svg>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                

            </div>
            
            <!-- Minimal Navigation Arrows -->
            <?php if (count($slides) > 1): ?>
                <button class="absolute left-6 top-1/2 -translate-y-1/2 w-12 h-12 bg-white/10 backdrop-blur-sm border border-white/20 text-white rounded-full transition-all duration-300 hover:bg-white/20 hover:scale-110 opacity-0 group-hover:opacity-100 z-20 prev-btn-<?= $safeId ?>">
                    <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
                
                <button class="absolute right-6 top-1/2 -translate-y-1/2 w-12 h-12 bg-white/10 backdrop-blur-sm border border-white/20 text-white rounded-full transition-all duration-300 hover:bg-white/20 hover:scale-110 opacity-0 group-hover:opacity-100 z-20 next-btn-<?= $safeId ?>">
                    <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            <?php endif; ?>
            
            <!-- Elegant Progress Dots -->
            <?php if (count($slides) > 1): ?>
                <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex items-center space-x-3 z-20">
                    <?php for ($i = 0; $i < count($slides); $i++): ?>
                        <button class="relative slider-dot-<?= $safeId ?> group/dot" data-slide="<?= $i ?>">
                            <div class="w-2 h-2 rounded-full bg-white/40 transition-all duration-300 group-hover/dot:bg-white/60 <?= $i === 0 ? 'bg-white scale-125' : '' ?>"></div>
                            <?php if ($i === 0): ?>
                                <div class="absolute inset-0 w-2 h-2 rounded-full bg-white/20 scale-150"></div>
                            <?php endif; ?>
                        </button>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
            
            <!-- Slide Counter -->
            <?php if (count($slides) > 1): ?>
                <div class="absolute top-8 right-8 text-white/70 text-sm font-light z-20">
                    <span class="slide-counter">01</span> / <?= str_pad(count($slides), 2, '0', STR_PAD_LEFT) ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Elegant Scoped Styles -->
    <style>
        /* Smooth slide animations */
        #<?= $sliderId ?> .slide-title,
        #<?= $sliderId ?> .slide-description,
        #<?= $sliderId ?> .slide-button {
            transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        #<?= $sliderId ?> .slide.active .slide-title {
            opacity: 1;
            transform: translateY(0);
            transition-delay: 0.3s;
        }
        
        #<?= $sliderId ?> .slide.active .slide-description {
            opacity: 1;
            transform: translateY(0);
            transition-delay: 0.5s;
        }
        
        #<?= $sliderId ?> .slide.active .slide-button {
            opacity: 1;
            transform: translateY(0);
            transition-delay: 0.7s;
        }
        
        /* Dot active state */
        #<?= $sliderId ?> .slider-dot-<?= $safeId ?>.active > div:first-child {
            background-color: white !important;
            transform: scale(1.25);
        }
        
        #<?= $sliderId ?> .slider-dot-<?= $safeId ?>.active > div:last-child {
            opacity: 1;
        }
        
        /* Hover effects for better UX */
        #<?= $sliderId ?> .slide img {
            transition: transform 20s ease-out;
        }
        
        #<?= $sliderId ?>:hover .slide img {
            transform: scale(1.02);
        }
        
        /* Loading state for images */
        #<?= $sliderId ?> .slide img {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        #<?= $sliderId ?> .slide img[src] {
            background: none;
            animation: none;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        

        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            #<?= $sliderId ?> .slide h2 {
                font-size: 2.5rem;
                line-height: 1.2;
            }
            
            #<?= $sliderId ?> .slide p {
                font-size: 1rem;
            }
            
            #<?= $sliderId ?> .slide .slide-button {
                padding: 0.75rem 1.5rem;
                font-size: 0.875rem;
            }
        }
        
        /* Smooth performance optimizations */
        #<?= $sliderId ?> .slider-wrapper {
            will-change: transform;
        }
        
        #<?= $sliderId ?> .slide {
            will-change: transform;
        }
        
        /* Focus states for accessibility */
        #<?= $sliderId ?> button:focus {
            outline: 2px solid white;
            outline-offset: 2px;
        }
    </style>
    
    <?php if (count($slides) > 1): ?>
        <script>
            (function() {
                const sliderId = '<?= $sliderId ?>';
                const safeId = '<?= $safeId ?>';
                let currentSlide = 0;
                const totalSlides = <?= count($slides) ?>;
                let autoSlideInterval;
                let isTransitioning = false;
                
                // Initialize first slide
                document.addEventListener('DOMContentLoaded', function() {
                    updateSlider();
                    startAutoSlide();
                });
                
                function updateSlider() {
                    if (isTransitioning) return;
                    
                    const slider = document.querySelector('#' + sliderId + ' .slider-wrapper');
                    const slides = document.querySelectorAll('#' + sliderId + ' .slide');
                    const counter = document.querySelector('#' + sliderId + ' .slide-counter');
                    
                    if (slider) {
                        isTransitioning = true;
                        slider.style.transform = `translateX(-${currentSlide * 100}%)`;
                        
                        // Update active slide
                        slides.forEach((slide, index) => {
                            slide.classList.toggle('active', index === currentSlide);
                        });
                        
                        // Update counter
                        if (counter) {
                            counter.textContent = String(currentSlide + 1).padStart(2, '0');
                        }
                        
                        // Reset transition flag
                        setTimeout(() => {
                            isTransitioning = false;
                        }, 700);
                    }
                    
                    // Update dots with smooth animation
                    const dots = document.querySelectorAll('.slider-dot-' + safeId);
                    dots.forEach((dot, index) => {
                        const isActive = index === currentSlide;
                        dot.classList.toggle('active', isActive);
                        
                        const innerDot = dot.querySelector('div:first-child');
                        const outerDot = dot.querySelector('div:last-child');
                        
                        if (isActive) {
                            innerDot.style.backgroundColor = 'white';
                            innerDot.style.transform = 'scale(1.25)';
                            if (outerDot) outerDot.style.opacity = '1';
                        } else {
                            innerDot.style.backgroundColor = 'rgba(255, 255, 255, 0.4)';
                            innerDot.style.transform = 'scale(1)';
                            if (outerDot) outerDot.style.opacity = '0';
                        }
                    });
                }
                
                function nextSlide() {
                    if (isTransitioning) return;
                    currentSlide = (currentSlide + 1) % totalSlides;
                    updateSlider();
                    resetAutoSlide();
                }
                
                function previousSlide() {
                    if (isTransitioning) return;
                    currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
                    updateSlider();
                    resetAutoSlide();
                }
                
                function goToSlide(slideIndex) {
                    if (isTransitioning || slideIndex === currentSlide) return;
                    currentSlide = slideIndex;
                    updateSlider();
                    resetAutoSlide();
                }
                
                function startAutoSlide() {
                    autoSlideInterval = setInterval(nextSlide, 6000);
                }
                
                function resetAutoSlide() {
                    clearInterval(autoSlideInterval);
                    startAutoSlide();
                }
                
                // Event listeners
                const nextBtn = document.querySelector('.next-btn-' + safeId);
                const prevBtn = document.querySelector('.prev-btn-' + safeId);
                
                if (nextBtn) nextBtn.addEventListener('click', nextSlide);
                if (prevBtn) prevBtn.addEventListener('click', previousSlide);
                
                // Dots event listeners
                const dots = document.querySelectorAll('.slider-dot-' + safeId);
                dots.forEach((dot, index) => {
                    dot.addEventListener('click', () => goToSlide(index));
                });
                
                // Keyboard navigation
                document.addEventListener('keydown', function(e) {
                    const sliderContainer = document.getElementById(sliderId);
                    if (!sliderContainer) return;
                    
                    // Check if slider is in viewport
                    const rect = sliderContainer.getBoundingClientRect();
                    const isVisible = rect.top < window.innerHeight && rect.bottom > 0;
                    
                    if (isVisible) {
                        if (e.key === 'ArrowLeft') {
                            e.preventDefault();
                            previousSlide();
                        } else if (e.key === 'ArrowRight') {
                            e.preventDefault();
                            nextSlide();
                        }
                    }
                });
                
                // Pause/resume on hover and visibility
                const sliderContainer = document.getElementById(sliderId);
                if (sliderContainer) {
                    sliderContainer.addEventListener('mouseenter', () => {
                        clearInterval(autoSlideInterval);
                    });
                    
                    sliderContainer.addEventListener('mouseleave', startAutoSlide);
                    
                    // Pause when page is not visible
                    document.addEventListener('visibilitychange', function() {
                        if (document.hidden) {
                            clearInterval(autoSlideInterval);
                        } else {
                            startAutoSlide();
                        }
                    });
                }
                
                // Touch/swipe support for mobile
                let touchStartX = 0;
                let touchEndX = 0;
                
                if (sliderContainer) {
                    sliderContainer.addEventListener('touchstart', function(e) {
                        touchStartX = e.changedTouches[0].screenX;
                    }, { passive: true });
                    
                    sliderContainer.addEventListener('touchend', function(e) {
                        touchEndX = e.changedTouches[0].screenX;
                        handleSwipe();
                    }, { passive: true });
                }
                
                function handleSwipe() {
                    const swipeThreshold = 50;
                    const diff = touchStartX - touchEndX;
                    
                    if (Math.abs(diff) > swipeThreshold) {
                        if (diff > 0) {
                            nextSlide(); // Swiped left
                        } else {
                            previousSlide(); // Swiped right
                        }
                    }
                }
            })();
        </script>
    <?php endif; ?>
    
    <?php
}

function renderGallerySection($section, $bgColor, $textColor) {
    $images = explode('|', $section['content']);
    ?>
    <section class="py-16" style="background-color: <?= $bgColor ?>; color: <?= $textColor ?>;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <div class="inline-flex items-center px-6 py-2 backdrop-blur-lg rounded-full text-sm font-semibold uppercase tracking-wider mb-6 border" 
                     style="background-color: <?= htmlspecialchars($textColor) ?>20; color: <?= htmlspecialchars($textColor) ?>; border-color: <?= htmlspecialchars($textColor) ?>20;">
                    <span class="w-2 h-2 bg-indigo-400 rounded-full mr-2 animate-pulse"></span>
                    Gallery
                </div>
                <h2 class="text-4xl md:text-5xl font-bold text-black mb-2">
                    <?= htmlspecialchars($section['title']) ?>
                </h2>
                <?php if ($section['subtitle']): ?>
                    <p class="text-lg mt-2 opacity-80 max-w-2xl mx-auto" style="color: <?= htmlspecialchars($textColor) ?>;">
                        <?= htmlspecialchars($section['subtitle']) ?>
                    </p>
                <?php endif; ?>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto rounded-full mt-2"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php foreach ($images as $image): ?>
                    <?php if (trim($image)): ?>
                        <div class="overflow-hidden rounded-lg shadow-md hover:shadow-lg transition duration-200">
                            <img src="<?= htmlspecialchars(trim($image)) ?>" alt="" class="w-full h-64 object-cover">
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}

function renderVideoSection($section, $bgColor, $textColor) {
    // Parse video data from JSON
    $videos = json_decode($section['content'] ?? '[]', true);
    if (!is_array($videos)) $videos = [];
    
    ?>
    <section class="py-16" style="background-color: <?= $bgColor ?>; color: <?= $textColor ?>;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <div class="inline-flex items-center px-6 py-2 backdrop-blur-lg rounded-full text-sm font-semibold uppercase tracking-wider mb-6 border" 
                     style="background-color: <?= htmlspecialchars($textColor) ?>20; color: <?= htmlspecialchars($textColor) ?>; border-color: <?= htmlspecialchars($textColor) ?>20;">
                    <span class="w-2 h-2 bg-indigo-400 rounded-full mr-2 animate-pulse"></span>
                    Video
                </div>
                <h2 class="text-4xl md:text-5xl font-bold text-black mb-2">
                    <?= htmlspecialchars($section['title']) ?>
                </h2>
                <?php if ($section['subtitle']): ?>
                    <p class="text-lg mt-2 opacity-80 max-w-2xl mx-auto" style="color: <?= htmlspecialchars($textColor) ?>;">
                        <?= htmlspecialchars($section['subtitle']) ?>
                    </p>
                <?php endif; ?>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto rounded-full mt-2"></div>
            </div>
            
            <?php if (!empty($videos)): ?>
                <?php 
                $videoCount = count($videos);
                $containerClass = '';
                $itemClass = '';
                
                if ($videoCount == 1) {
                    // Single video - centered, larger size
                    $containerClass = 'max-w-4xl mx-auto';
                    $itemClass = '';
                } elseif ($videoCount == 2) {
                    // Two videos - side by side
                    $containerClass = 'grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto';
                    $itemClass = '';
                } else {
                    // Three or more videos - 3 column grid
                    $containerClass = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8';
                    $itemClass = '';
                }
                ?>
                
                <div class="<?= $containerClass ?>">
                    <?php foreach ($videos as $index => $video): ?>
                        <div class="video-item group <?= $itemClass ?>">
                            <div class="aspect-video mb-4">
                                <iframe src="<?= htmlspecialchars($video['embed_url'] ?? $video['url']) ?>" 
                                        class="w-full h-full rounded-lg shadow-lg transition-transform duration-300 group-hover:scale-105"
                                        frameborder="0" 
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                        allowfullscreen
                                        loading="lazy">
                                </iframe>
                            </div>
                            
                            <?php if (!empty($video['description'])): ?>
                                <div class="px-2">
                                    <p class="text-sm opacity-90 leading-relaxed" style="color: <?= htmlspecialchars($textColor) ?>;">
                                        <?= nl2br(htmlspecialchars($video['description'])) ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Fallback for old single video format -->
                <div class="max-w-4xl mx-auto">
                    <div class="aspect-video">
                        <iframe src="<?= htmlspecialchars($section['content']) ?>" 
                                class="w-full h-full rounded-lg shadow-lg"
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen></iframe>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <style>
    .video-item {
        transition: all 0.3s ease;
    }
    
    .video-item:hover {
        transform: translateY(-2px);
    }
    
    @media (max-width: 768px) {
        .video-item {
            margin-bottom: 2rem;
        }
    }
    
    /* Responsive adjustments */
    @media (min-width: 1024px) and (max-width: 1280px) {
        .grid-cols-3 > .video-item:nth-child(3n+1):nth-last-child(-n+2) {
            grid-column: span 1;
        }
    }
    </style>
    <?php
}

function renderTimelineSection($section, $bgColor = null, $textColor = null) {
    $events = explode('||', $section['content']);
    
    // Get styling configuration with fallbacks
    $backgroundType = $section['background_type'] ?? 'solid';
    $bgColor = $bgColor ?? $section['background_color'] ?? '#ffffff';
    $textColor = $textColor ?? $section['text_color'] ?? '#000000';
    
    // Get background styling using helper functions
    $backgroundStyle = getSectionBackgroundStyle($section);
    $sectionTextColor = getSectionTextColor($section);
    
    // Generate unique section ID for scoped CSS
    $sectionId = 'timeline-section-' . uniqid();
    
    ?>
    <section id="<?= $sectionId ?>" class="py-24 relative overflow-hidden" style="<?= $backgroundStyle ?>">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-5">
            <div class="absolute top-0 left-0 w-full h-full" style="background-image: radial-gradient(circle at 25% 25%, currentColor 2px, transparent 2px); background-size: 50px 50px; color: <?= htmlspecialchars($sectionTextColor) ?>;"></div>
        </div>
        
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <!-- Enhanced Header -->
            <div class="text-center mb-20">
                <div class="inline-flex items-center px-6 py-2 backdrop-blur-lg rounded-full text-sm font-semibold uppercase tracking-wider mb-6 border" 
                     style="background-color: <?= htmlspecialchars($sectionTextColor) ?>20; color: <?= htmlspecialchars($sectionTextColor) ?>; border-color: <?= htmlspecialchars($sectionTextColor) ?>20;">
                    <span class="w-2 h-2 bg-indigo-400 rounded-full mr-2 animate-pulse"></span>
                    Our Journey
                </div>
                
                <div class="text-center mb-6">
                    <h2 class="text-4xl md:text-5xl font-bold text-black mb-2">
                        <?= htmlspecialchars($section['title']) ?>
                    </h2>
                    <?php if ($section['subtitle']): ?>
                        <p class="text-lg mt-2 opacity-80 max-w-2xl mx-auto" style="color: <?= htmlspecialchars($sectionTextColor) ?>;">
                            <?= htmlspecialchars($section['subtitle']) ?>
                        </p>
                    <?php endif; ?>
                    <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto rounded-full mt-2"></div>
                </div>
            </div>

            <!-- Modern Timeline -->
            <div class="relative">
                <!-- Elegant Central Line -->
                <div class="absolute left-1/2 transform -translate-x-1/2 w-1 h-full bg-gradient-to-b from-indigo-300 via-blue-400 via-teal-400 to-emerald-400 rounded-full shadow-lg">
                    <div class="absolute inset-0 w-full h-full bg-gradient-to-b from-indigo-300 via-blue-400 via-teal-400 to-emerald-400 rounded-full blur-sm opacity-50"></div>
                </div>
                
                <?php foreach ($events as $index => $event): ?>
                    <?php 
                    $parts = explode('|', $event);
                    if (count($parts) >= 3):
                        $isLeft = $index % 2 === 0;
                        // Balanced professional colors
                        $gradients = [
                            'from-indigo-500 to-blue-600',
                            'from-blue-500 to-teal-600',
                            'from-teal-500 to-emerald-600',
                            'from-emerald-500 to-green-600',  
                            'from-violet-500 to-indigo-600'
                        ];
                        $gradient = $gradients[$index % 5];
                        $delay = $index * 200;
                    ?>
                    
                    <div class="relative mb-16 timeline-item opacity-0" style="animation-delay: <?= $delay ?>ms;">
                        <!-- Enhanced Timeline Dot -->
                        <div class="absolute left-1/2 transform -translate-x-1/2 z-20">
                            <div class="relative">
                                <div class="w-6 h-6 bg-gradient-to-r <?= $gradient ?> rounded-full shadow-xl border-4 border-white">
                                    <div class="absolute inset-0 bg-gradient-to-r <?= $gradient ?> rounded-full animate-ping opacity-25"></div>
                                </div>
                                <div class="absolute inset-0 w-6 h-6 bg-gradient-to-r <?= $gradient ?> rounded-full blur-md opacity-40"></div>
                            </div>
                        </div>
                        
                        <!-- Content Container -->
                        <div class="flex items-center <?= $isLeft ? 'justify-end' : 'justify-start' ?>">
                            <div class="<?= $isLeft ? 'mr-12 text-right' : 'ml-12 text-left' ?> w-5/12">
                                <div class="relative">
                                    <!-- Background Card with Tilt Effect -->
                                    <div class="absolute inset-0 bg-gradient-to-r <?= $gradient ?> rounded-3xl transform rotate-2 shadow-2xl opacity-70"></div>
                                    
                                    <!-- Main Content Card -->
                                    <div class="relative bg-white rounded-3xl p-8 shadow-2xl border border-gray-100">
                                        <!-- Floating Date Badge -->
                                        <div class="absolute <?= $isLeft ? '-right-6' : '-left-6' ?> -top-4 z-10">
                                            <div class="bg-gradient-to-r <?= $gradient ?> text-white px-6 py-3 rounded-2xl shadow-xl transform <?= $isLeft ? 'rotate-10' : '-rotate-10' ?>">
                                                <span class="font-black text-lg tracking-wide">
                                                    <?= htmlspecialchars(trim($parts[0])) ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <!-- Card Content -->
                                        <div class="pt-4">
                                            <h3 class="text-2xl md:text-3xl font-black text-gray-900 mb-4 leading-tight">
                                                <?= htmlspecialchars(trim($parts[1])) ?>
                                            </h3>
                                            
                                            <p class="text-gray-600 leading-relaxed text-base md:text-lg font-medium">
                                                <?= htmlspecialchars(trim($parts[2])) ?>
                                            </p>
                                        </div>
                                        
                                        <!-- Decorative Elements -->
                                        <div class="absolute bottom-4 <?= $isLeft ? 'left-4' : 'right-4' ?>">
                                            <div class="flex space-x-1">
                                                <div class="w-8 h-1 bg-gradient-to-r <?= $gradient ?> rounded-full"></div>
                                                <div class="w-4 h-1 bg-gradient-to-r <?= $gradient ?> rounded-full opacity-60"></div>
                                                <div class="w-2 h-1 bg-gradient-to-r <?= $gradient ?> rounded-full opacity-30"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- SCOPED CSS -->
    <style>
        @keyframes slideInFromLeft {
            from {
                opacity: 0;
                transform: translateX(-100px) translateY(50px) rotate(-10deg);
            }
            to {
                opacity: 1;
                transform: translateX(0) translateY(0) rotate(0deg);
            }
        }

        @keyframes slideInFromRight {
            from {
                opacity: 0;
                transform: translateX(100px) translateY(50px) rotate(10deg);
            }
            to {
                opacity: 1;
                transform: translateX(0) translateY(0) rotate(0deg);
            }
        }

        #<?= $sectionId ?> .timeline-item {
            animation: slideInFromLeft 1s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        #<?= $sectionId ?> .timeline-item:nth-child(even) {
            animation-name: slideInFromRight;
        }

        /* Gradient animation for gradient backgrounds */
        <?php if ($backgroundType === 'gradient'): ?>
        #<?= $sectionId ?> {
            background-size: 400% 400%;
            animation: timelineGradientShift 8s ease infinite;
        }
        
        @keyframes timelineGradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        <?php endif; ?>

        /* Responsive Design */
        @media (max-width: 768px) {
            #<?= $sectionId ?> .timeline-item {
                margin-bottom: 3rem !important;
            }
            
            #<?= $sectionId ?> .timeline-item > div {
                flex-direction: column !important;
                align-items: center !important;
            }
            
            #<?= $sectionId ?> .timeline-item .w-5\/12 {
                width: 100% !important;
                margin: 0 !important;
                text-align: center !important;
                padding: 0 1rem;
            }
            
            #<?= $sectionId ?> .timeline-item .absolute.left-1\/2 {
                position: relative !important;
                left: auto !important;
                transform: none !important;
                margin: 0 auto 1rem auto;
            }
            
            #<?= $sectionId ?> .timeline-item .-right-6,
            #<?= $sectionId ?> .timeline-item .-left-6 {
                left: 50% !important;
                right: auto !important;
                transform: translateX(-50%) !important;
            }
        }

        /* Glass Morphism Effect */
        #<?= $sectionId ?> .backdrop-blur-lg {
            backdrop-filter: blur(16px);
        }

        /* Text Gradient */
        #<?= $sectionId ?> .bg-clip-text {
            -webkit-background-clip: text;
            background-clip: text;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Intersection Observer for scroll animations
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationPlayState = 'running';
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });

            // Observe all timeline items in this specific section
            document.querySelectorAll('#<?= $sectionId ?> .timeline-item').forEach(item => {
                item.style.animationPlayState = 'paused';
                observer.observe(item);
            });

            // Add smooth scroll behavior
            document.documentElement.style.scrollBehavior = 'smooth';
        });
    </script>
    <?php
}

// Helper function to get section background style (if not already defined)
if (!function_exists('getSectionBackgroundStyle')) {
    function getSectionBackgroundStyle($section) {
        $backgroundType = $section['background_type'] ?? 'solid';
        $bgColor = $section['background_color'] ?? '#ffffff';
        
        switch ($backgroundType) {
            case 'gradient':
                $gradientType = $section['gradient_type'] ?? 'linear';
                $gradientDirection = $section['gradient_direction'] ?? '45deg';
                $gradientColor1 = $section['gradient_color_1'] ?? $bgColor;
                $gradientColor2 = $section['gradient_color_2'] ?? '#e0e0e0';
                
                if ($gradientType === 'linear') {
                    return "background: linear-gradient({$gradientDirection}, {$gradientColor1}, {$gradientColor2});";
                } else {
                    return "background: radial-gradient(circle, {$gradientColor1}, {$gradientColor2});";
                }
                
            case 'image':
                $backgroundImage = $section['background_image'] ?? '';
                $backgroundPosition = $section['background_position'] ?? 'center center';
                $backgroundSize = $section['background_size'] ?? 'cover';
                $backgroundRepeat = $section['background_repeat'] ?? 'no-repeat';
                
                return "background-image: url('{$backgroundImage}'); background-position: {$backgroundPosition}; background-size: {$backgroundSize}; background-repeat: {$backgroundRepeat};";
                
            default: // solid
                return "background-color: {$bgColor};";
        }
    }
}

// Helper function to get section text color (if not already defined)
if (!function_exists('getSectionTextColor')) {
    function getSectionTextColor($section) {
        return $section['text_color'] ?? '#000000';
    }
}

function renderFaqSection($section, $bgColor, $textColor) {
    // Get styling configuration
    $backgroundType = $section['background_type'] ?? 'gradient';
    
    // Get background styling using the same helper functions as other sections
    $backgroundStyle = getSectionBackgroundStyle($section);
    $sectionTextColor = getSectionTextColor($section);
    
    // Generate unique section ID
    $sectionId = 'faq-section-' . uniqid();
    $faqDataId = 'faq-' . $section['id'];
    
    // Default gradient fallback
    $defaultGradient = '';
    if ($backgroundType === 'gradient' && empty($backgroundStyle)) {
        $defaultGradient = 'bg-gradient-to-r from-blue-600 to-purple-600';
    } 
    
    $faqs = explode('||', $section['content']);
    
    ?>
    
    <section id="<?= $sectionId ?>" class="faq-section <?= $defaultGradient ?> py-16 relative" 
             style="<?= $backgroundStyle ?>">
        
        <!-- Simple background overlay for images -->
        <?php if ($backgroundType === 'image'): ?>
            <div class="absolute inset-0 bg-black bg-opacity-30"></div>
        <?php endif; ?>
        
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <!-- Section Header -->
            <div class="text-center mb-16">
                <div class="inline-flex items-center px-6 py-2 backdrop-blur-lg rounded-full text-sm font-semibold uppercase tracking-wider mb-6 border" 
                     style="background-color: <?= htmlspecialchars($sectionTextColor) ?>20; color: <?= htmlspecialchars($sectionTextColor) ?>; border-color: <?= htmlspecialchars($sectionTextColor) ?>20;">
                    <span class="w-2 h-2 bg-indigo-400 rounded-full mr-2 animate-pulse"></span>
                    FAQ
                </div>
                <h2 class="text-4xl md:text-5xl font-bold text-black mb-2">
                    <?= htmlspecialchars($section['title']) ?>
                </h2>
                <?php if ($section['subtitle']): ?>
                    <p class="text-lg mt-2 opacity-80 max-w-2xl mx-auto" style="color: <?= htmlspecialchars($sectionTextColor) ?>;">
                        <?= htmlspecialchars($section['subtitle']) ?>
                    </p>
                <?php endif; ?>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto rounded-full mt-2"></div>
            </div>
            
            <!-- FAQ Items -->
            <div class="space-y-4">
                <?php foreach ($faqs as $index => $faq): ?>
                    <?php 
                    $parts = explode('|', $faq);
                    if (count($parts) >= 2):
                    $faqId = $faqDataId . '-' . $index;
                    ?>
                    <div class="faq-item bg-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden">
                        <!-- Question Button -->
                        <button class="faq-button w-full p-6 text-left focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50" 
                                data-target="<?= $faqId ?>">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold text-gray-900 pr-4">
                                    <?= htmlspecialchars(trim($parts[0])) ?>
                                </h3>
                                <div class="faq-icon-container flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 text-blue-600 transition-all duration-400">
                                    <i class="faq-icon fas fa-plus text-sm transition-transform duration-400" 
                                       id="icon-<?= $faqId ?>"></i>
                                </div>
                            </div>
                        </button>
                        
                        <!-- Answer with smooth dropdown -->
                        <div class="faq-answer overflow-hidden transition-all duration-500 ease-out" 
                             id="answer-<?= $faqId ?>"
                             style="max-height: 0;">
                            <div class="px-6 pb-6 pt-0 opacity-0 transform translate-y-[-8px] transition-all duration-400 ease-out">
                                <div class="border-t border-gray-200 pt-4">
                                    <p class="text-gray-600 leading-relaxed">
                                        <?= nl2br(htmlspecialchars(trim($parts[1]))) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <!-- Enhanced CSS for smooth animations -->
    <style>
        /* FAQ specific styles - scoped */
        #<?= $sectionId ?> .faq-item:hover {
            transform: translateY(-2px);
        }
        
        /* Smooth answer reveal - uses actual height calculation */
        #<?= $sectionId ?> .faq-answer.active {
            /* Height will be set dynamically via JavaScript */
        }
        
        #<?= $sectionId ?> .faq-answer.active > div {
            opacity: 1 !important;
            transform: translateY(0) !important;
        }
        
        /* Icon animations */
        #<?= $sectionId ?> .faq-icon.active {
            transform: rotate(45deg);
            color: #dc2626;
        }
        
        #<?= $sectionId ?> .faq-icon-container.active {
            background-color: #fee2e2;
        }
        
        /* Gradient animation for gradient backgrounds */
        <?php if ($backgroundType === 'gradient'): ?>
        #<?= $sectionId ?> {
            background-size: 400% 400%;
            animation: gradientMove 8s ease infinite;
        }
        
        @keyframes gradientMove {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        <?php endif; ?>
        
        /* Text shadows for image backgrounds */
        <?php if ($backgroundType === 'image'): ?>
        #<?= $sectionId ?> h2,
        #<?= $sectionId ?> p {
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        }
        <?php endif; ?>
    </style>
    
    <!-- Enhanced JavaScript for smooth dropdown -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const faqButtons = document.querySelectorAll('#<?= $sectionId ?> .faq-button[data-target^="<?= $faqDataId ?>"]');
            
            faqButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const answer = document.getElementById('answer-' + targetId);
                    const icon = document.getElementById('icon-' + targetId);
                    const iconContainer = icon.parentElement;
                    const content = answer.querySelector('div');
                    
                    if (answer && icon) {
                        const isActive = answer.classList.contains('active');
                        
                        if (isActive) {
                            // Close with smooth animation
                            answer.style.maxHeight = '0px';
                            content.style.opacity = '0';
                            content.style.transform = 'translateY(-8px)';
                            
                            // Remove active classes after animation starts
                            setTimeout(() => {
                                answer.classList.remove('active');
                                icon.classList.remove('active');
                                iconContainer.classList.remove('active');
                            }, 50);
                            
                        } else {
                            // Open with smooth animation
                            answer.classList.add('active');
                            icon.classList.add('active');
                            iconContainer.classList.add('active');
                            
                            // Calculate the actual content height
                            const contentHeight = content.scrollHeight;
                            
                            // Set max-height to actual content height for smooth animation
                            answer.style.maxHeight = contentHeight + 'px';
                            
                            // Animate content after a brief delay
                            setTimeout(() => {
                                content.style.opacity = '1';
                                content.style.transform = 'translateY(0)';
                            }, 100);
                        }
                    }
                });
            });
        });
    </script>
    
    <?php
}

function renderNewsletterSection($section, $bgColor, $textColor) {
    ?>
    <section class="py-16" style="background-color: <?= $bgColor ?>; color: <?= $textColor ?>;">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="text-center mb-6">
                <div class="inline-flex items-center px-6 py-2 backdrop-blur-lg rounded-full text-sm font-semibold uppercase tracking-wider mb-6 border" 
                     style="background-color: <?= htmlspecialchars($textColor) ?>20; color: <?= htmlspecialchars($textColor) ?>; border-color: <?= htmlspecialchars($textColor) ?>20;">
                    <span class="w-2 h-2 bg-indigo-400 rounded-full mr-2 animate-pulse"></span>
                    Newsletter
                </div>
                <h2 class="text-4xl md:text-5xl font-bold text-black mb-2">
                    <?= htmlspecialchars($section['title']) ?>
                </h2>
                <?php if ($section['subtitle']): ?>
                    <p class="text-lg mt-2 opacity-80 max-w-2xl mx-auto" style="color: <?= htmlspecialchars($textColor) ?>;">
                        <?= htmlspecialchars($section['subtitle']) ?>
                    </p>
                <?php endif; ?>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto rounded-full mt-2"></div>
            </div>
            <p class="text-lg mb-8"><?= htmlspecialchars($section['content']) ?></p>
            
            <form id="newsletterForm" class="max-w-md mx-auto">
                <div class="flex">
                    <input type="email" id="subscriber_email" name="email" placeholder="Enter your email" required
                           class="flex-1 px-4 py-3 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900">
                    <button type="submit" id="subscribeBtn" 
                            class="bg-blue-600 text-white px-6 py-3 rounded-r-lg hover:bg-blue-700 transition duration-200">
                        Subscribe
                    </button>
                </div>
                <div id="subscribeMessage" class="text-sm mt-4 hidden"></div>
            </form>
        </div>
    </section>

    <script>
    document.getElementById('newsletterForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const email = document.getElementById('subscriber_email').value;
        const btn = document.getElementById('subscribeBtn');
        const message = document.getElementById('subscribeMessage');
        
        btn.disabled = true;
        btn.textContent = 'Subscribing...';
        message.classList.add('hidden');
        
        try {
            const formData = new FormData();
            formData.append('email', email);
            
            const response = await fetch('<?= BASE_URL ?>/subscribe.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Success message with better contrast
                message.className = 'text-sm mt-4 text-green-800 bg-green-100 border border-green-300 p-3 rounded-lg';
                message.textContent = '✓ Successfully subscribed to our newsletter!';
                document.getElementById('subscriber_email').value = '';
            } else {
                // Error message with better contrast
                if (result.message.includes('already subscribed')) {
                    message.className = 'text-sm mt-4 text-orange-800 bg-orange-100 border border-orange-300 p-3 rounded-lg';
                } else {
                    message.className = 'text-sm mt-4 text-red-800 bg-red-100 border border-red-300 p-3 rounded-lg';
                }
                message.textContent = result.message || 'Subscription failed. Please try again.';
            }
        } catch (error) {
            message.className = 'text-sm mt-4 text-red-800 bg-red-100 border border-red-300 p-3 rounded-lg';
            message.textContent = 'Network error. Please try again.';
        }
        
        message.classList.remove('hidden');
        btn.disabled = false;
        btn.textContent = 'Subscribe';
        
        setTimeout(() => {
            message.classList.add('hidden');
        }, 5000);
    });
    </script>
    <?php
}


// Enhanced helper functions with full background image support
function getSectionBackgroundStyle($section) {
    $backgroundType = $section['background_type'] ?? 'solid';
    
    switch ($backgroundType) {
        case 'gradient':
            $direction = $section['gradient_direction'] ?? 'to right';
            $start = $section['gradient_start'] ?? '#3b82f6';
            $end = $section['gradient_end'] ?? '#8b5cf6';
            return "background: linear-gradient($direction, $start, $end);";
            
        case 'image':
            $imageUrl = $section['image_url'] ?? '';
            if (!$imageUrl) {
                return "background-color: #f3f4f6;";
            }
            
            $position = $section['image_position'] ?? 'center';
            $size = $section['image_size'] ?? 'cover';
            $overlay = $section['overlay_color'] ?? '';
            
            $style = "background-image: url('" . htmlspecialchars($imageUrl) . "'); ";
            $style .= "background-position: $position; ";
            $style .= "background-size: $size; ";
            $style .= "background-repeat: no-repeat; ";
            // REMOVED: background-attachment: fixed; (causes iOS issues)
            
            // iOS-specific fixes
            $style .= "background-attachment: scroll; ";  // Explicitly set to scroll
            $style .= "-webkit-background-size: $size; ";  // WebKit prefix for older iOS
            
            if ($overlay) {
                if (strpos($overlay, 'rgba') === 0 || strpos($overlay, 'rgb') === 0) {
                    $style .= "background-color: $overlay; ";
                    $style .= "background-blend-mode: overlay; ";
                } else if (strpos($overlay, '#') === 0) {
                    $style .= "background-color: $overlay; ";
                    $style .= "background-blend-mode: overlay; ";
                }
            }
            
            return $style;
            
        default: // solid
            $bgColor = $section['background_color'] ?? '#60a48e';
            return "background-color: $bgColor;";
    }
}

function getSectionTextColor($section) {
    $backgroundType = $section['background_type'] ?? 'solid';
    
    switch ($backgroundType) {
        case 'gradient':
            return $section['gradient_text_color'] ?? '#ffffff';
        case 'image':
            return $section['image_text_color'] ?? '#ffffff';
        default:
            return $section['text_color'] ?? '#000000';
    }
}

function renderCtaSection($section) {
    // Get styling configuration
    $backgroundType = $section['background_type'] ?? 'solid';
    $bgColor = $section['background_color'] ?? '#60a48e';
    $textColor = $section['text_color'] ?? '#000000';
    
    // Get button alignment and layout settings
    $buttonAlignment = $section['button_alignment'] ?? 'center';
    $buttonLayout = $section['button_layout'] ?? 'horizontal';
    
    // Check for button data with fallbacks
    $primaryButtonText = $section['button_text'] ?? 'Get Started';
    $primaryButtonUrl = $section['button_url'] ?? '#';
    $hasSecondButton = !empty($section['button_text_2']);
    
    // Determine alignment classes
    $alignmentClass = match($buttonAlignment) {
        'left' => 'text-left',
        'right' => 'text-right',
        default => 'text-center'
    };
    
    $justifyClass = match($buttonAlignment) {
        'left' => 'justify-start',
        'right' => 'justify-end',
        default => 'justify-center'
    };
    
    // Determine layout classes
    $layoutClass = $buttonLayout === 'vertical' ? 'flex-col space-y-4' : 'flex-wrap gap-4';
    
    // FIXED: Get iOS-safe background styling
    $backgroundStyle = getIOSSafeBackgroundStyle($section);

    // FIXED: Better text color handling for all background types
    $finalTextColor = $textColor; // Start with the specified text color

    // Only use getSectionTextColor if no text_color is explicitly set
    if (!isset($section['text_color']) && function_exists('getSectionTextColor')) {
        $finalTextColor = getSectionTextColor($section);
    } elseif (isset($section['text_color'])) {
        // Use the explicitly set text color
        $finalTextColor = $section['text_color'];
    }

    // For image backgrounds, provide better contrast options
    if ($backgroundType === 'image') {
        // If no text color is set, use white as default for image backgrounds
        if (!isset($section['text_color'])) {
            $finalTextColor = '#ffffff';
        }
        // But allow override if explicitly set
        if (isset($section['text_color'])) {
            $finalTextColor = $section['text_color'];
        }
    }
    
    // Generate unique section ID to scope CSS
    $sectionId = 'cta-section-' . uniqid();
    
    ?>
    
    <section id="<?= $sectionId ?>" class="cta-section py-20 relative overflow-hidden transition-all duration-500" 
             style="<?= $backgroundStyle ?>">
        
        <!-- SMART CONTROLLABLE DARK OVERLAY for Images -->
        <?php if ($backgroundType === 'image'): ?>
            <?php 
            // SMART OVERLAY LOGIC for CTA sections:
            // - If overlay_opacity is explicitly set to 0, show no overlay
            // - If overlay_opacity is not set, use default 0.5 (50%) for strong CTA readability
            // - If overlay_opacity is set to any other value, use that value
            
            $overlayOpacity = 0.5; // Default 50% for CTA sections (stronger for better readability)
            $overlayColor = $section['overlay_color'] ?? '#000000';
            
            if (isset($section['overlay_opacity'])) {
                $overlayOpacity = max(0, min(1, (float)$section['overlay_opacity']));
            }
            
            // Only show overlay if opacity > 0
            if ($overlayOpacity > 0) {
                ?>
                <!-- Dark overlay for text readability -->
                <div class="absolute inset-0 pointer-events-none" 
                     style="background-color: <?= htmlspecialchars($overlayColor) ?>; opacity: <?= $overlayOpacity ?>; z-index: 1;"></div>
                <?php 
            }
            ?>
        <?php else: ?>
            <!-- Light Pattern Overlay for Solid/Gradient only -->
            <div class="absolute inset-0 opacity-10 pointer-events-none">
                <div style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.3) 1px, transparent 0); background-size: 20px 20px; width: 100%; height: 100%;"></div>
            </div>
        <?php endif; ?>
        
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 <?= $alignmentClass ?> relative z-10">
            <!-- Title -->
            <?php if (!empty($section['title'])): ?>
                <h2 class="cta-title text-3xl md:text-5xl font-bold mb-6 transition-colors duration-300 <?= $backgroundType === 'image' ? 'drop-shadow-lg' : '' ?>" 
                     style="color: <?= htmlspecialchars($finalTextColor) ?>;">
                    <?= htmlspecialchars($section['title']) ?>
                </h2>
            <?php endif; ?>
            
            <!-- Subtitle -->
            <?php if (!empty($section['subtitle'])): ?>
                <p class="cta-subtitle text-xl mb-8 opacity-90 transition-colors duration-300 <?= $backgroundType === 'image' ? 'drop-shadow-md' : '' ?>" 
                    style="color: <?= htmlspecialchars($finalTextColor) ?>;">
                    <?= htmlspecialchars($section['subtitle']) ?>
                </p>
            <?php endif; ?>
            
            <!-- Content -->
            <p class="cta-content text-lg mb-8 opacity-80 transition-colors duration-300 <?= $backgroundType === 'image' ? 'drop-shadow-md' : '' ?>" 
                style="color: <?= htmlspecialchars($finalTextColor) ?>;">
                <?= nl2br(htmlspecialchars($section['content'])) ?>
            </p>
            
            <!-- Buttons Container -->
            <div class="flex <?= $layoutClass ?> <?= $justifyClass ?> items-center">
                <!-- Primary Button - Always show with fallback -->
                <?php
                $primaryBgColor = $section['button_bg_color'] ?? '#3b82f6';
                $primaryTextColor = $section['button_text_color'] ?? '#ffffff';
                ?>
                <a href="<?= htmlspecialchars($primaryButtonUrl) ?>" 
                   class="inline-block px-8 py-4 rounded-lg font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl cta-primary-button group relative overflow-hidden <?= $backgroundType === 'image' ? 'shadow-2xl' : '' ?>"
                   style="background-color: <?= htmlspecialchars($primaryBgColor) ?>; color: <?= htmlspecialchars($primaryTextColor) ?>;">
                    <!-- Button shine effect -->
                    <span class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-0 group-hover:opacity-20 transition-opacity duration-500 transform -skew-x-12 -translate-x-full group-hover:translate-x-full"></span>
                    <span class="relative z-10"><?= htmlspecialchars($primaryButtonText) ?></span>
                </a>
                
                <!-- Secondary Button - Only show if configured -->
                <?php if ($hasSecondButton): ?>
                    <?php
                    $secondaryBgColor = $section['button_bg_color_2'] ?? '#6b7280';
                    $secondaryTextColor = $section['button_text_color_2'] ?? '#ffffff';
                    $buttonStyle2 = $section['button_style_2'] ?? 'solid';
                    $secondaryButtonUrl = $section['button_url_2'] ?? '#';
                    
                    // Apply button style
                    $secondaryStyles = '';
                    $secondaryClasses = 'inline-block px-8 py-4 rounded-lg font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl cta-secondary-button group relative overflow-hidden';
                    
                    // Add extra shadow for image backgrounds
                    if ($backgroundType === 'image') {
                        $secondaryClasses .= ' shadow-2xl';
                    }
                    
                    switch ($buttonStyle2) {
                        case 'outline':
                            $secondaryStyles = "background-color: transparent; color: " . htmlspecialchars($secondaryBgColor) . "; border: 2px solid " . htmlspecialchars($secondaryBgColor) . ";";
                            break;
                        case 'ghost':
                            $secondaryStyles = "background-color: transparent; color: " . htmlspecialchars($secondaryBgColor) . "; border: none;";
                            break;
                        default: // solid
                            $secondaryStyles = "background-color: " . htmlspecialchars($secondaryBgColor) . "; color: " . htmlspecialchars($secondaryTextColor) . ";";
                            break;
                    }
                    ?>
                    <a href="<?= htmlspecialchars($secondaryButtonUrl) ?>" 
                       class="<?= $secondaryClasses ?>"
                       style="<?= $secondaryStyles ?>"
                       onmouseover="handleSecondaryButtonHover(this, '<?= $buttonStyle2 ?>', '<?= htmlspecialchars($secondaryBgColor) ?>', '<?= htmlspecialchars($secondaryTextColor) ?>')"
                       onmouseout="handleSecondaryButtonOut(this, '<?= $buttonStyle2 ?>', '<?= htmlspecialchars($secondaryBgColor) ?>', '<?= htmlspecialchars($secondaryTextColor) ?>')">
                        <!-- Button shine effect -->
                        <span class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-0 group-hover:opacity-20 transition-opacity duration-500 transform -skew-x-12 -translate-x-full group-hover:translate-x-full"></span>
                        <span class="relative z-10"><?= htmlspecialchars($section['button_text_2']) ?></span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <!-- COMPLETELY FIXED CSS - Overrides ALL external styles -->
    <style>
        /* FORCE COMPLETE OVERRIDE OF ALL EXTERNAL STYLES */
        #<?= $sectionId ?> {
            /* Reset all background blend modes */
            background-blend-mode: normal !important;
            mix-blend-mode: normal !important;
            
            <?php if ($backgroundType === 'image'): ?>
            /* Force iOS-safe background properties */
            background-attachment: scroll !important;
            -webkit-background-size: cover !important;
            background-size: cover !important;
            background-position: center !important;
            background-repeat: no-repeat !important;
            
            /* iOS performance optimizations */
            -webkit-transform: translateZ(0);
            transform: translateZ(0);
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
            will-change: transform;
            <?php endif; ?>
        }
        
        /* REMOVE ALL PSEUDO-ELEMENTS THAT MIGHT ADD OVERLAYS */
        #<?= $sectionId ?>::before,
        #<?= $sectionId ?>::after,
        #<?= $sectionId ?> .cta-section::before,
        #<?= $sectionId ?> .cta-section::after,
        #<?= $sectionId ?> .bg-image::before,
        #<?= $sectionId ?> .bg-image::after {
            display: none !important;
            content: none !important;
            background: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
        }
        
        /* OVERRIDE EXTERNAL CSS CLASSES THAT ADD OVERLAYS */
        #<?= $sectionId ?>.bg-overlay-dark::before,
        #<?= $sectionId ?>.bg-overlay-light::before,
        #<?= $sectionId ?> .bg-overlay-dark::before,
        #<?= $sectionId ?> .bg-overlay-light::before {
            display: none !important;
            opacity: 0 !important;
        }
        
        /* iOS Safari specific fixes */
        @supports (-webkit-touch-callout: none) {
            #<?= $sectionId ?> {
                background-attachment: scroll !important;
                -webkit-background-size: cover !important;
            }
        }
        
        /* Scope all styles to the specific CTA section */
        #<?= $sectionId ?> .cta-primary-button {
            position: relative;
            background-image: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            background-size: 200% 100%;
            background-position: 200% 0;
            transition: all 0.3s ease;
        }
        
        #<?= $sectionId ?> .cta-primary-button:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            background-position: -200% 0;
        }
        
        #<?= $sectionId ?> .cta-primary-button:active {
            transform: translateY(0) scale(1.02);
            transition: transform 0.1s ease;
        }
        
        /* Secondary Button Animations */
        #<?= $sectionId ?> .cta-secondary-button {
            position: relative;
            transition: all 0.3s ease;
        }
        
        #<?= $sectionId ?> .cta-secondary-button:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        #<?= $sectionId ?> .cta-secondary-button:active {
            transform: translateY(0) scale(1.02);
            transition: transform 0.1s ease;
        }
        
        /* Enhanced shadow effects for image backgrounds - SCOPED */
        <?php if ($backgroundType === 'image'): ?>
        #<?= $sectionId ?> .cta-primary-button,
        #<?= $sectionId ?> .cta-secondary-button {
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        
        #<?= $sectionId ?> .cta-primary-button:hover,
        #<?= $sectionId ?> .cta-secondary-button:hover {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }
        
        /* Enhanced text shadow for better readability on images - SCOPED */
        #<?= $sectionId ?> .cta-title,
        #<?= $sectionId ?> .cta-subtitle,
        #<?= $sectionId ?> .cta-content {
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
        }
        <?php endif; ?>
        
        /* Pulse animation for CTA section */
        @keyframes ctaPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        
        #<?= $sectionId ?> .cta-primary-button:hover {
            animation: ctaPulse 2s infinite;
        }
        
        /* Responsive adjustments - SCOPED */
        @media (max-width: 768px) {
            #<?= $sectionId ?> .cta-primary-button,
            #<?= $sectionId ?> .cta-secondary-button {
                width: 100%;
                text-align: center;
                margin-bottom: 1rem;
            }
            
            /* Force vertical layout on mobile */
            #<?= $sectionId ?> .flex-wrap {
                flex-direction: column !important;
            }
            
            /* Adjust text sizes on mobile - SCOPED */
            #<?= $sectionId ?> .cta-title {
                font-size: 2rem !important;
            }
            
            #<?= $sectionId ?> .cta-subtitle,
            #<?= $sectionId ?> .cta-content {
                font-size: 1rem !important;
            }
        }
        
        /* Gradient animation for gradient backgrounds - SCOPED */
        <?php if ($backgroundType === 'gradient'): ?>
        #<?= $sectionId ?> {
            background-size: 400% 400%;
            animation: gradientShift 8s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        <?php endif; ?>
        
        /* Dark mode adjustments - SCOPED */
        @media (prefers-color-scheme: dark) {
            #<?= $sectionId ?> .cta-primary-button,
            #<?= $sectionId ?> .cta-secondary-button {
                box-shadow: 0 4px 15px rgba(255, 255, 255, 0.1);
            }
            
            #<?= $sectionId ?> .cta-primary-button:hover,
            #<?= $sectionId ?> .cta-secondary-button:hover {
                box-shadow: 0 8px 25px rgba(255, 255, 255, 0.2);
            }
        }
    </style>
    
    <!-- JavaScript for Enhanced Interactions -->
    <script>
        // Debug function for CTA section
        
        // Enhanced secondary button hover effects
        function handleSecondaryButtonHover(button, style, bgColor, textColor) {
            switch(style) {
                case 'outline':
                    button.style.backgroundColor = bgColor;
                    button.style.color = textColor;
                    button.style.borderColor = bgColor;
                    break;
                case 'ghost':
                    button.style.backgroundColor = bgColor;
                    button.style.color = textColor;
                    break;
                default: // solid
                    button.style.opacity = '0.9';
                    button.style.filter = 'brightness(1.1)';
                    break;
            }
        }
        
        function handleSecondaryButtonOut(button, style, bgColor, textColor) {
            switch(style) {
                case 'outline':
                    button.style.backgroundColor = 'transparent';
                    button.style.color = bgColor;
                    button.style.borderColor = bgColor;
                    break;
                case 'ghost':
                    button.style.backgroundColor = 'transparent';
                    button.style.color = bgColor;
                    break;
                default: // solid
                    button.style.opacity = '1';
                    button.style.filter = 'brightness(1)';
                    break;
            }
        }
        
        // Add click ripple effect
        document.addEventListener('DOMContentLoaded', function() {
            // Run debug (uncomment to debug)
            // debugCtaSection();
            
            const buttons = document.querySelectorAll('#<?= $sectionId ?> .cta-primary-button, #<?= $sectionId ?> .cta-secondary-button');
            
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                        background: rgba(255, 255, 255, 0.3);
                        border-radius: 50%;
                        transform: scale(0);
                        animation: ripple 0.6s ease-out;
                        pointer-events: none;
                        z-index: 1;
                    `;
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
        });
    </script>
    
    <!-- Add ripple animation CSS -->
    <style>
        @keyframes ripple {
            to {
                transform: scale(2);
                opacity: 0;
            }
        }
    </style>
    
    <?php
}

function renderContactFormSection($section, $bgColor, $textColor) {
    // Get database connection
    global $pdo;
    
    // If $pdo is not available globally, create connection
    if (!isset($pdo)) {
        require_once 'config.php';
        // If config.php doesn't create $pdo automatically, create it here
        if (!isset($pdo)) {
            try {
                $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                $recaptchaEnabled = false;
                $recaptchaSiteKey = '';
                $contactDetails = [];
                goto skipDbQuery;
            }
        }
    }
    
    // Get section data and contact details from database
    try {
        $stmt = $pdo->prepare("SELECT * FROM page_sections WHERE id = ? AND section_type = 'contact_form'");
        $stmt->execute([$section['id']]);
        $sectionData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sectionData) {
            $sectionData = $section;
        }
        
        // Get contact details from settings table
        $contactDetails = [];
        $settingsQuery = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('contact_address', 'contact_phone', 'contact_email', 'website_url', 'business_hours') AND setting_value != '' AND setting_value IS NOT NULL");
        $settingsQuery->execute();
        $settingsResults = $settingsQuery->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($settingsResults as $setting) {
            $contactDetails[$setting['setting_key']] = $setting['setting_value'];
        }
        
        // Parse the content field to extract reCAPTCHA configuration
        $config = [];
        if (!empty($sectionData['content'])) {
            $configLines = explode("\n", $sectionData['content']);
            foreach ($configLines as $line) {
                $line = trim($line);
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $config[trim($key)] = trim($value);
                }
            }
        }
        
        // Extract reCAPTCHA settings
        $recaptchaEnabled = !empty($config['recaptcha_enabled']) && $config['recaptcha_enabled'] === 'true';
        $recaptchaSiteKey = $config['recaptcha_site_key'] ?? '';
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $recaptchaEnabled = false;
        $recaptchaSiteKey = '';
        $contactDetails = [];
    }
    
    skipDbQuery:
    
    // Check if we have any contact details to show
    $hasContactDetails = !empty($contactDetails);
    ?>
    
    <style>
    .contact-section {
        background: #f8fafc;
        padding: 4rem 0;
        min-height: 100vh;
    }
    
    .contact-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 2rem;
    }
    
    .contact-header {
        text-align: center;
        margin-bottom: 4rem;
    }
    
    .contact-title {
        font-size: 2.5rem;
        font-weight: 700;
        color: #1a202c;
        margin-bottom: 1rem;
        letter-spacing: -0.025em;
    }
    
    .contact-subtitle {
        font-size: 1.125rem;
        color: #64748b;
        max-width: 600px;
        margin: 0 auto;
        line-height: 1.6;
    }
    
    .contact-content {
        display: grid;
        grid-template-columns: <?= $hasContactDetails ? '1fr 1.2fr' : '1fr' ?>;
        gap: 4rem;
        align-items: start;
    }
    
    /* Contact Details Panel */
    .contact-info-panel {
        background: white;
        border-radius: 12px;
        padding: 2.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        border: 1px solid #e2e8f0;
        height: fit-content;
    }
    
    .contact-info-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #1a202c;
        margin-bottom: 0.75rem;
    }
    
    .contact-info-description {
        color: #64748b;
        margin-bottom: 2rem;
        line-height: 1.5;
    }
    
    .contact-detail-item {
        display: flex;
        align-items: flex-start;
        margin-bottom: 1.5rem;
        padding: 1rem;
        background: #f8fafc;
        border-radius: 8px;
        border-left: 3px solid #3b82f6;
        transition: all 0.2s ease;
    }
    
    .contact-detail-item:hover {
        background: #f1f5f9;
        border-left-color: #2563eb;
    }
    
    .contact-detail-icon {
        width: 18px;
        height: 18px;
        color: #3b82f6;
        margin-right: 0.75rem;
        margin-top: 1px;
        flex-shrink: 0;
    }
    
    .contact-detail-content {
        flex: 1;
    }
    
    .contact-detail-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.25rem;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }
    
    .contact-detail-value {
        color: #1f2937;
        font-size: 0.95rem;
        line-height: 1.4;
    }
    
    /* Contact Form Panel */
    .contact-form-panel {
        background: white;
        border-radius: 12px;
        padding: 2.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        border: 1px solid #e2e8f0;
    }
    
    .form-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #1a202c;
        margin-bottom: 1.5rem;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }
    
    .form-input {
        width: 100%;
        padding: 0.875rem 1rem;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 1rem;
        color: #1f2937;
        background: white;
        transition: all 0.2s ease;
        outline: none;
        font-family: inherit;
    }
    
    .form-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .form-input::placeholder {
        color: #9ca3af;
    }
    
    .form-textarea {
        min-height: 120px;
        resize: vertical;
        font-family: inherit;
    }
    
    .char-counter {
        text-align: right;
        font-size: 0.75rem;
        color: #6b7280;
        margin-top: 0.25rem;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    .submit-btn {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 0.875rem 1.5rem;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }
    
    .submit-btn:hover:not(:disabled) {
        background: #2563eb;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    }
    
    .submit-btn:active:not(:disabled) {
        transform: translateY(0);
    }
    
    .submit-btn:disabled {
        background: #9ca3af;
        cursor: not-allowed;
        transform: none;
    }
    
    .loading-spinner {
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 1s linear infinite;
        margin-right: 0.5rem;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    /* Message Styles */
    .message-box {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        border: 1px solid;
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .message-success {
        background: #f0fdf4;
        border-color: #16a34a;
        color: #15803d;
    }
    
    .message-error {
        background: #fef2f2;
        border-color: #dc2626;
        color: #dc2626;
    }
    
    .message-content {
        flex: 1;
    }
    
    .close-btn {
        background: none;
        border: none;
        cursor: pointer;
        color: inherit;
        opacity: 0.7;
        padding: 0.25rem;
        border-radius: 4px;
        transition: opacity 0.2s;
    }
    
    .close-btn:hover {
        opacity: 1;
    }
    
    .recaptcha-container {
        display: flex;
        justify-content: center;
        margin: 1.5rem 0;
    }
    
    .recaptcha-error {
        background: #fef2f2;
        border: 1px solid #fca5a5;
        color: #dc2626;
        padding: 0.75rem;
        border-radius: 8px;
        font-size: 0.875rem;
        text-align: center;
        margin-top: 0.5rem;
    }
    
    .error-tooltip {
        position: absolute;
        background: #dc2626;
        color: white;
        padding: 0.5rem 0.75rem;
        border-radius: 6px;
        font-size: 0.875rem;
        z-index: 1000;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        max-width: 280px;
        word-wrap: break-word;
    }
    
    .error-tooltip::before {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -5px;
        border-width: 5px;
        border-style: solid;
        border-color: #dc2626 transparent transparent transparent;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .contact-section {
            padding: 2rem 0;
        }
        
        .contact-container {
            padding: 0 1rem;
        }
        
        .contact-title {
            font-size: 2rem;
        }
        
        .contact-content {
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        
        .contact-info-panel,
        .contact-form-panel {
            padding: 1.5rem;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 480px) {
        .contact-title {
            font-size: 1.75rem;
        }
        
        .contact-info-panel,
        .contact-form-panel {
            padding: 1.25rem;
        }
    }
    </style>
    
    <?php if ($recaptchaEnabled && !empty($recaptchaSiteKey)): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
    
    <div class="contact-section">
        <div class="contact-container">
            <div class="text-center mb-16">
                <div class="inline-flex items-center px-6 py-2 backdrop-blur-lg rounded-full text-sm font-semibold uppercase tracking-wider mb-6 border" 
                     style="background-color: <?= htmlspecialchars($textColor) ?>20; color: <?= htmlspecialchars($textColor) ?>; border-color: <?= htmlspecialchars($textColor) ?>20;">
                    <span class="w-2 h-2 bg-indigo-400 rounded-full mr-2 animate-pulse"></span>
                    Contact Us
                </div>
                <h2 class="text-4xl md:text-5xl font-bold text-black mb-2">
                    <?= htmlspecialchars($section['title']) ?>
                </h2>
                <?php if ($section['subtitle']): ?>
                    <p class="text-lg mt-2 opacity-80 max-w-2xl mx-auto" style="color: <?= htmlspecialchars($textColor) ?>;">
                        <?= htmlspecialchars($section['subtitle']) ?>
                    </p>
                <?php endif; ?>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto rounded-full mt-2"></div>
            </div>
            
            <div class="contact-content">
                <!-- Contact Details Panel (only show if data exists) -->
                <?php if ($hasContactDetails): ?>
                <div class="contact-info-panel">
                    <h3 class="contact-info-title">Contact Information</h3>
                    <p class="contact-info-description">We're here to help and answer any question you might have.</p>
                    
                    <?php if (!empty($contactDetails['contact_address'])): ?>
                    <div class="contact-detail-item">
                        <svg class="contact-detail-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                        </svg>
                        <div class="contact-detail-content">
                            <div class="contact-detail-label">Address</div>
                            <div class="contact-detail-value"><?= htmlspecialchars($contactDetails['contact_address']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($contactDetails['contact_phone'])): ?>
                    <div class="contact-detail-item">
                        <svg class="contact-detail-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                        </svg>
                        <div class="contact-detail-content">
                            <div class="contact-detail-label">Phone</div>
                            <div class="contact-detail-value"><?= htmlspecialchars($contactDetails['contact_phone']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($contactDetails['contact_email'])): ?>
                    <div class="contact-detail-item">
                        <svg class="contact-detail-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                        </svg>
                        <div class="contact-detail-content">
                            <div class="contact-detail-label">Email</div>
                            <div class="contact-detail-value"><?= htmlspecialchars($contactDetails['contact_email']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($contactDetails['website_url'])): ?>
                    <div class="contact-detail-item">
                        <svg class="contact-detail-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.083 9h1.946c.089-1.546.383-2.97.837-4.118A6.004 6.004 0 004.083 9zM10 2a8 8 0 100 16 8 8 0 000-16zm0 2c-.076 0-.232.032-.465.262-.238.234-.497.623-.737 1.182-.389.907-.673 2.142-.766 3.556h3.936c-.093-1.414-.377-2.649-.766-3.556-.24-.56-.5-.948-.737-1.182C10.232 4.032 10.076 4 10 4zm3.971 5c-.089-1.546-.383-2.97-.837-4.118A6.004 6.004 0 0115.917 9h-1.946zm-2.003 2H8.032c.093 1.414.377 2.649.766 3.556.24.56.5.948.737 1.182.233.23.389.262.465.262.076 0 .232-.032.465-.262.238-.234.498-.623.737-1.182.389-.907.673-2.142.766-3.556zm1.166 4.118c.454-1.147.748-2.572.837-4.118h1.946a6.004 6.004 0 01-2.783 4.118zm-6.268 0C6.412 13.97 6.118 12.546 6.03 11H4.083a6.004 6.004 0 002.783 4.118z" clip-rule="evenodd"></path>
                        </svg>
                        <div class="contact-detail-content">
                            <div class="contact-detail-label">Website</div>
                            <div class="contact-detail-value"><?= htmlspecialchars($contactDetails['website_url']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($contactDetails['business_hours'])): ?>
                    <div class="contact-detail-item">
                        <svg class="contact-detail-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                        <div class="contact-detail-content">
                            <div class="contact-detail-label">Business Hours</div>
                            <div class="contact-detail-value"><?= htmlspecialchars($contactDetails['business_hours']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Contact Form Panel -->
                <div class="contact-form-panel">
                    <h3 class="form-title"><?= $hasContactDetails ? 'Send us a Message' : 'Contact Us' ?></h3>
                    
                    <!-- AJAX Messages Container -->
                    <div id="ajax-messages-<?= $section['id'] ?>" style="display: none;"></div>
                    
                    <?php if (isset($_SESSION['contact_success'])): ?>
                        <div class="message-box message-success" id="success-message">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="message-content"><?= htmlspecialchars($_SESSION['contact_success']) ?></div>
                            <button onclick="dismissMessage('success-message')" class="close-btn">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            </button>
                        </div>
                        <?php unset($_SESSION['contact_success']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['contact_error'])): ?>
                        <div class="message-box message-error" id="error-message">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="message-content"><?= htmlspecialchars($_SESSION['contact_error']) ?></div>
                            <button onclick="dismissMessage('error-message')" class="close-btn">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            </button>
                        </div>
                        <?php unset($_SESSION['contact_error']); ?>
                    <?php endif; ?>
                    
                    <form method="POST" action="<?= BASE_URL ?>/contact-submit_mail.php" id="contactForm-<?= $section['id'] ?>">
                        <input type="hidden" name="section_id" value="<?= $section['id'] ?>">
                        <input type="hidden" name="ajax_request" value="1">
                        <input type="hidden" name="recaptcha_enabled" value="<?= $recaptchaEnabled ? '1' : '0' ?>">
                        <input type="text" name="honeypot" style="display: none;" tabindex="-1" autocomplete="off">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" placeholder="Enter your full name" required maxlength="100" class="form-input"
                                       value="<?= isset($_SESSION['form_data']['name']) ? htmlspecialchars($_SESSION['form_data']['name']) : '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" placeholder="Enter your email address" required maxlength="255" class="form-input"
                                       value="<?= isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : '' ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" placeholder="Enter the subject of your message" required maxlength="200" class="form-input"
                                   value="<?= isset($_SESSION['form_data']['subject']) ? htmlspecialchars($_SESSION['form_data']['subject']) : '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Message</label>
                            <textarea name="message" placeholder="Enter your message here..." required maxlength="2000" class="form-input form-textarea"><?= isset($_SESSION['form_data']['message']) ? htmlspecialchars($_SESSION['form_data']['message']) : '' ?></textarea>
                            <div class="char-counter">
                                <span id="char-count-<?= $section['id'] ?>">0</span>/2000 characters
                            </div>
                        </div>
                        
                        <?php if ($recaptchaEnabled && !empty($recaptchaSiteKey)): ?>
                            <div class="recaptcha-container">
                                <div class="g-recaptcha" 
                                     data-sitekey="<?= htmlspecialchars($recaptchaSiteKey) ?>"
                                     data-theme="light"
                                     data-size="normal"
                                     id="recaptcha-<?= $section['id'] ?>">
                                </div>
                            </div>
                            <div id="recaptcha-error-<?= $section['id'] ?>" class="recaptcha-error" style="display: none;">
                                Please complete the reCAPTCHA verification.
                            </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="submit-btn" id="submitBtn-<?= $section['id'] ?>">
                            <span id="submit-text-<?= $section['id'] ?>">Send Message</span>
                            <span id="submit-loading-<?= $section['id'] ?>" style="display: none;">
                                <div class="loading-spinner"></div>
                                Sending...
                            </span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Function to dismiss messages with smooth animation
    function dismissMessage(messageId) {
        const message = document.getElementById(messageId);
        if (message) {
            message.style.opacity = '0';
            message.style.transform = 'translateY(-10px)';
            message.style.transition = 'all 0.3s ease-out';
            setTimeout(() => message.remove(), 300);
        }
    }
    
    // Function to show AJAX messages
    function showAjaxMessage(sectionId, message, type) {
        const container = document.getElementById('ajax-messages-' + sectionId);
        const messageId = 'ajax-message-' + sectionId + '-' + Date.now();
        
        const messageClass = type === 'success' ? 'message-success' : 'message-error';
        const icon = type === 'success' 
            ? '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>'
            : '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>';
        
        container.innerHTML = `
            <div class="message-box ${messageClass}" id="${messageId}">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    ${icon}
                </svg>
                <div class="message-content">${message}</div>
                <button onclick="dismissMessage('${messageId}')" class="close-btn">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
        `;
        
        container.style.display = 'block';
        
        // Auto-dismiss
        setTimeout(() => dismissMessage(messageId), type === 'success' ? 5000 : 8000);
        
        // Smooth scroll to message
        container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const sectionId = <?= $section['id'] ?>;
        const form = document.getElementById('contactForm-' + sectionId);
        const submitBtn = document.getElementById('submitBtn-' + sectionId);
        const submitText = document.getElementById('submit-text-' + sectionId);
        const submitLoading = document.getElementById('submit-loading-' + sectionId);
        const messageTextarea = form.querySelector('textarea[name="message"]');
        const charCount = document.getElementById('char-count-' + sectionId);
        const recaptchaEnabled = <?= $recaptchaEnabled ? 'true' : 'false' ?>;
        const recaptchaError = document.getElementById('recaptcha-error-' + sectionId);
        
        // Character counter
        if (messageTextarea && charCount) {
            const updateCharCount = function() {
                const length = messageTextarea.value.length;
                charCount.textContent = length;
                const counterElement = charCount.parentElement;
                
                if (length > 2000) {
                    counterElement.style.color = '#dc2626';
                } else if (length > 1800) {
                    counterElement.style.color = '#f59e0b';
                } else {
                    counterElement.style.color = '#6b7280';
                }
            };
            
            messageTextarea.addEventListener('input', updateCharCount);
            messageTextarea.addEventListener('paste', () => setTimeout(updateCharCount, 10));
            updateCharCount();
        }
        
        // reCAPTCHA validation
        function validateRecaptcha() {
            if (!recaptchaEnabled) return true;
            if (typeof grecaptcha === 'undefined') return false;
            
            const recaptchaResponse = grecaptcha.getResponse();
            if (!recaptchaResponse) {
                if (recaptchaError) {
                    recaptchaError.style.display = 'block';
                    recaptchaError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
                return false;
            }
            
            if (recaptchaError) recaptchaError.style.display = 'none';
            return true;
        }
        
        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Reset field styles
            const inputs = form.querySelectorAll('input, textarea');
            inputs.forEach(input => {
                input.style.borderColor = '';
                input.style.boxShadow = '';
            });
            
            // Client-side validation
            const formData = new FormData(form);
            const name = formData.get('name').trim();
            const email = formData.get('email').trim();
            const subject = formData.get('subject').trim();
            const message = formData.get('message').trim();
            
            let hasError = false;
            
            if (name.length < 2) {
                showFieldError(form.name, 'Please enter a valid name (at least 2 characters).');
                hasError = true;
            }
            
            if (!isValidEmail(email)) {
                showFieldError(form.email, 'Please enter a valid email address.');
                hasError = true;
            }
            
            if (subject.length < 3) {
                showFieldError(form.subject, 'Please enter a valid subject (at least 3 characters).');
                hasError = true;
            }
            
            if (message.length < 10) {
                showFieldError(form.message, 'Please enter a message (at least 10 characters).');
                hasError = true;
            }
            
            if (message.length > 2000) {
                showFieldError(form.message, 'Message is too long (maximum 2000 characters).');
                hasError = true;
            }
            
            if (formData.get('honeypot') !== '') hasError = true;
            if (!validateRecaptcha()) hasError = true;
            
            if (hasError) return false;
            
            // Add reCAPTCHA response
            if (recaptchaEnabled && typeof grecaptcha !== 'undefined') {
                formData.append('g-recaptcha-response', grecaptcha.getResponse());
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitText.style.display = 'none';
            submitLoading.style.display = 'inline-flex';
            
            // Send AJAX request
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAjaxMessage(sectionId, data.message, 'success');
                    form.reset();
                    if (charCount) {
                        charCount.textContent = '0';
                        charCount.parentElement.style.color = '#6b7280';
                    }
                    if (recaptchaEnabled && typeof grecaptcha !== 'undefined') {
                        grecaptcha.reset();
                    }
                } else {
                    showAjaxMessage(sectionId, data.message, 'error');
                    if (recaptchaEnabled && typeof grecaptcha !== 'undefined') {
                        grecaptcha.reset();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAjaxMessage(sectionId, 'An error occurred while sending your message. Please try again.', 'error');
                if (recaptchaEnabled && typeof grecaptcha !== 'undefined') {
                    grecaptcha.reset();
                }
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitText.style.display = 'inline';
                submitLoading.style.display = 'none';
            });
        });
        
        // Field error display
        function showFieldError(field, message) {
            field.style.borderColor = '#dc2626';
            field.style.boxShadow = '0 0 0 3px rgba(220, 38, 38, 0.1)';
            field.focus();
            showTooltipError(field, message);
        }
        
        // Tooltip error
        function showTooltipError(field, message) {
            const existingTooltip = document.querySelector('.error-tooltip');
            if (existingTooltip) existingTooltip.remove();
            
            const tooltip = document.createElement('div');
            tooltip.className = 'error-tooltip';
            tooltip.textContent = message;
            
            document.body.appendChild(tooltip);
            const fieldRect = field.getBoundingClientRect();
            tooltip.style.left = fieldRect.left + 'px';
            tooltip.style.top = (fieldRect.top - tooltip.offsetHeight - 8) + 'px';
            
            setTimeout(() => tooltip.remove(), 4000);
            
            field.addEventListener('focus', function() {
                if (tooltip.parentNode) tooltip.remove();
                field.style.borderColor = '';
                field.style.boxShadow = '';
            }, { once: true });
        }
        
        // Email validation
        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }
    });
    </script>
    
    <?php
    unset($_SESSION['form_data']);
}


// Build project modal content - ULTRA PREMIUM DESIGN
function renderProjectSection($section, $bgColor, $textColor) {
    // Parse projects from JSON content
    $projects = [];
    if (!empty($section['content'])) {
        $decodedProjects = json_decode($section['content'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedProjects)) {
            $projects = $decodedProjects;
        }
    }
    
    // Get all unique categories from projects
    $categories = ['ALL'];
    $projectCategories = [];
    
    foreach ($projects as $project) {
        if (!empty($project['model']) && !in_array($project['model'], $projectCategories)) {
            $projectCategories[] = $project['model'];
        }
    }
    
    // Define your specific categories in order
    $availableCategories = [
        'Foreign Projects',
        'Local Projects', 
        'Residential',
        'Commercial',
        'Industrial',
        'Infrastructure'
    ];
    
    // Only include categories that have projects
    foreach ($availableCategories as $category) {
        if (in_array($category, $projectCategories)) {
            $categories[] = $category;
        }
    }
    
    // If no projects with these categories, show all available ones
    if (count($categories) <= 1 && !empty($projectCategories)) {
        $categories = array_merge(['ALL'], $projectCategories);
    }
    
    // Generate unique IDs for this section
    $sectionId = 'projects_' . $section['id'];
    $modalId = 'projectModal_' . $section['id'];
    ?>
    
    <section class="py-16" style="background-color: <?= $bgColor ?>; color: <?= $textColor ?>;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Section Header -->
            <div class="text-center mb-16">
                <div class="inline-flex items-center px-6 py-2 backdrop-blur-lg rounded-full text-sm font-semibold uppercase tracking-wider mb-6 border" 
                     style="background-color: <?= htmlspecialchars($textColor) ?>20; color: <?= htmlspecialchars($textColor) ?>; border-color: <?= htmlspecialchars($textColor) ?>20;">
                    <span class="w-2 h-2 bg-indigo-400 rounded-full mr-2 animate-pulse"></span>
                    Projects
                </div>
                <h2 class="text-4xl md:text-5xl font-bold text-black mb-2" style="color: <?= htmlspecialchars($textColor) ?>;">
                    <?= htmlspecialchars($section['title']) ?>
                </h2>
                <?php if ($section['subtitle']): ?>
                    <p class="text-lg mt-2 opacity-80 max-w-2xl mx-auto" style="color: <?= htmlspecialchars($textColor) ?>;">
                        <?= htmlspecialchars($section['subtitle']) ?>
                    </p>
                <?php endif; ?>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto rounded-full mt-2"></div>
            </div>
            
            <!-- Category Filter Tabs -->
            <div class="mb-8">
                <div class="border-2 border-gray-300 rounded-lg p-1.5 bg-white">
                    <div class="flex flex-wrap justify-center gap-1">
                        <?php foreach ($categories as $index => $category): ?>
                            <button onclick="filterProjects('<?= $sectionId ?>', '<?= $category ?>')" 
                                    class="category-tab px-3 py-1.5 text-xs font-medium rounded-md transition-all duration-200 
                                           <?= $index === 0 ? 'bg-green-600 text-white' : 'text-gray-700 hover:bg-gray-100' ?>"
                                    data-category="<?= $category ?>"
                                    data-section="<?= $sectionId ?>">
                                <?php if ($category === 'ALL'): ?>
                                    <i class="fas fa-th-large mr-1 text-xs"></i>
                                <?php endif; ?>
                                <?= htmlspecialchars($category) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <?php if (empty($projects)): ?>
                <div class="text-center py-12">
                    <div class="text-6xl mb-4 opacity-50">🏗️</div>
                    <p class="text-xl opacity-70">No projects to display yet.</p>
                </div>
            <?php else: ?>
                <!-- Projects Grid -->
                <div id="<?= $sectionId ?>_grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($projects as $index => $project): ?>
                        <div class="project-card bg-white rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1" 
                             data-category="<?= htmlspecialchars($project['model'] ?? 'OTHER') ?>"
                             data-project-index="<?= $index ?>">
                            
                            <!-- Project Image Container with Lazy Loading -->
                            <div class="relative aspect-video overflow-hidden">
                                <?php if (!empty($project['images']) && !empty($project['images'][0])): ?>
                                    <img data-src="<?= getProjectImageUrl($project['images'][0]) ?>" 
                                         alt="<?= htmlspecialchars($project['name']) ?>"
                                         class="lazy-image w-full h-full object-cover transition-transform duration-300 bg-gray-200"
                                         loading="lazy">
                                    <!-- Loading Placeholder -->
                                    <div class="lazy-placeholder absolute inset-0 flex items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200">
                                        <div class="text-center">
                                            <div class="animate-pulse">
                                                <div class="w-12 h-12 bg-gray-300 rounded-full mx-auto mb-2"></div>
                                                <div class="h-2 bg-gray-300 rounded w-16 mx-auto"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200">
                                        <div class="text-center">
                                            <i class="fas fa-image text-4xl text-gray-400 mb-2"></i>
                                            <p class="text-gray-500 text-sm">No Image</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Desktop Overlay (hidden on mobile) -->
                                <div class="desktop-overlay absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 hover-overlay transition-all duration-300 hidden md:block">
                                    <div class="absolute bottom-0 left-0 right-0 p-6 text-white">
                                        <h3 class="text-xl font-bold mb-2 transform translate-y-4 transition-transform duration-300"><?= htmlspecialchars($project['name']) ?></h3>
                                        
                                        <?php if (!empty($project['client'])): ?>
                                            <p class="text-sm text-gray-200 mb-3 transform translate-y-4 transition-transform duration-300">
                                                <i class="fas fa-building mr-1"></i>
                                                <?= htmlspecialchars($project['client']) ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <button onclick="openProjectModal('<?= $modalId ?>', <?= $index ?>)" 
                                                class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all duration-200 font-medium text-sm transform translate-y-6 opacity-0">
                                            SEE MORE
                                            <i class="fas fa-arrow-right ml-2"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Mobile Overlay (visible on mobile only) -->
                                <div class="mobile-overlay absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent block md:hidden">
                                    <div class="absolute bottom-0 left-0 right-0 p-4 text-white">
                                        <h3 class="text-lg font-bold mb-2"><?= htmlspecialchars($project['name']) ?></h3>
                                        
                                        <?php if (!empty($project['client'])): ?>
                                            <p class="text-sm text-gray-200 mb-3 flex items-center">
                                                <i class="fas fa-building mr-2"></i>
                                                <?= htmlspecialchars($project['client']) ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <button onclick="openProjectModal('<?= $modalId ?>', <?= $index ?>)" 
                                                class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all duration-200 font-medium text-sm">
                                            <i class="fas fa-eye mr-2"></i>SEE MORE
                                            <i class="fas fa-arrow-right ml-2"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Status Badge -->
                                <?php if (!empty($project['status'])): ?>
                                    <div class="absolute top-3 right-3">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= getProjectStatusClass($project['status']) ?>">
                                            <?= htmlspecialchars($project['status']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Category Badge -->
                                <?php if (!empty($project['model'])): ?>
                                    <div class="absolute top-3 left-3">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?= htmlspecialchars($project['model']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- No Results Message -->
                <div id="<?= $sectionId ?>_no_results" class="hidden text-center py-12">
                    <div class="text-6xl mb-4 opacity-50">🔍</div>
                    <p class="text-xl opacity-70">No projects found in this category.</p>
                    <button onclick="filterProjects('<?= $sectionId ?>', 'ALL')" 
                            class="mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Show All Projects
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Project Modal - MODERN & RICH DESIGN -->
    <div id="<?= $modalId ?>" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden backdrop-blur-sm" onclick="closeProjectModal('<?= $modalId ?>')">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-hidden" onclick="event.stopPropagation();">
                <!-- Modal Header - Enhanced -->
                <div class="relative bg-gradient-to-r from-slate-800 to-slate-900 px-8 py-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-2xl font-bold text-white mb-1" id="<?= $modalId ?>_title">Project Details</h3>
                            <p class="text-slate-300 text-sm">Comprehensive project overview</p>
                        </div>
                        <button onclick="closeProjectModal('<?= $modalId ?>')" 
                                class="text-slate-400 hover:text-white transition-all duration-200 bg-slate-700 hover:bg-slate-600 rounded-full p-2">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                    <!-- Decorative gradient line -->
                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 via-purple-500 to-green-500"></div>
                </div>
                
                <!-- Modal Content - Rich Design -->
                <div class="p-8 overflow-y-auto max-h-[calc(90vh-120px)] bg-gradient-to-br from-gray-50 to-white" id="<?= $modalId ?>_content">
                    <div class="modal-loading flex flex-col items-center justify-center py-16">
                        <div class="relative">
                            <div class="animate-spin rounded-full h-16 w-16 border-4 border-slate-200 border-t-blue-600 mb-4"></div>
                            <div class="absolute inset-0 rounded-full border-4 border-transparent border-r-purple-400 animate-pulse"></div>
                        </div>
                        <span class="text-slate-600 font-medium">Loading project details...</span>
                        <div class="mt-4 w-32 h-1 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full animate-pulse"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        'use strict';
        
        // Store projects data for this section
        const projectsBasicData = <?= json_encode(array_map(function($project) {
            return [
                'name' => $project['name'] ?? '',
                'model' => $project['model'] ?? '',
                'status' => $project['status'] ?? '',
                'client' => $project['client'] ?? '',
                'location' => $project['location'] ?? '',
                'images' => isset($project['images']) ? [$project['images'][0] ?? ''] : []
            ];
        }, $projects)) ?>;
        
        let fullProjectsData = null;
        const modalCache = new Map();
        const sectionId = '<?= $sectionId ?>';
        const modalId = '<?= $modalId ?>';
        
        // Lazy loading for images
        function initLazyLoading() {
            const lazyImages = document.querySelectorAll('.lazy-image');
            
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            const placeholder = img.parentElement.querySelector('.lazy-placeholder');
                            
                            img.src = img.dataset.src;
                            img.onload = () => {
                                img.classList.remove('bg-gray-200');
                                if (placeholder) {
                                    placeholder.style.opacity = '0';
                                    setTimeout(() => placeholder.remove(), 300);
                                }
                            };
                            img.onerror = () => {
                                if (placeholder) {
                                    placeholder.innerHTML = `
                                        <div class="text-center">
                                            <i class="fas fa-exclamation-triangle text-2xl text-red-400 mb-2"></i>
                                            <p class="text-red-500 text-xs">Failed to load</p>
                                        </div>
                                    `;
                                }
                            };
                            
                            observer.unobserve(img);
                        }
                    });
                });
                
                lazyImages.forEach(img => imageObserver.observe(img));
            } else {
                lazyImages.forEach(img => {
                    img.src = img.dataset.src;
                });
            }
        }
        
        async function loadFullProjectData() {
            if (fullProjectsData) return fullProjectsData;
            
            try {
                fullProjectsData = <?= json_encode($projects) ?>;
                return fullProjectsData;
            } catch (error) {
                console.error('Failed to load full project data:', error);
                return projectsBasicData;
            }
        }
        
        // Filter projects by category
        function filterProjects(sectionId, category) {
            const projectCards = document.querySelectorAll(`#${sectionId}_grid .project-card`);
            const noResultsDiv = document.getElementById(`${sectionId}_no_results`);
            const categoryTabs = document.querySelectorAll(`[data-section="${sectionId}"]`);
            
            // Debug logging to help identify the issue
            if (!noResultsDiv) {
                console.warn(`Element with ID "${sectionId}_no_results" not found`);
            }
            
            let visibleCount = 0;
            
            categoryTabs.forEach(tab => {
                if (tab.dataset.category === category) {
                    tab.className = 'category-tab px-3 py-1.5 text-xs font-medium rounded-md transition-all duration-200 bg-green-600 text-white';
                } else {
                    tab.className = 'category-tab px-3 py-1.5 text-xs font-medium rounded-md transition-all duration-200 text-gray-700 hover:bg-gray-100';
                }
            });
            
            const cardsToShow = [];
            const cardsToHide = [];
            
            projectCards.forEach(card => {
                const cardCategory = card.dataset.category;
                const shouldShow = category === 'ALL' || cardCategory === category;
                const isCurrentlyVisible = card.style.display !== 'none' && card.style.opacity !== '0';
                
                if (shouldShow) {
                    cardsToShow.push({ card, isCurrentlyVisible });
                    visibleCount++;
                } else if (isCurrentlyVisible) {
                    cardsToHide.push(card);
                }
            });
            
            projectCards.forEach(card => {
                card.style.transition = 'none';
            });
            
            projectCards[0]?.offsetHeight;
            
            requestAnimationFrame(() => {
                projectCards.forEach(card => {
                    card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                });
                
                cardsToHide.forEach(card => {
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.8)';
                    
                    setTimeout(() => {
                        card.style.display = 'none';
                        card.style.transform = 'scale(1)';
                    }, 300);
                });
                
                cardsToShow.forEach(({ card, isCurrentlyVisible }, index) => {
                    if (!isCurrentlyVisible) {
                        card.style.display = 'block';
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(20px)';
                        
                        setTimeout(() => {
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        }, index * 50);
                    } else {
                        card.style.display = 'block';
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }
                });
            });
            
            setTimeout(() => {
                // Add null check to prevent the error
                if (noResultsDiv) {
                    if (visibleCount === 0) {
                        noResultsDiv.classList.remove('hidden');
                    } else {
                        noResultsDiv.classList.add('hidden');
                    }
                } else {
                    console.warn(`No results div not found for section: ${sectionId}`);
                }
            }, 100);
        }
        
        // Open project modal with reduced 2-second loading
        async function openProjectModal(modalId, projectIndex) {
            const modal = document.getElementById(modalId);
            const content = document.getElementById(modalId + '_content');
            const title = document.getElementById(modalId + '_title');
            
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            modal.style.opacity = '0';
            modal.style.transform = 'scale(0.95)';
            
            requestAnimationFrame(() => {
                modal.style.transition = 'all 0.3s ease';
                modal.style.opacity = '1';
                modal.style.transform = 'scale(1)';
            });
            
            // Simplified loading animation
            content.innerHTML = `
                <div class="flex flex-col items-center justify-center py-16">
                    <div class="relative mb-6">
                        <div class="w-16 h-16 border-4 border-slate-200 border-t-blue-600 rounded-full animate-spin"></div>
                        <div class="absolute inset-0 w-16 h-16 border-4 border-transparent border-r-purple-500 rounded-full animate-spin-reverse"></div>
                    </div>
                    
                    <h3 class="text-xl font-semibold text-slate-800 mb-2">Loading Project</h3>
                    <p class="text-slate-600" id="loadingText">Please wait...</p>
                    
                    <div class="w-64 h-1 bg-slate-200 rounded-full overflow-hidden mt-4">
                        <div class="h-full bg-gradient-to-r from-blue-500 to-purple-500 rounded-full animate-progress-fast"></div>
                    </div>
                </div>
            `;
            
            const loadingTexts = [
                "Please wait...",
                "Loading content...", 
                "Almost ready..."
            ];
            
            let textIndex = 0;
            const textInterval = setInterval(() => {
                const loadingTextElement = document.getElementById('loadingText');
                if (loadingTextElement && textIndex < loadingTexts.length) {
                    loadingTextElement.textContent = loadingTexts[textIndex];
                    textIndex++;
                } else {
                    clearInterval(textInterval);
                }
            }, 600);
            
            const startTime = Date.now();
            
            try {
                const cacheKey = `project_${projectIndex}`;
                let projectTitle = '';
                let projectContent = '';
                
                if (modalCache.has(cacheKey)) {
                    const cachedData = modalCache.get(cacheKey);
                    projectTitle = cachedData.title;
                    projectContent = cachedData.content;
                } else {
                    const projects = await loadFullProjectData();
                    const project = projects[projectIndex];
                    
                    if (!project) {
                        projectContent = '<div class="text-center py-8 text-red-600">Project not found</div>';
                        projectTitle = 'Error';
                    } else {
                        projectTitle = project.name || 'Project Details';
                        projectContent = buildProjectModalContent(project);
                        
                        modalCache.set(cacheKey, {
                            title: projectTitle,
                            content: projectContent
                        });
                    }
                }
                
                // Reduced to 2 seconds maximum
                const elapsedTime = Date.now() - startTime;
                const remainingTime = Math.max(0, 2000 - elapsedTime);
                
                setTimeout(() => {
                    clearInterval(textInterval);
                    
                    title.textContent = projectTitle;
                    
                    content.style.transition = 'opacity 0.3s ease';
                    content.style.opacity = '0';
                    
                    setTimeout(() => {
                        content.innerHTML = projectContent;
                        content.style.opacity = '1';
                        
                        // Simple entrance animation
                        const cards = content.querySelectorAll('.bg-white, .bg-gradient-to-br');
                        cards.forEach((card, index) => {
                            card.style.opacity = '0';
                            card.style.transform = 'translateY(10px)';
                            card.style.transition = 'all 0.4s ease';
                            
                            setTimeout(() => {
                                card.style.opacity = '1';
                                card.style.transform = 'translateY(0)';
                            }, index * 100);
                        });
                    }, 200);
                }, remainingTime);
                
            } catch (error) {
                console.error('Error loading project:', error);
                setTimeout(() => {
                    clearInterval(textInterval);
                    content.innerHTML = '<div class="text-center py-8 text-red-600">Failed to load project details</div>';
                }, 2000);
            }
        }
        
        function closeProjectModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        
        // Build project modal content
        function buildProjectModalContent(project) {
            let html = '';
            
            if (project.images && project.images.length > 0) {
                html += `
                    <div class="mb-8">
                        <div class="relative mb-6">
                            <div class="aspect-video rounded-xl overflow-hidden shadow-lg bg-gradient-to-br from-slate-100 to-slate-200">
                                <img src="${getProjectImageUrl(project.images[0])}" 
                                     alt="Main project image" 
                                     class="w-full h-full object-cover hover:scale-105 transition-transform duration-500 cursor-pointer"
                                     onclick="openImageLightbox('${getProjectImageUrl(project.images[0])}', 0)"
                                     onload="this.style.opacity='1';" 
                                     style="opacity: 0;">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent pointer-events-none"></div>
                                <div class="absolute top-4 right-4">
                                    <button onclick="openImageLightbox('${getProjectImageUrl(project.images[0])}', 0)" 
                                            class="bg-white/90 backdrop-blur-sm text-slate-700 px-4 py-2 rounded-full text-sm font-medium hover:bg-white transition-all duration-200 shadow-lg">
                                        <i class="fas fa-images mr-2"></i>View Gallery (${project.images.length})
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        ${project.images.length > 1 ? `
                        <div class="flex gap-3 overflow-x-auto pb-2">
                            ${project.images.slice(1, 6).map((image, index) => {
                                const imageUrl = getProjectImageUrl(image);
                                return `
                                    <div class="flex-shrink-0 w-20 h-20 rounded-lg overflow-hidden shadow-md hover:shadow-lg transition-all duration-200 cursor-pointer group" 
                                         onclick="openImageLightbox('${imageUrl}', ${index + 1})">
                                        <img src="${imageUrl}" 
                                             alt="Thumbnail ${index + 2}" 
                                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                                    </div>
                                `;
                            }).join('')}
                            ${project.images.length > 6 ? `
                                <div class="flex-shrink-0 w-20 h-20 rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center shadow-md hover:shadow-lg transition-all duration-200 cursor-pointer">
                                    <div class="text-center text-slate-600">
                                        <i class="fas fa-plus text-sm mb-1"></i>
                                        <div class="text-xs font-medium">+${project.images.length - 6}</div>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                        ` : ''}
                    </div>
                `;
            }
            
            html += '<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">';
            html += '<div class="lg:col-span-2 space-y-8">';
            
            if (project.description) {
                html += `
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-shadow duration-200">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-file-alt text-white"></i>
                            </div>
                            <h4 class="text-xl font-semibold text-slate-800">Project Overview</h4>
                        </div>
                        <p class="text-slate-600 leading-relaxed">${escapeHtml(project.description)}</p>
                    </div>
                `;
            }
            
            if (project.technologies) {
                html += `
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-shadow duration-200">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-cogs text-white"></i>
                            </div>
                            <h4 class="text-xl font-semibold text-slate-800">Technologies Used</h4>
                        </div>
                        <div class="flex flex-wrap gap-2">
                `;
                
                project.technologies.split(',').forEach(tech => {
                    html += `<span class="px-4 py-2 bg-gradient-to-r from-purple-50 to-purple-100 text-purple-700 rounded-full text-sm font-medium border border-purple-200 hover:shadow-sm transition-all duration-200">${escapeHtml(tech.trim())}</span>`;
                });
                
                html += `</div></div>`;
            }
            
            if (project.role) {
                html += `
                    <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl border border-green-200 p-6 hover:shadow-md transition-shadow duration-200">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-green-600 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-user-tie text-white"></i>
                            </div>
                            <h4 class="text-xl font-semibold text-slate-800">Our Role</h4>
                        </div>
                        <p class="text-green-700 font-medium">${escapeHtml(project.role)}</p>
                    </div>
                `;
            }
            
            html += '</div>';
            html += '<div class="space-y-6">';
            
            html += `
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-shadow duration-200">
                    <h4 class="text-lg font-semibold text-slate-800 mb-4 flex items-center">
                        <i class="fas fa-info-circle text-slate-500 mr-2"></i>
                        Project Details
                    </h4>
                    <div class="space-y-4">
            `;
            
            const projectInfo = [
                { label: 'Status', value: project.status, icon: 'fas fa-flag', color: getStatusColor(project.status) },
                { label: 'Category', value: project.model, icon: 'fas fa-tag', color: 'text-blue-600' },
                { label: 'Client', value: project.client, icon: 'fas fa-building', color: 'text-indigo-600' },
                { label: 'Location', value: project.location, icon: 'fas fa-map-marker-alt', color: 'text-red-600' },
                { label: 'Value', value: project.value, icon: 'fas fa-dollar-sign', color: 'text-green-600' },
                { label: 'Architects', value: project.architects, icon: 'fas fa-drafting-compass', color: 'text-orange-600' }
            ];
            
            projectInfo.forEach(info => {
                if (info.value && info.value.trim()) {
                    html += `
                        <div class="flex items-start space-x-3 p-3 rounded-lg hover:bg-slate-50 transition-colors duration-200">
                            <div class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                                <i class="${info.icon} ${info.color} text-sm"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium text-slate-500">${info.label}</div>
                                <div class="text-slate-900 font-medium break-words">${escapeHtml(info.value)}</div>
                            </div>
                        </div>
                    `;
                }
            });
            
            html += '</div></div>';
            
            if (project.githubUrl || project.demoUrl) {
                html += `
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-shadow duration-200">
                        <h4 class="text-lg font-semibold text-slate-800 mb-4 flex items-center">
                            <i class="fas fa-external-link-alt text-slate-500 mr-2"></i>
                            Quick Links
                        </h4>
                        <div class="space-y-3">
                `;
                
                if (project.githubUrl) {
                    html += `
                        <a href="${escapeHtml(project.githubUrl)}" target="_blank" 
                           class="flex items-center justify-center w-full p-3 bg-gradient-to-r from-gray-800 to-gray-900 text-white rounded-lg hover:from-gray-700 hover:to-gray-800 transition-all duration-200 group">
                            <i class="fab fa-github mr-3 group-hover:scale-110 transition-transform duration-200"></i>
                            <span class="font-medium">View on GitHub</span>
                            <i class="fas fa-external-link-alt ml-2 text-sm opacity-70"></i>
                        </a>
                    `;
                }
                
                if (project.demoUrl) {
                    html += `
                        <a href="${escapeHtml(project.demoUrl)}" target="_blank" 
                           class="flex items-center justify-center w-full p-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-500 hover:to-blue-600 transition-all duration-200 group">
                            <i class="fas fa-globe mr-3 group-hover:scale-110 transition-transform duration-200"></i>
                            <span class="font-medium">Live Demo</span>
                            <i class="fas fa-external-link-alt ml-2 text-sm opacity-70"></i>
                        </a>
                    `;
                }
                
                html += '</div></div>';
            }
            
            html += '</div>';
            html += '</div>';
            
            return html;
        }
        
        function getStatusColor(status) {
            switch ((status || '').toUpperCase()) {
                case 'COMPLETED': return 'text-green-600';
                case 'IN PROGRESS': return 'text-blue-600';
                case 'ON HOLD': return 'text-yellow-600';
                case 'PLANNED': return 'text-purple-600';
                default: return 'text-slate-600';
            }
        }
        
        function getProjectImageUrl(imagePath) {
            if (!imagePath) return '';
            
            if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
                return imagePath;
            }
            
            if (imagePath.startsWith('data:')) {
                return imagePath;
            }
            
            const baseUrl = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '');
            if (imagePath.startsWith('uploads/')) {
                return baseUrl + '/' + imagePath;
            }
            
            return baseUrl + '/uploads/' + imagePath;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Lightbox functionality
        let currentLightboxImages = [];
        let currentImageIndex = 0;
        
        function openImageLightbox(imageUrl, startIndex = 0) {
            currentLightboxImages = [imageUrl];
            currentImageIndex = 0;
            
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
        
        // Make functions global
        window.filterProjects = filterProjects;
        window.openProjectModal = openProjectModal;
        window.closeProjectModal = closeProjectModal;
        window.openImageLightbox = openImageLightbox;
        window.closeLightbox = closeLightbox;
        
        // Event listeners
        function setupModalEvents() {
            const modal = document.getElementById('<?= $modalId ?>');
            
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeProjectModal('<?= $modalId ?>');
                }
            });
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeProjectModal('<?= $modalId ?>');
                }
            });
        }
        
        setupModalEvents();
        
        // Initialize on DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            initLazyLoading();
            filterProjects('<?= $sectionId ?>', 'ALL');
            setupMobileInteractions();
            
            document.addEventListener('click', function(e) {
                const lightbox = document.getElementById('imageLightbox');
                if (lightbox && e.target === lightbox) {
                    closeLightbox();
                }
            });
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const lightbox = document.getElementById('imageLightbox');
                    if (lightbox && !lightbox.classList.contains('hidden')) {
                        closeLightbox();
                    }
                }
            });
            
            let resizeTimeout;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    initLazyLoading();
                }, 250);
            });
        });
        
        // Mobile interaction setup
        function setupMobileInteractions() {
            const projectCards = document.querySelectorAll('.project-card');
            
            projectCards.forEach(card => {
                // Touch start - show button immediately
                card.addEventListener('touchstart', function(e) {
                    // Remove active state from all other cards
                    projectCards.forEach(otherCard => {
                        if (otherCard !== this) {
                            otherCard.classList.remove('active-touch');
                        }
                    });
                    
                    // Add active state to this card
                    this.classList.add('active-touch');
                }, { passive: true });
                
                // Touch end - hide button after short delay
                card.addEventListener('touchend', function(e) {
                    // Keep button visible for a moment
                    setTimeout(() => {
                        this.classList.remove('active-touch');
                    }, 500); // Hide after 500ms
                }, { passive: true });
                
                // Touch cancel - hide button immediately
                card.addEventListener('touchcancel', function(e) {
                    this.classList.remove('active-touch');
                }, { passive: true });
                
                // Mouse events for testing on desktop (optional)
                card.addEventListener('mouseenter', function(e) {
                    const isMobile = window.innerWidth < 768;
                    if (!isMobile) return; // Only on mobile breakpoint
                    
                    projectCards.forEach(otherCard => {
                        if (otherCard !== this) {
                            otherCard.classList.remove('active-touch');
                        }
                    });
                    this.classList.add('active-touch');
                });
                
                card.addEventListener('mouseleave', function(e) {
                    const isMobile = window.innerWidth < 768;
                    if (!isMobile) return;
                    
                    this.classList.remove('active-touch');
                });
            });
            
            // Hide buttons when touching outside
            document.addEventListener('touchstart', function(e) {
                if (!e.target.closest('.project-card')) {
                    projectCards.forEach(card => {
                        card.classList.remove('active-touch');
                    });
                }
            }, { passive: true });
        }
        
    })();
    </script>
    
    <style>
    /* Consistent Card Design - Web and Mobile */
    .project-card {
        transition: all 0.3s ease;
        transform: translateZ(0);
    }
    
    /* Desktop hover effects */
    @media (min-width: 768px) {
        .project-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .project-card:hover .lazy-image {
            transform: scale(1.1);
        }
        
        .project-card:hover .desktop-overlay {
            opacity: 1 !important;
        }
        
        .project-card:hover .desktop-overlay h3 {
            transform: translateY(0) !important;
        }
        
        .project-card:hover .desktop-overlay p {
            transform: translateY(0) !important;
        }
        
        .project-card:hover .desktop-overlay button {
            opacity: 1 !important;
            transform: translateY(0) !important;
        }
        
        .desktop-overlay button:hover {
            background-color: #16a34a !important;
            transform: translateY(0) scale(1.05) !important;
        }
    }
    
    /* Mobile styles - button hidden by default, shows on touch */
    @media (max-width: 767px) {
        .project-card {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            cursor: pointer;
        }
        
        /* Default mobile overlay - lighter, button hidden */
        .mobile-overlay {
            background: linear-gradient(to top, rgba(0, 0, 0, 0.6) 0%, rgba(0, 0, 0, 0.2) 50%, transparent 100%);
            transition: background 0.2s ease;
        }
        
        /* Mobile button - completely hidden by default */
        .mobile-see-more-btn {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.4);
            font-weight: 600;
            letter-spacing: 0.025em;
            opacity: 0;
            transform: translateY(1rem);
            pointer-events: none;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        /* Show button only when card is actively touched */
        .project-card.active-touch .mobile-overlay {
            background: linear-gradient(to top, rgba(0, 0, 0, 0.85) 0%, rgba(0, 0, 0, 0.4) 50%, transparent 100%);
        }
        
        .project-card.active-touch .mobile-see-more-btn {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        
        .project-card.active-touch {
            transform: scale(1.02);
            box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.2), 0 4px 10px -2px rgba(0, 0, 0, 0.1);
        }
        
        /* Text shadows for better readability */
        .mobile-overlay h3 {
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.7);
        }
        
        .mobile-overlay p {
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.7);
        }
        
        /* Button active state */
        .mobile-see-more-btn:active {
            background: linear-gradient(135deg, #15803d 0%, #166534 100%);
            transform: translateY(0) scale(0.95);
            box-shadow: 0 2px 8px rgba(22, 163, 74, 0.3);
        }
        
        /* Touch feedback */
        .project-card {
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }
    }
    
    /* Image transitions */
    .lazy-image {
        transition: opacity 0.3s ease, transform 0.3s ease;
    }
    
    .lazy-placeholder {
        transition: opacity 0.3s ease;
    }
    
    /* Modal and lightbox styles */
    .modal-loading {
        min-height: 200px;
    }
    
    .category-tab {
        will-change: background-color, color;
    }
    
    .modal-image {
        transition: opacity 0.3s ease;
        min-height: 80px;
    }
    
    /* Premium loading animations */
    #<?= $modalId ?> {
        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    
    @keyframes progress-fast {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(0); }
    }
    
    .animate-progress-fast {
        animation: progress-fast 2s ease forwards;
    }
    
    @keyframes spin-reverse {
        from { transform: rotate(360deg); }
        to { transform: rotate(0deg); }
    }
    
    .animate-spin-reverse {
        animation: spin-reverse 1.5s linear infinite;
    }
    
    #<?= $modalId ?> .bg-white {
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        border: 1px solid rgba(226, 232, 240, 0.8);
        backdrop-filter: blur(20px);
    }
    
    #imageLightbox {
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }
    
    #lightboxImage {
        max-height: 90vh;
        max-width: 90vw;
        transition: opacity 0.3s ease;
    }
    
    /* Accessibility - Reduced motion support */
    @media (prefers-reduced-motion: reduce) {
        .project-card,
        .lazy-image,
        .lazy-placeholder,
        .category-tab {
            transition: none !important;
            animation: none !important;
        }
        
        .project-card:hover {
            transform: none !important;
        }
    }
    
    /* Performance optimizations */
    .project-card,
    .bg-white,
    .desktop-overlay,
    .mobile-overlay {
        transform: translateZ(0);
        will-change: transform;
    }
    
    /* Consistent button styling */
    .desktop-overlay button,
    .mobile-overlay button {
        font-family: inherit;
        font-weight: 600;
        letter-spacing: 0.025em;
        border: none;
        outline: none;
        cursor: pointer;
    }
    
    /* Badge consistency */
    .project-card .absolute span {
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }
    </style>
    
    <?php
}

// Helper Functions
function getProjectImageUrl($imagePath) {
    if (empty($imagePath)) return '';
    
    static $urlCache = [];
    
    if (isset($urlCache[$imagePath])) {
        return $urlCache[$imagePath];
    }
    
    $result = '';
    
    if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
        $result = $imagePath;
    } elseif (strpos($imagePath, 'data:') === 0) {
        $result = $imagePath;
    } elseif (strpos($imagePath, 'uploads/') === 0) {
        $result = BASE_URL . '/' . $imagePath;
    } else {
        $result = BASE_URL . '/uploads/' . $imagePath;
    }
    
    $urlCache[$imagePath] = $result;
    return $result;
}

function getProjectStatusClass($status) {
    static $statusClasses = [
        'COMPLETED' => 'bg-green-100 text-green-800',
        'IN PROGRESS' => 'bg-blue-100 text-blue-800',
        'ON HOLD' => 'bg-yellow-100 text-yellow-800',
        'PLANNED' => 'bg-purple-100 text-purple-800'
    ];
    
    $upperStatus = strtoupper($status);
    return $statusClasses[$upperStatus] ?? 'bg-gray-100 text-gray-800';
}

function limitText($text, $limit) {
    if (strlen($text) <= $limit) {
        return $text;
    }
    return substr($text, 0, $limit);
}


// ------------------------------------------------------------------------------------------------------------------
// ---------------------------------renderTextWithImageSection -------------------------------------------------------

function renderTextWithImageSection($section, $bgColor, $textColor) {
    // Early return optimizations
    if (empty($section['content'])) {
        return;
    }
    
    // Parse JSON content with error handling
    $decodedContent = json_decode($section['content'], true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedContent) || empty($decodedContent)) {
        return;
    }
    
    $sections = $decodedContent;
    
    // Cache frequently used values
    $backgroundType = $section['background_type'] ?? 'solid';
    $backgroundStyle = getSectionBackgroundStyle($section);
    $sectionId = 'text-with-image-section-' . uniqid();
    
    // Optimize text color logic
    $sectionTextColor = $textColor ?: ($section['text_color'] ?? null);
    if (!$sectionTextColor) {
        $sectionTextColor = getSectionTextColor($section);
    }
    if ($backgroundType === 'image' && !isset($section['text_color']) && !$textColor) {
        $sectionTextColor = $section['image_text_color'] ?? '#ffffff';
    }
    
    // Default gradient fallback
    $defaultGradient = ($backgroundType === 'gradient' && empty($backgroundStyle)) ? 'bg-gradient-to-r from-blue-600 to-purple-600' : '';
    
    // Pre-process sections to avoid repeated calculations
    $processedSections = [];
    foreach ($sections as $index => $textImageSection) {
        // Skip empty sections early
        if (empty($textImageSection['text']) && empty($textImageSection['image']) && 
            empty($textImageSection['title']) && empty($textImageSection['contentType'])) {
            continue;
        }
        
        $processed = [
            'contentType' => $textImageSection['contentType'] ?? '',
            'title' => $textImageSection['title'] ?? '',
            'text' => $textImageSection['text'] ?? '',
            'image' => $textImageSection['image'] ?? '',
            'position' => $textImageSection['position'] ?? 'left',
            'alt' => $textImageSection['alt'] ?? 'Content image',
            'buttonText' => $textImageSection['buttonText'] ?? '',
            'buttonUrl' => $textImageSection['buttonUrl'] ?? ''
        ];
        
        // Fix button URL once
        if (!empty($processed['buttonUrl'])) {
            if (!preg_match('/^https?:\/\/|^mailto:|^tel:/', $processed['buttonUrl'])) {
                $processed['buttonUrl'] = 'https://' . ltrim($processed['buttonUrl'], '/');
            }
        }
        
        // Pre-calculate classes
        $isImageLeft = ($processed['position'] === 'left');
        $processed['textOrder'] = $isImageLeft ? 'lg:order-2' : 'lg:order-1';
        $processed['imageOrder'] = $isImageLeft ? 'lg:order-1' : 'lg:order-2';
        $processed['imageAlignment'] = $isImageLeft ? 'object-left' : 'object-right';
        
        // Build full image URL once
        if (!empty($processed['image'])) {
            if (strpos($processed['image'], 'http') === 0 || strpos($processed['image'], 'data:') === 0) {
                $processed['fullImageUrl'] = $processed['image'];
            } else {
                $processed['fullImageUrl'] = BASE_URL . '/' . ltrim($processed['image'], '/');
            }
        } else {
            $processed['fullImageUrl'] = '';
        }
        
        $processedSections[] = $processed;
    }
    
    // If no valid sections after processing, return early
    if (empty($processedSections)) {
        return;
    }
    
    ?>
    
    <!-- Text with Image Section Wrapper -->
    <div class="text-with-image-wrapper">
        <section id="<?= $sectionId ?>" 
                 class="text-with-image-section <?= $defaultGradient ?> py-16 relative overflow-hidden" 
                 style="<?= $backgroundStyle ?>">
            
            <!-- Background Overlays -->
            <?php if ($backgroundType === 'image'): ?>
                <div class="absolute inset-0 bg-black bg-opacity-30 pointer-events-none"></div>
                <div class="absolute inset-0 opacity-5 pointer-events-none bg-dots-pattern"></div>
            <?php else: ?>
                <div class="absolute inset-0 opacity-5 pointer-events-none bg-dots-pattern"></div>
            <?php endif; ?>
            
            <!-- Main Container -->
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                
                <!-- Section Title -->
                <div class="text-center mb-16">
                    <h2 class="text-4xl md:text-5xl font-bold text-black mb-2">
                        <?= htmlspecialchars($section['title']) ?>
                    </h2>
                    <?php if (!empty($section['subtitle'])): ?>
                        <p class="text-lg mt-2 opacity-80 max-w-2xl mx-auto" style="color: <?= htmlspecialchars($sectionTextColor) ?>;">
                            <?= htmlspecialchars($section['subtitle']) ?>
                        </p>
                    <?php endif; ?>
                    <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto rounded-full mt-2"></div>
                </div>
                
                <!-- Text with Image Sections -->
                <div class="space-y-16">
                    <?php foreach ($processedSections as $item): ?>
                        
                        <!-- Individual Text with Image Item -->
                        <div class="text-image-item grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-center">
                            
                            <!-- Text Content -->
                            <div class="text-content <?= $item['textOrder'] ?>">
                                <div class="prose max-w-none">
                                    
                                    <!-- Content Type Badge -->
                                    <?php if (!empty($item['contentType'])): ?>
                                        <div class="mb-3">
                                            <span class="content-badge">
                                                <?= htmlspecialchars($item['contentType']) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Content Title -->
                                    <?php if (!empty($item['title'])): ?>
                                        <h3 class="text-xl md:text-2xl font-bold mb-4" 
                                            style="color: <?= htmlspecialchars($sectionTextColor) ?>;">
                                            <?= htmlspecialchars($item['title']) ?>
                                        </h3>
                                    <?php endif; ?>
                                    
                                    <!-- Main Text Content -->
                                    <?php if (!empty($item['text'])): ?>
                                        <div class="text-content-text text-base md:text-lg leading-relaxed mb-6" 
                                             style="color: <?= htmlspecialchars($sectionTextColor) ?>;">
                                            <?= nl2br(htmlspecialchars($item['text'])) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Button if provided -->
                                    <?php if (!empty($item['buttonText'])): ?>
                                        <div class="mt-6">
                                            <a href="<?= htmlspecialchars($item['buttonUrl'] ?: '#') ?>" 
                                               class="cta-button"
                                               target="_blank" rel="noopener noreferrer">
                                                <?= htmlspecialchars($item['buttonText']) ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Image Content -->
                            <div class="image-content <?= $item['imageOrder'] ?>">
                                <?php if (!empty($item['fullImageUrl'])): ?>
                                    <div class="relative">
                                        <img src="<?= htmlspecialchars($item['fullImageUrl']) ?>" 
                                             alt="<?= htmlspecialchars($item['alt']) ?>" 
                                             class="section-image <?= $item['imageAlignment'] ?>"
                                             loading="lazy"
                                             decoding="async">
                                    </div>
                                <?php else: ?>
                                    <!-- Placeholder when no image -->
                                    <div class="image-placeholder">
                                        <svg class="w-20 h-20 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                                        </svg>
                                        <p class="text-lg font-medium">No Image</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                        </div>
                        
                    <?php endforeach; ?>
                </div>
                
            </div>
        </section>
    </div>
    
    <?php
    // Only add CSS and JS once per page load
    static $assetsLoaded = false;
    if (!$assetsLoaded):
        $assetsLoaded = true;
    ?>
    
    <!-- OPTIMIZED CSS - Loaded once per page -->
    <style>
        /* Base styles for text-with-image sections */
        .text-with-image-wrapper {
            position: relative;
            z-index: 1;
            clear: both;
            margin: 0;
            padding: 0;
        }
        
        .text-with-image-section {
            position: relative !important;
            z-index: 1 !important;
            clear: both !important;
            margin: 0 !important;
        }
        
        /* Background patterns using CSS instead of inline styles */
        .bg-dots-pattern {
            background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.3) 1px, transparent 0);
            background-size: 20px 20px;
        }
        
        .text-with-image-section[class*="bg-gradient"] .bg-dots-pattern {
            background-size: 30px 30px;
            opacity: 0.05;
        }
        
        /* Content badge styling */
        .content-badge {
            display: inline-block;
            background-color: #dbeafe;
            color: #1e40af;
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.125rem 0.625rem;
            border-radius: 9999px;
            border: 1px solid #bfdbfe;
        }
        
        /* Image styling */
        .section-image {
            width: 100%;
            height: 16rem;
            object-fit: contain;
            border-radius: 0.5rem;
        }
        
        @media (min-width: 768px) {
            .section-image {
                height: 20rem;
            }
        }
        
        @media (min-width: 1024px) {
            .section-image {
                height: 24rem;
            }
        }
        
        /* Image placeholder */
        .image-placeholder {
            width: 100%;
            height: 16rem;
            background-color: #e5e7eb;
            border-radius: 0.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
        }
        
        @media (min-width: 768px) {
            .image-placeholder {
                height: 20rem;
            }
        }
        
        @media (min-width: 1024px) {
            .image-placeholder {
                height: 24rem;
            }
        }
        
        /* Button styling */
        .cta-button {
            display: inline-block;
            background-color: #2563eb;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .cta-button:hover {
            background-color: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }
        
        /* Text styling improvements */
        .text-content-text {
            line-height: 1.7;
        }
        
        .text-content h3 {
            margin: 0.75rem 0 1rem 0;
            line-height: 1.3;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .text-image-item {
                gap: 2rem;
            }
        }
        
        @media (max-width: 768px) {
            .text-with-image-section h2 {
                font-size: 2rem !important;
                margin-bottom: 1rem !important;
            }
            
            .text-with-image-section .text-content h3 {
                font-size: 1.25rem !important;
            }
            
            .text-with-image-section .text-content-text {
                font-size: 1rem !important;
            }
        }
        
        /* Performance optimizations */
        .text-with-image-section img {
            will-change: transform;
        }
        
        .text-with-image-section {
            contain: layout style paint;
        }
    </style>
    
    <?php endif; ?>
    
    <?php
}

// Helper function to get section text color (if not already defined)

function renderBannerSection($section) {
    // Parse banner content (Title|Subtitle format)
    $bannerParts = explode('|', $section['content']);
    $title = trim($bannerParts[0] ?? 'Welcome to Our Website');
    $subtitle = trim($bannerParts[1] ?? '');
    
    // Get styling configuration with defaults
    $backgroundType = $section['background_type'] ?? 'gradient';
    $bgColor = $section['background_color'] ?? '#3b82f6';
    $textColor = $section['text_color'] ?? '#ffffff';
    $bannerHeight = $section['banner_height'] ?? 'medium';
    
    // Get background styling
    $backgroundStyle = getSectionBackgroundStyle($section);
    $sectionTextColor = $section['text_color'] ?? $textColor ?? '#ffffff';
    
    // Generate unique section ID
    $sectionId = 'banner-section-' . uniqid();
    
    // Default gradient fallback
    $defaultGradient = '';
    if ($backgroundType === 'gradient' && empty($backgroundStyle)) {
        $defaultGradient = 'bg-gradient-to-r from-blue-600 to-blue-800';
    }
    
    // Height mapping
    $heightMap = [
        'small' => 'h-[100px]',
        'medium' => 'h-[140px]',
        'large' => 'h-[180px]',
        'hero' => 'h-[220px]'
    ];
    $heightClass = $heightMap[$bannerHeight] ?? 'h-[140px]';
    ?>
    
    <!-- Banner Wrapper - Encoding Safe -->
    <div class="banner-wrapper" style="position: relative; z-index: 1; clear: both; margin: 0; padding: 0;">
        <section id="<?= $sectionId ?>" 
                 class="banner-section <?= $defaultGradient ?> <?= $heightClass ?> relative overflow-hidden transition-all duration-500 flex items-center justify-center" 
                 style="<?= $backgroundStyle ?>; position: relative !important; z-index: 1 !important; display: flex !important; margin: 0 !important; padding: 0 !important;">
            
            <!-- Content Container -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative w-full" 
                 style="position: relative; z-index: 10;">
                
                <!-- Banner Title -->
                <h2 class="banner-title text-2xl md:text-3xl lg:text-4xl font-bold transition-colors duration-300" 
                    style="color: <?= htmlspecialchars($sectionTextColor) ?> !important;">
                    <?= htmlspecialchars($title) ?>
                </h2>
                
                <!-- Banner Subtitle -->
                <?php if (!empty($subtitle)): ?>
                    <p class="banner-subtitle text-sm md:text-base lg:text-lg opacity-90 transition-colors duration-300 max-w-4xl mx-auto mt-2" 
                       style="color: <?= htmlspecialchars($sectionTextColor) ?> !important;">
                        <?= htmlspecialchars($subtitle) ?>
                    </p>
                <?php endif; ?>
            </div>
        </section>
    </div>
    
    <!-- Preload background image -->
    <?php if ($backgroundType === 'image' && !empty($section['image_url'])): ?>
        <link rel="preload" as="image" href="<?= htmlspecialchars($section['image_url']) ?>">
    <?php endif; ?>
    
    <!-- Encoding-Safe CSS -->
    <style>
        /* Maximum specificity CSS - Encoding resistant */
        div.banner-wrapper {
            position: relative !important;
            z-index: 1 !important;
            clear: both !important;
            margin: 0 !important;
            padding: 0 !important;
            display: block !important;
            width: 100% !important;
            float: none !important;
        }
        
        #<?= $sectionId ?>.banner-section {
            position: relative !important;
            z-index: 1 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin: 0 !important;
            padding: 0 !important;
            top: auto !important;
            bottom: auto !important;
            left: auto !important;
            right: auto !important;
            transform: none !important;
            float: none !important;
            clear: both !important;
            width: 100% !important;
        }
        
        /* Text color with maximum specificity */
        #<?= $sectionId ?>.banner-section h2.banner-title,
        #<?= $sectionId ?>.banner-section p.banner-subtitle,
        #<?= $sectionId ?> .banner-title,
        #<?= $sectionId ?> .banner-subtitle,
        #<?= $sectionId ?> h2,
        #<?= $sectionId ?> p {
            color: <?= htmlspecialchars($sectionTextColor) ?> !important;
        }
        
        /* Content container positioning */
        #<?= $sectionId ?> .max-w-7xl {
            position: relative !important;
            z-index: 10 !important;
            width: 100% !important;
            margin: 0 auto !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        /* Ensure footer doesn't overlap */
        footer,
        .footer,
        #footer {
            position: relative !important;
            z-index: 0 !important;
            margin-top: 0 !important;
            clear: both !important;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            #<?= $sectionId ?> .banner-title {
                font-size: 1.5rem !important;
                line-height: 1.3 !important;
            }
            
            #<?= $sectionId ?> .banner-subtitle {
                font-size: 0.875rem !important;
                line-height: 1.4 !important;
                margin-top: 0.5rem !important;
            }
        }
        
        /* Gradient animation */
        <?php if ($backgroundType === 'gradient'): ?>
        #<?= $sectionId ?> {
            background-size: 400% 400% !important;
            animation: bannerGradientShift 12s ease infinite;
        }
        
        @keyframes bannerGradientShift {
            0% { background-position: 0% 50%; }
            25% { background-position: 100% 50%; }
            50% { background-position: 100% 100%; }
            75% { background-position: 0% 100%; }
            100% { background-position: 0% 50%; }
        }
        <?php endif; ?>
        
        /* Image background specific */
        <?php if ($backgroundType === 'image'): ?>
        #<?= $sectionId ?> {
            background-attachment: scroll !important;
            background-color: <?= htmlspecialchars($bgColor) ?> !important;
            background-size: cover !important;
            background-position: center !important;
            background-repeat: no-repeat !important;
        }
        <?php endif; ?>
    </style>
    
    <!-- JavaScript fallback for encoding -->
    <script>
    (function() {
        function fixBannerPosition() {
            const bannerSection = document.getElementById('<?= $sectionId ?>');
            const bannerWrapper = bannerSection?.closest('.banner-wrapper');
            
            if (bannerWrapper) {
                bannerWrapper.style.position = 'relative';
                bannerWrapper.style.zIndex = '1';
                bannerWrapper.style.clear = 'both';
            }
            
            if (bannerSection) {
                bannerSection.style.position = 'relative';
                bannerSection.style.zIndex = '1';
                bannerSection.style.display = 'flex';
                bannerSection.style.clear = 'both';
                bannerSection.style.margin = '0';
                bannerSection.style.padding = '0';
            }
            
            // Ensure footer doesn't overlap
            const footer = document.querySelector('footer') || document.querySelector('.footer') || document.querySelector('#footer');
            if (footer) {
                footer.style.position = 'relative';
                footer.style.zIndex = '0';
                footer.style.clear = 'both';
            }
        }
        
        // Run immediately and on DOM ready
        fixBannerPosition();
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fixBannerPosition);
        }
        
        // Run again after a short delay to handle encoding issues
        setTimeout(fixBannerPosition, 100);
    })();
    </script>
    
    <?php
}


function renderCustomSection($section, $bgColor = null, $textColor = null) {
    // Get styling configuration with fallbacks
    $backgroundType = $section['background_type'] ?? 'solid';
    $bgColor = $bgColor ?? $section['background_color'] ?? '#ffffff';
    $textColor = $textColor ?? $section['text_color'] ?? '#000000';
    
    // Get background styling using the same helper functions as CTA
    $backgroundStyle = getSectionBackgroundStyle($section);
    $sectionTextColor = getSectionTextColor($section);
    
    // Generate unique section ID for scoped CSS
    $sectionId = 'custom-section-' . uniqid();
    
    ?>
    <section id="<?= $sectionId ?>" class="custom-section py-16 relative overflow-hidden transition-all duration-500" 
             style="<?= $backgroundStyle ?>">
        
        <!-- Enhanced Background Overlay for Images -->
        <?php if ($backgroundType === 'image'): ?>
            <!-- Dark overlay for better text readability on images -->
            <div class="absolute inset-0 bg-black bg-opacity-30 pointer-events-none"></div>
            
            <!-- Optional Pattern Overlay for Images -->
            <div class="absolute inset-0 opacity-5 pointer-events-none">
                <div style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.8) 1px, transparent 0); background-size: 30px 30px; width: 100%; height: 100%;"></div>
            </div>
        <?php else: ?>
            <!-- Standard Pattern Overlay for Solid/Gradient -->
            <div class="absolute inset-0 opacity-5 pointer-events-none">
                <div style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.3) 1px, transparent 0); background-size: 20px 20px; width: 100%; height: 100%;"></div>
            </div>
        <?php endif; ?>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <!-- Title -->
            <?php if (!empty($section['title'])): ?>
                <h2 class="custom-title text-3xl font-bold text-center mb-8 transition-colors duration-300 <?= $backgroundType === 'image' ? 'drop-shadow-lg' : '' ?>" 
                    style="color: <?= htmlspecialchars($sectionTextColor) ?>;">
                    <?= htmlspecialchars($section['title']) ?>
                </h2>
            <?php endif; ?>
            
            <!-- Subtitle -->
            <?php if (!empty($section['subtitle'])): ?>
                <p class="custom-subtitle text-xl text-center mb-12 opacity-80 transition-colors duration-300 <?= $backgroundType === 'image' ? 'drop-shadow-md' : '' ?>" 
                   style="color: <?= htmlspecialchars($sectionTextColor) ?>;">
                    <?= htmlspecialchars($section['subtitle']) ?>
                </p>
            <?php endif; ?>
            
            <!-- Content -->
            <div class="prose max-w-none custom-content transition-colors duration-300 <?= $backgroundType === 'image' ? 'drop-shadow-sm' : '' ?>" 
                 style="color: <?= htmlspecialchars($sectionTextColor) ?>;">
                <?= $section['content'] ?>
            </div>
        </div>
    </section>
    
    <!-- SCOPED CSS - Only affects this custom section -->
    <style>
        /* Enhanced shadow effects for image backgrounds - SCOPED */
        <?php if ($backgroundType === 'image'): ?>
        #<?= $sectionId ?> .custom-title,
        #<?= $sectionId ?> .custom-subtitle,
        #<?= $sectionId ?> .custom-content {
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
        }
        
        #<?= $sectionId ?> {
            background-attachment: scroll;
        }
        <?php endif; ?>
        
        /* Gradient animation for gradient backgrounds - SCOPED */
        <?php if ($backgroundType === 'gradient'): ?>
        #<?= $sectionId ?> {
            background-size: 400% 400%;
            animation: customGradientShift 8s ease infinite;
        }
        
        @keyframes customGradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        <?php endif; ?>
        
        /* Responsive adjustments - SCOPED */
        @media (max-width: 768px) {
            #<?= $sectionId ?> .custom-title {
                font-size: 2rem !important;
            }
            
            #<?= $sectionId ?> .custom-subtitle {
                font-size: 1.25rem !important;
            }
        }
        
        /* Dark mode adjustments - SCOPED */
        @media (prefers-color-scheme: dark) {
            #<?= $sectionId ?> {
                filter: brightness(0.9);
            }
        }
        
        /* Smooth transitions for all elements - SCOPED */
        #<?= $sectionId ?> * {
            transition: all 0.3s ease;
        }
        
        /* Enhanced prose styling for content - SCOPED */
        #<?= $sectionId ?> .custom-content {
            line-height: 1.7;
        }
        
        #<?= $sectionId ?> .custom-content h1,
        #<?= $sectionId ?> .custom-content h2,
        #<?= $sectionId ?> .custom-content h3,
        #<?= $sectionId ?> .custom-content h4,
        #<?= $sectionId ?> .custom-content h5,
        #<?= $sectionId ?> .custom-content h6 {
            color: inherit;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        
        #<?= $sectionId ?> .custom-content p {
            color: inherit;
            margin-bottom: 1.5rem;
        }
        
        #<?= $sectionId ?> .custom-content a {
            color: inherit;
            opacity: 0.8;
            text-decoration: underline;
            transition: opacity 0.3s ease;
        }
        
        #<?= $sectionId ?> .custom-content a:hover {
            opacity: 1;
        }
        
        #<?= $sectionId ?> .custom-content ul,
        #<?= $sectionId ?> .custom-content ol {
            color: inherit;
            margin-bottom: 1.5rem;
        }
        
        #<?= $sectionId ?> .custom-content li {
            color: inherit;
            margin-bottom: 0.5rem;
        }
        
        #<?= $sectionId ?> .custom-content blockquote {
            border-left: 4px solid rgba(255, 255, 255, 0.3);
            padding-left: 1.5rem;
            margin: 2rem 0;
            font-style: italic;
            color: inherit;
            opacity: 0.9;
        }
        
        #<?= $sectionId ?> .custom-content code {
            background-color: rgba(255, 255, 255, 0.1);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            color: inherit;
        }
        
        #<?= $sectionId ?> .custom-content pre {
            background-color: rgba(0, 0, 0, 0.2);
            padding: 1rem;
            border-radius: 0.5rem;
            overflow-x: auto;
            margin: 1.5rem 0;
        }
        
        #<?= $sectionId ?> .custom-content pre code {
            background-color: transparent;
            padding: 0;
            color: inherit;
        }
    </style>
    
    <?php
}

// Helper function to get section background style (if not already defined)
if (!function_exists('getSectionBackgroundStyle')) {
    function getSectionBackgroundStyle($section) {
        $backgroundType = $section['background_type'] ?? 'solid';
        $bgColor = $section['background_color'] ?? '#ffffff';
        
        switch ($backgroundType) {
            case 'gradient':
                $gradientType = $section['gradient_type'] ?? 'linear';
                $gradientDirection = $section['gradient_direction'] ?? '45deg';
                $gradientColor1 = $section['gradient_color_1'] ?? $bgColor;
                $gradientColor2 = $section['gradient_color_2'] ?? '#e0e0e0';
                
                if ($gradientType === 'linear') {
                    return "background: linear-gradient({$gradientDirection}, {$gradientColor1}, {$gradientColor2});";
                } else {
                    return "background: radial-gradient(circle, {$gradientColor1}, {$gradientColor2});";
                }
                
            case 'image':
                $backgroundImage = $section['background_image'] ?? '';
                $backgroundPosition = $section['background_position'] ?? 'center center';
                $backgroundSize = $section['background_size'] ?? 'cover';
                $backgroundRepeat = $section['background_repeat'] ?? 'no-repeat';
                
                return "background-image: url('{$backgroundImage}'); background-position: {$backgroundPosition}; background-size: {$backgroundSize}; background-repeat: {$backgroundRepeat};";
                
            default: // solid
                return "background-color: {$bgColor};";
        }
    }
}

// Helper function to get section text color (if not already defined)
if (!function_exists('getSectionTextColor')) {
    function getSectionTextColor($section) {
        return $section['text_color'] ?? '#000000';
    }
}
?>