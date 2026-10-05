import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import './style.scss';
import './editor.scss';
import Edit from './edit';
import metadata from './block.json';

registerBlockType('jankx/custom-price-value', {
    title: __('Price Value', 'jankx'),
    category: 'jankx',
    icon: 'money',
    parent: ['jankx/custom-price'],
    supports: { html: false, reusable: false },
    edit: () => {
        const blockProps = useBlockProps({ className: 'jankx-price-value-placeholder' });
        return <div {...blockProps}>[{__('Giá tiền (tự động hiển thị ở Frontend)', 'jankx')}]</div>;
    },
    save: () => {
        const blockProps = useBlockProps.save({ className: 'jankx-price-value-placeholder' });
        return <div {...blockProps}></div>;
    }
} as any);

registerBlockType(metadata.name, {
    edit: Edit,
    save: () => <InnerBlocks.Content />,
} as any);
