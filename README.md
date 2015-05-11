# ExpressionEngine Resizer 2.0.0

Use Resizer to resize, cache, and retrieve images with a number of options.

## Setup

Move the resizer directory to your ExpressionEngine third_party folder. Resizer requires [PHP GD](http://php.net/manual/en/book.image.php). The following settings are available to configure defaults in your config file.

```php
$config['resizer_memory_limit'] = 32;         // PHP memory limit in MB, false to inherit system defaults
$config['resizer_quality'] = 80;              // Default image compression quality 0-100 with 100 being no compression
$config['resizer_responsive'] = false;        // Skip the width and height parameters for responsive images
$config['resizer_xhtml'] = false;             // Self close image tag (false for HTML, true for XHTML)
$config['resizer_sharpen'] = false;           // Slightly sharpen images by default, useful after resizing
$config['resizer_target'] = '/images/sized/'; // Default cache directory relative to root (must be writable)
$config['resizer_exclude_remote'] = false;    // Exclude remote domains from being downloaded and processed
$config['resizer_local_domain'] = 'test.com'; // Override the local domain used when translating external paths
```

## Parameters

	crop='yes'              // Crop image (defaults to no)
	height=''               // Optional height of rendered image
	width=''                // Optional width of rendered image
	filename=''             // Override image filename (defaults to source filename)
	fallback=''             // Fallback image source if provided source can't be found (no fallback by default)
	alt=''                  // Alt tag used when outputting image tags
	background=''           // 6 character HEX color background used on transparent images (png, gif) or set to false to maintain existing transparency (defaults to transparent)
	force_jpg=''            // Set to yes to convert non-jpg images to jpgs (defaults to no)
	responsive='no'         // Skip the width and height parameters for responsive images (defaults to config value else yes)
	quality=80              // Image compression quality 0-100 with 100 being no compression (defaults to config value else 80)
	scale_up='yes'          // Scale image larger than original if set width and/or height dictate (defaults to yes)
	xhtml='no'              // Self close image tag (defaults to HTML5 style, set to yes for XHTML)
	sharpen='yes'           // Slightly sharpen jpg images, useful after resizing (defaults to yes)
	target='/images/sized/' // Writeable cache directory relative to root (defaults to config value else '/images/sized/')
	host='http://cdn.com'   // Domain to prefix to the filepath
	exclude_remote='no'     // Exclude remote domains from being downloaded and processed
	local_domain='test.com' // Local domain used when translating external paths
	attr:class='img-left'   // Any attributes prepended by "attr:" will be added to the single tag output

## Usage

	{exp:resizer:path src="/assets/img/hero.jpg" width="100" height="100" crop="yes"}
	/images/sized/hero-2d149bc0ba00de4f7e7ee20fd25404a1.jpg

	{exp:resizer:tag src="/assets/img/hero.jpg" host="http://cdn.domain.com" width="100" height="100" responsive="yes" alt="Testing" crop="yes" attr:class="img-left"}
	<img src="http://cdn.domain.com/images/sized/hero-2d149bc0ba00de4f7e7ee20fd25404a1.jpg" alt="Testing" class="img-left">

	{exp:resizer:pair src="/assets/img/hero.jpg" width="100" height="100" crop="yes"}
	<img src="{resizer:path}" width="{resizer:width}" height="{resizer:height}" alt="Testing">
	{/exp:resizer:pair}
	<img src="/images/sized/hero-2d149bc0ba00de4f7e7ee20fd25404a1.jpg" width="100" height="100" alt="Testing">
	
	{exp:resizer:bulk width="100" height="100" crop="yes" responsive="yes" attr:class="embedded"}
	<img src="/assets/img/external.jpg" width="2000" height="2000" alt="Testing">
	{/exp:resizer:bulk}
	<img src="/images/sized/external-2d149bc0ba00de4f7e7ee20fd25404a1.jpg" alt="Testing" class="embedded">

## License

Copyright 2015 Caddis Interactive, LLC

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

	http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.