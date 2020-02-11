<?php
/**
	Description: Image Support for ESC/P Printing with full resize, URL based image fetching - 
  added as a plugin for RamyTalal's label printer repo (great code, just needed image support for my use case)
  Requirements: PHP Imagick 3+ extension enabled for this to work (image resizing, conversion)
	Author: Ilan Patao (ilan@dangerstudio.com)
	https://github.com/ilanpatao
	Written: 02/11/2020
*/

namespace Talal\LabelPrinter\Command;

class Image implements CommandInterface
{
    /**
     * @var string
     */
    protected $value;

    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @inheritdoc
     */
    public function read()
    {
        $imgurl = $this->value;
		
		function toColumnFormat(Imagick $im, $lineHeight) {
			$imgWidth = $im->getimagewidth ();
			if ($imgWidth == $lineHeight) {
				// Return glob of this panel
				$blob = $im->getimageblob ();
				$i = strpos ( $blob, "\n", 3 );
				return array (
						substr ( $blob, $i + 1 ) 
				);
			} else {
				$slicesLeft = ceil ( $imgWidth / $lineHeight / 2 );
				$widthLeft = $slicesLeft * $lineHeight;
				$widthRight = $imgWidth - $widthLeft;
				// Slice up
				$left = $im->clone ();
				$left->extentimage ( $widthLeft, $left->getimageheight (), 0, 0 );
				$right = $im->clone ();
				$right->extentimage ( $widthRight < $lineHeight ? $lineHeight : $widthRight, $right->getimageheight (), $widthLeft, 0 );
				return array_merge ( toColumnFormat ( $left, $lineHeight ), toColumnFormat ( $right, $lineHeight ) );
			}
		}
		
		function intLowHigh($input, $length) {
			$outp = "";
			for($i = 0; $i < $length; $i ++) {
				$outp .= chr ( $input % 256 );
				$input = ( int ) ($input / 256);
			}
			return $outp;
		}
		
		// Store logo image
		$src = file_get_contents($imgurl);
		file_put_contents('templogo.png',$src);
		$thumb = new Imagick('templogo.png');
		// Resize image in real-time
		$thumb->resizeImage(355,150,Imagick::FILTER_LANCZOS,1);
		$thumb->writeImage('logo.png');
		$thumb->destroy(); 
		unlink('templogo.png');


		// Configure
		$highDensityHorizontal = true;
		$highDensityVertical = true;
		$filename = isset($argv[1]) ? $argv[1] : 'logo.png';


		// Load image
		$im = new \Imagick();
		$im->setResourceLimit ( 6, 1 ); // Prevent libgomp1 segfaults, grumble grumble.
		$im->readimage ( $filename );
		$im-> setImageBackgroundColor('white');
		$im-> mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

		// Adjust image
		$im->setformat ( 'pbm' );
		$im->getimageblob ();
		$im->rotateImage ( '#fff', 90.0 );
		$im->flopImage ();
		$lineHeight = $highDensityVertical ? 3 : 1;
		$blobs = toColumnFormat ( $im, $lineHeight * 8 );

		// Generate ESC/POS image
		$ESC = "\x1b";
		$widthPixels = $im->getimageheight ();
		$densityCode = ($highDensityHorizontal ? 1 : 0) + ($highDensityVertical ? 32 : 0);
		$header = $ESC . "*" . chr ( $densityCode ) . intLowHigh ( $widthPixels, 2 );
		$logo = '';
		$logo = $ESC . "3" . chr ( 16 );
		foreach ( $blobs as $blob ) {
			$logo = $logo . $header . $blob . "\n";
		}
		$logo = $logo . $ESC . "2";
		unlink('logo.png');
		
		return $logo . chr(10);
		
    }
}
