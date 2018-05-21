<template>
  <div class="wrap">
    <h1 v-t="'title'" class="wp-heading-inline"></h1>
    <panel v-if="settingsStrings && config">
      <settings :strings="settingsStrings" v-model="config"></settings>
    </panel>
  </div>
</template>

<script>
import Settings from './components/Settings'
import Panel from './components/Panel'

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
    Panel
  }
}
</script>

<i18n>
  default:
    title: 'PhastPress'
</i18n>
