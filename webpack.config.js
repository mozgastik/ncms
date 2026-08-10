// webpack.config.js
const Encore = require('@symfony/webpack-encore');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // ВАЖЛИВО: Правильний шлях для сервера
    .setOutputPath('public_html/build/')
    .setPublicPath('/build')
    
    // Не додавайте enableStimulusBridge якщо немає контролерів
    // .enableStimulusBridge('./assets/controllers.json')
    
    .addEntry('app', './assets/app.js')
    
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(false)  // Вимкніть на production
    .enableVersioning(true)    // Увімкніть для кеш-бастінгу
    
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.38';
    })
    
    .enablePostCssLoader();

module.exports = Encore.getWebpackConfig();