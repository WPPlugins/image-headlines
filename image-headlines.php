<?php
/*
Plugin Name: Headline Images
Plugin URI: http://www.coldforged.org/image-headlines-plugin-for-wordpress-15/
Description: Replaces Post headlines with PNG images of text, from ALA's <a href="http://www.alistapart.com/articles/dynatext/">Dynamic Text Replacement</a>. Includes soft-shadows, improved configuration, and previews. Configure on the <a href="../wp-admin/admin.php?page=image-headlines.php">Headline Images Configuration</a> page.
Version: 2.6
Author: Brian "ColdForged" Dupuis
Author URI: http://www.coldforged.org
*/

/* 
	Bundled font is the beautiful Warp 1 by Alex Gollner. Take a
	gander at some of his other fonts at 

	http://www.project.com/alex/fonts/index.html
*/	

/*
	Plugin originally by Joel “Jaykul” Bennett, but heavily modified
	to the point seen here. 

	Dynamic Heading Generator
	By Stewart Rosenberger
	http://www.stewartspeak.com/headings/
	http://www.alistapart.com/articles/dynatext/

	This script generates PNG images of text, written in
	the font/size that you specify. These PNG images are passed
	back to the browser. Optionally, they can be cached for later use.
	If a cached image is found, a new image will not be generated,
	and the existing copy will be sent to the browser.

	Additional documentation on PHP's image handling capabilities can
	be found at http://www.php.net/image/
*/

define( "IMAGEHEADLINES_VERSION", "2.5" );

if (! isset($wp_version))
{
	require_once (dirname(dirname(dirname(__FILE__))) . "/wp-config.php");
	global $wp_version;
}

if (substr($wp_version, 0, 3) == "1.1" || substr($wp_version, 0, 3) == "1.0" || substr($wp_version, 0, 3) == "1.2" )
{
	echo "The Image Headlines Plugin requires at least WordPress version 1.3";
	return;
}

define( "FONT_DIRECTORY", dirname(dirname(__FILE__))."/image-headlines" );

if (function_exists('load_plugin_textdomain'))
{
	load_plugin_textdomain('headlinedomain');
}

if (! function_exists('ImageHeadline_update_option'))
{
	function ImageHeadline_update_option($option, $new_settings) 
	{
		update_option($option, $new_settings);
	}
	function ImageHeadline_get_settings($option)
	{
		$settings = get_settings($option);

		// HACK for problems with some WordPress 1.3 installations.
		if( is_string($settings) )
		{
			$unserialized = @ unserialize(stripslashes($settings));
			if( $unserialized !== FALSE )
				$settings = $unserialized;
		}
		return $settings;
	}
}

if (! function_exists('ImageHeadline_option_set'))
{
	function ImageHeadline_option_set($option) 
	{
		if (! $options = ImageHeadline_get_settings('ImageHeadline_options'))
			return false;
		else
			return (in_array($option, $options));
	}

}

if (! function_exists('ImageHeadline_add_options_page')) 
{
	function ImageHeadline_add_options_page() 
	{
		if (function_exists('add_options_page'))
			add_options_page(__("Headline Options Page"), __('Headlines'), 7, basename(__FILE__));
	}

}

if (! function_exists('ImageHeadline_option_set'))
{
	function ImageHeadline_option_set($option) 
	{
		if (! $options = ImageHeadline_get_settings('ImageHeadline_options'))
			return false;
		else
			return (in_array($option, $options));
	}

}

if (!function_exists('ImageHeadline_check_flag'))
{
	function ImageHeadline_check_flag($flagname, $allflags) 
	{
		echo (in_array($flagname, $allflags) ? 'checked="checked"' : '');
	}
}

if (!function_exists('ImageHeadline_check_select'))
{
	function ImageHeadline_check_select($flagname, $allflags, $value) 
	{
		echo ($allflags[$flagname] == $value ? ' selected="selected"' : '');
	}
}


if (!function_exists('ImageHeadline_check_radio'))
{
	function ImageHeadline_check_radio($flagname, $allflags, $value) 
	{
		echo ($allflags[$flagname] == $value ? 'checked="checked"' : '');
	}
}

if( !function_exists('ImageHeadline_gd_version' ) )
{
	function ImageHeadline_gd_version() {
	   static $gd_version_number = null;
	   if ($gd_version_number === null) {
		   // Use output buffering to get results from phpinfo()
		   // without disturbing the page we're in.  Output
		   // buffering is "stackable" so we don't even have to
		   // worry about previous or encompassing buffering.
		   ob_start();
		   phpinfo(8);
		   $module_info = ob_get_contents();
		   ob_end_clean();
		   if (preg_match("/\bgd\s+version\b[^\d\n\r]+?([\d\.]+)/i",
				   $module_info,$matches)) {
			   $gd_version_number = $matches[1];
		   } else {
			   $gd_version_number = 0;
		   }
	   }
	   return $gd_version_number;
	} 
}

if( !function_exists('ImageHeadline_readint'))
{
	function ImageHeadline_readint( $file, $offset, $size )
	{
		@fseek( $file, $offset, SEEK_SET );
		$string = @fread( $file, $size );
		$myarray = @unpack( (( $size == 2 ) ? "n" : "N")."*", $string );
		return intval($myarray[1]);
	}
}

if( !function_exists('ImageHeadline_get_ttf_font_name'))
{
	function ImageHeadline_get_ttf_font_name( $fullpath )
	{
		$return_string = '';
		
		$thefile = @fopen( $fullpath, "rb" );
		if( $thefile )
		{
			// Read the number of records.
			$num_tables = ImageHeadline_readint( $thefile, 4, 2 );

			// Loop through looking for the name record.
			$offset = 12;
			$name_offset = 0;
			$name_length = 0;
			for( $x = 0; ( $x < $num_tables ) && !feof( $thefile ) && ( $name_offset == 0 ); $x++ )
			{
				@fseek( $thefile, $offset, SEEK_SET );
				$tag = @fread( $thefile, 4 );

				if( !strcmp( $tag, 'name' ) )
				{
					// Found the 'name' tag so read the offset into the file of
					// the name table.
					$offset += 8;
					$name_offset = ImageHeadline_readint( $thefile, $offset, 4 );
					$name_length = ImageHeadline_readint( $thefile, $offset+4, 4 );
				} else {
					$offset += 16;
				}	
			}

			// See if we have an offset to the name table.
			if( $name_offset != 0 )
			{
				// Yay, likely this is a valid TTF file. That's nice. See how many name entries
				// we have.
				$num_names = ImageHeadline_readint( $thefile, $name_offset+2, 2 );
				$string_storage_offset = ImageHeadline_readint( $thefile, $name_offset+4, 2 );
				$name_id_offset = $name_offset + 12;
				
				// Let's find the name record that we desire. We're looking for a name ID
				// of 4.
				$name_string_offset = 0;
				$good_count = 0;
				$preferred = 0;
				for( $x = 0; ( $x < $num_names ) && !feof( $thefile ) /*&& ( $name_string_offset == 0 )*/; $x++ )
				{
					$name_id = ImageHeadline_readint( $thefile, $name_id_offset, 2 );
					if( $name_id == 4 )
					{
						$good_names[$good_count]["platform_id"] = ImageHeadline_readint( $thefile, $name_id_offset-6, 2 );
						$good_names[$good_count]["encoding_id"] = ImageHeadline_readint( $thefile, $name_id_offset-4, 2 );
						$good_names[$good_count]["language_id"] = ImageHeadline_readint( $thefile, $name_id_offset-2, 2 );

						// Odd I know... we're searching for a Windows platform string with these
						// precise parameters. It's the most common among fonts that I've seen. Not that this is a
						// Unicode string so we'll have to deal with that rather naively below. The problem with
						// the other formats is that many shareware/freeware fonts -- which a lot of people
						// will probably use -- is that they're inconsistent with their string settings (like the
						// bundled font... one of the strings is left at "Arial".
						if( ( $good_names[$good_count]["platform_id"] == 3 ) && 
							( $good_names[$good_count]["encoding_id"] == 1 ) && 
							( $good_names[$good_count]["language_id"] == 1033 ) ) {
							$preferred = $good_count;
						}							
							
						$good_names[$good_count]["string_length"] = ImageHeadline_readint( $thefile, $name_id_offset+2, 2 );
						$good_names[$good_count++]["string_offset"] = ImageHeadline_readint( $thefile, $name_id_offset+4, 2 );
					}						
					$name_id_offset += 12;
				}

				// Did we find one?
				if( $good_count )
				{
					// This getting old yet? What a goofy file format, and PHP is far from the most
					// efficient binary file parsers available. Anyway, we apparently have our string.
					// Let's read out the damned thing and have done with it.
					@fseek( $thefile, $name_offset + $string_storage_offset + $good_names[$preferred]["string_offset"], SEEK_SET );
					$return_string = @fread( $thefile, $good_names[$preferred]["string_length"] );
					for( $x = 0; $x < 32; $x++ )
						$unicode_chars[] = chr($x);
					$return_string = str_replace( $unicode_chars, "", $return_string );
				}
			}
			fclose( $thefile );
		}

		return $return_string;   
	}
}

if( !function_exists( 'ImageHeadline_clear_cache' ) )
{
	function ImageHeadline_clear_cache( $dirname, $lifetime )
	{
		if( ( $mydir = @opendir( $dirname ) ) !== false )
		{
			while( ( $file = @readdir( $mydir ) ) !== false )
			{
               if( preg_match("/[a-f0-9]{32}\.png/i", $file) ) 
			   {
				   $difftime = ( time() - filectime( "$dirname/$file" ) ) / 60 / 60 / 24;
				   if( $difftime > $lifetime ) 
				   {
					   @unlink( "$dirname/$file" );
				   }
			   }
			}
		}
	}
}

if( !function_exists( 'ImageHeadline_maybe_clear_cache' ) )
{
	function ImageHeadline_maybe_clear_cache( $dirname, $lifetime )
	{
		$clearit = false;

		// Determine if the cache needs clearing. We do this every 12 hours.
		if( file_exists( "$dirname/cache.info" ) )
		{
			$difftime = time() - filectime( "$dirname/cache.info" );
			if( $difftime > ( 12 * 60 * 60 ) )
			{
				$clearit = true;
				@touch( "$dirname/cache.info" );
			}
		}
		else
		{
			if( ( $file = @fopen( "$dirname/cache.info", "wb" ) ) !== false )
			{
				@fwrite( $file, "Hi there." );
				@fclose( $file );
			}
			$clearit = true;
		}

		if( $clearit ) 
		{
			ImageHeadline_clear_cache($dirname, $lifetime);
		}
	}
}
if( !function_exists( 'ImageHeadline_traverse_directory' ) )
{
	function ImageHeadline_traverse_directory($dirname, &$return_array)
	{
		$current_num = count($return_array);

		// Get a list of files in the directory.
		if( ($mydir = @opendir( $dirname )) !== false )
		{
			while(($file = readdir($mydir))!== false )
			{
				if ($file != "." && $file != "..")
				{
					$font_name = ImageHeadline_get_ttf_font_name( $dirname."/".$file );
					if( $font_name != '' )
					{
						$return_array[$current_num]["font_name"] = $font_name;
						$return_array[$current_num++]["font_file"] = $dirname."/".$file;
					}
				}
			}

			closedir($mydir);
		}
		return $current_num;
	}
}

if( !function_exists( 'ImageHeadline_get_fonts' ) )
{
	/* Returns an associative array of font file names and the corresponding font name */
	function ImageHeadline_get_fonts()
	{
		$num_fonts = 0;
		$return_array = Array();

		// Get a list of files in the directory. We'll also check the upload directory
		// since that's an easy place for WordPress administrators to put new files.
		ImageHeadline_traverse_directory( FONT_DIRECTORY, $return_array );
		ImageHeadline_traverse_directory( get_settings('fileupload_realpath'), $return_array );

		// For each supposed font file parse out a font name string.
		return $return_array;
	}
}

if( !function_exists( 'ImageHeadline_gaussian' ) ) {
	function ImageHeadline_gaussian( &$image, $width, $height, $spread )
	{
		$use_GD1 = ImageHeadline_option_set( 'only_use_imagecreate' );
		if( $use_GD1 )
			return;

		// Check for silly spreads
		if( $spread == 0 )
			return;

		if( $spread > 10 )
			$spread = 10;

		if( strlen( $memory_limit = trim(ini_get('memory_limit' )) ) > 0 )
		{
			$last = strtolower($memory_limit{strlen($memory_limit)-1});
			switch($last) {
				// The 'G' modifier is available since PHP 5.1.0
				case 'g':
					$memory_limit *= 1024;
				case 'm':
					$memory_limit *= 1024;
				case 'k':
					$memory_limit *= 1024;
			}

			if( $memory_limit <= 8 * 1024 * 1024 )
			{
				$use_low_memory_method = true;
			}
			
		} else {
			$use_low_memory_method = false;
		}

		// Perform gaussian blur convlution. First, prepare the convolution 
		// kernel and precalculated multiplication array. Algorithm
		// adapted from the simply exceptional code by Mario Klingemann
		// <http://incubator.quasimondo.com>. Kernel is essentially an
		// approximation of a gaussian distribution by utilizing squares.
		$kernelsize = $spread*2-1;
		$kernel = array_fill( 0, $kernelsize, 0 );
		$mult = array_fill( 0, $kernelsize, array_fill( 0, 256, 0 ) );
		for( $i = 1; $i < $spread; $i++ )
		{
			$smi = $spread - $i;
			$kernel[$smi-1]=$kernel[$spread+$i-1]=$smi*$smi;
			for( $j = 0; $j < 256; $j++ )
			{
				$mult[$smi-1][$j] = $mult[$spread+$i-1][$j] = $kernel[$smi-1]*$j;
			}
		}
		$kernel[$spread-1]=$spread*$spread;
		for( $j = 0; $j < 256; $j++ )
		{
			$mult[$spread-1][$j] = $kernel[$spread-1]*$j;
		}

		if( !$use_low_memory_method ) {

			// Kernel and multiplication array calculated, let's get the image
			// read out into a usable format.
			$imagebytes = $width*$height;
			$i = 0;
			for( $x = 0; $x < $width; $x++ )
			{
				for( $y = 0; $y < $height; $y++ )
				{
					$rgb = imagecolorat( $image, $x, $y );
					$imagearray[$i++] = $rgb;
				}				
			}
		}
			
		// Everything's set. Let's run the first pass. Our first pass will be a 
		// vertical pass.
		for( $x = 0; $x < $width; $x++ )
		{
			for( $y = 0; $y < $height; $y++ )
			{
				$sum = 0;
				$cr = $cg = $cb = 0;
				for( $j = 0; $j < $kernelsize; $j++ )
				{
					$kernely = $y + ( $j - ( $spread - 1 ) );
					if( ( $kernely >= 0 ) && ( $kernely < $height ) )
					{
						if( !$use_low_memory_method ) {
							$ci = ( $x * $height ) + $kernely;
							$rgb = $imagearray[$ci];
						} else {
							$rgb = imagecolorat( $image, $x, $kernely );
						}
						$cr += $mult[$j][($rgb >> 16 ) & 0xFF];
						$cg += $mult[$j][($rgb >> 8 ) & 0xFF];
						$cb += $mult[$j][$rgb & 0xFF];
						$sum += $kernel[$j];
					}
				}
				$ci = ( $x * $height ) + $y;
				$shadowarray[$ci] = ( ( intval(round($cr/$sum)) & 0xff ) << 16 ) | ( ( intval(round($cg/$sum)) & 0xff ) << 8 ) | ( intval(round($cb/$sum)) & 0xff );
			}
		}			

		// Free up some memory
		if( isset( $imagearray ) ) {
			unset( $imagearray );
		}

		// Now let's make with the horizontal passing. That sentence
		// contruct never gets old: "make with the". Oh the humor.
		for( $x = 0; $x < $width; $x++ )
		{
			for( $y = 0; $y < $height; $y++ )
			{
				$sum = 0;
				$cr = $cg = $cb = 0;
				for( $j = 0; $j < $kernelsize; $j++ )
				{
					$kernelx = $x + ( $j - ( $spread - 1 ) );
					if( ( $kernelx >= 0 ) && ( $kernelx < $width ) )
					{
						$ci = ( $kernelx * $height ) + $y;
						$cr += $mult[$j][($shadowarray[$ci] >> 16 ) & 0xFF];
						$cg += $mult[$j][($shadowarray[$ci] >> 8 ) & 0xFF];
						$cb += $mult[$j][$shadowarray[$ci] & 0xFF];
						$sum += $kernel[$j];
					}
				}
				$r = intval(round($cr/$sum));
				$g = intval(round($cg/$sum));
				$b = intval(round($cb/$sum));
		
				if( $r < 0 ) $r = 0;
				else if( $r > 255 ) $r = 255;
				if( $g < 0 ) $g = 0;
				else if( $g > 255 ) $g = 255;
				if( $b < 0 ) $b = 0;
				else if( $b > 255 ) $b = 255;
		
				$color = ( $r << 16 ) | ($g << 8 ) | $b;
		
				if( !isset( $colors[ $color ] ) ) {
					$colors[ $color ] = imagecolorallocate( $image, $r, $g, $b );
				}
		
				imagesetpixel( $image, $x, $y, $colors[$color] );
			}
		}
	}
}

/* To override the format for a particular transformation, specify the name of
   the settings to override followed by an '=' and the value to set it to. 
   Separate multiple settings with an ampersand (&) */
if( !function_exists( 'ImageHeadline_render' ) ) {	
	function ImageHeadline_render($text, $format_override='') {
		$current_settings = ImageHeadline_get_settings('ImageHeadline_settings');
		global $DebugImgHead;

		// Clear the cache if necessary.
		ImageHeadline_maybe_clear_cache( $current_settings['cache_folder'], isset($current_settings['image_lifetime']) ? $current_settings['image_lifetime'] : 14 );

		// Check for format overrides.
		if( $format_override != '' ) {
			$formats = explode( '&', $format_override );
			foreach( $formats as $format ) {
				$setting = explode( "=", $format, 2 );
				if( isset( $current_settings[ $setting[0] ] ) ) {
					$current_settings[ $setting[0] ] = $setting[1];
				}
			}
		}

		$mime_type = 'image/png' ;
		$extension = '.png' ;
		$send_buffer_size = 4096 ;
		
		$retVal = $text;

		// check for GD support
		if(get_magic_quotes_gpc())
			$text = stripslashes($text) ;

		$hashinput = basename($current_settings['font_file']) . 
			IMAGEHEADLINES_VERSION .
			$current_settings['font_size'] . 
			$current_settings['font_color'] . 
			$current_settings['background_color'] .
			$current_settings['left_padding'] . 
			$current_settings['max_width'] . 
			$current_settings['space_between_lines'] . 
			$current_settings['line_indent'] . 
			$current_settings['background_image'] . 
			ImageHeadline_option_set('transparent_background') . 
			ImageHeadline_option_set('shadows') . 
			ImageHeadline_option_set('split_lines') . 
			$current_settings['soft_shadows'] .
			$text;

		// look for cached copy, send if it exists
		if( ImageHeadline_option_set( 'shadows' ) )
		{
			if( !$current_settings['soft_shadows'] )
			{
				$hash = md5(
					$hashinput .
					$current_settings['shadow_first_color'] . 
					$current_settings['shadow_second_color'] . 
					$current_settings['shadow_offset']) ;
			}
			else
			{
				$hash = md5(
					$hashinput .
					$current_settings['shadow_color'] . 
					$current_settings['shadow_horizontal_offset'] . 
					$current_settings['shadow_vertical_offset'] . 
					$current_settings['shadow_spread']) ;
			}
		}
		else
		{
			$hash = md5($hashinput) ;
		}   
																	
		$cache_filename = $current_settings['cache_folder'] . '/' . $hash . $extension ;
		$generated_url = $current_settings['cache_url'] . '/' . $hash . $extension ;
		
		if( file_exists( $cache_filename ))
		{
			list($width, $height, $type, $attr) = getimagesize($cache_filename);
			$retVal = "<img src='$generated_url' alt='$text' width='$width' height='$height' />" ;
		} elseif( is_writable( $current_settings['cache_folder'] ) ){
			
			// check font availability
			$font_found = is_readable($current_settings['font_file']) ;
			if( !$font_found ) {
				if( $DebugImgHead ) echo('Error: The server is missing the specified font.') ;
				$retVal = $text;
			} else {
				
				// create image
				$background_rgb = ImageHeadline_hex_to_rgb($current_settings['background_color'], $DebugImgHead) ;
				$font_rgb = ImageHeadline_hex_to_rgb($current_settings['font_color'], $DebugImgHead) ;

				$box["width"] = 0;
				$box["height"] = 0;
				$current_y = -1;
				$max_y = -1;

				// Calculate how much additional space is needed for shadows.
				$vertical_shadow_spacing = $horizontal_shadow_spacing = 0;
				if( ImageHeadline_option_set( 'shadows' ) )
				{
					if( !$current_settings['soft_shadows'] )
					{
						$vertical_shadow_spacing = $horizontal_shadow_spacing = 2;
					}
					else
					{
						$vertical_shadow_spacing += $current_settings['shadow_vertical_offset'] + $current_settings['shadow_spread'];
						$horizontal_shadow_spacing += $current_settings['shadow_horizontal_offset'] + $current_settings['shadow_spread'];
					}
				}

				// Split the text into complete lines of no greater than max_width if we've been
				// told to split lines. Otherwise, work with the whole text regardless of line length.
				if( ImageHeadline_option_set( 'split_lines' ) ) {
					$text_array = ImageHeadline_break_text_into_lines( $text, $current_settings );
				} else {
					$text_array[] = $text;
				}
				
				// Now we need to calculate the overall dimensions of the resultant image. We have to 
				//take into account the number of lines, all formatting options (e.g. space between 
				// lines, indent) as well as use of shadows and spreads for shadows.
				foreach( $text_array as $line )
				{
					$bbox = @ImageTTFBBox($current_settings['font_size'],0,$current_settings['font_file'],$line);
					$width = $current_settings['left_padding'] + $horizontal_shadow_spacing + ( max($bbox[0],$bbox[2],$bbox[4],$bbox[6]) - min($bbox[0],$bbox[2],$bbox[4],$bbox[6]) ) + 2;
					$height = ( max($bbox[1],$bbox[3],$bbox[5],$bbox[7]) - min($bbox[1],$bbox[3],$bbox[5],$bbox[7]) ) + $vertical_shadow_spacing;

					// If this isn't the first line of multi-line text, we have to take into account
					// the space between each line vertically as well any line indent horizontally.
					if( $max_y > 0 )
					{
						$box["height"] += $current_settings['space_between_lines'];
						$width += $current_settings['line_indent'];
					}

					if( $height > $max_y )
					{
						$max_y = $height;
					}
					if( $current_y == -1 )
					{
						$current_y = abs(min($bbox[5], $bbox[7])) - 1;
					}

					// Increment height and latch width to the widest line.	
					if( $box["width"] < $width )
					{
						$box["width"] = $width;
					}
				}
				$box["height"] += count( $text_array ) * $max_y;

				// Creat the image and fill it with our background color.	
				if( ImageHeadline_option_set( 'only_use_imagecreate' ) ) {
					$image = @ImageCreate($box["width"],$box["height"]);
				} else {
					$image = @ImageCreateTrueColor($box["width"],$box["height"]);
				}
				$background_color = @ImageColorAllocate($image,$background_rgb['red'], $background_rgb['green'], $background_rgb['blue'] ) ;
				imagefill( $image, 0, 0, $background_color );
				
				if( !$image || !$box ) {
					if( $DebugImgHead ) echo('Error: The server could not create this heading image.') ;
					$retVal = $text;
				} else {
					// allocate colors and draw text
					$current_settings['font_color'] = @ImageColorAllocate($image,$font_rgb['red'], $font_rgb['green'], $font_rgb['blue']) ;

					// Blit the background image in there. This is always fun.
					if( ImageHeadline_option_set('use_background_image') )
					{
						if(!empty( $current_settings['background_image'] ) && is_readable( $current_settings['background_image'] ) ) {
							list($widthi, $heighti, $typei, $attri) = getImageSize($current_settings['background_image']);
							$backgroundimage = ImageCreateFromPNG( $current_settings['background_image'] ); //NOTE use png for this
							ImageColorTransparent($backgroundimage,$background_color);
							
							// merge the two together with alphablending on!
							ImageAlphaBlending($backgroundimage, true);
							
							ImageCopyMerge ( $image, $backgroundimage, 0, 0, 0, 0, $widthi, $heighti, 99);
						} else {
							if( $debug ) echo "Image not found: ".$current_settings['background_image']." is apparently missing.";
						}
					}
					
					$saved_y = $current_y;

					// Render the shadows depending on the selected method.
					if( ImageHeadline_option_set( 'shadows' ) )
					{
						if( !$current_settings['soft_shadows'] )
						{   
							// "Classic" method of text drawn in two different colors.
							$current_x = 0;
							$shadow_rgb = ImageHeadline_hex_to_rgb($current_settings['shadow_first_color'], $DebugImgHead);
							$shadow_1 = @ImageColorAllocate($image,$shadow_rgb['red'], $shadow_rgb['green'], $shadow_rgb['blue']);
							$shadow_rgb = ImageHeadline_hex_to_rgb($current_settings['shadow_second_color'], $DebugImgHead);
							$shadow_2 = @ImageColorAllocate($image,$shadow_rgb['red'], $shadow_rgb['green'], $shadow_rgb['blue']);
							foreach( $text_array as $line )
							{
								ImageTTFText($image, $current_settings['font_size'], 0, $current_x + $current_settings['left_padding'] + $current_settings['shadow_offset'] * 2, $current_y + $current_settings['shadow_offset'] * 2, $shadow_2, $current_settings['font_file'], $line) ;
								ImageTTFText($image, $current_settings['font_size'], 0, $current_x + $current_settings['left_padding'] + $current_settings['shadow_offset'], $current_y + $current_settings['shadow_offset'], $shadow_1, $current_settings['font_file'], $line) ;
								
								$current_y += $max_y + $current_settings['space_between_lines'];
								if( $current_x == 0 )
								{
									$current_x += $current_settings['line_indent'];
								}
							}
						}
						else /* soft shadows */
						{
							// Gaussian blurred "soft-shadow" method.
							if( ImageHeadline_option_set( 'only_use_imagecreate' ) ) {
								$shadow_image = @ImageCreate( $box['width'], $box['height']);
							} else {
								$shadow_image = @ImageCreateTrueColor( $box['width'], $box['height']);
							}
							imagefill( $shadow_image, 0, 0, $background_color );
	
							$shadow_rgb = ImageHeadline_hex_to_rgb($current_settings['shadow_color'], $DebugImgHead);
							$shadow_color = @ImageColorAllocate($shadow_image,$shadow_rgb['red'], $shadow_rgb['green'], $shadow_rgb['blue']);
							$current_x = 0;
							foreach( $text_array as $line )
							{
								ImageTTFText($shadow_image, $current_settings['font_size'], 0, $current_x + $current_settings['left_padding'] + $current_settings['shadow_horizontal_offset'], $current_y + $current_settings['shadow_vertical_offset'], $shadow_color, $current_settings['font_file'], $line) ;
	
								$current_y += $max_y + $current_settings['space_between_lines'];
								if( $current_x == 0 )
								{
									$current_x += $current_settings['line_indent'];
								}
							}
	
							ImageHeadline_gaussian( $shadow_image, $box['width'], $box['height'], $current_settings['shadow_spread'] );
							if(ImageHeadline_option_set('transparent_background'))
								ImageColorTransparent($shadow_image,$background_color) ;
	
							ImageAlphaBlending($shadow_image, true );
							ImageCopyMerge( $image, $shadow_image, 0,0,0,0, $box["width"], $box["height"],50);
	
							ImageDestroy($shadow_image);
						}
					}
					
					$current_x = 0;
					$current_y = $saved_y;
					foreach( $text_array as $line )
					{
						ImageTTFText($image, $current_settings['font_size'], 0, $current_x + $current_settings['left_padding'], $current_y, $current_settings['font_color'], $current_settings['font_file'], $line) ;
						
						$current_y += $max_y + $current_settings['space_between_lines'];
						if( $current_x == 0 )
						{
							$current_x += $current_settings['line_indent'];
						}
					}
					
					// set transparency
					if(ImageHeadline_option_set('transparent_background'))
						ImageColorTransparent($image,$background_color) ;
					
					@ImagePNG($image,$cache_filename) ;
					ImageDestroy($image) ;
					if( file_exists( $cache_filename ) )
					{
						$retVal = "<img src='$generated_url' alt='".addslashes($text)."' width='".$box['width']."' height='".$box['height']."' />" ;
					} else {
						if( $DebugImgHead ) echo( "Unknown Error creating file." );
						$retVal = $text;
					}
				}
			}
		} else {
			if( $DebugImgHead ) echo( "Error: The Folder [".dirname( $cache_filename )."] is not writeable." );
			$retVal = $text;
		}

		return $retVal;
	}	   
}

if( !function_exists( 'ImageHeadline_hex_to_rgb' ) ) {
	/*
		decode an HTML hex-code into an array of R,G, and B values.
		accepts these formats: (case insensitive) #ffffff, ffffff, #fff, fff
	*/
	function ImageHeadline_hex_to_rgb($hex, $DebugImgHead)
	{
		// remove '#'
		if(substr($hex,0,1) == '#')
			$hex = substr($hex,1) ;
		
		// expand short form ('fff') color
		if(strlen($hex) == 3)
		{
			$hex = substr($hex,0,1) . substr($hex,0,1) .
				substr($hex,1,1) . substr($hex,1,1) .
				substr($hex,2,1) . substr($hex,2,1) ;
		}
		
		if(strlen($hex) != 6 && $DebugImgHead ) {
			echo('Error: Invalid color "'.$hex.'"') ;
		}
		
		// convert
		$rgb['red'] = hexdec(substr($hex,0,2)) ;
		$rgb['green'] = hexdec(substr($hex,2,2)) ;
		$rgb['blue'] = hexdec(substr($hex,4,2)) ;
		
		return $rgb ;
	}   
}

if( !function_exists( 'ImageHeadline_break_text_into_lines' ) ) {
	/*
		Break the line into several lines if it exceeds the maximum allowed 
		width.
	*/	
	function ImageHeadline_break_text_into_lines( $text, $current_settings )
	{	   
		
		// the returned array of strings to be on separate lines.
		$text_array = array();
		
		// Figure out how big a space is. Yes, I'm being anal.
		$bbox = imagettfbbox($current_settings['font_size'],0, $current_settings['font_file'], ' ');
		$space_width = max($bbox[2],$bbox[4]) - min($bbox[0], $bbox[6]);
		
		// Split the array into word components.
		$words = explode( ' ', $text );
		$current_line = '';
		$current_width = $current_settings['left_padding'];
		foreach( $words as $word )
		{
			$bbox = imagettfbbox($current_settings['font_size'], 0, $current_settings['font_file'], $word );
			$word_width = max($bbox[2],$bbox[4]) - min($bbox[0], $bbox[6]);
	
			// See if the current word will fit on the line.
			if( $word_width + $current_width + $space_width > $current_settings['max_width'] )
			{
				// It won't. Check the border case where we have a friggin'
				// huge first word. If so, it'll have to be rendered on the
				// line regardless.
				if( $current_line != '' )
				{
					$text_array[] = $current_line;
					$current_width = $word_width + $current_settings['left_padding'] + $current_settings['line_indent'];
					$current_line = $word;
				}
				else
				{
					$text_array[] = $word;
					$current_width = $current_settings['left_padding'] + $current_settings['line_indent'];
					$current_line = '';
				}
	
				continue;
			}
	
			// Word fits, so append it.
			if( $current_line != '' )
			{
				$current_line .= ' ';
			}
	
			$current_line .= $word;
			$current_width += $word_width + $space_width;
		}
	
		if( $current_line != '' )
		{
			$text_array[] = $current_line;
		}
	
		return $text_array;
	}
}

if( !function_exists( 'imageheadlines' ) ) {
	/*
		Main workhorse function for actually replacing headlines with text.
	*/	 
	function imageheadlines($text) {
		$current_settings = ImageHeadline_get_settings('ImageHeadline_settings');
		global $DebugImgHead;

		// Check for XML feeds. Don't replace in feeds.
		// If you have $before set, and it doesn't match, we're done.
		if( strpos($text, $current_settings['before_text']) === FALSE ) {
			return $text;
		} else {
			if( !empty($current_settings['before_text']) ) {
				$text = substr( $text, strlen($current_settings['before_text']) );
			}   

			// If you have problems, set this to TRUE and see what pops up ;-)
			$DebugImgHead = true;

			if( ImageHeadline_option_set( 'disable_headlines' ) )
			{
				return $text;
			}
			else
			{
				// get/make an image for this text.
				return ImageHeadline_render( $text );
			}
		}   
	}
}

if( is_plugin_page() ) {
	global $user_level;

	// Set up the default settings.
	$default_settings = array(
		'font_file' => FONT_DIRECTORY."/warp1.ttf",
		'font_size' => 18,
		'font_color' => '#FF0000',
		'background_color' => '#FFF',
		'shadow_color' => '#000',
		'shadow_spread' => 3,
		'shadow_vertical_offset' => 2,
		'shadow_horizontal_offset' => 2,
		'left_padding' => 0,
		'max_width' => 450,
		'space_between_lines' => 5,
		'line_indent' => 10,
		'preview_text' => 'The quick brown fox jumped over the lazy dog.',
		'cache_url' => ( get_option('use_fileupload') ? get_settings('fileupload_url'):get_settings( 'siteurl' )."/wp-content/image-headlines" ),
		'cache_folder' => ( get_option('use_fileupload') ? get_settings('fileupload_realpath'):dirname(dirname(__FILE__))."/image-headlines" ),
		'soft_shadows' => 1,
		'shadow_first_color' => '#FFF',
		'shadow_second_color' => '#BBB',
		'shadow_offset' => 1,
		'background_image' => '',
		'before_text' => '-image-',
		'image_lifetime' => 14
		);

	$default_options = array(
		'shadows',
		'split_lines',
		'transparent_background',
	);

	global $ImageHeadline_options;

	if( isset( $_POST['update_options'] ) )
	{
		ImageHeadline_update_option('ImageHeadline_options',  $_POST['ImageHeadline_options']);

		$new_settings = array_merge(ImageHeadline_get_settings('ImageHeadline_settings'), $_POST['ImageHeadline_settings']);
		foreach($default_settings as $key => $val)
			if (!isset($new_settings[$key]))
				$new_settings[$key] = $val;

		// A bit of error checking.
		if( $new_settings['shadow_spread'] <= 0 )
		{
			$new_settings['shadow_spread'] = 1;
		}

		if( $new_settings['shadow_spread'] > 10 )
		{
			$new_settings['shadow_spread'] = 10;
		}

		$new_settings['preview_text'] = apply_filters('title_save_pre', $new_settings['preview_text']);

		ImageHeadline_update_option('ImageHeadline_settings', $new_settings);

		echo '<div class="updated"><p><strong>' . __('Options updated.', 'headlinedomain') . '</strong></p></div>';
		$ImageHeadline_options = ImageHeadline_get_settings('ImageHeadline_options');
		$ImageHeadline_settings = ImageHeadline_get_settings('ImageHeadline_settings');
		if(!is_array($ImageHeadline_settings)) 
			$ImageHeadline_settings = $default_settings;
		if(!is_array($ImageHeadline_options)) 
			$ImageHeadline_options = $default_options;
	}
	else
	{
		add_option('ImageHeadline_options', $default_options);
		add_option('ImageHeadline_settings', $default_settings);
		$ImageHeadline_options = ImageHeadline_get_settings('ImageHeadline_options');
		$ImageHeadline_settings = ImageHeadline_get_settings('ImageHeadline_settings');
		if(!is_array($ImageHeadline_settings)) 
			$ImageHeadline_settings = $default_settings;
		if(!is_array($ImageHeadline_options)) 
			$ImageHeadline_options = $default_options;
	}

	$ImageHeadline_settings = array_merge( $default_settings, $ImageHeadline_settings );
	ImageHeadline_update_option('ImageHeadline_settings', $ImageHeadline_settings);

	$edited_preview_text = format_to_edit($ImageHeadline_settings['preview_text']);
	$edited_preview_text = apply_filters('title_edit_pre', $edited_preview_text);
	$render_title = apply_filters('the_title', $ImageHeadline_settings['preview_text']);

	// Check for some errors.
	if( ( ImageHeadline_gd_version() < 2 ) || (!function_exists( 'ImageCreate' ) ) )
	{
		if( function_exists( 'ImageCreate' ) )
		{
			$ImageHeadline_options[] = 'only_use_imagecreate';
			if(ImageHeadline_option_set( 'disable_headlines' ) )
			{
				// Somebody must have installed what we need... enable us again.
				unset($ImageHeadline_options[array_search('disable_headlines',$ImageHeadline_options)]);
				$ImageHeadline_options = array_values( $ImageHeadline_options );
			}

			if( $ImageHeadline_settings['soft_shadows'] == 1 )
			{
				$ImageHeadline_settings['soft_shadows'] = 0;
				ImageHeadline_update_option('ImageHeadline_settings', $ImageHeadline_settings);
			}
		}
		else
		{
			$ImageHeadline_options[] = 'disable_headlines';
			echo '<div class="updated" style="background-color: #FF8080;border: 3px solid #F00;"><p><strong>' . __('FATAL: Your PHP installation does not support the ImageCreateTrueColor() or the ImageCreate() function. Unforunately, there is not much this plugin can do without that. Talk to your hosting administrator about upgrading to a version that supports image manipulation and try again with the plugin.', 'headlinedomain') . '</strong></p></div>';
		}
		ImageHeadline_update_option('ImageHeadline_options',  $ImageHeadline_options);
	}
	else
	{
		if( ImageHeadline_option_set( 'disable_headlines' ) )
		{   
			unset($ImageHeadline_options[array_search('disable_headlines',$ImageHeadline_options)]);
			$ImageHeadline_options = array_values( $ImageHeadline_options );
			ImageHeadline_update_option('ImageHeadline_options',  $ImageHeadline_options);
		}
		if(ImageHeadline_option_set( 'only_use_imagecreate' ) && function_exists( 'ImageCreateTrueColor' ) )
		{
			unset($ImageHeadline_options[array_search('only_use_imagecreate',$ImageHeadline_options)]);
			$ImageHeadline_options = array_values( $ImageHeadline_options );
			ImageHeadline_update_option('ImageHeadline_options',  $ImageHeadline_options);
		}
	}


	if( !ImageHeadline_option_set( 'disable_headlines' ) )
	{
		if( !file_exists( $ImageHeadline_settings['cache_folder'] ) ) {
			if( !@mkdir ( $ImageHeadline_settings['cache_folder'], 0755 ) ) {
				echo '<div class="updated" style="background-color: #FF8080;border: 3px solid #F00;"><p><strong>' . __('FATAL: The directory you specified to cache the image files did not exist and I could not create it. Either create it for me or select a different directory.', 'headlinedomain') . '</strong></p></div>';
			}
		}
		if( !is_writable( $ImageHeadline_settings['cache_folder'] ) )
		{
			echo '<div class="updated" style="background-color: #FF8080;border: 3px solid #F00;"><p><strong>' . __('FATAL: The directory you specified to cache the image files is not writeable from the Apache task. Either select a different directory or make the directory you specified writable by the Apache task (chmod 755 the directory).', 'headlinedomain') . '</strong></p></div>';
			$cache_folder_error = true;
		}
	
		$fonts = ImageHeadline_get_fonts();
		$allowed_types = explode(' ', trim(strtolower(get_settings('fileupload_allowedtypes'))));
	
		if ( ( get_option('use_fileupload') ) && $user_level >= get_settings('fileupload_minlevel') ) {
			if( in_array('ttf', $allowed_types) ) {
				$upload_text = __('. You may use the <a href="','headlinedomain').get_settings( 'siteurl' )."/wp-admin/upload.php\">".__('Upload</a> feature of WordPress to upload more fonts','headlinedomain');
			} else {
				$upload_text = __('. Upload more fonts using the Upload feature of WordPress, but first you must <a href="','headlinedomain').get_settings( 'siteurl' )."/wp-admin/options-misc.php\">".__('allow</a> TTF files to be uploaded','headlinedomain');
			}
		} else {
			$upload_text = '';
		}

?>

<div class="wrap">
	<h2><?php _e("Headline Image Options", 'headlinedomain') ?></h2>
<form name="headline_form" method="post">
	<fieldset class="options">
		<legend>
			<?php _e("Preview", 'headlinedomain')?>
		</legend>
			<?php _e('This is a preview of your current settings. Save your settings to update the preview.', 'headlinedomain' ) ?>
		<p>
			<?php echo imageheadlines( $ImageHeadline_settings['before_text'].$render_title ); ?>
		</p>
		<label for="preview_text">
			<?php _e('Preview text to display:', 'headlinedomain' ) ?>
		</label>
		<input type="text" name="ImageHeadline_settings[preview_text]" value="<?php echo stripslashes($edited_preview_text) ?>" size="70">
	</fieldset>
	<fieldset class="options">
		<legend>
				<?php _e("General Configuration", 'headlinedomain')?>
				
		</legend>
		<table width="100%" cellspacing="2" cellpadding="5" class="editform">
			<tr valign="top">
				<th width="45%" scope="row"><?php if( $cache_folder_error ) echo "<span style='color: #f00;'>"; _e('Image cache folder <em>(Where images will be cached on your server. MUST be a full path to a folder writeable by the Apache process.):', 'headlinedomain' ); if( $cache_folder_error ) echo "</span>" ?></th>
				<td><input type="text" name="ImageHeadline_settings[cache_folder]" value="<?php echo stripslashes($ImageHeadline_settings['cache_folder']) ?>" size="70" /></td>
			</tr>
			<tr valign="top">
				<th width="45%" scope="row"><?php _e('Image cache URL <em>(URL to the same folder as above.)</em>:', 'headlinedomain' ) ?></th>
				<td><input type="text" name="ImageHeadline_settings[cache_url]" value="<?php echo $ImageHeadline_settings['cache_url'] ?>" size="70" /></td>
			</tr>
			<tr valign="top">
				<th width="45%" scope="row"><?php _e('Image cache lifetime <em>(time in days that images will remain in cache)</em>:', 'headlinedomain' ) ?></th>
				<td><input type="text" name="ImageHeadline_settings[image_lifetime]" value="<?php echo $ImageHeadline_settings['image_lifetime'] ?>" size="4" /></td>
			</tr>
		</table>
	</fieldset>
	<fieldset class="options">
		<legend>
				<?php _e("Font and Colors", 'headlinedomain')?>
				
		</legend>
		<table width="100%" cellspacing="2" cellpadding="5" class="editform">
			<tr valign="center">
				<th width="45%" scope="row"><?php _e('Font <em>(must be a .ttf file', 'headlinedomain' ); echo $upload_text; _e('.)</em>:', 'headlinedomain' ) ?></th>
<?php if( count( $fonts ) > 0 ) { ?>
					<td><select name="ImageHeadline_settings[font_file]">
					<?php for( $x = 0; $x < count( $fonts ); $x++ ) { ?>
						<option value="<?php echo $fonts[$x]["font_file"];?>"<?php ImageHeadline_check_select('font_file',$ImageHeadline_settings,$fonts[$x]["font_file"]);?>><?php echo $fonts[$x]["font_name"];?></option>
					<?php } ?>
					</select></td>
<?php } else { ?>					
					<td>You have no fonts installed. They should be installed in the 'wp-content/image-headlines' directory of your WordPress installation or in your WordPress uploads directory. Only TrueType (TTF) fonts are allowed.</td>
<?php } ?>
			</tr>

			<tr valign="top">
				<th width="45%" scope="row"><?php _e('Font size <em>(in points)</em>:', 'headlinedomain' ) ?></th>
				<td><input type="text" name="ImageHeadline_settings[font_size]" value="<?php echo $ImageHeadline_settings['font_size'] ?>" size="4"></td>
			</tr>
			<tr valign="top">
				<th width="45%" scope="row"><?php _e('Font color <em>(in HTML format, e.g. #44CCAA)</em>:', 'headlinedomain' ) ?></th>
				<td><input type="text" name="ImageHeadline_settings[font_color]" value="<?php echo $ImageHeadline_settings['font_color'] ?>" size="8"></td>
			</tr>
			<tr valign="top">
				<th width="45%" scope="row"><?php _e('Background color <em>(in HTML format, e.g. #44CCAA)</em>:', 'headlinedomain' ) ?></th>
				<td><input type="text" name="ImageHeadline_settings[background_color]" value="<?php echo $ImageHeadline_settings['background_color'] ?>" size="8"></td>
			</tr>
			<tr valign="top">
				<th width="45%" scope="row"></th>
				<td><input name="ImageHeadline_options[]" type="checkbox" id="transparent_background" value="transparent_background" <?php ImageHeadline_check_flag('transparent_background', $ImageHeadline_options); ?> /><label for="transparent_background"><?php _e("Make background color transparent.", 'headlinedomain')?></label></td>
			</tr>
		</table>
		<fieldset class="options">
			<legend>
				<input name="ImageHeadline_options[]" type="checkbox" id="use_background_image" value="use_background_image" <?php ImageHeadline_check_flag('use_background_image', $ImageHeadline_options); ?> />
				<label for="use_background_image"><?php _e("Display a background image", 'headlinedomain')?></label>
			</legend>
			<table width="100%" cellspacing="2" cellpadding="5" class="editform">
				<tr valign="top">
					<th width="45%" scope="row"><?php _e('Background image <em>(in PNG format)</em>:', 'headlinedomain' ) ?></th>
					<td><input type="text" name="ImageHeadline_settings[background_image]" value="<?php echo $ImageHeadline_settings['background_image'] ?>" size="70"></td>
				</tr>
			</table>
		</fieldset>
	</fieldset>
	<fieldset class="options">
		<legend>
				<?php _e("Line Spacing", 'headlinedomain')?>
				
		</legend>
				<table width="100%" cellspacing="2" cellpadding="5" class="editform">
			<tr valign="top">
				<th width="45%" scope="row"><?php _e('Left padding between image edge and text start <em>(in pixels)</em>:', 'headlinedomain' ) ?></th>
				<td><input type="text" name="ImageHeadline_settings[left_padding]" value="<?php echo $ImageHeadline_settings['left_padding'] ?>" size="4"></td>
			</tr>
		</table>			
		<fieldset class="options">
			<legend>
				<input name="ImageHeadline_options[]" type="checkbox" id="split_lines" value="split_lines" <?php ImageHeadline_check_flag('split_lines', $ImageHeadline_options); ?> />
				<label for="split_lines"><?php _e("Split long lines", 'headlinedomain')?></label>
			</legend>
			<table width="100%" cellspacing="2" cellpadding="5" class="editform">
				<tr valign="top">
					<th width="45%" scope="row"><?php _e('Maximimum line length before line break <em>(in pixels)</em>:', 'headlinedomain' ) ?></th>
					<td><input type="text" name="ImageHeadline_settings[max_width]" value="<?php echo $ImageHeadline_settings['max_width'] ?>" size="4" /></td>
				</tr>
				<tr valign="top">
					<th width="45%" scope="row"><?php _e('Vertical space between lines <em>(in pixels)</em>:', 'headlinedomain' ) ?></th>
					<td><input type="text" name="ImageHeadline_settings[space_between_lines]" value="<?php echo $ImageHeadline_settings['space_between_lines'] ?>" size="4"></td>
				</tr>
				<tr valign="top">
					<th width="45%" scope="row"><?php _e('Line indent from left border <em>(in pixels)</em>:', 'headlinedomain' ) ?></th>
					<td><input type="text" name="ImageHeadline_settings[line_indent]" value="<?php echo $ImageHeadline_settings['line_indent'] ?>" size="4"></td>
				</tr>
			</table>
		</fieldset>
	</fieldset>
	<fieldset class="options">
		<legend>
			<input name="ImageHeadline_options[]" type="checkbox" id="shadows" value="shadows" <?php ImageHeadline_check_flag('shadows', $ImageHeadline_options); ?> />
			<label for="shadows"><?php _e("Enable shadow behind text", 'headlinedomain')?></label>
		</legend>
		<fieldset class="options">
			<legend>
				<input name="ImageHeadline_settings[soft_shadows]" <?php if( ImageHeadline_option_set( 'only_use_imagecreate' )) echo "disabled"?> type="radio" value="1" <?php ImageHeadline_check_radio('soft_shadows', $ImageHeadline_settings, 1); ?> />
<?php if( ImageHeadline_option_set( 'only_use_imagecreate' )) { ?>
				<label for="ImageHeadline_settings[soft_shadows]"><?php _e("Use soft-shadows <em>(Not available... requires GD version 2.0 or higher.)</em>", 'headlinedomain')?></label>
<?php } else { ?>
				<label for="ImageHeadline_settings[soft_shadows]"><?php _e("Use soft-shadows <em>(computationally expensive)</em>", 'headlinedomain')?></label>
<?php } ?>
			</legend>
			<table width="100%" cellspacing="2" cellpadding="5" class="editform">
				<tr valign="top">
					<th width="45%" scope="row"><?php _e('Shadow color <em>(in HTML format, e.g. #44CCAA)</em>:', 'headlinedomain' ) ?></th>
					<td><input type="text" <?php if( ImageHeadline_option_set( 'only_use_imagecreate' )) echo "disabled"?> name="ImageHeadline_settings[shadow_color]" value="<?php echo $ImageHeadline_settings['shadow_color'] ?>" size="8"></td>
				</tr>
				<tr valign="top">
					<th width="45%" scope="row"><?php _e('Shadow spread <em>(in pixels (1 - 10). The larger the spread the more diffuse the effect and slower to process)</em>:', 'headlinedomain' ) ?></th>
					<td><input type="text" <?php if( ImageHeadline_option_set( 'only_use_imagecreate' )) echo "disabled"?> name="ImageHeadline_settings[shadow_spread]" value="<?php echo $ImageHeadline_settings['shadow_spread'] ?>" size="2"></td>
				</tr>
				<tr valign="top">
					<th width="45%" scope="row"><?php _e('Vertical offset <em>(in pixels)</em>:', 'headlinedomain' ) ?></th>
					<td><input type="text" <?php if( ImageHeadline_option_set( 'only_use_imagecreate' )) echo "disabled"?> name="ImageHeadline_settings[shadow_vertical_offset]" value="<?php echo $ImageHeadline_settings['shadow_vertical_offset'] ?>" size="2"></td>
				</tr>
				<tr valign="top">
					<th width="45%" scope="row"><?php _e('Horizontal offset <em>(in pixels)</em>:', 'headlinedomain' ) ?></th>
					<td><input type="text" <?php if( ImageHeadline_option_set( 'only_use_imagecreate' )) echo "disabled"?> name="ImageHeadline_settings[shadow_horizontal_offset]" value="<?php echo $ImageHeadline_settings['shadow_horizontal_offset'] ?>" size="2"></td>
				</tr>
			</table>
		</fieldset>
		<fieldset class="options">
			<legend>
				<input name="ImageHeadline_settings[soft_shadows]" type="radio" value="0" <?php ImageHeadline_check_radio('soft_shadows', $ImageHeadline_settings, 0); ?> />
				<label for="ImageHeadline_settings[soft_shadows]"><?php _e("Use classic shadows <em>(simple and fast but not as pretty)</em>", 'headlinedomain')?></label>
			</legend>
			<table width="100%" cellspacing="2" cellpadding="5" class="editform">
				<tr valign="top">
					<th width="45%" scope="row"><?php _e('Shadow offset <em>(in pixels)</em>:', 'headlinedomain' ) ?></th>
					<td><input type="text" name="ImageHeadline_settings[shadow_offset]" value="<?php echo $ImageHeadline_settings['shadow_offset'] ?>" size="2"></td>
				</tr>
				<tr valign="top">
					<th width="45%" scope="row"><?php _e('First shadow color <em>(in HTML format, e.g. #44CCAA)</em>:', 'headlinedomain' ) ?></th>
					<td><input type="text" name="ImageHeadline_settings[shadow_first_color]" value="<?php echo $ImageHeadline_settings['shadow_first_color'] ?>" size="8"></td>
				</tr>
				<tr valign="top">
					<th width="45%" scope="row"><?php _e('Second shadow color <em>(in HTML format, e.g. #44CCAA)</em>:', 'headlinedomain' ) ?></th>
					<td><input type="text" name="ImageHeadline_settings[shadow_second_color]" value="<?php echo $ImageHeadline_settings['shadow_second_color'] ?>" size="8"></td>
				</tr>
			</table>
		</fieldset>
	</fieldset>
	<?php if( ImageHeadline_option_set( 'only_use_imagecreate' ) ) { ?>
	<input name="ImageHeadline_options[]" type="hidden" id="only_use_imagecreate" value="only_use_imagecreate" />
	<?php } ?>
	<p class="submit">
	<input type="submit" name="update_options" value="<?php _e('Update Options') ?>" />
	</p>
</form>
</div>
<?php
	}

} else {

	// Add a filter to the titles so all titles (that have the prepended text)
	// will be replaced with images.
	add_filter('the_title', 'imageheadlines', 12);
	add_action('admin_menu', 'ImageHeadline_add_options_page');
}
?>
