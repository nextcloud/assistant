const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')
const ESLintPlugin = require('eslint-webpack-plugin')
const StyleLintPlugin = require('stylelint-webpack-plugin')

const buildMode = process.env.NODE_ENV
const isDev = buildMode === 'development'
webpackConfig.devtool = isDev ? 'cheap-source-map' : 'source-map'
// webpackConfig.bail = false

webpackConfig.stats = {
	colors: true,
	modules: false,
}

const appId = 'assistant'
webpackConfig.entry = {
	imageGenerationPage: { import: path.join(__dirname, 'src', 'imageGenerationPage.js'), filename: appId + '-imageGenerationPage.js' },
	imageGenerationReference: { import: path.join(__dirname, 'src', 'imageGenerationReference.js'), filename: appId + '-imageGenerationReference.js' },
	textGenerationReference: { import: path.join(__dirname, 'src', 'textGenerationReference.js'), filename: appId + '-textGenerationReference.js' },
	speechToTextResultPage: { import: path.join(__dirname, 'src', 'speechToTextResultPage.js'), filename: appId + '-speechToTextResultPage.js' },
	speechToTextReference: { import: path.join(__dirname, 'src', 'speechToTextReference.js'), filename: appId + '-speechToTextReference.js' },
	personalSettings: { import: path.join(__dirname, 'src', 'personalSettings.js'), filename: appId + '-personalSettings.js' },
	adminSettings: { import: path.join(__dirname, 'src', 'adminSettings.js'), filename: appId + '-adminSettings.js' },
	main: { import: path.join(__dirname, 'src', 'main.js'), filename: appId + '-main.js' },
	assistantPage: { import: path.join(__dirname, 'src', 'assistantPage.js'), filename: appId + '-assistantPage.js' },
}

webpackConfig.plugins.push(
	new ESLintPlugin({
		extensions: ['js', 'vue'],
		files: 'src',
		failOnError: !isDev,
	}),
)
webpackConfig.plugins.push(
	new StyleLintPlugin({
		files: 'src/**/*.{css,scss,vue}',
		failOnError: !isDev,
	}),
)

module.exports = webpackConfig
