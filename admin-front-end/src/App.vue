<template>
  <div id="app">

    <on-off-switch on-value="asd" off-value="qwe" v-model="t"></on-off-switch>
    <on-off-switch disabled="true"></on-off-switch>
    {{ t }}
    <br><br>
    Let info be here
    <information>
      Here we go with the extra info
      Here we go with the extra info
      Here we go with the extra info
      Here we go with the extra info
    </information>
    <section-title color="blue">Title in slot</section-title>
    <setting>
      The is the first setting
    </setting>
    <setting>
      This is the second setting with info
      <information>
        This is the info for the second setting
      </information>
    </setting>
    <notification type="error">
      This is an error
    </notification>
    <notification type="warning">
      This is a warning
    </notification>
    <notification type="information">
      This is information
    </notification>
    <notification type="success">
      This is success
    </notification>
    <br><br>
    <settings :strings="settingsStrings" v-model="config"></settings>
  </div>
</template>

<script>
import OnOffSwitch from './components/OnOffSwitch'
import Information from './components/Information'
import SectionTitle from './components/SectionTitle'
import Setting from './components/Setting'
import Notification from './components/Notification'
import Settings from './components/Settings'

export default {
  name: 'App',
  data () {
    return {
      t: 'asd',
      settingsStrings: {
        userEmail: 'the-user@phastpress.com',
        urlWithPhast: 'url-with',
        urlWithoutPhast: 'url-without'
      },
      config: {
        'phastpress-enabled': 'on',
        'phastpress-admin-only': 'on',
        'phastpress-pathinfo-query-format': 'on',
        'phastpress-footer-link': 'off',
        'phastpress-img-optimization-tags': 'off',
        'phastpress-img-optimization-css': 'on',
        'phastpress-img-optimization-api': 'off',
        'phastpress-css-optimization': 'on',
        'phastpress-scripts-rearrangement': 'on',
        'phastpress-scripts-defer': 'on',
        'phastpress-scripts-proxy': 'off',
        'phastpress-iframe-defer': 'on'
      }
    }
  },
  watch: {
    config (newConfig) {
      console.log(newConfig)
      this.config = newConfig
    }
  },
  components: {
    Settings,
    Notification,
    Setting,
    OnOffSwitch,
    Information,
    SectionTitle
  }
}
</script>

<style>
#app {
  font-family: 'Avenir', Helvetica, Arial, sans-serif;
}
</style>
