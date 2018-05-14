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
  *, *:before, *:after {
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
    width: 50%;
    height: 100%;
    top: 0;
    left: 0;
    padding: 2px;
    transition: left .25s;
  }

  .button:after {
    display: block;
    content: " ";
    width: 100%;
    height: 100%;
    background-color: #00aeef;
    border: 1px solid #c3d7df;
    border-radius: 4px;
  }

  .on .button {
    left: 50%;
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
</style>
