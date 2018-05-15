<template>
  <div class="notification" :class="type">
    <div class="message">
      <span class="message-title">
        {{ type }}:
      </span>
      <span class="message-body">
        <slot></slot>
      </span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Notification',
  props: ['type']
}
</script>

<style scoped lang="sass">
  @import "global"

  .notification
    display: flex
    align-items: center
    height: 60px
    padding-left: 20px
    border-radius: 4px
    color: white

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

    &.error
      background-color: #f36523
      &:before
        background-image: url('../assets/error.png')
    &.warning
      background-color: #2e3192
      &:before
        background-image: url('../assets/warning.png')
    &.information
      background-color: $blue
      &:before
        background-image: url('../assets/information.png')
    &.success
      background-color: #3ab54a
      &:before
        background-image: url('../assets/success.png')

    &:after
      display: block
      position: absolute
      right: 16px
      content: '\00d7'
      font-size: 32px
      font-weight: bold
      opacity: 0.3
      color: black

  .message-title
    text-transform: uppercase
</style>
