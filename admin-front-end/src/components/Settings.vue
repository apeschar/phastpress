<template>
  <div>

    <div class="settings-section">
      <section-title color="#00aeef">Plugin</section-title>

      <setting>
        PhastPress optimizations
        <information>
          <b>Tip: </b> Test your site
          <a href="#">without phastpress</a>
          and
          <a href="#">with phastpress</a>
        </information>
      </setting>

      <setting>
        Disable for non-logged users
        <information>
          <i>On: </i> Only logged in users will be served with optimized version
          <br>
          <i>Off: </i> All users will be served with optimized version
          <br>
          <b>Tip: </b> Use to preview your site before launching the optimizations
        </information>
      </setting>

      <setting>
        Use path info format for service queries
        <information>
          <i>On: </i> use a more 'fancy' format for queries for processed resources
          <br>
          <i>Off: </i> use the GET parameters for queries for processed resources
          <br>
          This setting will be automatically set to <i>On</i> if available after you install PhastPress.
        </information>
      </setting>

      <setting>
        Let the world know about PhastPress
        <information>
          Add a "Optimized by PhastPress" notice to the footer of your site and help spread the word.
        </information>
      </setting>
    </div>

    <div class="settings-section">
      <section-title color="#f26c4f">Images</section-title>

      <setting>
        Optimize images in tags
        <information>
          Compress images with optimal settings.
          <br>
          Resize images to fit 3840x2160 pixels
          or to the appropriate size for
          <code>&lt;img&gt;</code> tags with
          <code>width</code> or <code>height</code>.
          <br>
          Reload changed images while still leveraging browser caching.
        </information>
      </setting>

      <setting>
        Optimize images in CSS
        <information>
          Compress images in stylesheets with optional settings and resizes the to fit 3840x2160 pixels.
          <br>
          Reload changed images while still leveraging browser caching.
        </information>
      </setting>

      <setting>
        Use the Phast Image Optimization API
        <information>
          Optimize your images on out servers free of charge.
          <br>
          This will give you the best possible results without installing any software
          and will reduce the load on your hosting.
          <br>
          <i>
            We will use your email address <a href="mailto:">the-address@the-site.com</a>
            to keep you up to date about changes to the API.
          </i>
        </information>
      </setting>
    </div>

    <div class="settings-section">
      <section-title color="#a287be">HTML, CSS &amp; JS</section-title>

      <setting>
        Optimize CSS
        <information>
          Incline critical styles first and prevent unused styles from blocking the page load.
          <br>
          Minify stylesheets and leverage browser caching.
          <br>
          Inline Google Fonts CSS to speed up font loading.
        </information>
      </setting>

      <setting>
        Move scripts to end of body
        <information>
          Prevent scripts from blocking the page load by loading the after the HTML and CSS.
        </information>
      </setting>

      <setting>
        Load scripts asynchronously
        <information>
          Allow the page to finish loading before all scripts have been executed.
        </information>
      </setting>

      <setting>
        Minify scripts and improve caching
        <information>
          Minify scripts and fix caching for Google Analytics and Hotjar.
          <br>
          Reload changed scripts while still leveraging browser caching.
        </information>
      </setting>

      <setting>
        Defer IFrame loading
        <information>
          Start loading IFrames after the page has finished loading.
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
