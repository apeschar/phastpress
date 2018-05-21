<template>
  <div>
    <div class="phastpress-section-title" :style="{'border-left-color' : color}">
      <slot></slot>
    </div>
  </div>
</template>
<script>
export default {
  name: 'SectionTitle',
  props: ['color']
}
</script>
<style scoped lang="sass">
  .phastpress-section-title
    margin-bottom: 10px
    height: 30px
    line-height: 30px
    padding-left: 12px
    border-left: 4px solid
    font-weight: bold
    font-size: 15px
</style>
