<template>
  <div class="phastpress-status-info">
    <div class="phastpress-status-image">&times;</div>
    <div class="phastpress-status-title" v-if="$slots.title"><slot name="title"></slot></div>
    <div class="phastpress-status-text"><slot></slot></div>
  </div>
</template>

<script>
export default {
  name: 'StatusInfo'
}
</script>

<style scoped lang="sass">
  .phastpress-status-info
    display: flex
    flex-direction: column
    text-align: center

  $icon-size: 60px
  .phastpress-status-image
    margin: auto
    margin-bottom: 24px
    width: $icon-size
    height: $icon-size
    border-radius: $icon-size
    border: $icon-size / 12 solid #d6d6d6
    font-size: $icon-size
    line-height: $icon-size
    color: #e2412f

  .phastpress-status-title
    margin-bottom: 50px
    font-weight: bold
    font-size: 16px

</style>
