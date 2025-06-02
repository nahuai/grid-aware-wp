const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
	...defaultConfig,
	entry: {
		index: './src/index.js',
	},
	output: {
		path: path.resolve(__dirname, 'build'),
		filename: '[name].js',
	},
}; 