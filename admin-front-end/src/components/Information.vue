<template>
  <div class="information">
    <sup class="icon"></sup>
    <div class="block">
      <div class="title">
        <slot name="title">Additional information</slot>
      </div>
      <div class="content">
        <slot name="content"></slot>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Information'
}
</script>

<style scoped>

  .information {
    display: inline-block;
    position: relative;
  }

  .information:hover .block {
    display: block;
  }

  .icon {
    display: inline-block;
    margin-left: 5px;
    width: 12px;
    height: 12px;
    background: no-repeat center/12px url('../assets/exclamation.png');
    opacity: 0.5;
    cursor: pointer;
  }

  .block {
    display: none;
    position: absolute;
    top: -20px;
    left: 14px;
    box-sizing: border-box;
    margin-left: 15px;
    width: 329px;
    padding: 12px 10px 15px 56px;
    border: 1px solid #f4f4f4;
    border-radius: 4px;
  }

  .block::before {
    display: block;
    position: absolute;
    top: 15px;
    left: 20px;
    content: " ";
    width: 24px;
    height: 24px;
    background: no-repeat 0 0 url('../assets/exclamation.png');
    opacity: 0.5;
  }

  .block::after {
    display: block;
    position: absolute;
    width: 15px;
    height: 15px;
    top: 15px;
    left: -9px;
    content: " ";
    transform: rotate(45deg);
    border-left: 1px solid #f4f4f4;
    border-bottom: 1px solid #f4f4f4;
    background: white;
  }

  .title {
    margin-bottom: 8px;
    font-size: 12px;
    font-weight: bold;
    color: #74d2f6;
    text-transform: uppercase;
  }

  .content {
    font-size: 12px;
    color: #c2c2c2;
  }

</style>
