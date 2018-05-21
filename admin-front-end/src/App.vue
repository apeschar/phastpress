<template>
  <div class="wrap">
    <h1 v-t="'title'" class="wp-heading-inline"></h1>
    <notification type="error" v-for="error in errors" :key="error.type" class="phastpress-notification">
      <i18n :path="'errors.' + error.type">
        <span place="candidates">{{ error.candidates.join(', ') }}</span>
      </i18n>
    </notification>
    <panel v-if="settingsStrings && config">
      <settings :strings="settingsStrings" v-model="config"></settings>
    </panel>
  </div>
</template>

<script>
import Notification from './components/Notification'
import Panel from './components/Panel'
import Settings from './components/Settings'

export default {
  name: 'App',

  props: ['client'],

  created () {
    this.client.getAdminPanelData()
      .then(data => {
        this.settingsStrings = data.settingsStrings
        this.currentConfig = data.config
        this.errors = data.errors
      })
  },

  data () {
    return {
      settingsStrings: null,
      currentConfig: null,
      errors: []
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
    Notification,
    Settings,
    Panel
  }
}
</script>

<style scoped lang="sass">
  .phastpress-notification
    margin-bottom: 12px
</style>

<i18n>
  default:
    title: PhastPress
    errors:
      'no-cache-root': 'PhastPress failed to create a service configuration in any of the following directories: {candidates}'
      'no-service-config': 'PhastPress failed to create a service configuration in any of the following directories: {candidates}'
</i18n>
