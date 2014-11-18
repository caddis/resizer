<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
	'pi_name' => 'Resizer',
	'pi_version' => '1.1.1',
	'pi_author' => 'Caddis',
	'pi_author_url' => 'http://www.caddis.co',
	'pi_description' => 'Resize, cache, and retrieve images',
	'pi_usage' => Resizer::usage()
);

class Resizer {

	public $defaults = array(
		'alt' => '',
		'background' => false,
		'crop' => false,
		'fallback' => false,
		'filename' => false,
		'height' => false,
		'force_jpg' => false,
		'responsive' => false,
		'scale_up' => false,
		'xhtml' => true,
		'quality' => false,
		'sharpen' => false,
		'target' => '/images/sized/',
		'width' => false,
		'host' => ''
	);

	private $_settings = array();

	public function __construct()
	{
		foreach ($this->defaults as $key => $val) {
			$param = ee()->TMPL->fetch_param($key);

			$this->_settings[$key] = ($param !== false) ? (($param == 'yes') ? true : $param) : $this->defaults[$key];
		}

		$this->_settings['root'] = $_SERVER['DOCUMENT_ROOT'];
		$this->_settings['target'] = $this->_settings['root'] . $this->_settings['target'];

		// PHP memory limit
		if (ee()->config->item('resizer_memory_limit') !== false) {
			ini_set('memory_limit', ee()->config->item('resizer_memory_limit') . 'M');
		}

		// Image quality
		if ($this->_settings['quality'] === false) {
			$this->_settings['quality'] = (ee()->config->item('resizer_quality') !== false) ? ee()->config->item('resizer_quality') : 80;
		}

		// Responsive
		if ($this->_settings['responsive'] === false) {
			$this->_settings['responsive'] = ee()->config->item('resizer_responsive');
		}

		// Self close
		if ($this->_settings['xhtml'] === false) {
			$this->_settings['xhtml'] = ee()->config->item('resizer_xhtml');
		}

		// Sharpen
		if ($this->_settings['sharpen'] === false) {
			$this->_settings['sharpen'] = ee()->config->item('resizer_sharpen');
		}

		// Target
		if (ee()->TMPL->fetch_param('target') === false) {
			$this->_settings['target'] = $this->_settings['root'] . ((ee()->config->item('resizer_target') !== false) ? ee()->config->item('resizer_target') : $this->defaults['target']);
		}

		// Image source
		$src = ee()->TMPL->fetch_param('src');

		if ($src === false) {
			return '';
		}

		// Generate and cache image
		$this->image = $this->_create($src, $this->_settings);
	}

	public function path()
	{
		if ($this->image) {
			return $this->_settings['host'] . $this->image['path'];
		}

		return '';
	}

	public function tag()
	{
		if ($this->image) {
			$tag = '<img src="' . $this->_settings['host'] . $this->image['path'] . '"';

			if ($this->_settings['responsive'] !== true) {
				$tag .= ' width="' . $this->image['width'] . '" height="' . $this->image['height'] . '"';
			}

			$tag .= ' alt="' . $this->_settings['alt'] . '"';

			$params = ee()->TMPL->tagparams;

			foreach ($params as $key => $value) {
				if (substr($key, 0, 5) == 'attr:') {
					$tag .= ' ' . substr($key, 5) . '="' . $value . '"';
				}
			}

			$tag .= (($this->_settings['xhtml'] === true) ? ' />' : '>');

			return $tag;
		}

		return '';
	}

	public function pair()
	{
		$tagdata = ee()->TMPL->tagdata;

		if ($this->image) {
			$variables = array(
				'resizer:path' => $this->_settings['host'] . $this->image['path'],
				'resizer:width' => $this->image['width'],
				'resizer:height' => $this->image['height']
			);

			return ee()->TMPL->parse_variables_row($tagdata, $variables);
		}

		return $tagdata;
	}

	private function _create($src, $params = array())
	{
		$allowed = array('.jpg', '.jpeg', '.gif', '.png');

		$target = $params['target'];
		$path = $params['root'] . $src;

		// Check for image, display fallback if necessary
		if (! is_file($path) or ! file_exists($path)) {
			if ($params['fallback']) {
				$path = $params['root'] . $params['fallback'];

				if (! file_exists($path)) {
					return false;
				}
			} else {
				return false;
			}
		}

		$path_parts = pathinfo($path);
		$filename = $path_parts['filename'];

		$data = getimagesize($path);

		if (! isset($data) or ! is_array($data)) {
			return false;
		}

		$type = $data[2];
		$ext = image_type_to_extension($type);

		if (! in_array($ext, $allowed)) {
			return false;
		}

		if ($ext === '.jpeg' or $params['force_jpg'] === true) {
			$ext = '.jpg';
		}

		$orig_width = $data[0];
		$orig_height = $data[1];

		// General filename
		if ($params['filename']) {
			$new_path = $target . $params['filename'] . $ext;
		} else {
			unset($params['alt']);
			unset($params['fallback']);
			unset($params['self_close']);

			ksort($params);

			$new_path = $target . $filename . '-' . md5(serialize($params)) . $ext;
		}

		// Calculate target width and height
		$orig_ratio = ($orig_width / $orig_height);

		$width = $params['width'];
		$height = $params['height'];

		if (! $width and ! $height) {
			$width = $target_width = $orig_width;
			$height = $target_height = $orig_height;
		} else {
			if ($width and $height) {
				$target_width = ($width < $orig_width) ? $width : (($params['scale_up'] === true) ? $width : $orig_width);
				$target_height = ($height < $orig_height) ? $height : (($params['scale_up'] === true) ? $height : $orig_height);
			} else if ($width) {
				$target_width = ($width < $orig_width) ? $width : (($params['scale_up'] === true) ? $width : $orig_width);
				$target_height = floor($target_width / $orig_ratio);
			} else if ($height) {
				$target_height = ($height < $orig_height) ? $height : (($params['scale_up'] === true) ? $height : $orig_height);
				$target_width = floor($target_height * $orig_ratio);
			}
		}

		$target_ratio = ($target_width / $target_height);

		$create = true;

		// Determine if image generation is needed
		if (file_exists($new_path)) {
			$create = false;

			$orig_time = date('YmdHis', filemtime($path));
			$new_time = date('YmdHis', filemtime($new_path));

			if ($new_time < $orig_time) {
				$create = true;
			}
		}

		// Create image if required
		if ($create) {
			$tmp_image = false;

			if ($type == 1) {
				$tmp_image = imagecreatefromgif($path);
			} else if ($type == 2) {
				$tmp_image = imagecreatefromjpeg($path);
			} else if ($type == 3) {
				$tmp_image = imagecreatefrompng($path);
			}

			$orig_x = $orig_y = $target_x = $target_y = 0;

			if ($tmp_image) {
				$new = imagecreatetruecolor($target_width, $target_height);

				$orig_ratio = $orig_width / $orig_height;
				$target_ratio = $target_width / $target_height;

				if ($params['crop'] === true and $width !== false and $height  !== false) {
					if ($orig_ratio > $target_ratio) {
						$temp_width = $orig_height * $target_ratio;
						$temp_height = $orig_height;

						$orig_x = ($orig_width - $temp_width) / 2;
					} else {
						$temp_width = $orig_width;
						$temp_height = $orig_width / $target_ratio;

						$orig_y = ($orig_height - $temp_height) / 2;
					}

					$orig_width = $temp_width;
					$orig_height = $temp_height;
				} else {
					if ($orig_ratio < $target_ratio) {
						$temp_width = $target_height * $orig_ratio;
						$temp_height = $target_height;

						$target_x = ($target_width - $temp_width) / 2;
					} else {
						$temp_width = $target_width;
						$temp_height = $target_width / $orig_ratio;

						$target_y = ($target_height - $temp_height) / 2;
					}

					$target_width = $temp_width;
					$target_height = $temp_height;
				}

				if ($type == 2) {
					$color = ($params['background'] !== false) ? $params['background'] : 'ffffff';

					$rgb = $this->_convert_hex($color);

					$background = imagecolorallocate($new, $rgb['r'], $rgb['g'], $rgb['b']);

					imagefill($new, 0, 0, $background);
				} else {
					// Set image background
					if ($params['force_jpg'] === true and $params['background'] === false) {
						$params['background'] = 'ffffff';
					}

					if ($params['background']) {
						$rgb = $this->_convert_hex($params['background']);

						imagefilter($new, IMG_FILTER_COLORIZE, $rgb['r'], $rgb['g'], $rgb['b']);
					} else if ($params['force_jpg'] !== true) {
						$transparent = imagecolortransparent($new, imagecolorallocatealpha($new, 0, 0, 0, 127));

						if ($type == 3) {
							imagealphablending($new, false);
							imagesavealpha($new, true);
						}

						imagefill($new, 0, 0, $transparent);
					}
				}

				imagecopyresampled($new, $tmp_image, $target_x, $target_y, $orig_x, $orig_y, $target_width, $target_height, $orig_width, $orig_height);

				// Sharpen image
				if ($type == 2 or $params['force_jpg'] === true) {
					if ($params['sharpen']) {
						$sharpen = array(
							array(0, -1, 0),
							array(-1, 12, -1),
							array(0, -1, 0)
						);

						$divisor = array_sum(array_map('array_sum', $sharpen));

						imageconvolution($new, $sharpen, $divisor, 0);
					}

					imagejpeg($new, $new_path, $params['quality']);
				} else {
					if ($type == 1) {
						imagegif($new, $new_path);
					} else {
						imagepng($new, $new_path, 9);
					}
				}

				// Remove temporary images
				imagedestroy($new);
				imagedestroy($tmp_image);
			} else {
				return false;
			}
		}

		// Return relative path
		$image = str_replace($params['root'], '', $new_path);

		return array(
			'path' => $image,
			'width' => $target_width,
			'height' => $target_height
		);
	}

	private function _convert_hex($hex)
	{
		if (strlen($hex) == 6) {
			list($r, $g, $b) = array($hex[0] . $hex[1], $hex[2] . $hex[3], $hex[4] . $hex[5]);

			return array('r' => hexdec($r), 'g' => hexdec($g), 'b' => hexdec($b));
		}

		return false;
	}

	public static function usage()
	{
		ob_start();
?>
See docs and examples on GitHub:
https://github.com/caddis/resizer
<?php
		$buffer = ob_get_contents();

		ob_end_clean();

		return $buffer;
	}
}
?>