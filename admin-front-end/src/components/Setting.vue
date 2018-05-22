<template>
  <div class="phastpress-setting">
    <div class="phastpress-title">
      <slot></slot>
    </div>
    <on-off-switch
      :value="value"
      @input="$emit('input', $event)"
      :on-value="true"
      :off-value="false"
      :disabled="disabled"
    />
  </div>
</template>

<script>
import OnOffSwitch from './OnOffSwitch'
export default {
  name: 'Setting',
  components: {OnOffSwitch},
  props: ['value', 'disabled']
}
</script>

<style scoped lang="sass">
  .phastpress-setting
    display: flex
    align-items: center
    justify-content: space-between
    padding-left: 16px
</style>
