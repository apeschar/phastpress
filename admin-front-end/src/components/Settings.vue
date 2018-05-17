<template>
  <div>

    <div class="settings-section">
      <section-title color="#00aeef">
        <span v-t="'sections.plugin.title'"></span>
      </section-title>

      <setting>
        <span v-t="'sections.plugin.enabled.name'"></span>
        <information>
          <b v-t="'common.tip'"></b>
          <i18n path="sections.plugin.enabled.description.main">
            <a href="#" place="without" v-t="'sections.plugin.enabled.description.without'"></a>
            <a href="#" place="with" v-t="'sections.plugin.enabled.description.with'"></a>
          </i18n>
        </information>
      </setting>

      <setting>
        {{ $t('sections.plugin.admin-only.name')}}
        <information>
          <i v-t="'common.on'"></i> <span v-t="'sections.plugin.admin-only.description.on'"></span>
          <br>
          <i v-t="'common.off'"></i> {{ $t('sections.plugin.admin-only.description.off')}}
          <br>
          <b v-t="'common.tip'"></b> <span v-t="'sections.plugin.admin-only.description.tip'"></span>
        </information>
      </setting>

      <setting>
        <span v-t="'sections.plugin.pathinfo.name'"></span>
        <information>
          <i v-t="'common.on'"></i>
          <span v-t="'sections.plugin.pathinfo.description.on'"></span>
          <br>
          <i v-t="'common.off'"></i>
          <span v-t="'sections.plugin.pathinfo.description.off'"></span>
          <br>
          <i18n path="sections.plugin.pathinfo.description.more">
            <i v-t="'sections.plugin.pathinfo.description.value'" place="value"></i>
          </i18n>
        </information>
      </setting>

      <setting>
        <span v-t="'sections.plugin.footer-link.name'"></span>
        <information>
          <span v-t="'sections.plugin.footer-link.description'"></span>
        </information>
      </setting>
    </div>

    <div class="settings-section">
      <section-title color="#f26c4f">
        <span v-t="'sections.images.title'"></span>
      </section-title>

      <setting>
        <span v-t="'sections.images.tags.name'"></span>
        <information>
          <i18n path="sections.images.tags.description">
            <br place="newline">
            <code place="imgTag">&lt;img&gt;</code>
            <code place="widthAttr">width</code>
            <code place="heightAttr">height</code>
          </i18n>
        </information>
      </setting>

      <setting>
        <span v-t="'sections.images.css.name'"></span>
        <information>
          <div v-t="'sections.images.css.description.0'"></div>
          <div v-t="'sections.images.css.description.1'"></div>
        </information>
      </setting>

      <setting>
        <span v-t="'sections.images.api.name'"></span>
        <information>
          <div v-t="'sections.images.api.description.0'"></div>
          <div v-t="'sections.images.api.description.1'"></div>
          <div>
            <i18n path="sections.images.api.description.2">
              <a href="mailto:" place="email">the-address@the-site.com</a>
            </i18n>
          </div>
        </information>
      </setting>
    </div>

    <div class="settings-section">
      <section-title color="#a287be">
        <span v-t="'sections.html-filters.title'"></span>
      </section-title>

      <setting>
        <span v-t="'sections.html-filters.css.name'"></span>
        <information>
          <div v-t="'sections.html-filters.css.description.0'"></div>
          <div v-t="'sections.html-filters.css.description.1'"></div>
          <div v-t="'sections.html-filters.css.description.2'"></div>
        </information>
      </setting>

      <setting>
        <span v-t="'sections.html-filters.move-js.name'"></span>
        <information>
          <span v-t="'sections.html-filters.move-js.description'"></span>
        </information>
      </setting>

      <setting>
        <span v-t="'sections.html-filters.async-js.name'"></span>
        <information>
          <span v-t="'sections.html-filters.async-js.description'"></span>
        </information>
      </setting>

      <setting>
        <span v-t="'sections.html-filters.minify-js.name'"></span>
        <information>
          <div v-t="'sections.html-filters.minify-js.description.0'"></div>
          <div v-t="'sections.html-filters.minify-js.description.1'"></div>
        </information>
      </setting>

      <setting>
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
  components: {Information, Setting, SectionTitle}
}
</script>
<style scoped>
  .settings-section {
    margin-bottom: 72px;
  }
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
          name: 'Use path info format for service queries'
          description:
            on: 'use a more "fancy" format for queries for processed resources'
            off: 'use the GET parameters for queries for processed resources'
            more: 'This setting will be automatically set to {value} if available after you install PhastPress.'
            value: 'On'
        footer-link:
          name: 'Let the world know about PhastPress'
          description: 'Add a "Optimized by PhastPress" notice to the footer of your site and help spread the word.'
      images:
        title: 'Images'
        tags:
          name: 'Optimize images in tags'
          description: >
            Compress images with optimal settings. {newline}
            Resize images to fit 3840x2160 pixels or to the appropriate size for
            {imgTag} tags with {widthAttr} or {heightAttr}. {newline}
            Reload changed images while still leveraging browser caching.
        css:
          name: 'Optimize images in CSS'
          description:
            - 'Compress images in stylesheets with optional settings and resizes the to fit 3840x2160 pixels.'
            - 'Reload changed images while still leveraging browser caching.'
        api:
          name: 'Use the Phast Image Optimization API'
          description:
            - 'Optimize your images on our servers free of charge.'
            - >
              This will give you the best possible results without installing any software
              and will reduce the load on your hosting.
            - >
               We will use your email address {email}
               to keep you up to date about changes to the API.
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
