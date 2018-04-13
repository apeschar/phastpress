=== PhastPress ===

Tags: pagespeed insights, optimization, page speed, optimisation, speed, performance, load time, loadtime, images, css
Requires at least: 4.4
Requires PHP: 5.6
Stable tag: trunk
Tested up to: 4.9.2
License: AGPL-3.0

PhastPress automatically optimizes your site for the best possible performance.

== Description ==

PhastPress has the open source [Phast web page optimisation project](https://github.com/kiboit/phast) by [Kibo IT](https://kiboit.com/) at its core.

It uses advanced techniques to manipulate your pages, scripts, stylesheets and images in such a way that significant improves load times. It is specifically designed to conform to Google PageSpeed Insights recommendations and thus improves your site's score.

Phast optimizes images using PNG quantization and JPEG recoding, optionally through a free API. Small images are inlined into your page to save HTTP requests.

Phast loads all scripts on your page asynchronously, while maintaining full compatibility with legacy scripts, due to our custom script loader. External scripts are proxied to extend their cache lifetime.

Phast inlines critical CSS on the fly by comparing the rules in your stylesheets with the elements on your page. PhastPress also inlines Google Fonts CSS.

Phast bundles all CSS into a single file, which is loaded asynchronously.

Phast lazily loads IFrames to prioritize the main page load.

**Get the full power of Phast for your website by installing PhastPress now.**

== Installation ==

1. Upload the PhastPress plugin to your site and activate it.
2. Logged-in administrators can now preview the site being optimized by PhastPress.
3. You can play with the settings in Settings » PhastPress.
4. Once you are happy with what you see enable the optimizations for all users through the Settings » PhastPress screen.

== Changelog ==

= 1.2.0 =
[See Phast change log](https://github.com/kiboit/phast/blob/master/CHANGELOG.md)

= 1.1.0 =
[See Phast change log](https://github.com/kiboit/phast/blob/master/CHANGELOG.md)
