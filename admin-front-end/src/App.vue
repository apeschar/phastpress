<template>
  <div class="wrap">
    <h1 v-t="'title'" class="wp-heading-inline"></h1>
    <admin-panel :client="client"></admin-panel>
  </div>
</template>

<script>
import AdminPanel from './components/AdminPanel'
export default {
  name: 'App',
  components: {AdminPanel},
  props: ['client']
}
</script>
