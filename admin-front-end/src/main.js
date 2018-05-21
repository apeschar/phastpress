// The Vue build version to load with the `import` command
// (runtime-only or standalone) has been set in webpack.base.conf with an alias.
import Vue from 'vue'
import VueI18n from 'vue-i18n'
import WordPressAPIClient from './api/Client'
import App from './App'

// eslint-disable-next-line
const client = new WordPressAPIClient(ajaxurl)

Vue.use(VueI18n)
Vue.config.productionTip = false

/* eslint-disable no-new */
new Vue({
  i18n: new VueI18n({
    fallbackLocale: 'default',
    silentTranslationWarn: true
  }),
  el: '#app',
  components: { App },
  template: '<App :client="client" />',
  data () {
    return {
      client
    }
  }
})
