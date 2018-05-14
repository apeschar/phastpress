<template>
  <div :class="{'on-off-switch': true, on: on}" @click="toggle()">
    <div class="layer layer-bottom">
      <div class="label label-off">
        Off
      </div>
      <div class="label label-on">
        On
      </div>
    </div>
    <div class="button"></div>
    <div class="layer layer-top">
      <div class="label label-off">
        Off
      </div>
      <div class="label label-on">
        On
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'OnOffSwitch',
  data () {
    return {
      on: false
    }
  },
  methods: {
    toggle () {
      this.on = !this.on
    }
  }
}
</script>

<style scoped>
  .on-off-switch {
    cursor: pointer;
    position: relative;
    width: 163px;
    height: 34px;
    padding: 2px;
    border: 1px solid #fbfbfb;
    border-radius: 4px;
    background-color: #dddddd;
  }

  .layer {
    position: absolute;
    width: calc(100% - 4px);
  }

  .layer-bottom {
    color: #d3d3d3;
  }

  .layer-top {
    color: white;
  }

  .label {
    position: absolute;
    box-sizing: border-box;
    display: inline-block;
    width: 82px;
    padding-top: 10px;
    text-align: center;
    overflow: hidden;
  }

  .label-on {
    left: 50%;
  }

  .button {
    position: absolute;
    width: 80px;
    height: 32px;
    background-color: #00aeef;
    border: 1px solid #c3d7df;
    border-radius: 4px;
  }

  .layer-top .label-on {
    width: 0;
  }

  .on .button {
    right: 2px;
  }

  .on .layer-top .label-on {
    width: 82px;
  }

  .on .layer-top .label-off {
    background: red;
    width: 0;
  }
</style>
