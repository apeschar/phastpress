<template>
  <div class="wrap">
    <h1 v-t="'title'" class="wp-heading-inline"></h1>
    <admin-panel :client="client"></admin-panel>
  </div>
</template>

<script>
import AdminPanel from './components/AdminPanel'
import WordPressAPIClient from './api/Client'

// eslint-disable-next-line
const client = new WordPressAPIClient(ajaxurl)
export default {
  name: 'WordPressApp',
  components: {AdminPanel},
  data () {
    return {
      client: client
    }
  }
}
</script>

<i18n>
  default:
    title: 'PhastPress'
</i18n>
