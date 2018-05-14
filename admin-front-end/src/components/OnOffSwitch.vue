<template>
  <div class="on-off-switch" :class="{on: isOn}" @click="toggle()">
    <div class="button"></div>
    <div class="label label-off">
      {{ offLabel || 'Off' }}
    </div>
    <div class="label label-on">
      {{ onLabel || 'On' }}
    </div>
  </div>
</template>

<script>
export default {
  name: 'OnOffSwitch',
  props: ['onValue', 'offValue', 'value', 'onLabel', 'offLabel'],
  data () {
    return {
      isOn: this.value === this.onValue
    }
  },
  methods: {

    toggle () {
      this.isOn = !this.isOn
      this.$emit('input', this.isOn ? this.onValue : this.offValue)
    }

  }
}
</script>

<style scoped>
  * {
    box-sizing: border-box;
  }

  .on-off-switch {
    display: flex;
    align-items: center;
    justify-content: space-around;
    cursor: pointer;
    position: relative;
    width: 169px;
    height: 40px;
    border: 1px solid #fbfbfb;
    border-radius: 4px;
    background-color: #dddddd;
    user-select: none;
  }

  .label {
    position: relative;
    padding-top: 3px;
  }

  .button {
    position: absolute;
    left: 2px;
    width: 80px;
    top: 2px;
    bottom: 2px;
    background-color: #00aeef;
    border: 1px solid #c3d7df;
    border-radius: 4px;
    animation: 0.25s button-slide-off;
  }

  .on .button {
    left: 84px;
    animation: 0.25s button-slide-on;
  }

  .label-on {
    color: #d3d3d3;
  }

  .label-off {
    color: white;
  }

  .on .label-on {
    color: white;
  }

  .on .label-off {
    color: #d3d3d3;
  }

  @keyframes button-slide-on {
    from {
      left: 2px;
    }

    to {
      left: 84px;
    }
  }

  @keyframes button-slide-off {
    from {
      left: 84px;
    }

    to {
      left: 2px;
    }
  }
</style>
