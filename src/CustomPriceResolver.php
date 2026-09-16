<?php
namespace Jankx\Extensions\CustomPrice;

class CustomPriceResolver
{
    /**
     * Resolve the current post ID from block context, global loop, or query context.
     *
     * @param \WP_Block|array|null $block
     * @return int
     */
    public static function resolvePostId($block = null): int
    {
        if ($block instanceof \WP_Block && !empty($block->context['postId'])) {
            return (int) $block->context['postId'];
        }

        if (is_array($block) && !empty($block['context']['postId'])) {
            return (int) $block['context']['postId'];
        }

        $postId = get_the_ID();
        if ($postId) {
            return (int) $postId;
        }

        global $post;
        if ($post && isset($post->ID)) {
            return (int) $post->ID;
        }

        return 0;
    }

    /**
     * Check if currently inside the template editor or site editor.
     *
     * @return bool
     */
    public static function isTemplateEditor(): bool
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (
                strpos($uri, '/wp-json/wp/v2/template') !== false ||
                strpos($uri, '/wp-json/wp/v2/template-part') !== false
            ) {
                return true;
            }
        }

        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && in_array($screen->id, ['site-editor', 'appearance_page_gutenberg-edit-site'], true)) {
                return true;
            }
        }

        if (isset($_GET['_wp-find-template']) || (isset($_GET['postType']) && $_GET['postType'] === 'wp_template')) {
            return true;
        }

        global $post;
        if ((is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) && (empty($post) || empty($post->post_content))) {
            return true;
        }

        return false;
    }

    /**
     * Resolve prices from ProductRegistry, post type meta, or custom meta key.
     *
     * @param int   $postId
     * @param array $attributes
     * @return array
     */
    public static function resolvePriceData(int $postId, array $attributes = []): array
    {
        $priceSource = $attributes['priceSource'] ?? 'auto';
        $metaKey = $attributes['metaKey'] ?? '_price';
        $customMetaKey = $attributes['customMetaKey'] ?? '';
        $maxPriceMetaKey = $attributes['maxPriceMetaKey'] ?? 'none';
        $maxPriceCustomMetaKey = $attributes['maxPriceCustomMetaKey'] ?? '';

        if ($metaKey === 'custom' && !empty($customMetaKey)) {
            $metaKey = $customMetaKey;
        }
        if ($maxPriceMetaKey === 'custom' && !empty($maxPriceCustomMetaKey)) {
            $maxPriceMetaKey = $maxPriceCustomMetaKey;
        }

        $price = 0.0;
        $regularPrice = 0.0;
        $salePrice = 0.0;
        $maxPrice = 0.0;
        $isOnSale = false;
        $sourceCurrency = '';

        if ($postId > 0) {
            // Check stored currency for multi-currency conversion
            $currencyMeta = get_post_meta($postId, '_price_currency', true)
                ?: (get_post_meta($postId, '_experience_currency', true)
                ?: (get_post_meta($postId, '_tour_price_currency', true)
                ?: get_post_meta($postId, '_product_currency', true)));
            if (!empty($currencyMeta)) {
                $sourceCurrency = strtoupper(trim((string) $currencyMeta));
            }

            $postType = get_post_type($postId);

            if ($priceSource === 'meta') {
                // Explicit manual meta key mode
                $rawPrice = get_post_meta($postId, $metaKey, true);
                if (is_numeric($rawPrice)) {
                    $price = (float) $rawPrice;
                }
                if ($maxPriceMetaKey && $maxPriceMetaKey !== 'none') {
                    $rawMax = get_post_meta($postId, $maxPriceMetaKey, true);
                    if (is_numeric($rawMax)) {
                        $maxPrice = (float) $rawMax;
                    }
                }
            } else {
                // Try ProductRegistry from Jankx base-ecommerce
                $product = null;
                if (class_exists('\\Jankx\\Extensions\\Ecommerce\\Registry\\ProductRegistry')) {
                    $registry = \Jankx\Extensions\Ecommerce\Registry\ProductRegistry::get_instance();
                    if ($registry->isSupported($postId)) {
                        $product = $registry->createProduct($postId);
                    }
                }

                if ($product) {
                    $regularPrice = (float) $product->getRegularPrice();
                    $salePrice = (float) $product->getSalePrice();
                    $activePrice = (float) $product->getPrice();

                    if ($postType === 'tour') {
                        if ($activePrice <= 0) {
                            $startingPrice = get_post_meta($postId, '_experience_starting_price', true);
                            if ($startingPrice !== '') {
                                $activePrice = (float) $startingPrice;
                            }
                        }
                        $activePrice = (float) apply_filters('jankx/travel/tour/starting_price', $activePrice, $postId);
                    }

                    if ($salePrice > 0 && $regularPrice > 0 && $salePrice < $regularPrice) {
                        $isOnSale = true;
                    }

                    switch ($priceSource) {
                        case 'regular_price':
                            $price = $regularPrice > 0 ? $regularPrice : $activePrice;
                            break;
                        case 'sale_price':
                            $price = $salePrice;
                            break;
                        case 'auto':
                        case 'product_price':
                        default:
                            $price = $activePrice > 0 ? $activePrice : ($regularPrice > 0 ? $regularPrice : $salePrice);
                            break;
                    }
                } else {
                    // Fallback to post-type specific meta keys
                    if ($postType === 'product') {
                        // Jankx ecommerce-product extension
                        $regularPrice = (float) get_post_meta($postId, '_product_regular_price', true);
                        $salePrice = (float) get_post_meta($postId, '_product_sale_price', true);
                        $sellingPrice = (float) get_post_meta($postId, '_product_price', true);

                        if ($salePrice > 0 && $regularPrice > 0 && $salePrice < $regularPrice) {
                            $isOnSale = true;
                        }

                        switch ($priceSource) {
                            case 'regular_price':
                                $price = $regularPrice > 0 ? $regularPrice : $sellingPrice;
                                break;
                            case 'sale_price':
                                $price = $salePrice;
                                break;
                            case 'auto':
                            case 'product_price':
                            default:
                                $price = $isOnSale ? $salePrice : ($sellingPrice > 0 ? $sellingPrice : $regularPrice);
                                break;
                        }
                    } elseif ($postType === 'tour') {
                        // Jankx travel extension
                        $regularPrice = (float) get_post_meta($postId, '_tour_regular_price', true);
                        $salePrice = (float) get_post_meta($postId, '_tour_sale_price', true);
                        $startingPrice = get_post_meta($postId, '_experience_starting_price', true);
                        $tourPrice = get_post_meta($postId, '_tour_price', true);

                        $rawTour = !empty($startingPrice) ? (float) $startingPrice : (float) $tourPrice;
                        $rawTour = (float) apply_filters('jankx/travel/tour/starting_price', $rawTour, $postId);

                        if ($salePrice > 0 && $regularPrice > 0 && $salePrice < $regularPrice) {
                            $isOnSale = true;
                        }

                        switch ($priceSource) {
                            case 'regular_price':
                                $price = $regularPrice > 0 ? $regularPrice : $rawTour;
                                break;
                            case 'sale_price':
                                $price = $salePrice;
                                break;
                            case 'auto':
                            case 'product_price':
                            default:
                                $price = $isOnSale ? $salePrice : $rawTour;
                                break;
                        }
                    } elseif ($postType === 'service') {
                        // Jankx services extension
                        $regularPrice = (float) get_post_meta($postId, '_service_regular_price', true);
                        $salePrice = (float) get_post_meta($postId, '_service_sale_price', true);
                        $servicePrice = (float) get_post_meta($postId, '_service_price', true);

                        if ($salePrice > 0 && $regularPrice > 0 && $salePrice < $regularPrice) {
                            $isOnSale = true;
                        }

                        switch ($priceSource) {
                            case 'regular_price':
                                $price = $regularPrice > 0 ? $regularPrice : $servicePrice;
                                break;
                            case 'sale_price':
                                $price = $salePrice;
                                break;
                            case 'auto':
                            case 'product_price':
                            default:
                                $price = $isOnSale ? $salePrice : ($servicePrice > 0 ? $servicePrice : $regularPrice);
                                break;
                        }
                    } else {
                        // Generic post types: check standard meta keys
                        $rawPrice = get_post_meta($postId, $metaKey, true);
                        $rawRegular = get_post_meta($postId, '_regular_price', true);
                        $rawSale = get_post_meta($postId, '_sale_price', true);

                        $regularPrice = is_numeric($rawRegular) ? (float) $rawRegular : 0.0;
                        $salePrice = is_numeric($rawSale) ? (float) $rawSale : 0.0;

                        if ($salePrice > 0 && $regularPrice > 0 && $salePrice < $regularPrice) {
                            $isOnSale = true;
                        }

                        if (is_numeric($rawPrice) && (float) $rawPrice > 0) {
                            $price = (float) $rawPrice;
                        } elseif ($isOnSale) {
                            $price = $salePrice;
                        } elseif ($regularPrice > 0) {
                            $price = $regularPrice;
                        }
                    }
                }

                // Range handling
                if ($maxPriceMetaKey && $maxPriceMetaKey !== 'none') {
                    $rawMax = get_post_meta($postId, $maxPriceMetaKey, true);
                    if (is_numeric($rawMax)) {
                        $maxPrice = (float) $rawMax;
                    }
                }
            }
        }

        $isRange = ($maxPrice > 0 && $maxPrice > $price);

        $priceData = [
            'price'          => $price,
            'regularPrice'   => $regularPrice,
            'salePrice'      => $salePrice,
            'maxPrice'       => $maxPrice,
            'isOnSale'       => $isOnSale,
            'isRange'        => $isRange,
            'sourceCurrency' => $sourceCurrency,
        ];

        return (array) apply_filters('jankx/custom_price/price_data', $priceData, $postId, $attributes);
    }

    /**
     * Format a price amount using CurrencyManager (base-ecommerce) or custom fallback.
     *
     * @param float  $amount
     * @param array  $attributes
     * @param int    $postId
     * @param string $sourceCurrency
     * @return string
     */
    public static function formatPrice(float $amount, array $attributes = [], int $postId = 0, string $sourceCurrency = ''): string
    {
        if ($amount <= 0) {
            return '';
        }

        $currencySource = $attributes['currencySource'] ?? 'auto';
        $currencySymbol = $attributes['currencySymbol'] ?? 'đ';
        $currencyPosition = $attributes['currencyPosition'] ?? 'right';
        $numberFormat = $attributes['numberFormat'] ?? 'vi-VN';

        // Check if CurrencyManager is available and not disabled by custom mode
        if ($currencySource !== 'custom' && class_exists('\\Jankx\\Extensions\\Ecommerce\\Currency\\CurrencyManager')) {
            $currencyManager = '\\Jankx\\Extensions\\Ecommerce\\Currency\\CurrencyManager';
            $defaultCurrency = $currencyManager::getDefaultCurrency();
            $source = !empty($sourceCurrency) ? $sourceCurrency : $defaultCurrency;

            // formatPriceWithConversion handles conversion, exchange rates, positioning and symbol
            $formatted = $currencyManager::formatPriceWithConversion($amount, $source);

            return (string) apply_filters('jankx/custom_price/formatted_price', $formatted, $amount, $postId, $attributes);
        }

        // Custom / fallback formatting
        $decCount = 0;
        if ($numberFormat === 'en-US') {
            $decSep = '.';
            $thousandSep = ',';
        } else {
            $decSep = ',';
            $thousandSep = '.';
        }

        $formattedNumber = number_format($amount, $decCount, $decSep, $thousandSep);
        $symbolHtml = '<span class="currency-symbol">' . esc_html($currencySymbol) . '</span>';

        switch ($currencyPosition) {
            case 'left':
                $formatted = $symbolHtml . $formattedNumber;
                break;
            case 'left_space':
                $formatted = $symbolHtml . ' ' . $formattedNumber;
                break;
            case 'right_space':
                $formatted = $formattedNumber . ' ' . $symbolHtml;
                break;
            case 'right':
            default:
                $formatted = $formattedNumber . $symbolHtml;
                break;
        }

        return (string) apply_filters('jankx/custom_price/formatted_price', $formatted, $amount, $postId, $attributes);
    }

    /**
     * Render the custom price block HTML.
     *
     * @param array             $attributes
     * @param string            $content
     * @param \WP_Block|null    $block
     * @return string
     */
    public static function render(array $attributes, string $content = '', $block = null): string
    {
        $postId = self::resolvePostId($block);
        $isTemplateEditor = self::isTemplateEditor();

        if (!$postId && $isTemplateEditor) {
            $priceData = [
                'price'          => 2500000.0,
                'regularPrice'   => 3000000.0,
                'salePrice'      => 2500000.0,
                'maxPrice'       => 0.0,
                'isOnSale'       => true,
                'isRange'        => false,
                'sourceCurrency' => '',
            ];
        } else {
            $priceData = self::resolvePriceData($postId, $attributes);
        }

        $emptyText = $attributes['emptyText'] ?? __('Liên hệ', 'jankx');
        $showWhenEmpty = $attributes['showWhenEmpty'] ?? true;
        $prefix = $attributes['prefix'] ?? '';
        $suffix = $attributes['suffix'] ?? '';
        $showRegularPriceWhenOnSale = $attributes['showRegularPriceWhenOnSale'] ?? true;

        $price = $priceData['price'];
        $regularPrice = $priceData['regularPrice'];
        $salePrice = $priceData['salePrice'];
        $maxPrice = $priceData['maxPrice'];
        $isOnSale = $priceData['isOnSale'];
        $isRange = $priceData['isRange'];
        $sourceCurrency = $priceData['sourceCurrency'];

        $isEmpty = ($price <= 0 && $regularPrice <= 0 && $salePrice <= 0);

        if ($isEmpty) {
            if (!$showWhenEmpty) {
                return '';
            }
            $formattedPrice = esc_html($emptyText);
            $formattedRegularPrice = '';
            $formattedSalePrice = '';
            $formattedMaxPrice = '';
        } else {
            $formattedPrice = self::formatPrice($price, $attributes, $postId, $sourceCurrency);
            $formattedRegularPrice = $regularPrice > 0 ? self::formatPrice($regularPrice, $attributes, $postId, $sourceCurrency) : '';
            $formattedSalePrice = $salePrice > 0 ? self::formatPrice($salePrice, $attributes, $postId, $sourceCurrency) : '';
            $formattedMaxPrice = $maxPrice > 0 ? self::formatPrice($maxPrice, $attributes, $postId, $sourceCurrency) : '';
        }

        $priceSource = $attributes['priceSource'] ?? 'auto';
        $isDualSaleDisplay = ($isOnSale && $showRegularPriceWhenOnSale && in_array($priceSource, ['auto', 'product_price'], true) && !empty($formattedRegularPrice));

        $wrapperClasses = ['jankx-custom-price'];
        if ($isDualSaleDisplay) {
            $wrapperClasses[] = 'has-sale-price';
        }
        if ($isRange) {
            $wrapperClasses[] = 'has-price-range';
        }

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => implode(' ', $wrapperClasses),
        ]);

        ob_start();
        ?>
        <div <?php echo $wrapperAttrs; ?>>
            <?php if (!empty($prefix)): ?>
                <span class="price-prefix"><?php echo esc_html($prefix); ?></span>
            <?php endif; ?>

            <?php if ($isEmpty): ?>
                <span class="price-amount price-empty"><?php echo $formattedPrice; ?></span>
            <?php elseif ($isDualSaleDisplay): ?>
                <span class="price-amount jankx-price-sale"><?php echo $formattedSalePrice ?: $formattedPrice; ?></span>
                <del class="price-regular jankx-price-regular"><?php echo $formattedRegularPrice; ?></del>
            <?php elseif ($isRange && !empty($formattedMaxPrice)): ?>
                <span class="price-amount jankx-price-range"><?php echo $formattedPrice . ' - ' . $formattedMaxPrice; ?></span>
            <?php else: ?>
                <span class="price-amount jankx-price-single"><?php echo $formattedPrice; ?></span>
            <?php endif; ?>

            <?php if (!empty($suffix)): ?>
                <span class="price-suffix"><?php echo esc_html($suffix); ?></span>
            <?php endif; ?>
        </div>
        <?php
        $html = ob_get_clean();

        return (string) apply_filters('jankx/custom_price/render_html', $html, $priceData, $postId, $attributes);
    }
}
