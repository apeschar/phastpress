=== PhastPress ===

Tags: pagespeed insights, optimization, page speed, optimisation, speed, performance, load time, loadtime, images, css, webp, async, asynchronous, gtmetrix
Requires at least: 5.7
Requires PHP: 7.3
Stable tag: 2.16
Tested up to: 6.2
License: AGPL-3.0
Contributors: apeschar

PhastPress automatically optimizes your site for the best possible performance.


== Description ==

PhastPress uses advanced techniques to manipulate your pages, scripts, stylesheets and images to significantly improve load times. It's designed to conform to Google PageSpeed Insights and GTmetrix recommendations and can improve your site's score dramatically.

PhastPress' motto is _no configuration_.  Install, activate and go!

PhastPress has the Phast web page optimisation engine by [Albert Peschar](https://kiboit.com/) and [Milko Kosturkov](https://twitter.com/mkosturkov) at its core.

**Image optimization:**

* Phast optimizes images using PNG quantization ([pngquant](https://pngquant.org/)) and JPEG recoding ([libjpeg-turbo](https://libjpeg-turbo.org/)).
* Phast inlines small images (< 512 bytes) in the page.
* Phast converts JPEG images into WebP for supporting browsers.
* Phast enables [native lazy loading](https://web.dev/native-lazy-loading/) to speed up page loading and save bandwidth.

**Asynchronous scripts and stylesheets:**

* Phast loads all scripts on your page asynchronously and in a single request, while maintaining full compatibility with legacy scripts, due to our custom script loader.
* Phast proxies external scripts to extend their cache lifetime.
* Phast inlines critical CSS automatically by comparing the rules in your stylesheets with the elements on your page.
* Phast loads non-critical CSS asynchronously and in a single request.
* Phast inlines Google Fonts CSS.
* Phast lazily loads IFrames to prioritize the main page load.

Get the full power of Phast for your website by installing PhastPress now.

[**For commercial support and bug reports, click here.**](https://kiboit.com/phastpress-support)


== Installation ==

1. Upload the PhastPress plugin to your site and activate it.
2. Make sure that PhastPress is activated on the Settings page.
3. Test your site. If you experience any issues, you may [request commercial support](https://kiboit.com/phastpress-support).


== Frequently Asked Questions ==

= Should I use other optimization plugins with PhastPress? =

No. You do not need any other plugins, such as image optimization (e.g., Smush) or file minification (e.g., Autoptimize) after you install PhastPress, because PhastPress includes all necessary optimizations.

I recommend using the simple combination of PhastPress and [WP Super Cache](https://wordpress.org/plugins/wp-super-cache/) only. This reduces the potential for plugin conflicts, and it is really all you need.

[Fast Velocity Minify](https://wordpress.org/plugins/fast-velocity-minify/) is not compatible with PhastPress, and causes PhastPress not to work. Please use either plugin, but not both.

= What about caching and compatibility with caching plugins? =

Caching means saving the HTML from the first visit to a page for later visits, so it does not have to be generated each time. Caching also helps performance with PhastPress, because the page needs to be optimized only once. It is recommendable to use a caching plugin with PhastPress.

PhastPress is not a caching plugin. I recommend using [WP Super Cache](https://wordpress.org/plugins/wp-super-cache/) in combination with PhastPress to speed up your server response time (TTFB).

In case you are using another caching plugin, please read the notes below:

**WP Fastest Cache**: Compatible with PhastPress, but non-caching optimizations must be **disabled**. Turn off the WP Fastest Cache options in [this screenshot](https://peschar.net/s/yQVWIuOuI4ThfRZfkKJa/).

**W3 Total Cache**: Compatible with PhastPress, but non-caching optimizations must be **disabled**. Specifically, the _Prevent caching of objects after settings change_ option causes problems.

**Cache Enabler** (by KeyCDN): Not compatible with PhastPress. Cached pages will not be optimized.

Generally, PhastPress should be compatible with other caching plugins as well. Some caching plugins include optimizations of JavaScript, CSS and/or images. I recommend turning off all optimizations to avoid conflicts with PhastPress.

= Is PhastPress compatible with Asset CleanUp: Page Speed Booster? =

Yes.  The core functionality of Asset CleanUp: Page Speed Booster complements PhastPress by removing unused JavaScript and CSS from the page.

Do not use Asset CleanUp's features for optimizing CSS and JS.  These features can cause conflicts with PhastPress, and they are not needed, because PhastPress already does this.

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

If you (or a plugin) are using `wp_enqueue_script` to add the script to the page, you can use the `phast_no_defer` data key to stop PhastPress from processing the script:

    wp_script_add_data('my_script_name', 'phast_no_defer', true);

Make sure this is run after registering the script.  If you are trying to apply this to a script loaded by a plugin, you could use the `wp_print_scripts` hook:

    add_action('wp_print_scripts', function () {
        wp_script_add_data('my_script_name', 'phast_no_defer', true);
    });

If you use the HTML source code to find the script name, note that `-js` and `-js-extra` are _not_ part of the name.  For example, for a script like `<script id="jquery-core-js">` in the source code, the script name is `jquery-core`, and that is what you should pass to `wp_script_add_data`.

This is applied automatically for these scripts:

* Google Analytics script inserted by Monsterinsights since PhastPress 1.29.
* Tracking script inserted by Slimstat Analytics since PhastPress 1.30.
* Google Analytics script inserted by Google Site Kit since PhastPress 1.75.
* Google Analytics script inserted by GA Google Analytics since PhastPress 1.76.

= Does PhastPress collect data or use external services? =

Images are optimized using a free API, provided by the creator of PhastPress.

During image optimization, the following data is sent to the API:

* the URL on which PhastPress is used
* the version of the plugin
* the PHP version (to track compatibility requirements)
* the image itself

Images are sent to the API only once. Processed images are stored locally, and not retained by the API.

If image optimization is switched off, the API will not be used.

= I get an error saying "Headers already sent". How do I fix this? =

Your theme or a plugin is trying to send HTTP headers after the page has started rendering and bytes have been sent to the browser.  This is wrong, but it works when PHP output buffering is enabled.

PhastPress always sends output as soon as possible, to reduce the time to first byte.  That means this problem cannot be fixed without slowing down sites without buggy themes/plugins.

To fix the problem on your site, the following code needs to be run in order to enable output buffering:

`<?php
add_action('template_redirect', function () {
    ob_start();
});`

You can add this code to your theme's `functions.php`, or create a file `output-buffer.php` in `wp-content/mu-plugins` with the above code.  You may have to create this directory first.

Alternatively, [download `output-buffer.zip`](https://peschar.net/files/output-buffer.zip) and extract the contents into your web folder.  You should end up with a file named `output-buffer.php` in `wp-content/mu-plugins`.

= Can I optimize images without changing their URLs? =

Yes. Add these two lines to your `.htaccess` file:

`RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^wp-content/.*[^/]\.(jpe?g|gif|png)$ wp-content/plugins/phastpress/phast.php [L,NC,E=PHAST_SERVICE:images]`

Then in PhastPress settings, <strong>disable</strong> image optimization in tags and CSS.

<img src="https://peschar.net/scr/elKjRUypwMy4f3Q5fV2I.png">

Now, reload your site and check if images are optimized.

= Is it possible to use PhastPress with a CSP? =

Yes, as long as you use a CSP with a `script-src` policy containing `nonce-*`. To enable Phast's support for CSP implement the `phastpress_csp_nonce` filter:

`<?php
add_filter('phastpress_csp_nonce', function () {
    return 'my-nonce';
});`

= Why do images not get converted to WebP when using Cloudflare? =

Cloudflare [does not support `Vary: Accept`](https://serverfault.com/questions/780882/impossible-to-serve-webp-images-using-cloudflare), so sending WebP via Cloudflare can cause browsers that don't support WebP to download the wrong image type. You can try using [Cloudflare Polish](https://support.cloudflare.com/hc/en-us/articles/360000607372-Using-Cloudflare-Polish-to-compress-images) instead.

== Changelog ==

= 2.16 - 2023-05-14 =

* Fix deprecation warnings in the control panel.
* Fix installation notice on dashboard.

Update Phast to version 1.109:

* Fix deprecation warnings.

= 2.15 - 2023-05-13 =

Update Phast to version 1.108:

* Fix deprecation warnings.

= 2.14 - 2023-04-17 =

* Fix fatal error on `phastpress_disable` if unconfigured.

= 2.13 - 2023-04-17 =

Update Phast to version 1.107:

* Prevent duplication of self-closing `<meta charset>` tags.

= 2.12 - 2023-03-30 =

* Fix WordPress 6.2 compatibility.

= 2.11 - 2023-03-18 =

* Bump "Tested up to" to WordPress 6.2.

= 2.10 - 2022-11-18 =

Update Phast to version 1.106:

* Set `Content-Type: application/json` header on bundler responses.
* Remove `X-Robots-Tag` header.

= 2.9 - 2022-11-07 =

* Prevent undefined array index warning.

= 2.8 - 2022-11-07 =

* Don't defer [Burst Statistics](https://wordpress.org/plugins/burst-statistics/) scripts.

= 2.7 - 2022-10-31 =

* Bump "Tested up to" to WordPress 6.1.

Update Phast to version 1.105:

* Set `X-Robots-Tag: none` header on bundler responses to prevent search engines from indexing them.

= 2.6 - 2022-10-11 =

* Bump "Tested up to" to WordPress 6.0.2.

= 2.5 - 2022-08-06 =

* Add `nonce` attributes to `script` tags generated by PhastPress itself.

PhastPress now requires WordPress 5.7 or later.

= 2.4 - 2022-07-26 =

* Add `phastpress_csp_nonce` filter.

= 2.3 - 2022-04-04 =

* Clarity PDO_SQLITE requirement message.

= 2.2 - 2022-04-03 =

Update Phast to version 1.104:

* Improve CSP support.
* Use SQLite3 database for caching instead of a file tree.

= 2.1 - 2021-10-07 =

Update Phast to version 1.103:

* Update CA bundle.

= 2.0 - 2021-09-27 =

* Require PHP 7.3.

Update Phast to version 1.102:

* Don't rewrite the URLs of dynamically inserted `module` scripts. This fixes compatibility with [Presto Player](https://wordpress.org/plugins/presto-player/).

= 1.125 - 2021-09-07 =

Update Phast to version 1.101:

* Ensure the security token never gets reset when the cache grows too large. This prevents resource URLs from changing suddenly.

= 1.124 - 2021-05-31 =

* Prevent dark flash when OS dark mode is active but theme dark mode is disabled.

= 1.123 - 2021-05-26 =

* Add missing file; simplify logic; improve log message.

= 1.122 - 2021-05-26 =

* Prevent light flash when using the dark mode in the Twenty Twenty One theme, even when the page is large enough to trigger multiple renders.

This release misses a file. Please use 1.123 instead.

= 1.121 - 2021-05-20 =

* Prevent light flash when using the dark mode in the Twenty Twenty One theme.

= 1.120 - 2021-05-13 =

Update Phast to version 1.100:

* Send 403 and 404 status codes for unauthorized and not found resource URLs respectively, if they cannot be safely redirected to the original resource.

= 1.119 - 2021-05-09 =

* Fix notice on undefined cspNonce variable.

= 1.118 - 2021-05-04 =

* Avoid an issue with the Stop Spammers plugin preventing the install notice from being closed.

= 1.117 - 2021-04-28 =

Update Phast to version 1.99:

* Prefix `async`, `defer` attributes with `data-phast-` to please W3C validator.

= 1.116 - 2021-04-21 =

* Update settings labels about IFrame lazy loading.

= 1.115 - 2021-04-21 =

Update Phast to version 1.98:

* Use [native IFrame lazy loading](https://web.dev/iframe-lazy-loading/).

= 1.114 - 2021-03-17 =

* Deterministically generate security key based on WordPress secret keys. This avoids URLs changing when the cache is emptied.

= 1.112 - 2021-03-17 =

* Bump WordPress compatibility to 5.7.

= 1.111 - 2021-03-17 =

Update Phast to version 1.97:

* Fix [open redirect](https://cwe.mitre.org/data/definitions/601.html) on `phast.php`. This would allow a malicious person to redirect someone to a third-party site via `phast.php` by sending them a link. This can enable phishing attacks if the user is mislead by the hostname of the initial URL. It does not compromise the security of your site itself.

= 1.110 - 2021-03-11 =

Update Phast to version 1.96:

* Don't emulate `document.currentScript` for scripts that are executed normally. This prevents some scripts from seeing the wrong `currentScript` accidentally.

= 1.109 - 2021-03-09 =

Update Phast to version 1.95:

* Do not rewrite `<img>` element `src` when it has a `rev-slidebg` class and points to `transparent.png`. This is because [Revolution Slider](https://www.sliderrevolution.com/)'s JavaScript depends on the image filename for its logic.

= 1.108 - 2021-03-09 =

* Optimize AJAX responses generated by the quick view functionality in [Flatsome](https://themeforest.net/item/flatsome-multipurpose-responsive-woocommerce-theme/5484319) theme.

= 1.107 - 2021-03-09 =

* Optimize AJAX responses generated by [YITH WooCommerce Quick View Pro](https://plugins.yithemes.com/yith-woocommerce-quick-view/).

Update Phast to version 1.94 to support this improvement.

= 1.106 - 2021-03-08 =

* Optimize AJAX responses generated by [YITH WooCommerce Quick View](https://wordpress.org/plugins/yith-woocommerce-quick-view/).

Update Phast to version 1.93:

* Don't optimize snippets if they look like JSON objects, ie, start with `{"`.

= 1.105 - 2021-03-08 =

Update Phast to version 1.92:

* Support whitespace in `url()` in CSS.  Eg, `url( 'file.jpg' )` is not
  processed correctly.

= 1.104 - 2021-03-04 =

Update Phast to version 1.91:

* Make message about inability to override `document.readyState` a warning rather than an error, to avoid spurious complaints from PageSpeed Insights.

= 1.103 - 2021-03-04 =

Update Phast to version 1.90:

* Correctly support additional arguments when using setTimeout. This fixes a regression in version 1.83.

= 1.102 - 2021-03-04 =

* Add `phastpress_optimize_snippet` function to allow arbitrary HTML to be optimized.

Update Phast to version 1.89:

* Ensure error pages are always interpreted as UTF-8.

= 1.101 - 2021-02-26 =

Update Phast to version 1.88:

* Simplify `PATH_INFO` calculation if the environment variable is missing. This is now determined by splitting the path component of `REQUEST_URI` on `.php/`.
* Improve error messages, hopefully aiding troubleshooting when `phast.php` isn't doing it's job.

= 1.100 - 2021-02-18 =

* Handle multisite installations in subdirectories.

= 1.99 - 2021-02-05 =

Update Phast to version 1.87:

* Fix handling of closing parenthesis and string literal separated by newline in JSMin.

= 1.98 - 2021-02-02 =

* Disable PhastPress while editing with [Oxygen Builder](https://oxygenbuilder.com/).

= 1.97 - 2021-02-01 =

Update Phast to version 1.86:

* Use `text/plain` MIME type for the bundled CSS and JS responses. This helps apply automatic response compression in some server configurations (specifically o2switch).

= 1.96 - 2021-01-28 =

Update Phast to version 1.85:

* Raise maximum page size to 2 MiB.

= 1.95 - 2021-01-28 =

* Add compatibility for LiteSpeed Cache. PhastPress optimizations would not work before this.

= 1.94 - 2021-01-18 =

Update Phast to version 1.84:

* Detect WOFF2 support using a feature test, instead of relying on the user agent. This fixes Google Fonts on iOS 9 and earlier.

= 1.93 - 2021-01-04 =

Update Phast to version 1.83:

* Make sure setTimeout chains in DOMContentLoaded are completely executed before the load event is triggered. This fixes some uses of jQuery's ready event.

= 1.92 - 2020-12-16 =

Update Phast to version 1.81:

* Use Base64-based path info for server-generated URLs.

= 1.91 - 2020-12-16 =

Update Phast to version 1.80:

* Encode characters that cannot occur in URLs. This fixes canonical URLs for optimized images if those URLs contained special characters.

= 1.90 - 2020-11-19 =

* Delay [NextGEN Gallery](https://wordpress.org/plugins/nextgen-gallery/) resource manager output buffer hook until after PhastPress deployment if WP Super Cache late init is enabled. This fixes an issue where footer scripts would disappear when NextGEN Gallery and WP Super Cache late init were used at the same time.

= 1.89 - 2020-11-18 =

* Delay deployment until `init` hook if [WP Super Cache](https://wordpress.org/plugins/wp-super-cache/) late init is enabled. This fixes PhastPress optimizations being done on every load in WP Super Cache's Simple mode, and not being done at all in Expert mode.

= 1.88 - 2020-11-18 =

Update Phast to version 1.79:

* Support `document.currentScript` in optimized scripts. (This fixed compatibility with [PDF Embedder](https://wordpress.org/plugins/pdf-embedder/).)
* Prevent (suppressed) notice from `ob_end_clean`.

= 1.87 - 2020-10-28 =

Update Phast to version 1.78:

* Handle `<!doctype html ...>` declarations correctly, and don't insert `<meta charset>` before them. (This broke pages using old XHTML doctypes.)

= 1.86 - 2020-10-23 =

* Disable PhastPress when editing with WPBakery.

= 1.85 - 2020-10-23 =

Update Phast to version 1.77:

* Insert `<meta charset=utf-8>` tag right after `<head>` and remove existing `<meta charset>` tags.  This fixes an issue where the `<meta charset>` tag appears more than 512 bytes into the document, causing encoding issues.

= 1.84 - 2020-10-23 =

Update Phast to version 1.76:

* Stop proxying external scripts like Google Analytics. This feature had no performance benefit, and its only purpose was to improve scores in old versions of PageSpeed Insights.

= 1.83 - 2020-10-22 =

Update Phast to version 1.75:

* Insert path separators (`/`) into bundler URLs in order to avoid Apache's 255 character filename limit.

= 1.82 - 2020-10-20 =

Update Phast to version 1.74:

* Ignore calls to `document.write` from `async` or `defer` scripts, in line with normal browser behaviour.

= 1.81 - 2020-10-20 =

* Apply `phast_no_defer` script attribute to scripts generated by `wp_localize_script`.

= 1.80 - 2020-10-05 =

* Prevent direct access to `bootstrap.php`, `low-php-version.php` and files in `classes` directory. This is not a security risk, but could generate errors.

= 1.79 - 2020-09-21 =

* Don't defer [AdThrive Ads](https://wordpress.org/plugins/adthrive-ads/) scripts.

= 1.78 - 2020-09-09 =

* Don't resize images based on `width`/`height` attributes on `img` tags.

= 1.77 - 2020-09-08 =

* Exclude cache from All-in-One WP Migration backups.

Update Phast to version 1.71.

* Only process JPEG, GIF and PNG images. (Fix regression in 1.65.)

= 1.76 - 2020-09-04 =

* Don't defer GA Google Analytics scripts.

= 1.75 - 2020-09-04 =

* Don't defer Google Site Kit Analytics script.
* Add support for phast_no_defer script attribute.

= 1.74 - 2020-08-30 =

Update Phast to version 1.70.

* Add Last-Modified header to service response.

= 1.73 - 2020-08-27 =

* Don't use error suppression when checking query parameters, instead use isset. This prevents notices from appearing in some error logging plugins, even though they are suppressed.

= 1.72 - 2020-08-26 =

Update Phast to version 1.69.

* Fix CSS proxy URL generation not to include `__p__` filename twice.

= 1.71 - 2020-08-25 =

Update Phast to version 1.68.

* Support URLs generated via Retina.js (when path info is enabled).

= 1.70 - 2020-08-21 =

Update Phast to version 1.67.

* Fix IE 11 stylesheet fallbacks.

= 1.69 - 2020-08-21 =

Update Phast to version 1.66.

* Convert `<link onload="media='all'">` to `<link media="all">` before inlining.
* Elide `media` attribute on generated `style` tags if it is `all`.

= 1.68 - 2020-08-20 =

Update Phast to version 1.65.

* Use path info URLs for bundler and dynamically inserted scripts.
* Don't whitelist local URLs but check that the referenced files exist.
* Support [BunnyCDN](https://wordpress.org/plugins/bunnycdn/) by optimizing resources on the CDN domain and loading processed resources via the CDN domain.

= 1.67 - 2020-08-18 =

Update Phast to version 1.64.

* Preserve control characters in strings in minified JavaScript.
* Use JSON_INVALID_UTF8_IGNORE on PHP 7.2+ instead of regexp-based invalid UTF-8 character removal.

= 1.66 - 2020-08-13 =

Update Phast to version 1.63.

* Images in AMP documents are now optimized. No other optimizations are performed in AMP documents.

= 1.65 - 2020-08-11 =

Update Phast to version 1.62.

* Add an option to lazy load images using native lazy loading (`loading=lazy` attribute). This is enabled by default.

= 1.64 - 2020-07-21 =

Update Phast to version 1.61.

* Added an option to disable gzip compression of processed resources downloaded via `phast.php`. This might help to fix issues on hosts that compress already compressed responses.

= 1.63 - 2020-07-21 =

Update Phast to version 1.60:

* Ensure that requestAnimationFrame callbacks run before onload event.
* Don't rewrite anchor URLs (like `#whatever`) in CSS.

= 1.62 - 2020-07-08 =

Update Phast to version 1.58:

* Rewrite each URL in a CSS rule, not just the first one.

= 1.61 - 2020-06-17 =

* Disable PhastPress during Asset Cleanup: Page Speed Booster analysis.

= 1.60 - 2020-06-10 =

Update Phast to version 1.55:

* Only rewrite image URLs in arbitrary attributes inside the `<body>` tag.
* Don't optimize image URLs in attributes of `<meta>` tags.
* When optimizing images, send the local PHP version to the API, to investigate whether PHP 5.6 support can be phased out.

= 1.59 - 2020-06-09 =

Update Phast to version 1.54:

* Fix writing existing read-only cache files (on Windows).

= 1.58 - 2020-06-09 =

Update Phast to version 1.53:

* Fix caching on Windows by not setting read-only permissions on cache files.
* Add a checksum to cache files to prevent accidental modifications causing trouble.

= 1.57 - 2020-06-05 =

Update Phast to version 1.51:

* Rewrite image URLs in any attribute, as long as the URL points to a local file and ends with an image extension.

= 1.56 - 2020-06-04 =

Update Phast to version 1.50:

* Ignore `link` elements with empty `href`, or one that consists only of slashes.
* Replace `</style` inside inlined stylesheets with `</ style` to prevent stylesheet content ending up inside the DOM.
* Add `font-swap: block` for Ionicons.
* Remove UTF-8 byte order mark from inlined stylesheets.

= 1.55 - 2020-05-28 =

* Fix release.

= 1.54 - 2020-05-28 =

* Improve compatibility with [Nimble Page Builder](https://wordpress.org/plugins/nimble-builder/) and [Child Theme Configurator](https://wordpress.org/plugins/child-theme-configurator/).

= 1.53 - 2020-05-27 =

Update Phast to version 1.49:

* Send uncompressed responses to Cloudflare.  Cloudflare will handle compression.

= 1.52 - 2020-05-25 =

Update Phast to version 1.48:

* Stop excessive error messages when IndexedDB is unavailable.

= 1.51 - 2020-05-19 =

Update Phast to version 1.47:

* Process image URLs in `data-src`, `data-srcset`, `data-wood-src` and `data-wood-srcset` attributes on `img` tags.

= 1.50 - 2020-05-18 =

This release should have updated Phast to version 1.47, but didn't, by accident.

= 1.49 - 2020-05-14 =

Update Phast to version 1.46:

* Whitelist `cdnjs.cloudflare.com` for CSS processing.

= 1.48 - 2020-05-13 =

Update Phast to version 1.45:

* Use `font-display: block` for icon fonts (currently Font Awesome, GeneratePress and Dashicons).

= 1.47 - 2020-05-04 =

Update Phast to version 1.44:

* Support `data-pagespeed-no-defer` and `data-cfasync="false"` attributes on scripts for disabling script deferral (in addition to `data-phast-no-defer`).
* Leave `data-{phast,pagespeed}-no-defer` and `data-cfasync` attributes in place to aid debugging.

= 1.46 - 2020-04-30 =

Update Phast to version 1.43:

* Base64 encode the config JSON passed to the frontend, to stop Gtranslate or other tools from mangling the service URL that is contained in it.

= 1.45 - 2020-04-15 =

Update Phast to version 1.42:

* Speed up script load, and fix a bug with setTimeout functions running before the next script is loaded.

= 1.44 =

Update Phast to version 1.41:

* Support compressed external resources (ie, proxied styles and scripts).

= 1.43 =

* Image optimization functionality works again.  You will have to re-enable it in the settings panel.

Update Phast to version 1.40:

* Add s.pinimg.com, google-analytics.com/gtm/js to script proxy whitelist.

= 1.42 =

Update Phast to version 1.39:

* Remove blob script only after load.  This fixes issues with scripts sometimes not running in Safari.

= 1.41 =

Update Phast to version 1.38:

* Fixed a regression causing external scripts to be executed out of order.

= 1.40 =

Update Phast to version 1.37:

* Execute scripts by inserting a `<script>` tag with a blob URL, instead of using global eval, so that global variables defined in strict-mode scripts are globally visible.

= 1.39 =

Update Phast to version 1.36:

* Clean any existing output buffer, instead of flushing it, before starting Phast output buffer.

= 1.38 =

Update Phast to version 1.35:

* Use all service parameters for hash-based cache marker.  This might fix some issues with stale stylesheets being used.

= 1.37 =

* The `phastpress_disable` hook is now triggered during `template_redirect` instead of `plugins_loaded`, which allows you to use many more functions in your hook handlers.

Update Phast to version 1.34.

= 1.36 =

Update Phast to version 1.33:

* Stop proxying dynamically inserted scripts after onload hits.
* Combine the hash-based cache marker with the original modification time-based cache marker.
* Remove comment tags (`<!-- ... -->`) from inline scripts.
* Send `Content-Length` header for images.

= 1.35 =

Update Phast to version 1.31:

* Change CSS cache marker when dependencies (eg, images) change.  This prevents showing old images because CSS referencing an old optimized version is cached.

= 1.34 =

* Store service config in `service-config-*` files for AppArmor compatibility, if there's a rule that prevents writing `*.php` files.
* Create index.html in cache directory to prevent path enumeration.

= 1.33 =

Update Phast to version 1.29:

* Trick mod_security into accepting script proxy requests by replacing
  `src=http://...` with `src=hxxp://...`.

= 1.32 =

Update Phast to version 1.28:

* Don't send WebP images via Cloudflare.  Cloudflare [does not support `Vary:
  Accept`](https://serverfault.com/questions/780882/impossible-to-serve-webp-images-using-cloudflare), so sending WebP via Cloudflare can cause browsers that don't support
  WebP to download the wrong image type.  [Use Cloudflare Polish
  instead.](https://support.cloudflare.com/hc/en-us/articles/360000607372-Using-Cloudflare-Polish-to-compress-images)

= 1.31 =

Update Phast to version 1.26:

* Keep `id` attributes on `style` elements. (This fixes compatibility with [css-element-queries](https://github.com/marcj/css-element-queries).)

= 1.30 =

* Don't delay SlimStats script.

= 1.29 =

* Don't delay Monsterinsights script so that Google Analytics works more reliably.

Update Phast to version 1.25:

* Keep newlines when minifying HTML.

= 1.28 =

Update Phast to version 1.24:

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

Update Phast to version 1.23:

* Make CSS filters configurable using switches.

= 1.23 =

* Disable optimizations inside Yellow Pencil editor.

= 1.22 =

* Mitigate restrictive access rules for /wp-content by adding our own .htaccess for phast.php.
* Try to put cache directory in wp-content/cache or wp-content/uploads before using the plugin directory.

Update Phast to version 1.22:

* Remove empty media queries from optimize CSS.
* Use token to refer to bundled resources, to shorten URL length.
* Clean up server-side statistics.
* Add HTML minification (whitespace removal).
* Add inline JavaScript and JSON minification (whitespace removal).
* Add a build system to generate a single PHP file with minified scripts.

= 1.21 =

Update Phast to version 1.21:

* Don't attempt to optimize CSS selectors containing parentheses, avoiding a bug removing applicable :not(.class) selectors.

= 1.20 =

* Fix compatibility issues by not prepending our autoloader.

= 1.19 =

Update Phast to version 1.20:

* Add *.typekit.net, stackpath.bootstrapcdn.com to CSS whitelist.
* Don't apply rot13 on url-encoded characters.
* Use valid value for script `type` to quiet W3C validator.

= 1.18 =

Update Phast to version 1.18:

* Don't rewrite page-relative fragment image URLs like `fill: url(#destination)`.

= 1.17 =

Update Phast to version 1.17:

* Restore `script` attributes in sorted order (that is, `src` before `type`) to stop Internet Explorer from running scripts twice when they have `src` and `type` set.

= 1.16 =

* Add `phastpress_disable` hook.

= 1.15 =

* Fix an issue whereby updating to 1.14 would reset the security token, invalidating links used in pages in a full-page cache. (To fix the issue, clear the cache of your full-page caching plugin.)

= 1.14 =

* Use the correct service URL when the site URL changes after activation.

Update Phast to version 1.16:

* Encode bundler request query to avoid triggering adblockers.
* Use a promise to delay bundler requests until the end of the event loop, rather than setTimeout.

= 1.13 =

Update Phast to version 1.15:

* Scripts can now be loaded via `document.write`. This restores normal browser behaviour.

= 1.12 =

Update Phast to version 1.14:

* `document.write` now immediately inserts the HTML into the page. This fixes compatibility with Google AdSense.

= 1.11.0 =

Update Phast to version 1.13.1:

* Remove query strings from URLs to stylesheets and scripts loaded from the local server. It is redundant, since we add the modification time to the URL ourselves.

= 1.10.3 =

* Add version information to console log.
* Fix notice regarding undefined variable in settings panel.

= 1.10.2 =

Update Phast to version 1.12.2:

* Increase timeouts for API connection.

= 1.10.1 =

Update Phast to version 1.12.1:

* Don't use IndexedDB-backed cache on Safari.

= 1.10.0 =

* Use HTTPS for the API connection.

Update Phast to version 1.12.0:

* Rewrite `data-lazy-src` and `data-lazy-srcset` on `img`, `source` tags for compatibility with lazy loading via [BJ Lazy Load](https://wordpress.org/plugins/bj-lazy-load/), possibly other plugins.

= 1.9.0 =

* Removed script rearrangement setting.

Update Phast to version 1.11.0:

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

Update Phast to version 1.9.3:

* `<!--` comments in inline scripts are removed only at the beginning.

= 1.8.3 =

Update Phast to version 1.9.2:

* Empty scripts are cached correctly.

= 1.8.2 =

Update Phast to version 1.9.1:

* Async scripts are now not loaded before sync scripts that occur earlier in the document.

= 1.8.1 =

Update Phast to version 1.9.0:

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
