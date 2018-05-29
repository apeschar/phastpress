<template>
  <div class="phastpress-saving-status" :class="{'phastpress-hidden': !saving}">
    <span v-if="saving" v-t="'saving'"></span>
    <span v-else v-t="'saved'"></span>
  </div>
</template>
<script>
export default {
  name: 'SavingStatus',
  props: ['client'],

  created () {
    this.client.onsavestarted = function () {
      this.saving = true
    }.bind(this)
    this.client.onsavefinished = function () {
      this.saving = false
    }.bind(this)
  },

  data () {
    return {
      saving: false
    }
  }
}
</script>
<style scoped lang="sass">
  .phastpress-saving-status
    font-weight: bold
    &::after
      content: '...'
    &.phastpress-hidden
      opacity: 0
      transition: opacity 250ms linear 1.5s
      &::after
        content: ''
</style>

<i18n>
  default:
    saving: Saving
    saved: Saved
</i18n>
