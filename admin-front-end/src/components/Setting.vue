<template>
  <div class="setting">
    <div class="title">
      <slot></slot>
    </div>
    <div class="switch-holder">
      <on-off-switch v-model="currentValue" on-value="on" off-value="off" />
    </div>
  </div>
</template>

<script>
import OnOffSwitch from './OnOffSwitch'
export default {
  name: 'Setting',
  components: {OnOffSwitch},
  props: ['value'],
  data () {
    return {
      currentValue: this.value
    }
  },
  watch: {
    currentValue (v) {
      this.$emit('input', v)
    }
  }
}
</script>

<style scoped lang="sass">
  .setting
    display: flex
    align-items: center
    justify-content: space-between
    margin-bottom: 23px
    padding-left: 16px
</style>
