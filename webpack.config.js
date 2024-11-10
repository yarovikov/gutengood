const defaultConfig = require('./node_modules/@wordpress/scripts/config/webpack.config.js');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        'editor': [
            path.resolve(__dirname, 'resources/src/scripts/editor.js'),
            path.resolve(__dirname, 'resources/src/styles/editor.css'),
        ],
    },
    output: {
        path: path.resolve(__dirname, 'resources/build'),
        filename: '[name].js',
    },
    optimization: {
        ...defaultConfig.optimization,
    },
    module: {
        ...defaultConfig.module,
    },
    plugins: [...defaultConfig.plugins],
};
