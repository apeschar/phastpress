<template>
  <div class="wrap">
    <h1 v-t="'title'" class="wp-heading-inline"></h1>

    <template v-if="loaded">
      <notification v-for="error in errors" :key="error.type" type="error" class="phastpress-notification">
        <i18n :path="'errors.' + error.type">
          <span place="params">{{ error.params.join(', ') }}</span>
        </i18n>
      </notification>

      <notification v-for="warning in warnings" :key="warning" type="warning" class="phastpress-notification">
        {{ warning }}
      </notification>

      <panel>
        <settings :strings="settingsStrings" v-model="config"></settings>
      </panel>
    </template>
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
        this.loaded = true
        this.setData(data)
      })
  },

  data () {
    return {
      loaded: false,
      settingsStrings: null,
      currentConfig: null,
      errors: [],
      serverWarnings: []
    }
  },

  methods: {
    setData (data) {
      this.settingsStrings = data.settingsStrings
      this.currentConfig = data.config
      this.errors = data.errors
      this.serverWarnings = data.warnings
    }
  },

  computed: {

    config: {
      get () {
        return this.currentConfig
      },
      async set (newConfig) {
        this.setData(await this.client.saveConfig(newConfig))
      }
    },

    warnings () {
      if (!this.config.enabled) {
        return [this.$t('warnings.disabled')].concat(this.serverWarnings)
      }
      if (this.config['admin-only']) {
        return [this.$t('warnings.admin-only')].concat(this.serverWarnings)
      }
      return this.serverWarnings
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
      'no-cache-root': 'PhastPress can not write to any cache directory! Please, make one of the following directories writable: {params}'
      'no-service-config': 'PhastPress failed to create a service configuration in any of the following directories: {params}'
    warnings:
      disabled: 'PhastPress optimizations are off!'
      'admin-only': >
        PhastPress optimizations will be applied only for logged-in users with the "Administrator" privilege.
        This is for previewing purposes.
        Select the "On" setting for "PhastPress optimizations" below to activate for all users!
</i18n>
