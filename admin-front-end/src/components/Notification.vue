<template>
  <div class="phastpress-notification" :class="['phastpress-' + type, {'phastpress-dismissible' : dismissible}]">
    <div class="phastpress-message">
      <span class="phastpress-message-title">
        {{ $t(type ) }}:
      </span>
      <span class="phastpress-message-body">
        <slot></slot>
      </span>
    </div>
    <div class="phastpress-close-btn" v-if="dismissible" @click="dismiss">&times;</div>
  </div>
</template>

<script>
export default {
  name: 'Notification',
  props: ['type', 'dismissible', 'dismissTimeout'],

  created () {
    if (this.dismissTimeout) {
      setTimeout(this.dismiss, this.dismissTimeout)
    }
  },

  _dismissTimeoutHnd: false,
  methods: {
    dismiss () {
      clearTimeout(this._dismissTimeoutHnd)
      this.$emit('dismiss')
    }
  }
}
</script>

<style scoped lang="sass">
  @import "global"

  .phastpress-notification
    display: flex
    align-items: center
    height: 60px
    padding: 0 16px 0 20px
    border-radius: 4px
    color: white
    font-weight: bold

    &:before
      display: block
      content: " "
      position: relative
      width: 32px
      height: 32px
      margin-right: 16px
      background-size: 32px
      background-repeat: no-repeat
      background-position: center
      opacity: 0.5

    &.phastpress-error
      background-color: #f36523
      &:before
        background-image: url('../assets/error.png')
    &.phastpress-warning
      background-color: #2e3192
      &:before
        background-image: url('../assets/warning.png')
    &.phastpress-information
      background-color: $blue
      &:before
        background-image: url('../assets/information.png')
    &.phastpress-success
      background-color: #3ab54a
      &:before
        background-image: url('../assets/success.png')

    &.phastpress-dismissible .phastpress-close-btn
      font-size: 32px
      font-weight: bold
      opacity: 0.3
      color: black
      cursor: pointer

  .phastpress-message
    flex: 1

  .phastpress-message-title
    text-transform: uppercase
</style>

<i18n>
  default:
    error: error
    warning: warning
    information: information
    success: success
</i18n>
