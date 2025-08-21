<?php
/**
 * SEO Helper Functions
 * Include this file in your pages to easily retrieve and display SEO data
 * 
 * Usage: require_once 'includes/seo_functions.php';
 */

/**
 * Get SEO data for a specific page
 * @param int $page_id The page ID
 * @return array SEO data array
 */
function getPageSEOData($page_id) {
    global $pdo;
    
    // Get page-specific SEO data
    $stmt = $pdo->prepare("SELECT * FROM page_seo WHERE page_id = ?");
    $stmt->execute([$page_id]);
    $pageSEO = $stmt->fetch();
    
    // Get default SEO settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN (
        'default_meta_title', 'default_meta_description', 'default_meta_keywords',
        'google_analytics_id', 'google_search_console', 'site_name'
    )");
    $defaultSettings = [];
    while ($row = $stmt->fetch()) {
        $defaultSettings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Get page title if no SEO title is set
    $stmt = $pdo->prepare("SELECT title FROM pages WHERE id = ?");
    $stmt->execute([$page_id]);
    $page = $stmt->fetch();
    
    return [
        'meta_title' => $pageSEO['meta_title'] ?? $defaultSettings['default_meta_title'] ?? $page['title'] ?? $defaultSettings['site_name'] ?? 'Your Website',
        'meta_description' => $pageSEO['meta_description'] ?? $defaultSettings['default_meta_description'] ?? '',
        'meta_keywords' => $pageSEO['meta_keywords'] ?? $defaultSettings['default_meta_keywords'] ?? '',
        'og_title' => $pageSEO['og_title'] ?? $pageSEO['meta_title'] ?? $defaultSettings['default_meta_title'] ?? $page['title'] ?? '',
        'og_description' => $pageSEO['og_description'] ?? $pageSEO['meta_description'] ?? $defaultSettings['default_meta_description'] ?? '',
        'og_image' => $pageSEO['og_image'] ?? '',
        'canonical_url' => $pageSEO['canonical_url'] ?? '',
        'robots' => $pageSEO['robots'] ?? 'index,follow',
        'google_analytics_id' => $defaultSettings['google_analytics_id'] ?? '',
        'google_search_console' => $defaultSettings['google_search_console'] ?? ''
    ];
}

/**
 * Get SEO data by page slug
 * @param string $slug The page slug
 * @return array SEO data array
 */
function getPageSEOBySlug($slug) {
    global $pdo;
    
    // Get page ID by slug
    $stmt = $pdo->prepare("SELECT id FROM pages WHERE slug = ? AND is_active = 1");
    $stmt->execute([$slug]);
    $page = $stmt->fetch();
    
    if (!$page) {
        return getDefaultSEOData();
    }
    
    return getPageSEOData($page['id']);
}

/**
 * Get default SEO data (fallback)
 * @return array Default SEO data
 */
function getDefaultSEOData() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN (
        'default_meta_title', 'default_meta_description', 'default_meta_keywords',
        'google_analytics_id', 'google_search_console', 'site_name'
    )");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    return [
        'meta_title' => $settings['default_meta_title'] ?? $settings['site_name'] ?? 'Your Website',
        'meta_description' => $settings['default_meta_description'] ?? '',
        'meta_keywords' => $settings['default_meta_keywords'] ?? '',
        'og_title' => $settings['default_meta_title'] ?? $settings['site_name'] ?? '',
        'og_description' => $settings['default_meta_description'] ?? '',
        'og_image' => '',
        'canonical_url' => '',
        'robots' => 'index,follow',
        'google_analytics_id' => $settings['google_analytics_id'] ?? '',
        'google_search_console' => $settings['google_search_console'] ?? ''
    ];
}

/**
 * Generate SEO meta tags HTML
 * @param array $seoData SEO data array
 * @param string $currentUrl Current page URL (optional)
 * @return string HTML meta tags
 */
function generateSEOTags($seoData, $currentUrl = '') {
    $html = '';
    
    // Basic meta tags
    if (!empty($seoData['meta_title'])) {
        $html .= '<title>' . htmlspecialchars($seoData['meta_title']) . '</title>' . "\n";
    }
    
    if (!empty($seoData['meta_description'])) {
        $html .= '<meta name="description" content="' . htmlspecialchars($seoData['meta_description']) . '">' . "\n";
    }
    
    if (!empty($seoData['meta_keywords'])) {
        $html .= '<meta name="keywords" content="' . htmlspecialchars($seoData['meta_keywords']) . '">' . "\n";
    }
    
    if (!empty($seoData['robots'])) {
        $html .= '<meta name="robots" content="' . htmlspecialchars($seoData['robots']) . '">' . "\n";
    }
    
    // Canonical URL
    if (!empty($seoData['canonical_url'])) {
        $html .= '<link rel="canonical" href="' . htmlspecialchars($seoData['canonical_url']) . '">' . "\n";
    } elseif (!empty($currentUrl)) {
        $html .= '<link rel="canonical" href="' . htmlspecialchars($currentUrl) . '">' . "\n";
    }
    
    // Open Graph tags
    if (!empty($seoData['og_title'])) {
        $html .= '<meta property="og:title" content="' . htmlspecialchars($seoData['og_title']) . '">' . "\n";
    }
    
    if (!empty($seoData['og_description'])) {
        $html .= '<meta property="og:description" content="' . htmlspecialchars($seoData['og_description']) . '">' . "\n";
    }
    
    if (!empty($seoData['og_image'])) {
        $html .= '<meta property="og:image" content="' . htmlspecialchars($seoData['og_image']) . '">' . "\n";
    }
    
    if (!empty($currentUrl)) {
        $html .= '<meta property="og:url" content="' . htmlspecialchars($currentUrl) . '">' . "\n";
    }
    
    $html .= '<meta property="og:type" content="website">' . "\n";
    
    // Twitter Card tags
    $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    
    if (!empty($seoData['og_title'])) {
        $html .= '<meta name="twitter:title" content="' . htmlspecialchars($seoData['og_title']) . '">' . "\n";
    }
    
    if (!empty($seoData['og_description'])) {
        $html .= '<meta name="twitter:description" content="' . htmlspecialchars($seoData['og_description']) . '">' . "\n";
    }
    
    if (!empty($seoData['og_image'])) {
        $html .= '<meta name="twitter:image" content="' . htmlspecialchars($seoData['og_image']) . '">' . "\n";
    }
    
    // Google Search Console verification
    if (!empty($seoData['google_search_console'])) {
        $html .= '<meta name="google-site-verification" content="' . htmlspecialchars($seoData['google_search_console']) . '">' . "\n";
    }
    
    return $html;
}

/**
 * Generate Google Analytics script
 * @param string $analyticsId Google Analytics ID
 * @return string HTML script tags
 */
function generateGoogleAnalytics($analyticsId) {
    if (empty($analyticsId)) {
        return '';
    }
    
    return '
<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=' . htmlspecialchars($analyticsId) . '"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag("js", new Date());
  gtag("config", "' . htmlspecialchars($analyticsId) . '");
</script>
';
}

/**
 * Example usage function for a page
 * @param int|string $pageIdentifier Page ID or slug
 * @param string $currentUrl Current page URL
 */
function displayPageSEO($pageIdentifier, $currentUrl = '') {
    // Get SEO data
    if (is_numeric($pageIdentifier)) {
        $seoData = getPageSEOData($pageIdentifier);
    } else {
        $seoData = getPageSEOBySlug($pageIdentifier);
    }
    
    // Generate and output SEO tags
    echo generateSEOTags($seoData, $currentUrl);
    
    // Generate and output Google Analytics if in head section
    if (!empty($seoData['google_analytics_id'])) {
        echo generateGoogleAnalytics($seoData['google_analytics_id']);
    }
}

/**
 * Get structured data for a page (JSON-LD)
 * @param array $seoData SEO data
 * @param string $currentUrl Current page URL
 * @param array $additionalData Additional structured data
 * @return string JSON-LD script
 */
function generateStructuredData($seoData, $currentUrl = '', $additionalData = []) {
    $structuredData = [
        "@context" => "https://schema.org",
        "@type" => "WebPage",
        "name" => $seoData['meta_title'] ?? '',
        "description" => $seoData['meta_description'] ?? '',
        "url" => $currentUrl ?: $seoData['canonical_url'] ?? ''
    ];
    
    if (!empty($seoData['og_image'])) {
        $structuredData['image'] = $seoData['og_image'];
    }
    
    // Merge with additional data
    $structuredData = array_merge($structuredData, $additionalData);
    
    return '<script type="application/ld+json">' . json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
}

/**
 * Get SEO data for breadcrumbs
 * @param array $breadcrumbs Array of breadcrumb items
 * @param string $currentUrl Current page URL
 * @return string JSON-LD breadcrumb script
 */
function generateBreadcrumbStructuredData($breadcrumbs, $currentUrl = '') {
    if (empty($breadcrumbs)) {
        return '';
    }
    
    $items = [];
    $position = 1;
    
    foreach ($breadcrumbs as $breadcrumb) {
        $items[] = [
            "@type" => "ListItem",
            "position" => $position,
            "name" => $breadcrumb['name'],
            "item" => $breadcrumb['url']
        ];
        $position++;
    }
    
    $structuredData = [
        "@context" => "https://schema.org",
        "@type" => "BreadcrumbList",
        "itemListElement" => $items
    ];
    
    return '<script type="application/ld+json">' . json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
}

/**
 * Generate article structured data (for blog posts)
 * @param array $articleData Article information
 * @param array $seoData SEO data
 * @return string JSON-LD article script
 */
function generateArticleStructuredData($articleData, $seoData) {
    $structuredData = [
        "@context" => "https://schema.org",
        "@type" => "Article",
        "headline" => $seoData['meta_title'] ?? $articleData['title'] ?? '',
        "description" => $seoData['meta_description'] ?? $articleData['excerpt'] ?? '',
        "datePublished" => $articleData['created_at'] ?? date('c'),
        "dateModified" => $articleData['updated_at'] ?? date('c')
    ];
    
    if (!empty($articleData['author'])) {
        $structuredData['author'] = [
            "@type" => "Person",
            "name" => $articleData['author']
        ];
    }
    
    if (!empty($seoData['og_image'])) {
        $structuredData['image'] = $seoData['og_image'];
    }
    
    if (!empty($articleData['publisher'])) {
        $structuredData['publisher'] = [
            "@type" => "Organization",
            "name" => $articleData['publisher']
        ];
    }
    
    return '<script type="application/ld+json">' . json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
}

/**
 * Generate Organization structured data
 * @param array $orgData Organization information
 * @return string JSON-LD organization script
 */
function generateOrganizationStructuredData($orgData) {
    $structuredData = [
        "@context" => "https://schema.org",
        "@type" => "Organization",
        "name" => $orgData['name'] ?? '',
        "url" => $orgData['url'] ?? ''
    ];
    
    if (!empty($orgData['logo'])) {
        $structuredData['logo'] = $orgData['logo'];
    }
    
    if (!empty($orgData['contactPoint'])) {
        $structuredData['contactPoint'] = [
            "@type" => "ContactPoint",
            "telephone" => $orgData['contactPoint']['phone'] ?? '',
            "contactType" => $orgData['contactPoint']['type'] ?? 'customer service'
        ];
    }
    
    if (!empty($orgData['address'])) {
        $structuredData['address'] = [
            "@type" => "PostalAddress",
            "streetAddress" => $orgData['address']['street'] ?? '',
            "addressLocality" => $orgData['address']['city'] ?? '',
            "addressRegion" => $orgData['address']['state'] ?? '',
            "postalCode" => $orgData['address']['zip'] ?? '',
            "addressCountry" => $orgData['address']['country'] ?? ''
        ];
    }
    
    if (!empty($orgData['sameAs'])) {
        $structuredData['sameAs'] = $orgData['sameAs'];
    }
    
    return '<script type="application/ld+json">' . json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
}

/**
 * Generate FAQ structured data
 * @param array $faqs Array of FAQ items
 * @return string JSON-LD FAQ script
 */
function generateFAQStructuredData($faqs) {
    if (empty($faqs)) {
        return '';
    }
    
    $questions = [];
    foreach ($faqs as $faq) {
        $questions[] = [
            "@type" => "Question",
            "name" => $faq['question'],
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => $faq['answer']
            ]
        ];
    }
    
    $structuredData = [
        "@context" => "https://schema.org",
        "@type" => "FAQPage",
        "mainEntity" => $questions
    ];
    
    return '<script type="application/ld+json">' . json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
}

/**
 * Validate and clean meta description
 * @param string $description Meta description
 * @param int $maxLength Maximum length (default 160)
 * @return string Cleaned description
 */
function cleanMetaDescription($description, $maxLength = 160) {
    // Remove HTML tags
    $description = strip_tags($description);
    
    // Remove extra whitespace
    $description = preg_replace('/\s+/', ' ', trim($description));
    
    // Truncate if too long
    if (strlen($description) > $maxLength) {
        $description = substr($description, 0, $maxLength - 3) . '...';
    }
    
    return $description;
}

/**
 * Validate and clean meta title
 * @param string $title Meta title
 * @param int $maxLength Maximum length (default 60)
 * @return string Cleaned title
 */
function cleanMetaTitle($title, $maxLength = 60) {
    // Remove HTML tags
    $title = strip_tags($title);
    
    // Remove extra whitespace
    $title = preg_replace('/\s+/', ' ', trim($title));
    
    // Truncate if too long
    if (strlen($title) > $maxLength) {
        $title = substr($title, 0, $maxLength - 3) . '...';
    }
    
    return $title;
}

/**
 * Generate meta robots tag based on page settings
 * @param bool $isActive Whether page is active
 * @param bool $isPublic Whether page is public
 * @param string $customRobots Custom robots setting
 * @return string Robots directive
 */
function generateRobotsDirective($isActive = true, $isPublic = true, $customRobots = '') {
    if (!empty($customRobots)) {
        return $customRobots;
    }
    
    if (!$isActive || !$isPublic) {
        return 'noindex,nofollow';
    }
    
    return 'index,follow';
}
?>