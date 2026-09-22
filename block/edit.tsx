import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl } from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
    const {
        priceSource,
        metaKey,
        customMetaKey,
        maxPriceMetaKey,
        maxPriceCustomMetaKey,
        showRegularPriceWhenOnSale,
        currencySource,
        currencySymbol,
        currencyPosition,
        emptyText,
        numberFormat,
        prefix,
        suffix,
    } = attributes;

    const blockProps = useBlockProps({
        className: 'jankx-custom-price'
    });

    const priceSourceOptions = [
        { label: __('Auto (Product API / Smart Detect)', 'jankx'), value: 'auto' },
        { label: __('Selling / Active Price', 'jankx'), value: 'product_price' },
        { label: __('Regular / Compare-at Price', 'jankx'), value: 'regular_price' },
        { label: __('Sale Price', 'jankx'), value: 'sale_price' },
        { label: __('Manual Meta Key', 'jankx'), value: 'meta' },
    ];

    const metaKeyOptions = [
        { label: __('Generic Price (_price)', 'jankx'), value: '_price' },
        { label: __('Product Price (_product_price)', 'jankx'), value: '_product_price' },
        { label: __('Product Regular Price (_product_regular_price)', 'jankx'), value: '_product_regular_price' },
        { label: __('Product Sale Price (_product_sale_price)', 'jankx'), value: '_product_sale_price' },
        { label: __('Tour Starting Price (_experience_starting_price)', 'jankx'), value: '_experience_starting_price' },
        { label: __('Tour Price (_tour_price)', 'jankx'), value: '_tour_price' },
        { label: __('Service Price (_service_price)', 'jankx'), value: '_service_price' },
        { label: __('Regular Price (_regular_price)', 'jankx'), value: '_regular_price' },
        { label: __('Sale Price (_sale_price)', 'jankx'), value: '_sale_price' },
        { label: __('Custom Meta Key', 'jankx'), value: 'custom' },
    ];

    const maxOptions = [
        { label: __('None', 'jankx'), value: 'none' },
        { label: __('Max Price (_price_max)', 'jankx'), value: '_price_max' },
        { label: __('Custom Key', 'jankx'), value: 'custom' },
    ];

    const currencySourceOptions = [
        { label: __('Jankx E-Commerce Currency (CurrencyManager)', 'jankx'), value: 'auto' },
        { label: __('Custom / Manual Currency', 'jankx'), value: 'custom' },
    ];

    const currencyPositionOptions = [
        { label: __('Right (100.000₫)', 'jankx'), value: 'right' },
        { label: __('Right with Space (100.000 ₫)', 'jankx'), value: 'right_space' },
        { label: __('Left ($100)', 'jankx'), value: 'left' },
        { label: __('Left with Space ($ 100)', 'jankx'), value: 'left_space' },
    ];

    const formatOptions = [
        { label: __('Vietnamese (vi-VN: 100.000)', 'jankx'), value: 'vi-VN' },
        { label: __('English / US (en-US: 100,000)', 'jankx'), value: 'en-US' },
    ];

    // Format helper for preview
    const formatNumber = (num) => {
        try {
            return new Intl.NumberFormat(numberFormat || 'vi-VN', { maximumFractionDigits: 0 }).format(num);
        } catch (e) {
            return String(num);
        }
    };

    const attachCurrency = (numStr) => {
        if (currencySource !== 'custom') {
            return `${numStr}₫`;
        }
        const sym = currencySymbol || 'đ';
        switch (currencyPosition) {
            case 'left':
                return `${sym}${numStr}`;
            case 'left_space':
                return `${sym} ${numStr}`;
            case 'right_space':
                return `${numStr} ${sym}`;
            case 'right':
            default:
                return `${numStr}${sym}`;
        }
    };

    const previewPrice = attachCurrency(formatNumber(2000000));
    const previewRegular = attachCurrency(formatNumber(2500000));
    const showSalePreview = showRegularPriceWhenOnSale && (priceSource === 'auto' || priceSource === 'product_price');

    return (
        <div {...blockProps}>
            <InspectorControls>
                <PanelBody title={__('Price Source Settings', 'jankx')} initialOpen={true}>
                    <SelectControl
                        label={__('Price Source', 'jankx')}
                        value={priceSource || 'auto'}
                        options={priceSourceOptions}
                        onChange={(value) => setAttributes({ priceSource: value })}
                        help={__('Auto uses ProductRegistry or canonical post type pricing (product, tour, service)', 'jankx')}
                    />

                    {priceSource === 'meta' && (
                        <SelectControl
                            label={__('Select Price Meta Key', 'jankx')}
                            value={metaKey || '_price'}
                            options={metaKeyOptions}
                            onChange={(value) => setAttributes({ metaKey: value })}
                        />
                    )}

                    {(priceSource === 'meta' && metaKey === 'custom') && (
                        <TextControl
                            label={__('Enter Custom Meta Key', 'jankx')}
                            value={customMetaKey}
                            onChange={(value) => setAttributes({ customMetaKey: value })}
                            help={__('Enter the post meta key to retrieve the price from.', 'jankx')}
                        />
                    )}

                    {(priceSource === 'auto' || priceSource === 'product_price') && (
                        <ToggleControl
                            label={__('Show Regular Price when On Sale', 'jankx')}
                            checked={showRegularPriceWhenOnSale !== false}
                            onChange={(value) => setAttributes({ showRegularPriceWhenOnSale: value })}
                            help={__('Displays strikethrough regular price alongside the sale price.', 'jankx')}
                        />
                    )}

                    <SelectControl
                        label={__('Max Price (Range)', 'jankx')}
                        value={maxPriceMetaKey || 'none'}
                        options={maxOptions}
                        onChange={(value) => setAttributes({ maxPriceMetaKey: value })}
                        help={__('Optionally display a price range (e.g. 100.000₫ - 200.000₫)', 'jankx')}
                    />

                    {maxPriceMetaKey === 'custom' && (
                        <TextControl
                            label={__('Enter Custom Max Price Key', 'jankx')}
                            value={maxPriceCustomMetaKey}
                            onChange={(value) => setAttributes({ maxPriceCustomMetaKey: value })}
                        />
                    )}
                </PanelBody>

                <PanelBody title={__('Currency & Conversion', 'jankx')} initialOpen={false}>
                    <SelectControl
                        label={__('Currency Handling', 'jankx')}
                        value={currencySource || 'auto'}
                        options={currencySourceOptions}
                        onChange={(value) => setAttributes({ currencySource: value })}
                        help={__('Jankx CurrencyManager automatically converts rates and formats symbol/position based on user preference.', 'jankx')}
                    />

                    {currencySource === 'custom' && (
                        <>
                            <TextControl
                                label={__('Currency Symbol', 'jankx')}
                                value={currencySymbol}
                                onChange={(value) => setAttributes({ currencySymbol: value })}
                            />
                            <SelectControl
                                label={__('Currency Position', 'jankx')}
                                value={currencyPosition || 'right'}
                                options={currencyPositionOptions}
                                onChange={(value) => setAttributes({ currencyPosition: value })}
                            />
                            <SelectControl
                                label={__('Number Format', 'jankx')}
                                value={numberFormat || 'vi-VN'}
                                options={formatOptions}
                                onChange={(value) => setAttributes({ numberFormat: value })}
                            />
                        </>
                    )}
                </PanelBody>

                <PanelBody title={__('Labels & Display', 'jankx')} initialOpen={false}>
                    <TextControl
                        label={__('Prefix Text', 'jankx')}
                        value={prefix || ''}
                        placeholder={__('e.g. Từ ', 'jankx')}
                        onChange={(value) => setAttributes({ prefix: value })}
                    />
                    <TextControl
                        label={__('Suffix Text', 'jankx')}
                        value={suffix || ''}
                        placeholder={__('e.g. / người', 'jankx')}
                        onChange={(value) => setAttributes({ suffix: value })}
                    />
                    <TextControl
                        label={__('Empty Price Text', 'jankx')}
                        value={emptyText}
                        onChange={(value) => setAttributes({ emptyText: value })}
                        help={__('Shown when price is empty or 0', 'jankx')}
                    />
                </PanelBody>
            </InspectorControls>

            {prefix ? <span className="price-prefix">{prefix}</span> : null}
            {showSalePreview ? (
                <>
                    <span className="price-amount jankx-price-sale">{previewPrice}</span>
                    <del className="price-regular jankx-price-regular">{previewRegular}</del>
                </>
            ) : (
                <span className="price-amount jankx-price-single">{previewPrice || emptyText}</span>
            )}
            {suffix ? <span className="price-suffix">{suffix}</span> : null}
        </div>
    );
}
