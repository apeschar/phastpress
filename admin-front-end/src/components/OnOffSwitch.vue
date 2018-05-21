<template>
  <div
    class="phastpress-on-off-switch"
    :class="{'phastpress-on': isOn,
    'phastpress-disabled': disabled}"
    @click="toggle()"
  >
    <div class="phastpress-button"></div>
    <div class="phastpress-label phastpress-label-off">
      {{ offLabel || $t('off') }}
    </div>
    <div class="phastpress-label phastpress-label-on">
      {{ onLabel || $t('on') }}
    </div>
  </div>
</template>

<script>
export default {
  name: 'OnOffSwitch',
  props: ['onValue', 'offValue', 'value', 'onLabel', 'offLabel', 'disabled'],
  data () {
    return {
      isOn: this.value === this.onValue
    }
  },
  methods: {

    toggle () {
      if (this.disabled) {
        return
      }
      this.isOn = !this.isOn
      this.$emit('input', this.isOn ? this.onValue : this.offValue)
    }

  }
}
</script>

<style scoped lang="sass">
  @import "global"

  .phastpress-on-off-switch
    display: flex
    align-items: center
    justify-content: space-around
    cursor: pointer
    position: relative
    width: 169px
    height: 40px
    border: 1px solid #fbfbfb
    border-radius: 4px
    background-color: #dddddd
    user-select: none

  .phastpress-label
    position: relative
    padding-top: 3px

  .phastpress-button
    position: absolute
    width: 50%
    height: 100%
    top: 0
    left: 0
    padding: 2px
    transition: left .25s

  .phastpress-button:after
    display: block
    content: " "
    width: 100%
    height: 100%
    background-color: $blue
    border: 1px solid #c3d7df
    border-radius: 4px

  .phastpress-disabled .phastpress-button:after
    background-color: #c0c0c0
    border-color: #c0c0c0

  .phastpress-on .phastpress-button
    left: 50%

  .phastpress-label-on
    color: #d3d3d3

  .phastpress-label-off
    color: white

  .phastpress-on .phastpress-label-on
    color: white

  .phastpress-on .phastpress-label-off
    color: #d3d3d3
</style>

<i18n>
  default:
    on: 'On'
    off: 'Off'
</i18n>
