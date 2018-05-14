<template>
  <div id="app">
    <on-off-switch on-value="asd" off-value="qwe" v-model="t"></on-off-switch>
    {{ t }}
    <br><br>
    Let info be here
    <information>
      Here we go with the extra info
      Here we go with the extra info
      Here we go with the extra info
      Here we go with the extra info
    </information>
    <section-title color="blue">Title in slot</section-title>
    <setting>
      The is the first setting
    </setting>
    <setting>
      This is the second setting with info
      <information>
        This is the info for the second setting
      </information>
    </setting>
  </div>
</template>

<script>
import OnOffSwitch from './components/OnOffSwitch'
import Information from './components/Information'
import SectionTitle from './components/SectionTitle'
import Setting from './components/Setting'

export default {
  name: 'App',
  data () {
    return {
      t: 'asd'
    }
  },
  components: {
    Setting,
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
