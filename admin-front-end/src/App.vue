<template>
  <div id="app">
    <OnOffSwitch on-value="asd" off-value="qwe" v-model="t"></OnOffSwitch>
    {{ t }}
  </div>
</template>

<script>
import OnOffSwitch from './components/OnOffSwitch'

export default {
  name: 'App',
  data () {
    return {
      t: 'asd'
    }
  },
  components: {
    OnOffSwitch
  }
}
</script>

<style>
#app {
  font-family: 'Avenir', Helvetica, Arial, sans-serif;
}
</style>
