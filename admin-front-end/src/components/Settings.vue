<template>
  <div>

    <div class="phastpress-settings-section">
      <section-title color="#00aeef">
        <span v-t="'sections.plugin.title'"></span>
      </section-title>

      <setting :value="v('enabled')" @input="update('enabled', $event)">
        <span v-t="'sections.plugin.enabled.name'"></span>
        <information>
          <b v-t="'common.tip'"></b>
          <i18n path="sections.plugin.enabled.description.main">
            <a
                v-t="'sections.plugin.enabled.description.without'"
                place="without"
                :href="strings.urlWithoutPhast"
                target="_blank"
            ></a>
            <a
                v-t="'sections.plugin.enabled.description.with'"
                place="with"
                :href="strings.urlWithPhast"
                target="_blank"
            ></a>
          </i18n>
        </information>
      </setting>

      <setting :disabled="disabled" :value="v('adminOnly')" @input="update('adminOnly', $event)">
        <span v-t="'sections.plugin.admin-only.name'"></span>
        <information>
          <i v-t="'common.on'"></i>
          <span v-t="'sections.plugin.admin-only.description.on'"></span>
          <br>
          <i v-t="'common.off'"></i>
          <span v-t="'sections.plugin.admin-only.description.off'"></span>
          <br>
          <b v-t="'common.tip'"></b>
          <span v-t="'sections.plugin.admin-only.description.tip'"></span>
        </information>
      </setting>

      <setting :disabled="disabled" :value="v('pathinfoQueryFormat')" @input="update('pathinfoQueryFormat', $event)">
        <span v-t="'sections.plugin.pathinfo.name'"></span>
        <information>
          <span v-t="'sections.plugin.pathinfo.description.start'"></span>
          <br>
          <i v-t="'common.on'"></i>
          <span v-t="'sections.plugin.pathinfo.description.on'"></span>
          <br>
          <i v-t="'common.off'"></i>
          <span v-t="'sections.plugin.pathinfo.description.off'"></span>
        </information>
      </setting>

      <setting :disabled="disabled" :value="v('footerLink')" @input="update('footerLink', $event)">
        <span v-t="'sections.plugin.footer-link.name'"></span>
        <information>
          <span v-t="'sections.plugin.footer-link.description'"></span>
        </information>
      </setting>
    </div>

    <div class="phastpress-settings-section">
      <section-title color="#f26c4f">
        <span v-t="'sections.images.title'"></span>
      </section-title>

      <setting :disabled="disabled" :value="v('imgOptimizationTags')" @input="update('imgOptimizationTags', $event)">
        <span v-t="'sections.images.tags.name'"></span>
        <information>
          <i18n path="sections.images.tags.description">
            <br place="newline">
            <span place="width">{{ strings.maxImageWidth }}</span>
            <span place="height">{{ strings.maxImageHeight }}</span>
            <code place="imgTag">&lt;img&gt;</code>
            <code place="widthAttr">width</code>
            <code place="heightAttr">height</code>
          </i18n>
        </information>
      </setting>

      <setting :disabled="disabled" :value="v('imgOptimizationCss')" @input="update('imgOptimizationCss', $event)">
        <span v-t="'sections.images.css.name'"></span>
        <information>
          <i18n path="sections.images.css.description.0" tag="div">
            <span place="width">{{ strings.maxImageWidth }}</span>
            <span place="height">{{ strings.maxImageHeight }}</span>
          </i18n>
          <div v-t="'sections.images.css.description.1'"></div>
        </information>
      </setting>

      <setting :disabled="disabled" :value="v('imgOptimizationApi')" @input="update('imgOptimizationApi', $event)">
        <span v-t="'sections.images.api.name'"></span>
        <information>
          <div v-t="'sections.images.api.description.0'"></div>
          <div v-t="'sections.images.api.description.1'"></div>
        </information>
      </setting>
    </div>

    <div class="phastpress-settings-section">
      <section-title color="#a287be">
        <span v-t="'sections.html-filters.title'"></span>
      </section-title>

      <setting :disabled="disabled" :value="v('cssOptimization')" @input="update('cssOptimization', $event)">
        <span v-t="'sections.html-filters.css.name'"></span>
        <information>
          <div v-t="'sections.html-filters.css.description.0'"></div>
          <div v-t="'sections.html-filters.css.description.1'"></div>
          <div v-t="'sections.html-filters.css.description.2'"></div>
        </information>
      </setting>

      <setting :disabled="disabled" :value="v('scriptsRearrangement')" @input="update('scriptsRearrangement', $event)">
        <span v-t="'sections.html-filters.move-js.name'"></span>
        <information>
          <span v-t="'sections.html-filters.move-js.description'"></span>
        </information>
      </setting>

      <setting :disabled="disabled" :value="v('scriptsDefer')" @input="update('scriptsDefer', $event)">
        <span v-t="'sections.html-filters.async-js.name'"></span>
        <information>
          <span v-t="'sections.html-filters.async-js.description'"></span>
        </information>
      </setting>

      <setting :disabled="disabled" :value="v('scriptsProxy')" @input="update('scriptsProxy', $event)">
        <span v-t="'sections.html-filters.minify-js.name'"></span>
        <information>
          <div v-t="'sections.html-filters.minify-js.description.0'"></div>
          <div v-t="'sections.html-filters.minify-js.description.1'"></div>
        </information>
      </setting>

      <setting :disabled="disabled" :value="v('iframeDefer')" @input="update('iframeDefer', $event)">
        <span v-t="'sections.html-filters.iframe.name'"></span>
        <information>
          <span v-t="'sections.html-filters.iframe.description'"></span>
        </information>
      </setting>
    </div>

  </div>
</template>
<script>
import SectionTitle from './SectionTitle'
import Setting from './Setting'
import Information from './Information'
export default {
  name: 'Settings',
  components: {Information, Setting, SectionTitle},
  props: ['value', 'strings'],

  methods: {
    v (key) {
      return this.value[this.getConfigKey(key)]
    },
    update (key, value) {
      const newConfig = Object.assign({}, this.value)
      newConfig[this.getConfigKey(key)] = value
      this.$emit('input', newConfig)
    },
    getConfigKey (key) {
      return key.replace(/[A-Z]/g, m => '-' + m.toLowerCase())
    }
  },

  computed: {
    disabled () {
      return !this.value[this.getConfigKey('enabled')]
    }
  }
}
</script>
<style scoped lang="sass">
  .phastpress-settings-section
    margin-bottom: 72px

  .phastpress-settings-section:last-child
    margin-bottom: 0

  .phastpress-setting
    margin-bottom: 23px

  code
    padding-right: 5px
    background: rgba(0, 0, 0, .2)
    border: 1px solid rgba(0, 0, 0, .02)
    border-radius: 2px
    font-size: 13px
    color: #f8f8f8

</style>

<i18n>
  default:
    common:
      tip: 'Tip:'
      on: 'On:'
      off: 'Off:'
    sections:
      plugin:
        title: 'Plugin'
        enabled:
          name: 'PhastPress optimizations'
          description:
            main: 'Test your site {without} and {with}'
            without: 'without phastpress'
            with: 'with phastpress'
        admin-only:
          name: 'Disable for non-logged users'
          description:
            on:  'Only logged in users will be served with optimized version'
            off: 'All users will be served with optimized version'
            tip: 'Use to preview your site before launching the optimizations'
        pathinfo:
          name: 'Remove query string from processed resources'
          description:
            start: 'Make sure that processed resources don''t have query strings, for a higher score in GTmetrix.'
            on: 'Use the path for requests for processed resources. This requires a server that supports "PATH_INFO".'
            off: 'Use the GET parameters for requests for processed resources.'
        footer-link:
          name: 'Let the world know about PhastPress'
          description: 'Add a "Optimized by PhastPress" notice to the footer of your site and help spread the word.'
      images:
        title: 'Images'
        tags:
          name: 'Optimize images in tags'
          description: >
            Compress images with optimal settings. {newline}
            Resize images to fit {width}x{height} pixels or to the appropriate size for
            {imgTag} tags with {widthAttr} or {heightAttr}. {newline}
            Reload changed images while still leveraging browser caching.
        css:
          name: 'Optimize images in CSS'
          description:
            - 'Compress images in stylesheets with optional settings and resizes the to fit {width}x{height} pixels.'
            - 'Reload changed images while still leveraging browser caching.'
        api:
          name: 'Use the Phast Image Optimization API'
          description:
            - 'Optimize your images on our servers free of charge.'
            - >
              This will give you the best possible results without installing any software
              and will reduce the load on your hosting.
      html-filters:
        title: 'HTML, CSS & JS'
        css:
          name: 'Optimize CSS'
          description:
            - 'Incline critical styles first and prevent unused styles from blocking the page load.'
            - 'Minify stylesheets and leverage browser caching.'
            - 'Inline Google Fonts CSS to speed up font loading.'
        move-js:
          name: 'Move scripts to end of body'
          description: 'Prevent scripts from blocking the page load by loading the after the HTML and CSS.'
        async-js:
          name: 'Load scripts asynchronously'
          description: 'Allow the page to finish loading before all scripts have been executed.'
        minify-js:
          name: 'Minify scripts and improve caching'
          description:
            - 'Minify scripts and fix caching for Google Analytics and Hotjar.'
            - 'Reload changed scripts while still leveraging browser caching.'
        iframe:
          name: 'Defer IFrame loading'
          description: 'Start loading IFrames after the page has finished loading.'
</i18n>
