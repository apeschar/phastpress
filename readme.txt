=== PhastPress ===

Tags: pagespeed insights, optimization, page speed, optimisation, speed, performance, load time, loadtime, images, css
Requires at least: 4.4
Requires PHP: 5.6
Stable tag: 1.16
Tested up to: 5.0
License: AGPL-3.0
Contributors: apeschar

PhastPress automatically optimizes your site for the best possible performance.


== Description ==

PhastPress uses advanced techniques to manipulate your pages, scripts, stylesheets and images to significantly improve load times. It's designed to conform to Google PageSpeed Insights recommendations and can improve your site's score dramatically.

PhastPress has the Phast web page optimisation engine by [Kibo IT](https://kiboit.com/) at its core:

* Phast optimizes images using PNG quantization and JPEG recoding, optionally through a free API. Small images are inlined into your page to save HTTP requests.
* Phast loads all scripts on your page asynchronously, while maintaining full compatibility with legacy scripts, due to our custom script loader. External scripts are proxied to extend their cache lifetime.
* Phast inlines critical CSS on the fly by comparing the rules in your stylesheets with the elements on your page. PhastPress also inlines Google Fonts CSS.
* Phast bundles all CSS into a single file, which is loaded asynchronously.
* Phast lazily loads IFrames to prioritize the main page load.

Get the full power of Phast for your website by installing PhastPress now.

**Experience any issues?** Please [contact us on info@kiboit.com](mailto:info@kiboit.com) or post on the [support forum](https://wordpress.org/support/plugin/phastpress).


== Installation ==

1. Upload the PhastPress plugin to your site and activate it.
2. Make sure that PhastPress is activated on the Settings page.
3. Test your site. If you experience any issues, please [contact us on info@kiboit.com](mailto:info@kiboit.com) or post on the [support forum](https://wordpress.org/support/plugin/phastpress).


== Frequently Asked Questions ==

= Should I use other optimization plugins with PhastPress? =

No. You do not need any other plugins, such as image optimization (e.g., Smush) or file minification (e.g., Autoptimize) after you install PhastPress, because PhastPress includes all necessary optimizations.

We recommend using the simple combination of PhastPress and [WP Super Cache](https://wordpress.org/plugins/wp-super-cache/) only. This reduces the potential for plugin conflicts, and it is really all you need.

= Is PhastPress a caching plugin? Do you recommend another caching plugin? =

No, PhastPress does not do caching. We recommend using [WP Super Cache](https://wordpress.org/plugins/wp-super-cache/) in combination with PhastPress to speed up your server response time (TTFB).

= Is PhastPress compatible with WP Fastest Cache? =

Yes, but non-caching optimizations must be **disabled**. Turn off the WP Fastest Cache options in [this screenshot](https://peschar.net/s/yQVWIuOuI4ThfRZfkKJa/).

= Is PhastPress compatible with other caching plugins? =

Yes. Some caching plugins include optimizations of JavaScript, CSS and/or images. We recommend turning off all optimizations to avoid conflicts with PhastPress.

= Can I use a hook to disable PhastPress? =

PhastPress is started during the `plugins_loaded` hook. Should you need to disable PhastPress on certain pages, you can use the following code to do so:

    add_filter('phastpress_disable', '__return_true');

Make sure that this code runs during `plugins_loaded` with a lower priority than the default (`10`), or earlier.


== Changelog ==

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
