<?php

// GET parameter:
// time=YMDHms
// gif_lenght=seconds (> 0)
// image_link=image path [if no width/height]
// width= (> 0)
// height= (> 0)
// font_size= (> 0)
// color=rrggbb

// Leave all this stuff as it is
date_default_timezone_set('Asia/Tokyo');
include 'GIFEncoder.class.php';
$font_file = __DIR__.DIRECTORY_SEPARATOR.'Futura.ttc';
$time = $_GET['time'] ?? 0;
$gif_length = $_GET['gif_length'] ?? 70; // gif length in seconds
$image_link = $_GET['image_link'] ?? null; // 'images/countdown.png'
$width = $_GET['width'] ?? 460;
$height = $_GET['width'] ?? 90;
$font_size = $_GET['font_size'] ?? 50;
$color = $_GET['color'] ?? '555555';
$bg_color = $_GET['bg_color'] ?? 'ffffff';

// prep time, it is in YYYYMMDDHHMMSS
if (!preg_match("/^2020[0-1][0-9][0-3][0-9][0-2][0-9][0-5][0-9][0-5][0-9]$/", $time)) {
	$time = 0;
} else {
	$time = substr($time, 0, 4).'-'. // year
		substr($time, 4, 2).'-'. // month
		substr($time, 6, 2).' '. // day
		substr($time, 8, 2).':'. // hour
		substr($time, 10, 2).':'. // min
		substr($time, 12, 2); // sec
}
// valid check must be between 1 and 1000 seconds
if (!is_numeric($gif_length) ||
	(is_numeric($gif_length) && $gif_length < 1) ||
	(is_numeric($gif_length) && $gif_length > 1000)
) {
	$gif_length = 60;
}
// check if file exsits
if ($image_link !== null && !is_file($image_link)) {
	$image_link = null;
}
// also check if file is png, we ignore all others
if ($image_link !== null && is_file($image_link)) {
	list($il_width, $il_height, $il_type) = getimagesize($image_link);
	if ($il_type != IMG_PNG) {
		$image_link = null;
	}
}
// width/heigth
if (!is_numeric($width) ||
	(is_numeric($width) && $width < 1) ||
	(is_numeric($width) && $width > 1000)
) {
	$width = 460;
}
if (!is_numeric($height) ||
	(is_numeric($height) && $height < 1) ||
	(is_numeric($height) && $height > 1000)
) {
	$width = 90;
}
if (!is_numeric($font_size) ||
	(is_numeric($font_size) && $font_size < 1) ||
	(is_numeric($font_size) && $font_size > 200)
) {
	$font_size = 70;
}
// color check, must be vaild 6 character hex string
if (!preg_match("/^[A-Fa-f0-0]{6}$/", $color)) {
	$color = '555555';
}
if (!preg_match("/^[A-Fa-f0-0]{6}$/", $bg_color)) {
	$color = 'ffffff';
}

$future_date = new DateTime(date('r', strtotime($time)));
$time_now = time();
$now = new DateTime(date('r', $time_now));
$frames = [];
$delays = [];
$loops = 0;
$delay = 100; // milliseconds

/**
 * converts a hex RGB color to the int numbers
 * @param  string            $hexStr         RGB hexstring
 * @param  bool              $returnAsString flag to return as string
 * @param  string            $seperator      string seperator: default: ","
 * @return string|array|bool                 false on error or array with RGB or a string with the seperator
 */
function hex2rgb(string $hexStr, bool $returnAsString = false, string $seperator = ',')
{
	$hexStr = preg_replace("/[^0-9A-Fa-f]/", '', $hexStr); // Gets a proper hex string
	$rgbArray = array();
	if (strlen($hexStr) == 6) {
		// If a proper hex code, convert using bitwise operation. No overhead... faster
		$colorVal = hexdec($hexStr);
		$rgbArray['R'] = 0xFF & ($colorVal >> 0x10);
		$rgbArray['G'] = 0xFF & ($colorVal >> 0x8);
		$rgbArray['B'] = 0xFF & $colorVal;
	} elseif (strlen($hexStr) == 3) {
		// If shorthand notation, need some string manipulations
		$rgbArray['R'] = hexdec(str_repeat(substr($hexStr, 0, 1), 2));
		$rgbArray['G'] = hexdec(str_repeat(substr($hexStr, 1, 1), 2));
		$rgbArray['B'] = hexdec(str_repeat(substr($hexStr, 2, 1), 2));
	} else {
		// Invalid hex color code
		return false;
	}
	// returns the rgb string or the associative array
	return $returnAsString ? implode($seperator, $rgbArray) : $rgbArray;
}

/**
 * create plain image or load image from path
 * @param  string|null $image_link image link string or null
 * @param  int         $width      if no image link width
 * @param  int         $height     if no image link height
 * @param  string      $bg_color   string for background color
 * @return resouce|bool            image or false for failure
 */
function createImage(?
	string $image_link = null, int $width = null, int $height = null, string $bg_color = null)
{
	if ($image_link !== null && is_file($image_link)) {
		// Your image link
		$image = imagecreatefrompng($image_link);
	} else {
		// or create 640x120 white png
		$image = imagecreatetruecolor($width, $height);
		$rgb = hex2rgb($bg_color);
		$whiteBackground = imagecolorallocate($image, $rgb['R'], $rgb['G'], $rgb['B']);
		imagefill($image, 0, 0, $whiteBackground);
	}
	return $image;
}

$rgb = hex2rgb($color);
$font = [
	'size' => $font_size, // Font size, in pts usually.
	'angle' => 0, // Angle of the text
	'x-offset' => 20, // The larger the number the further the distance from the left hand side, 0 to align to the left.
	'y-offset' => 75, // The vertical alignment, trial and error between 20 and 60.
	'file' => $font_file, // Font path
	'color' => imagecolorallocate(createImage($image_link, $width, $height, $bg_color), $rgb['R'], $rgb['G'], $rgb['B']), // RGB Colour of the text
];
// *** CENTER X ***
// text box after rendering
$text_box = imagettfbbox(
	$font_size,
	0,
	$font_file,
	'00:00:00:00'
);
// calc font pos only once with 00
$font['x-offset'] = ceil(($width / 2) - (($text_box[2] - $text_box[0]) / 2));
// *** CENTER Y ***
$text_box_height = abs(max(array($text_box[5], $text_box[7]))) + abs(max(array($text_box[1], $text_box[3])));
// from bottom
$font_pos_y = floor(($height - $text_box_height) / 2);
$font['y-offset'] = $height - $font_pos_y;
// loop over gif length
for ($i = 0; $i <= $gif_length; $i++) {
	$interval = date_diff($future_date, $now);

	if ($future_date < $now) {
		$text = $interval->format('00:00:00:00');
		$loops = 1;
		// break;
	} else {
		$text = $interval->format(($interval->days > 9 ? '' : '0').'%a:%H:%I:%S');
		$loops = 0;
	}
	// create base image
	$image = createImage($image_link, $width, $height, $bg_color);
	imagettftext($image, $font['size'], $font['angle'], $font['x-offset'], $font['y-offset'], $font['color'], $font['file'], $text);
	ob_start();
	imagegif($image);
	$frames[] = ob_get_contents();
	$delays[] = $delay;
	ob_end_clean();
	if ($loops == 1) {
		break;
	}
	// move timer up
	$now->modify('+1 second');
}

//expire this image instantly
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
$gif = new GIFEncoder\AnimatedGif($frames, $delays, $loops);
$gif->display();

// __END__
