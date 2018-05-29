<template>
  <div>
    <notification
      v-if="requestError"
      type="error"
      dismissible="true"
      dismiss-timeout="10000"
      @dismiss="requestError = false"
    >
      <i18n path="errors.network-error">
        <span place="params">{{ requestError.toString() }}</span>
      </i18n>
    </notification>

    <low-php-version-notice v-if="lowPHPVersion" :php-version="lowPHPVersion" />
    <template v-else-if="loaded">
      <notification v-for="error in errors" :key="error.type" type="error">
        <i18n :path="'errors.' + error.type">
          <span place="params">{{ error.params.join(', ') }}</span>
        </i18n>
      </notification>

      <notification v-for="warning in warnings" :key="warning" type="warning">
        {{ warning }}
      </notification>

      <saving-status :client="client" />

      <panel>
        <settings :strings="settingsStrings" v-model="config"></settings>
      </panel>
    </template>
  </div>
</template>

<script>
import Notification from './Notification'
import Panel from './Panel'
import Settings from './Settings'
import LowPhpVersionNotice from './LowPhpVersionNotice'
import SavingStatus from './SavingStatus'

export default {
  name: 'AdminPanel',

  props: ['client'],

  async created () {
    try {
      const data = await this.client.getAdminPanelData()
      if (data.error && data.error.type === 'low-php') {
        this.lowPHPVersion = data.error.version
      } else {
        this.loaded = true
        this.setData(data)
      }
    } catch (e) {
      this.requestError = e
    }
  },

  data () {
    return {
      lowPHPVersion: false,
      loaded: false,
      requestError: false,
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
        try {
          this.setData(await this.client.saveConfig(newConfig))
        } catch (e) {
          this.requestError = e
        }
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
    SavingStatus,
    LowPhpVersionNotice,
    Notification,
    Settings,
    Panel
  }
}
</script>

<style scoped lang="sass">
  .phastpress-notification
    margin-bottom: 12px
  .phastpress-saving-status
    padding-right: 5px
    text-align: right
</style>

<i18n>
  default:
    errors:
      'no-cache-root': 'PhastPress can not write to any cache directory! Please, make one of the following directories writable: {params}'
      'no-service-config': 'PhastPress failed to create a service configuration in any of the following directories: {params}'
      'network-error': 'Failed to connect to WordPress server! Please, try again later! {params}'
    warnings:
      disabled: 'PhastPress optimizations are off!'
      'admin-only': >
        PhastPress optimizations will be applied only for logged-in users with the "Administrator" privilege.
        This is for previewing purposes.
        Select the "On" setting for "PhastPress optimizations" below to activate for all users!
</i18n>
