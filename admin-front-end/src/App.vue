<template>
  <div id="app">
    <OnOffSwitch on-value="asd" off-value="qwe" v-model="t"></OnOffSwitch>
    {{ t }}
    <br><br>
    Let info be here
    <Information>
      <template slot="title">Additional information in a (but in slot)</template>
      <templte slot="content">
        Here we go with the extra info
        Here we go with the extra info
        Here we go with the extra info
        Here we go with the extra info
      </templte>
    </Information>
  </div>
</template>

<script>
import OnOffSwitch from './components/OnOffSwitch'
import Information from './components/Information'

export default {
  name: 'App',
  data () {
    return {
      t: 'asd'
    }
  },
  components: {
    Information,
    OnOffSwitch
  }
}
</script>

<style>
#app {
  font-family: 'Avenir', Helvetica, Arial, sans-serif;
}
</style>
