<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

include_once(PATH_THIRD . 'resizer/addon.setup.php');

/**
 * Resizer plugin
 *
 * @package resizer
 * @author Caddis <hello@caddis.co>
 * @link https://github.com/caddis/resizer
 * @copyright Copyright (c) 2015, Caddis Interactive, LLC
 */

$plugin_info = array(
	'pi_author' => RESIZER_AUTHOR,
	'pi_author_url' => RESIZER_AUTHOR_URL,
	'pi_name' => RESIZER_NAME,
	'pi_version' => RESIZER_VER,
	'pi_description' => RESIZER_DESC,
	'pi_usage' => Resizer::usage()
);

class Resizer
{
	private $defaults = array(
		'alt' => '',
		'background' => false,
		'crop' => false,
		'exclude_remote' => false,
		'fallback' => false,
		'filename' => false,
		'force_jpg' => false,
		'height' => false,
		'host' => false,
		'local_domain' => false,
		'quality' => false,
		'responsive' => false,
		'scale_up' => false,
		'sharpen' => false,
		'target' => '/images/sized/',
		'width' => false,
		'xhtml' => false
	);

	private $settings = array();

	public function __construct()
	{
		foreach ($this->defaults as $key => $val) {
			$param = ee()->TMPL->fetch_param($key);

			$this->settings[$key] = ($param !== false) ?
				(($param == 'yes') ? true : $param) :
				$this->defaults[$key];
		}

		$this->settings['root'] = $_SERVER['DOCUMENT_ROOT'];
		$this->settings['target'] = $this->settings['root'] . $this->settings['target'];

		// PHP memory limit
		$configMemoryLimit = ee()->config->item('resizer_memory_limit');

		if ($configMemoryLimit !== false) {
			ini_set('memory_limit', $configMemoryLimit . 'M');
		}

		// Image quality
		if ($this->settings['quality'] === false) {
			$configQuality = ee()->config->item('resizer_quality');

			$this->settings['quality'] = $configQuality !== false ?
				$configQuality :
				80;
		}

		// Host prefix
		if ($this->settings['host'] === false) {
			$configHost = ee()->config->item('resizer_host');

			$this->settings['host'] = $configHost !== false ? $configHost : '';
		}

		// Remote images
		if ($this->settings['exclude_remote'] === false) {
			$this->settings['exclude_remote'] = ee()->config->item('resizer_exclude_remote');
		}

		// Local domain
		if ($this->settings['local_domain'] === false) {
			$this->settings['local_domain'] = ee()->config->item('resizer_local_domain');
		}

		// Responsive
		if ($this->settings['responsive'] === false) {
			$this->settings['responsive'] = ee()->config->item('resizer_responsive');
		}

		// Self closing tags
		if ($this->settings['xhtml'] === false) {
			$this->settings['xhtml'] = ee()->config->item('resizer_xhtml');
		}

		// Sharpen
		if ($this->settings['sharpen'] === false) {
			$this->settings['sharpen'] = ee()->config->item('resizer_sharpen');
		}

		// Target
		if (ee()->TMPL->fetch_param('target') === false) {
			$configTarget = ee()->config->item('resizer_target');

			$this->settings['target'] = $this->settings['root'] . (
				$configTarget !== false ?
					$configTarget :
					$this->defaults['target']
			);
		}
	}

	/**
	 * Get resized image path
	 *
	 * @return string
	 */
	public function path()
	{
		// Image source
		$src = ee()->TMPL->fetch_param('src');

		if ($src === false) {
			return '';
		}

		// Generate and cache image
		$this->image = $this->create($src, $this->settings);

		if ($this->image) {
			return $this->settings['host'] . $this->image['path'];
		}

		return '';
	}

	/**
	 * Build complete image tag
	 *
	 * @return string
	 */
	public function tag()
	{
		// Image source
		$src = ee()->TMPL->fetch_param('src');

		if ($src === false) {
			return '';
		}

		// Generate and cache image
		$this->image = $this->create($src, $this->settings);

		if ($this->image) {
			$params = $this->set_attributes();

			return $this->build_tag($this->image, $params);
		}

		return '';
	}

	/**
	 * Parse interior content of tag pair for granular output
	 *
	 * @return string
	 */
	public function pair()
	{
		// Image source
		$src = ee()->TMPL->fetch_param('src');

		if ($src === false) {
			return '';
		}

		// Generate and cache image
		$this->image = $this->create($src, $this->settings);

		$tagdata = ee()->TMPL->tagdata;

		if ($this->image) {
			$variables = array(
				'resizer:path' => $this->settings['host'] . $this->image['path'],
				'resizer:width' => $this->image['width'],
				'resizer:height' => $this->image['height']
			);

			return ee()->TMPL->parse_variables_row($tagdata, $variables);
		}

		return $tagdata;
	}

	/**
	 * Process bulk content for image replacements
	 *
	 * @return string
	 */
	public function bulk()
	{
		$scope = $this;
		$tagdata = ee()->TMPL->tagdata;

		return preg_replace_callback('/(<img.*?>)/', function($match) use ($scope) {
			$attributes = array();

			preg_match_all('/(\w+)=[\'"]([^<>"\']*)[\'"]/', $match[0], $matches, PREG_SET_ORDER);

			if ($matches) {
				foreach ($matches as $match) {
					$attributes[$match[1]] = $match[2];
				}

				if (isset($attributes['src']) && $attributes['src'] !== '') {
					$image = $this->create($attributes['src'], $this->settings);
					$params = array();

					foreach ($attributes as $key => $value) {
						if (! in_array($key, array('src', 'width', 'height', 'style'))) {
							$params[$key] = $value;
						}
					}

					$params = $this->set_attributes($params);

					return $scope->build_tag($image, $params);
				}
			}

			return $match[0];
		}, $tagdata);
	}

	/**
	 * Process image according to manipulation settings
	 *
	 * @access private
	 * @param string $src
	 * @param array $params
	 * @return array
	 */
	private function create($src, $params = array())
	{
		$allowed = array(
			'.jpg',
			'.jpeg',
			'.gif',
			'.png'
		);

		$target = $params['target'];

		$exists = true;

		// Replace absolute local path
		$localDomain = $params['local_domain'] ?: $_SERVER['HTTP_HOST'];
		$src = preg_replace('/^(https?:)?\/\/' . $localDomain . '/i', '', $src);

		// Check for absolute path
		$absolute = preg_match('/^(https?:)?\/\//i', $src);

		$path = $params['root'] . $src;

		if (! $absolute && ! (is_file($path) || file_exists($path))) {
			$exists = false;
		}

		if ($absolute) {
			$content = file_get_contents($src);

			if ($content) {
				$path = $params['root'] . '/' . basename($src);

				file_put_contents($path, $content);
			} else {
				$exists = false;
			}
		}

		if (! $exists) {
			if ($params['fallback']) {
				$path = $params['root'] . $params['fallback'];

				if (! file_exists($path)) {
					return false;
				}
			} else {
				return false;
			}
		}

		$pathParts = pathinfo($path);
		$filename = $pathParts['filename'];

		$data = getimagesize($path);
		$origWidth = $data[0];
		$origHeight = $data[1];

		// Calculate target width and height
		$origRatio = ($origWidth / $origHeight);

		$width = $params['width'];
		$height = $params['height'];

		if (! $width && ! $height) {
			$width = $targetWidth = $origWidth;
			$height = $targetHeight = $origHeight;
		} else {
			if ($width && $height) {
				$targetWidth = $width < $origWidth ?
					$width :
					(($params['scale_up'] === true) ? $width : $origWidth);
				$targetHeight = $height < $origHeight ?
					$height :
					(($params['scale_up'] === true) ? $height : $origHeight);
			} elseif ($width) {
				$targetWidth = $width < $origWidth ?
					$width :
					(($params['scale_up'] === true) ? $width : $origWidth);
				$targetHeight = floor($targetWidth / $origRatio);
			} elseif ($height) {
				$targetHeight = $height < $origHeight ?
					$height :
					(($params['scale_up'] === true) ? $height : $origHeight);
				$targetWidth = floor($targetHeight * $origRatio);
			}
		}

		// Return original if remote and remotes are excluded
		if ($absolute && $params['exclude_remote']) {
			return array(
				'path' => $src,
				'width' => $targetWidth,
				'height' => $targetHeight
			);
		}

		if (! isset($data) || ! is_array($data)) {
			return false;
		}

		$type = $data[2];
		$ext = image_type_to_extension($type);

		if (! in_array($ext, $allowed)) {
			return false;
		}

		if ($ext === '.jpeg' || $params['force_jpg'] === true) {
			$ext = '.jpg';
		}

		// General filename
		if ($params['filename']) {
			$newPath = $target . $params['filename'] . $ext;
		} else {
			unset($params['alt']);
			unset($params['fallback']);
			unset($params['self_close']);

			ksort($params);

			$newPath = $target . $filename . '-' . md5(serialize(array_filter($params))) . $ext;
		}

		$create = true;

		// Determine if image generation is needed
		if (file_exists($newPath)) {
			$create = false;

			$origTime = date('YmdHis', filemtime($path));
			$newTime = date('YmdHis', filemtime($newPath));

			if ($newTime < $origTime) {
				$create = true;
			}
		}

		// Create image if required
		if ($create) {
			$tmpImage = false;

			if ($type == 1) {
				$tmpImage = imagecreatefromgif($path);
			} elseif ($type == 2) {
				$tmpImage = imagecreatefromjpeg($path);
			} elseif ($type == 3) {
				$tmpImage = imagecreatefrompng($path);
			}

			$origX = $origY = $targetX = $targetY = 0;

			if ($tmpImage) {
				$new = imagecreatetruecolor($targetWidth, $targetHeight);

				$origRatio = $origWidth / $origHeight;
				$targetRatio = $targetWidth / $targetHeight;

				if ($params['crop'] === true && $width !== false && $height !== false) {
					if ($origRatio > $targetRatio) {
						$tempWidth = $origHeight * $targetRatio;
						$tempHeight = $origHeight;

						$origX = ($origWidth - $tempWidth) / 2;
					} else {
						$tempWidth = $origWidth;
						$tempHeight = $origWidth / $targetRatio;

						$origY = ($origHeight - $tempHeight) / 2;
					}

					$origWidth = $tempWidth;
					$origHeight = $tempHeight;
				} else {
					if ($origRatio < $targetRatio) {
						$tempWidth = $targetHeight * $origRatio;
						$tempHeight = $targetHeight;

						$targetX = ($targetWidth - $tempWidth) / 2;
					} else {
						$tempWidth = $targetWidth;
						$tempHeight = $targetWidth / $origRatio;

						$targetY = ($targetHeight - $tempHeight) / 2;
					}

					$targetWidth = $tempWidth;
					$targetHeight = $tempHeight;
				}

				if ($type == 2) {
					$color = $params['background'] !== false ? $params['background'] : 'ffffff';

					$rgb = $this->convert_hex($color);

					$background = imagecolorallocate($new, $rgb['r'], $rgb['g'], $rgb['b']);

					imagefill($new, 0, 0, $background);
				} else {
					// Set image background
					if ($params['force_jpg'] === true && $params['background'] === false) {
						$params['background'] = 'ffffff';
					}

					if ($params['background']) {
						$rgb = $this->convert_hex($params['background']);

						imagefilter($new, IMG_FILTER_COLORIZE, $rgb['r'], $rgb['g'], $rgb['b']);
					} elseif ($params['force_jpg'] !== true) {
						$transparent = imagecolortransparent($new, imagecolorallocatealpha($new, 0, 0, 0, 127));

						if ($type == 3) {
							imagealphablending($new, false);
							imagesavealpha($new, true);
						}

						imagefill($new, 0, 0, $transparent);
					}
				}

				imagecopyresampled($new, $tmpImage, $targetX, $targetY, $origX, $origY, $targetWidth, $targetHeight, $origWidth, $origHeight);

				// Sharpen image
				if ($type == 2 || $params['force_jpg'] === true) {
					if ($params['sharpen']) {
						$sharpen = array(
							array(0, -1, 0),
							array(-1, 12, -1),
							array(0, -1, 0)
						);

						$divisor = array_sum(array_map('array_sum', $sharpen));

						imageconvolution($new, $sharpen, $divisor, 0);
					}

					imagejpeg($new, $newPath, $params['quality']);
				} else {
					if ($type == 1) {
						imagegif($new, $newPath);
					} else {
						imagepng($new, $newPath, 9);
					}
				}

				// Remove temporary images
				imagedestroy($new);
				imagedestroy($tmpImage);
			} else {
				return false;
			}

			if ($absolute) {
				unlink($path);
			}
		}

		// Return relative path
		$image = str_replace($params['root'], '', $newPath);

		return array(
			'path' => $image,
			'width' => $targetWidth,
			'height' => $targetHeight
		);
	}

	/**
	 * Construct an image tag
	 *
	 * @access private
	 * @param array $image
	 * @param array $params
	 * @return string
	 */
	private function build_tag($image, $params = array())
	{
		$attributes = array();

		if (preg_match('/^(https?:)?\/\//i', $image['path'])) {
			$attributes['src'] = $image['path'];
		} else {
			$attributes['src'] = $this->settings['host'] . $image['path'];
		}

		if ($this->settings['responsive'] !== true) {
			$attributes['width'] = $image['width'];
			$attributes['height'] = $image['height'];
		}

		$attributes['alt'] = $this->settings['alt'];

		$attributes = array_merge($attributes, $params);

		$tag = '<img';

		foreach($attributes as $key => $value){
			$tag .= ' ' . $key . '="' . $value . '"';
		}

		$tag .= $this->settings['xhtml'] === true ? ' />' : '>';

		return $tag;
	}

	/**
	 * Capture custom tag attributes
	 *
	 * @access private
	 * @param array $params
	 * @return array
	 */
	private function set_attributes($params = array())
	{
		foreach (ee()->TMPL->tagparams as $key => $value) {
			if (substr($key, 0, 5) == 'attr:') {
				$params[substr($key, 5)] = $value;
			}
		}

		return $params;
	}

	/**
	 * Convert HEX color array to RGB array
	 *
	 * @access private
	 * @param array $hex
	 * @return array
	 */
	private function convert_hex($hex)
	{
		if (strlen($hex) == 6) {
			list($r, $g, $b) = array(
				$hex[0] . $hex[1],
				$hex[2] . $hex[3],
				$hex[4] . $hex[5]
			);

			return array(
				'r' => hexdec($r),
				'g' => hexdec($g),
				'b' => hexdec($b)
			);
		}

		return false;
	}

	public static function usage()
	{
		return 'See docs and examples at https://github.com/caddis/resizer';
	}
}