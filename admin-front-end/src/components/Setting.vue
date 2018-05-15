<template>
  <div class="setting">
    <div class="title">
      <slot></slot>
    </div>
    <div class="switch-holder">
      <on-off-switch v-model="value" on-value="On" off-value="Off" />
    </div>
  </div>
</template>

<script>
import OnOffSwitch from './OnOffSwitch'
export default {
  name: 'Setting',
  components: {OnOffSwitch},
  props: ['value']
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
