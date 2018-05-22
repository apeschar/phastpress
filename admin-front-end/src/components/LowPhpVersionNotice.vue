<template>
  <panel>
    <div class="phastpress-welcome">
      <div class="phastpress-welcome-heading" v-t="'welcome.heading'"></div>
      <div class="phastpress-welcome-subheading" v-t="'welcome.sub-heading'"></div>
    </div>
    <div class="phastpress-error">
      <status-info>
        <span  v-t="'error.title'" slot="title"></span>
        <span v-html="$t('error.message', {version: phpVersion})"></span>
      </status-info>
    </div>
  </panel>
</template>

<script>
import Panel from './Panel'
import StatusInfo from './StatusInfo'
export default {
  name: 'LowPhpVersionNotice',
  components: {StatusInfo, Panel},
  props: ['phpVersion']
}
</script>

<style scoped lang="sass">

  .phastpress-welcome-heading
    font-size: 23px
    color: #434446

  .phastpress-welcome-subheading
    margin-top: 11px
    font-size: 16px
    color: #898989

  .phastpress-error
    margin: 150px auto
</style>
<i18n>
  default:
    welcome:
      heading: 'Welcome to PhastPress!'
      sub-heading: 'This plugin will boost your web speed & total performance drastically!'
    error:
      title: 'Ups! Unfortunately this is an error!'
      message: >
        The version of PHP your server is running is lower than that required by PhastPress!<br>
        You will not be able to run PhastPress unless you upgrade to PHP 5.6 or higher.<br>
        Required PHP version: >= 5.6. Your PHP version: {version}.
</i18n>
