<template>
  <div class="phastpress-information">
    <sup class="phastpress-icon"></sup>
    <div class="phastpress-block">
      <div class="phastpress-title">
        {{ title || $t('additional') }}
      </div>
      <div class="phastpress-content">
        <slot></slot>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Information',
  props: ['title']
}
</script>

<style scoped lang="sass">

  @import "global"

  $title-color: #82d6f8
  $border-color: #f4f4f4

  @mixin exclamation($size, $color)
    $border-size: 1px
    $dimension: $size + $border-size * 2
    content: "\0021"
    width: $dimension
    height: $dimension
    border: $border-size solid $color
    border-radius: $size
    font-size: $size
    line-height: $size
    text-align: center
    color: $color
    font-weight: bold

  .phastpress-information
    display: inline-block
    position: relative

  .phastpress-information:hover .phastpress-block
    display: block

  .phastpress-icon::before
    @include exclamation(12px, $title-color)
    display: inline-block
    margin-left: 5px
    cursor: pointer

  .phastpress-block
    display: none
    position: absolute
    top: -17px
    left: 14px
    margin-left: 15px
    z-index: 1000
    width: 400px
    padding: 12px 10px 15px 56px
    border: 1px solid $border-color
    border-radius: 4px
    background-color: white
    box-shadow: 5px 12px 24px -8px rgba(0, 0, 0, .2)
    font-size: 12px

  .phastpress-block::before
    @include exclamation(24px, #858585)
    display: block
    position: absolute
    top: 10px
    left: 20px

  .phastpress-block::after
    display: block
    position: absolute
    width: 15px
    height: 15px
    top: 15px
    left: -9px
    content: " "
    transform: rotate(45deg)
    border-left: 1px solid $border-color
    border-bottom: 1px solid $border-color
    background: white

  .phastpress-title
    margin-bottom: 5px
    font-weight: bold
    color: $title-color
    text-transform: uppercase

</style>

<i18n>
  default:
    additional: 'Additional information'
</i18n>
