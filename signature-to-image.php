<?php
/**
 *	Signature to Image: A supplemental script for Signature Pad that
 *	generates an image of the signature’s JSON output server-side using PHP.
 *
 *	@project	ca.thomasjbradley.applications.signaturetoimage
 *	@author		Thomas J Bradley <hey@thomasjbradley.ca>
 *	@link		http://thomasjbradley.ca/lab/signature-to-image
 *	@link		http://github.com/thomasjbradley/signature-to-image
 *	@copyright	Copyright MMXI–, Thomas J Bradley
 *	@license	New BSD License
 *	@version	1.0.1
 */

class SignaturePadToImage {

/**
 * Determine max width/height from Signature Pad signature data.
 * Defaults to false.
 *
 * @var bool True finds max, false uses SignaturePadToImage::imageWidth and SignaturePadToImage::imageHeight
 * @see SignaturePadToImage::getSizeFromSignatureData()
 */
	public $autoSize = FALSE;

/**
 * Last successfully created image or NULL if create failed
 *
 * @var resource an image resource
 */
	private $image = NULL;

/**
 * The colour fill for the background of the image.
 * Defaults to array( 0xff, 0xff, 0xff )
 *
 * @var array hex red, hex green, hex blue
 */
	public $bgColour = array( 0xff, 0xff, 0xff );

/**
 * Multiplier for internal image size, helps create nice antialiased return image
 * Defaults to 12
 *
 * @var int
 */
	public $drawMultiplier = 12;

/**
 * Determines the final output height of the image.
 * Defaults to 55
 *
 * @var int
 */
	public $imageHeight = 55;

/**
 * Determines the final output width of the image.
 * Defaults to 198
 *
 * @var int
 */
	public $imageWidth = 198;


/**
 * Colour of the drawing ink.
 * Defaults to array( 0x14, 0x53, 0x94 )
 *
 * @var array hex red, hex green, hex blue
 */
	public $penColour = array( 0x14, 0x53, 0x94 );

/**
 * Thickness, in pixels, of the drawing pen
 * Defaults to 2
 *
 * @var int
 */
	public $penWidth = 2;

/**
 * Sets options on construct.
 *
 * @param array $options An array of optional key => val.
 * 	autoSize,
 * 	bgColour,
 * 	drawMulitplier,
 * 	imageHeight,
 * 	imageWidth,
 * 	penColour,
 * 	penWidth
 */
	public function __construct( $options ) {
		foreach ( $options as $k => $v ) {
			if ( isset( $this->{$k} ) ) {
				$this->{$k} = $v;
			}
		}
	}

/**
 *	Accepts a signature created by signature pad in Json format
 *	Converts it to an image resource
 *	The image resource can then be changed into png, jpg whatever PHP GD supports
 *
 *	To create a nicely anti-aliased graphic the signature is drawn 12 times it's original size then shrunken
 *
 *	@param	string|array	$json
 *	@param	array	$options	OPTIONAL; the options for image creation
 *		imageSize => array(width, height)
 *		bgColour => array(red, green, blue)
 *		penWidth => int
 *		penColour => array(red, green, blue)
 *
 *	@return	resource an image resource identifier on success, NULL on errors.
 */
	public function sigJsonToImage($json, $options = array()) {
		$defaultOptions = array(
			'autoSize' => $this->autoSize,
			'imageSize' => array( $this->imageWidth, $this->imageHeight ),
			'bgColour' => $this->bgColour,
			'penWidth' => $this->penWidth,
			'penColour' => $this->penColour,
			'drawMultiplier'=> $this->drawMultiplier,
		);

		// check for autoSize and don't override $options['imageSize']
		if ( $defaultOptions['autoSize'] && !isset( $options['imageSize'] ) ) {
			// do autoSize
			$options['imageSize'] = array_values( $this->getSizeFromSignatureData( $json ) );
		}

		$options = array_merge($defaultOptions, $options);

		$img = imagecreatetruecolor($options['imageSize'][0] * $options['drawMultiplier'], $options['imageSize'][1] * $options['drawMultiplier']);
		$bg = imagecolorallocate($img, $options['bgColour'][0], $options['bgColour'][1], $options['bgColour'][2]);
		$pen = imagecolorallocate($img, $options['penColour'][0], $options['penColour'][1], $options['penColour'][2]);
		imagefill($img, 0, 0, $bg);

		if(is_string($json))
			$json = json_decode(stripslashes($json));

		foreach($json as $v)
			$this->drawThickLine($img, $v->lx * $options['drawMultiplier'], $v->ly * $options['drawMultiplier'], $v->mx * $options['drawMultiplier'], $v->my * $options['drawMultiplier'], $pen, $options['penWidth'] * ($options['drawMultiplier'] / 2));

		$imgDest = imagecreatetruecolor($options['imageSize'][0], $options['imageSize'][1]);
		$isResized = imagecopyresampled($imgDest, $img, 0, 0, 0, 0, $options['imageSize'][0], $options['imageSize'][0], $options['imageSize'][0] * $options['drawMultiplier'], $options['imageSize'][0] * $options['drawMultiplier']);

		imagedestroy($img);

		if ( $isResized ) {
			$this->image = $imgDest;
		} else {
			$this->image = NULL;
		}

		return $this->image;
	}

/**
 *	Draws a thick line
 *	Changing the thickness of a line using imagesetthickness doesn't produce as nice of result
 *
 *	@param	object	$img
 *	@param	int		$startX
 *	@param	int		$startY
 *	@param	int		$endX
 *	@param	int		$endY
 *	@param	object	$colour
 *	@param	int		$thickness
 *
 *	@return	void
 */
	private function drawThickLine($img, $startX, $startY, $endX, $endY, $colour, $thickness) {
		$angle = (atan2(($startY - $endY), ($endX - $startX)));

		$dist_x = $thickness * (sin($angle));
		$dist_y = $thickness * (cos($angle));

		$p1x = ceil(($startX + $dist_x));
		$p1y = ceil(($startY + $dist_y));
		$p2x = ceil(($endX + $dist_x));
		$p2y = ceil(($endY + $dist_y));
		$p3x = ceil(($endX - $dist_x));
		$p3y = ceil(($endY - $dist_y));
		$p4x = ceil(($startX - $dist_x));
		$p4y = ceil(($startY - $dist_y));

		$array = array(0=>$p1x, $p1y, $p2x, $p2y, $p3x, $p3y, $p4x, $p4y);
		imagefilledpolygon($img, $array, (count($array)/2), $colour);
	}

/**
 * Get the created image
 *
 * @return resource an image resource identifier if set, NULL if no image set
 */
	public function getImage() {
		return $this->image;
	}

/**
 * Get the max width and height from Signature Pad signature data.
 *
 * @param array $signatureData Signature Pad signature data
 * @return array with keys 'width' and 'height', boolean FALSE if failure
 */
	protected function getSizeFromSignatureData( &$signatureData ) {
		$rval = FALSE;

		if ( is_string( $signatureData ) ) {
			$signatureData = json_decode( stripslashes( $signatureData ) );
		}

		if ( is_array( $signatureData ) ) {
			$rval = array( 'width'  => 0, 'height' => 0 );
			// cycle through and find max
			foreach ( $signatureData as &$v ) {
				$maxX = max( array( $v->lx, $v->mx ) );
				if ( $maxX > $rval['width'] ) {
					$rval['width'] = $maxX;
				}
				$maxY = max( array( $v->ly, $v->my ) );
				if ( $maxY > $rval['height'] ) {
					$rval['height'] = $maxY;
				}
			}
		}

		return $rval;
	}

/**
 * Output image using imagepng()
 *
 * @param filename string [optional] <p>
 * 	The path to save the file to. If not set or NULL, the raw image
 * 	stream will be outputted directly.
 * </p>
 * @param quality int [optional] <p>
 * 	Compression level: from 0 (no compression) to 9.
 * </p>
 * @param filters int [optional] <p>
 * 	Allows reducing the PNG file size. It is a bitmask field which may be
 * 	set to any combination of the PNG_FILTER_XXX constants. PNG_NO_FILTER or PNG_ALL_FILTERS may also be
 * 	used to respectively disable or activate all filters.
 * </p>
 * @return bool Returns TRUE on success or FALSE on failure.
 */
	public function outputImage( $filename = null, $quality = null, $filters = null ) {
		$isOutput = FALSE;
		if ( $this->image ) {
			// saving to file? don't output to browser
			if ( !$filename ) {
				header('Content-Type: image/png');
			}
			$isOutput = imagepng( $this->image, $filename, $quality, $filters );
		}
		return $isOutput;
	}

}
