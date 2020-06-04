=== PhastPress ===

Tags: pagespeed insights, optimization, page speed, optimisation, speed, performance, load time, loadtime, images, css, webp, async, asynchronous, gtmetrix
Requires at least: 4.4
Requires PHP: 5.6
Stable tag: 1.57
Tested up to: 5.4
License: AGPL-3.0
Contributors: apeschar

PhastPress automatically optimizes your site for the best possible performance.


== Description ==

PhastPress uses advanced techniques to manipulate your pages, scripts, stylesheets and images to significantly improve load times. It's designed to conform to Google PageSpeed Insights and GTmetrix recommendations and can improve your site's score dramatically.

PhastPress' motto is _no configuration_.  Install, activate and go!

PhastPress has the Phast web page optimisation engine by [Albert Peschar](https://kiboit.com/) at its core.

**Image optimization:**

* Phast optimizes images using PNG quantization ([pngquant](https://pngquant.org/)) and JPEG recoding ([libjpeg-turbo](https://libjpeg-turbo.org/)).
* Phast inlines small images (< 512 bytes) in the page.
* Phast converts JPEG images into WebP for supporting browsers.

**Asynchronous scripts and stylesheets:**

* Phast loads all scripts on your page asynchronously and in a single request, while maintaining full compatibility with legacy scripts, due to our custom script loader.
* Phast proxies external scripts to extend their cache lifetime.
* Phast inlines critical CSS automatically by comparing the rules in your stylesheets with the elements on your page.
* Phast loads non-critical CSS asynchronously and in a single request.
* Phast inlines Google Fonts CSS.
* Phast lazily loads IFrames to prioritize the main page load.

Get the full power of Phast for your website by installing PhastPress now.

**Experience any issues?** Please [contact me (Albert) on albert@peschar.net](mailto:albert@peschar.net).


== Installation ==

1. Upload the PhastPress plugin to your site and activate it.
2. Make sure that PhastPress is activated on the Settings page.
3. Test your site. If you experience any issues, please [contact me (Albert) on albert@peschar.net](mailto:albert@peschar.net).


== Frequently Asked Questions ==

= Should I use other optimization plugins with PhastPress? =

No. You do not need any other plugins, such as image optimization (e.g., Smush) or file minification (e.g., Autoptimize) after you install PhastPress, because PhastPress includes all necessary optimizations.

I recommend using the simple combination of PhastPress and [WP Super Cache](https://wordpress.org/plugins/wp-super-cache/) only. This reduces the potential for plugin conflicts, and it is really all you need.

[Fast Velocity Minify](https://wordpress.org/plugins/fast-velocity-minify/) is not compatible with PhastPress, and causes PhastPress not to work. Please use either plugin, but not both.

= Is PhastPress a caching plugin? Do you recommend another caching plugin? =

No, PhastPress does not do caching. I recommend using [WP Super Cache](https://wordpress.org/plugins/wp-super-cache/) in combination with PhastPress to speed up your server response time (TTFB).

= Is PhastPress compatible with WP Fastest Cache? =

Yes, but non-caching optimizations must be **disabled**. Turn off the WP Fastest Cache options in [this screenshot](https://peschar.net/s/yQVWIuOuI4ThfRZfkKJa/).

= Is PhastPress compatible with W3 Total Cache? =

Yes, but non-caching optimizations must be **disabled**.

Specifically, the _Prevent caching of objects after settings change_ option causes problems.

= Is PhastPress compatible with other caching plugins? =

Yes. Some caching plugins include optimizations of JavaScript, CSS and/or images. I recommend turning off all optimizations to avoid conflicts with PhastPress.

= PhastPress is enabled, but nothing happens =

You might be using a plugin that compresses the page before PhastPress processes it.  When that happens, PhastPress cannot apply optimizations.

For example, if you are using the [Far Future Expiry Header](https://wordpress.org/plugins/far-future-expiry-header/) plugin, disable the option "Enable Gzip Compression".

= Why does PhastPress not impact the "Fully Loaded Time" measured by GTmetrix? =

The "Fully Loaded Time" in GTmetrix is the amount of time taken until all network activity ceases.  This measurement can be misleading because it does not take into account the order in which resources load.

Normally, external resources such as scripts and stylesheets must be downloaded, parsed and executed before the page can be rendered.  PhastPress changes this sequence by including all necessary resources (that is, the critical CSS) in the page, and executing scripts asynchronously, so that they do not block the rendering of the page.

This causes the page to be visible earlier in the browser, but does not change GTmetrix's fully loaded time.

In order to see this effect, register and log in to GTmetrix and enable the "Video" option.  Then test your site (with Phast enabled), and use the "Compare" button to again test your site, but while appending "?phast=-phast" to the URL (eg, https://example.com/?phast=-phast).  When the comparison loads, select the "Filmstrips" tab and you'll see the difference.  The Phast-optimized version of your site should start rendering much earlier.

= Can I use a hook to disable PhastPress? =

Should you need to disable PhastPress on certain pages, you can use the following code to do so:

    add_filter('phastpress_disable', '__return_true');

Make sure that this code runs during `template_redirect` or earlier.

= Can I use disable PhastPress on WooCommerce checkout and cart pages? =

Add this code to your theme's functions.php, or to a new file in wp-content/mu-plugins:

    add_filter('phastpress_disable', function ($disable) {
        return $disable || is_cart() || is_checkout();
    });

= How and when does PhastPress clean the cache? =

PhastPress uses filesize and modification time information to detect file changes, so clearing the cache is generally not needed.  When you change a script or CSS file, the change should be visible immediately after reloading.

If you do want to clear the cache, you can delete all the data inside `wp-content/cache/phastpress` or `wp-content/plugins/phastpress/cache`.

= How do I exclude a specific script from optimization? =

By default, PhastPress delays the load of all scripts until after the DOM has finished loading, so that the browser can render the page as quickly as possible.  If you wish to load specific scripts as soon as possible, such as Google Analytics, you may add the `data-phast-no-defer` attribute to the script.  It would be preferable to also mark external scripts as `async`, when possible.

For example:

    <script data-phast-no-defer>
    // my script goes here
    </script>

Or:

    <script async data-phast-no-defer src="http://url.to.my.script/"></script>

This is applied automatically for the Google Analytics script inserted by Monsterinsights since PhastPress 1.29.


== Changelog ==

= 1.57 - 2020-06-05 =

Phast was updated to version 1.51:

* Rewrite image URLs in any attribute, as long as the URL points to a local file and ends with an image extension.

= 1.56 - 2020-06-04 =

Phast was updated to version 1.50:

* Ignore `link` elements with empty `href`, or one that consists only of slashes.
* Replace `</style` inside inlined stylesheets with `</ style` to prevent stylesheet content ending up inside the DOM.
* Add `font-swap: block` for Ionicons.
* Remove UTF-8 byte order mark from inlined stylesheets.

= 1.55 - 2020-05-28 =

* Fix release.

= 1.54 - 2020-05-28 =

* Improve compatibility with [Nimble Page Builder](https://wordpress.org/plugins/nimble-builder/) and [Child Theme Configurator](https://wordpress.org/plugins/child-theme-configurator/).

= 1.53 - 2020-05-27 =

Phast was updated to version 1.49:

* Send uncompressed responses to Cloudflare.  Cloudflare will handle compression.

= 1.52 - 2020-05-25 =

Phast was updated to version 1.48:

* Stop excessive error messages when IndexedDB is unavailable.

= 1.51 - 2020-05-19 =

Phast was updated to version 1.47:

* Process image URLs in `data-src`, `data-srcset`, `data-wood-src` and `data-wood-srcset` attributes on `img` tags.

= 1.50 - 2020-05-18 =

This release should have updated Phast to version 1.47, but didn't, by accident.

= 1.49 - 2020-05-14 =

Phast was updated to version 1.46:

* Whitelist `cdnjs.cloudflare.com` for CSS processing.

= 1.48 - 2020-05-13 =

Phast was updated to version 1.45:

* Use `font-display: block` for icon fonts (currently Font Awesome, GeneratePress and Dashicons).

= 1.47 - 2020-05-04 =

Phast was updated to version 1.44:

* Support `data-pagespeed-no-defer` and `data-cfasync="false"` attributes on scripts for disabling script deferral (in addition to `data-phast-no-defer`).
* Leave `data-{phast,pagespeed}-no-defer` and `data-cfasync` attributes in place to aid debugging.

= 1.46 - 2020-04-30 =

Phast was updated to version 1.43:

* Base64 encode the config JSON passed to the frontend, to stop Gtranslate or other tools from mangling the service URL that is contained in it.

= 1.45 - 2020-04-15 =

Phast was updated to version 1.42:

* Speed up script load, and fix a bug with setTimeout functions running before the next script is loaded.

= 1.44 =

Phast was updated to version 1.41:

* Support compressed external resources (ie, proxied styles and scripts).

= 1.43 =

* Image optimization functionality works again.  You will have to re-enable it in the settings panel.

Phast was updated to version 1.40:

* Add s.pinimg.com, google-analytics.com/gtm/js to script proxy whitelist.

= 1.42 =

Phast was updated to version 1.39:

* Remove blob script only after load.  This fixes issues with scripts sometimes not running in Safari.

= 1.41 =

Phast was updated to version 1.38:

* Fixed a regression causing external scripts to be executed out of order.

= 1.40 =

Phast was updated to version 1.37:

* Execute scripts by inserting a `<script>` tag with a blob URL, instead of using global eval, so that global variables defined in strict-mode scripts are globally visible.

= 1.39 =

Phast was updated to version 1.36:

* Clean any existing output buffer, instead of flushing it, before starting Phast output buffer.

= 1.38 =

Phast was updated to version 1.35:

* Use all service parameters for hash-based cache marker.  This might fix some issues with stale stylesheets being used.

= 1.37 =

* The `phastpress_disable` hook is now triggered during `template_redirect` instead of `plugins_loaded`, which allows you to use many more functions in your hook handlers.

Phast was updated to version 1.34.

= 1.36 =

Phast was updated to version 1.33:

* Stop proxying dynamically inserted scripts after onload hits.
* Combine the hash-based cache marker with the original modification time-based cache marker.
* Remove comment tags (`<!-- ... -->`) from inline scripts.
* Send `Content-Length` header for images.

= 1.35 =

Phast was updated to version 1.31:

* Change CSS cache marker when dependencies (eg, images) change.  This prevents showing old images because CSS referencing an old optimized version is cached.

= 1.34 =

* Store service config in `service-config-*` files for AppArmor compatibility, if there's a rule that prevents writing `*.php` files.
* Create index.html in cache directory to prevent path enumeration.

= 1.33 =

Phast was updated to version 1.29:

* Trick mod_security into accepting script proxy requests by replacing
  `src=http://...` with `src=hxxp://...`.

= 1.32 =

Phast was updated to version 1.28:

* Don't send WebP images via Cloudflare.  Cloudflare [does not support `Vary:
  Accept`](https://serverfault.com/questions/780882/impossible-to-serve-webp-images-using-cloudflare), so sending WebP via Cloudflare can cause browsers that don't support
  WebP to download the wrong image type.  [Use Cloudflare Polish
  instead.](https://support.cloudflare.com/hc/en-us/articles/360000607372-Using-Cloudflare-Polish-to-compress-images)

= 1.31 =

Phast was updated to version 1.26:

* Keep `id` attributes on `style` elements. (This fixes compatibility with [css-element-queries](https://github.com/marcj/css-element-queries).)

= 1.30 =

* Don't delay SlimStats script.

= 1.29 =

* Don't delay Monsterinsights script so that Google Analytics works more reliably.

Phast was updated to version 1.25:

* Keep newlines when minifying HTML.

= 1.28 =

Phast was updated to version 1.24:

* Send Content-Security-Policy and X-Content-Type-Options headers on resources
  to speculatively prevent any XSS attacks via MIME sniffing.

= 1.27 =

* Load configuration via `wp-load.php` instead of `wp-config.php`.

= 1.26 =

* Fix incompatibility with Thrive Architect.

= 1.25 =

* Test with WordPress 5.3.
* Fix incompatibility with Divi Visual Builder.

= 1.24 =

Phast was updated to version 1.23:

* Make CSS filters configurable using switches.

= 1.23 =

* Disable optimizations inside Yellow Pencil editor.

= 1.22 =

* Mitigate restrictive access rules for /wp-content by adding our own .htaccess for phast.php.
* Try to put cache directory in wp-content/cache or wp-content/uploads before using the plugin directory.

Phast was updated to version 1.22:

* Remove empty media queries from optimize CSS.
* Use token to refer to bundled resources, to shorten URL length.
* Clean up server-side statistics.
* Add HTML minification (whitespace removal).
* Add inline JavaScript and JSON minification (whitespace removal).
* Add a build system to generate a single PHP file with minified scripts.

= 1.21 =

Phast was updated to version 1.21:

* Don't attempt to optimize CSS selectors containing parentheses, avoiding a bug removing applicable :not(.class) selectors.

= 1.20 =

* Fix compatibility issues by not prepending our autoloader.

= 1.19 =

Phast was updated to version 1.20:

* Add *.typekit.net, stackpath.bootstrapcdn.com to CSS whitelist.
* Don't apply rot13 on url-encoded characters.
* Use valid value for script `type` to quiet W3C validator.

= 1.18 =

Phast was updated to version 1.18:

* Don't rewrite page-relative fragment image URLs like `fill: url(#destination)`.

= 1.17 =

Phast was updated to version 1.17:

* Restore `script` attributes in sorted order (that is, `src` before `type`) to stop Internet Explorer from running scripts twice when they have `src` and `type` set.

= 1.16 =

* Add `phastpress_disable` hook.

= 1.15 =

* Fix an issue whereby updating to 1.14 would reset the security token, invalidating links used in pages in a full-page cache. (To fix the issue, clear the cache of your full-page caching plugin.)

= 1.14 =

* Use the correct service URL when the site URL changes after activation.

Phast was updated to version 1.16:

* Encode bundler request query to avoid triggering adblockers.
* Use a promise to delay bundler requests until the end of the event loop, rather than setTimeout.

= 1.13 =

Phast was updated to version 1.15:

* Scripts can now be loaded via `document.write`. This restores normal browser behaviour.

= 1.12 =

Phast was updated to version 1.14:

* `document.write` now immediately inserts the HTML into the page. This fixes compatibility with Google AdSense.

= 1.11.0 =

Phast was updated to version 1.13.1:

* Remove query strings from URLs to stylesheets and scripts loaded from the local server. It is redundant, since we add the modification time to the URL ourselves.

= 1.10.3 =

* Add version information to console log.
* Fix notice regarding undefined variable in settings panel.

= 1.10.2 =

Phast was updated to version 1.12.2:

* Increase timeouts for API connection.

= 1.10.1 =

Phast was updated to version 1.12.1:

* Don't use IndexedDB-backed cache on Safari.

= 1.10.0 =

* Use HTTPS for the API connection.

Phast was updated to version 1.12.0:

* Rewrite `data-lazy-src` and `data-lazy-srcset` on `img`, `source` tags for compatibility with lazy loading via [BJ Lazy Load](https://wordpress.org/plugins/bj-lazy-load/), possibly other plugins.

= 1.9.0 =

* Removed script rearrangement setting.

Phast was updated to version 1.11.0:

* Proxy CSS for maxcdn.bootstrapcdn.com, idangero.us, *.github.io.
* Proxy icon fonts and other resources from fonts.googleapis.com.
* Improve log messages from image filter.
* Do not proxy maps.googleapis.com, to fix NotLoadingAPIFromGoogleMapError.
* Removed `src` attribute from scripts that are loaded through the bundler, so that old versions of Firefox do not make extraneous downloads.
* Check that the bundler returns the right amount of responses.
* Per-script debugging message when executing scripts.
* Animated GIFs are no longer processed, so that animation is preserved.

= 1.8.5 =

* Disable PhastPress for Elementor previews (edit mode).

= 1.8.4 =

* Fix installation notice dismissal.

Phast was updated to version 1.9.3:

* `<!--` comments in inline scripts are removed only at the beginning.

= 1.8.3 =

Phast was updated to version 1.9.2:

* Empty scripts are cached correctly.

= 1.8.2 =

Phast was updated to version 1.9.1:

* Async scripts are now not loaded before sync scripts that occur earlier in the document.

= 1.8.1 =

Phast was updated to version 1.9.0:

* Scripts are now retrieved in a single request.
* Non-existent filter classes are ignored, and an error is logged.
* A 'dummy filename' such as `__p__.js` is appended to service requests to trick Cloudflare into caching those responses.
* The maximum document size for filters to be applied was corrected to be 1 MiB, not 1 GiB

= 1.8.0 =

This release was built with a pre-release version of Phast 1.9.0 that caused incorrect triggering of the browser `load` event. Please upgrade to PhastPress 1.8.1.

= 1.7.0 =

* Update Phast to version 1.8.0.

= 1.6.2 =

* Do not regenerate the service request token on every configuration change or plugin update.

= 1.6.1 =

* Fix issue with CSS not respecting disabled path info setting.

= 1.6.0 =

* Update Phast to commit 9e1471a.
* Fix MyParcel (and possibly other plugins) compatibility by not optimizing any pages but WordPress' index.php.

= 1.5.3 =

* Revamped the settings panel.

= 1.5.2 =

* Remove old notice about sending admin email.

[See Phast change log](https://github.com/kiboit/phast/blob/master/CHANGELOG.md)

= 1.5.1b =

* The admin email is no longer sent to the image optimisation API.

= 1.5.1a =

* Update to Phast 1.5.1.

[See Phast change log](https://github.com/kiboit/phast/blob/master/CHANGELOG.md)

= 1.5.1 =

* Disable scripts rearrangement by default.

This version was based on Phast 1.5.0.

= 1.5.0 =

[See Phast change log](https://github.com/kiboit/phast/blob/master/CHANGELOG.md)

= 1.4.0 =

* Add automatically configured option to use query strings rather than path info for service requests.
* Automatically enable PhastPress if everything seems fine.
* Use WordPress' Requests library instead of cURL.

[See Phast change log](https://github.com/kiboit/phast/blob/master/CHANGELOG.md)

= 1.3.2 =

* PhastPress is now automatically enabled on installation.
* The image optimisation API is now automatically enabled on installation.

[See Phast change log](https://github.com/kiboit/phast/blob/master/CHANGELOG.md)

= 1.3.1 =

[See Phast change log](https://github.com/kiboit/phast/blob/master/CHANGELOG.md)

= 1.3.0 =

* PhastPress now works on Windows.

[See Phast change log](https://github.com/kiboit/phast/blob/master/CHANGELOG.md)

= 1.2.0 =

[See Phast change log](https://github.com/kiboit/phast/blob/master/CHANGELOG.md)

= 1.1.0 =

[See Phast change log](https://github.com/kiboit/phast/blob/master/CHANGELOG.md)
