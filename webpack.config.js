const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

const EXTENSION_DIR = __dirname;

const filteredPlugins = (defaultConfig.plugins || []).filter((plugin) => {
    const name = plugin.constructor?.name ?? '';
    return name !== 'CopyPlugin' && name !== 'CleanWebpackPlugin';
});

module.exports = {
    ...defaultConfig,
    context: EXTENSION_DIR,
    entry: {
        'block/build/index': './block/index.tsx',
    },
    output: {
        ...defaultConfig.output,
        path: EXTENSION_DIR,
        filename: '[name].js',
        clean: false,
    },
    plugins: filteredPlugins,
};
