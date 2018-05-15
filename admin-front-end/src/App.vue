<template>
  <div id="app">
    <on-off-switch on-value="asd" off-value="qwe" v-model="t"></on-off-switch>
    <on-off-switch disabled="true"></on-off-switch>
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
    <notification type="error">
      This is an error
    </notification>
    <notification type="warning">
      This is a warning
    </notification>
    <notification type="information">
      This is information
    </notification>
    <notification type="success">
      This is success
    </notification>
  </div>
</template>

<script>
import OnOffSwitch from './components/OnOffSwitch'
import Information from './components/Information'
import SectionTitle from './components/SectionTitle'
import Setting from './components/Setting'
import Notification from './components/Notification'

export default {
  name: 'App',
  data () {
    return {
      t: 'asd'
    }
  },
  components: {
    Notification,
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
