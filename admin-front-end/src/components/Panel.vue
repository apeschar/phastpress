<template>
  <div class="phastpress-panel">
      <slot></slot>
  </div>
</template>

<script>
export default {
  name: 'Panel'
}
</script>

<style scoped lang="sass">
  .phastpress-panel
    padding: 20px 44px 29px 22px
    border: 1px solid #e1e1e1
    border-radius: 4px
    background-color: white
    color: #444444
</style>
