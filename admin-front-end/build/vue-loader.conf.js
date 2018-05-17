'use strict'
const utils = require('./utils')
const config = require('../config')
const isProduction = process.env.NODE_ENV === 'production'
const sourceMapEnabled = isProduction
  ? config.build.productionSourceMap
  : config.dev.cssSourceMap

const loaders = utils.cssLoaders({
  sourceMap: sourceMapEnabled,
  extract: isProduction
})

loaders.i18n = '@kazupon/vue-i18n-loader'

module.exports = {
  loaders,
  preLoaders: {
    i18n: 'yaml-loader'
  },
  cssSourceMap: sourceMapEnabled,
  cacheBusting: config.dev.cacheBusting,
  transformToRequire: {
    video: ['src', 'poster'],
    source: 'src',
    img: 'src',
    image: 'xlink:href'
  }
}
