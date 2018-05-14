<template>
  <div id="app">
    <OnOffSwitch on-value="asd" off-value="qwe" v-model="t"></OnOffSwitch>
    {{ t }}
    <br><br>
    Let info be here
    <Information>
      <template slot="title">Additional information in a (but in slot)</template>
      <template slot="content">
        Here we go with the extra info
        Here we go with the extra info
        Here we go with the extra info
        Here we go with the extra info
      </template>
    </Information>
    <section-title color="blue">Title in slot</section-title>
  </div>
</template>

<script>
import OnOffSwitch from './components/OnOffSwitch'
import Information from './components/Information'
import SectionTitle from './components/SectionTitle'

export default {
  name: 'App',
  data () {
    return {
      t: 'asd'
    }
  },
  components: {
    OnOffSwitch,
    Information,
    SectionTitle
  }
}
</script>

<style>
#app {
  font-family: 'Avenir', Helvetica, Arial, sans-serif;
}
</style>
