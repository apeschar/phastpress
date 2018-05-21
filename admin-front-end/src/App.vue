<template>
  <div id="app" v-if="settingsStrings && config">
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

  props: ['client'],

  created () {
    this.client.getAdminPanelData()
      .then(data => {
        this.settingsStrings = data.settingsStrings
        this.currentConfig = data.config
      })
  },

  data () {
    return {
      settingsStrings: null,
      currentConfig: null
    }
  },

  computed: {
    config: {
      get () {
        return this.currentConfig
      },
      async set (newConfig) {
        this.currentConfig = await this.client.saveConfig(newConfig)
      }
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
