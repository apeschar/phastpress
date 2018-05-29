=== PhastPress ===

Tags: pagespeed insights, optimization, page speed, optimisation, speed, performance, load time, loadtime, images, css
Requires at least: 4.4
Requires PHP: 5.6
Stable tag: trunk
Tested up to: 4.9.2
License: AGPL-3.0

PhastPress automatically optimizes your site for the best possible performance.

== Description ==

PhastPress uses advanced techniques to manipulate your pages, scripts, stylesheets and images to significantly improve load times. It's designed to conform to Google PageSpeed Insights recommendations and can improve your site's score dramatically.

PhastPress has the open source [Phast web page optimisation project](https://github.com/kiboit/phast) by [Kibo IT](https://kiboit.com/) at its core:

* Phast optimizes images using PNG quantization and JPEG recoding, optionally through a free API. Small images are inlined into your page to save HTTP requests.
* Phast loads all scripts on your page asynchronously, while maintaining full compatibility with legacy scripts, due to our custom script loader. External scripts are proxied to extend their cache lifetime.
* Phast inlines critical CSS on the fly by comparing the rules in your stylesheets with the elements on your page. PhastPress also inlines Google Fonts CSS.
* Phast bundles all CSS into a single file, which is loaded asynchronously.
* Phast lazily loads IFrames to prioritize the main page load.

Get the full power of Phast for your website by installing PhastPress now.

**Experience any issues?** Please [contact us on info@kiboit.com](mailto:info@kiboit.com) or post on [support forum](https://wordpress.org/support/plugin/phastpress).


== Installation ==

1. Upload the PhastPress plugin to your site and activate it.
2. Make sure that PhastPress is activated on the Settings page.
3. Test your site. If you experience any issues, please [contact us on info@kiboit.com](mailto:info@kiboit.com) or post on [support forum](https://wordpress.org/support/plugin/phastpress).

== Changelog ==

= Unreleased =
* Revamp of the administrator panel

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
