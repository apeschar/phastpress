<template>
  <div id="app">
    <OnOffSwitch></OnOffSwitch>
  </div>
</template>

<script>
import OnOffSwitch from './components/OnOffSwitch'

export default {
  name: 'App',
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
