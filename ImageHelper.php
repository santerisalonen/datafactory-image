<?php


use \Imagine\Imagick\Imagine;
use \Imagine\Image\Palette\RGB;
use \Imagine\Image\Box;
use \Imagine\Image\Point;
use \Imagine\Image\ImageInterface;


class ImageHelper {
  
  public static function attachBadge($image_path, $badge) {
    
    $imagine = new Imagine();
    $rgb = new RGB();

    if( !isset($badge['font_file'] ) ) {
      $badge['font_file'] = 'tahoma.ttf'; 
    }
    
    if( !isset($badge['bg_alpha'] ) ) {
      $badge['bg_alpha'] = 100;
    }
    
    if( !isset($badge['text_alpha'] ) ) {
      $badge['text_alpha'] = 100;
    }
    
    $font_path = BASE_DIR . '/fonts/'. $badge['font_file'];
    
    if(isset($badge['background_image'])) {
      $box = $imagine->open( BASE_DIR . '/temp/'. $badge['background_image']);
      $curr_size = $box->getSize();
      if( $curr_size->getWidth() != $badge['width'] || $curr_size->getHeight() != $badge['height'] ) {
        $box->resize(new Box($badge['width'], $badge['height']));
      }

    }
    else {
      if(!isset($badge['background'])) {
        throw new \Exception('missing value for background', 400);
      }
      
        
      // create box
      $box = $imagine->create(new Box($badge['width'], $badge['height']), $rgb->color('#fff', 0)); 
        
      if($badge['shape'] == 'box') {
        // draw shadow
        
        /* no shadow for box 
        $coords = array( 
          new Point(0, 0), 
          new Point($badge['width'], 0), 
          new Point($badge['width'], $badge['height']), 
          new Point(0, $badge['height'])
        );
        $box->draw()->polygon(
          $coords, 
          $rgb->color($badge['background'], $badge['bg_alpha'])->darken(30), 
          $fill = true, 
          $border = 0); 
        */
        
        // draw box
        $coords = array( 
          new Point(0, 0), 
          new Point($badge['width'], 0), 
          new Point($badge['width'], $badge['height']), 
          new Point(0, $badge['height'])
        );
        $box->draw()->polygon(
          $coords, 
          $rgb->color($badge['background'], $badge['bg_alpha']), 
          $fill = true, 
          $border = 0); 
      
      }
      elseif($badge['shape'] == 'ellipse' ) {

        
        // draw shadow
        $box->draw()->ellipse(
          new Point($badge['width'] / 2, $badge['height'] / 2), 
          new Box($badge['width'] - 1, $badge['height'] - 1), 
          $rgb->color($badge['background'], $badge['bg_alpha'])->darken(30), 
          $fill = true, 
          $border = 0);
        
        // draw circle
        $box->draw()->ellipse(
          new Point( ( $badge['width'] / 2 ), ( $badge['height'] / 2 ) - 2 ), 
          new Box( $badge['width'] - 2, $badge['height'] - 3), 
          $rgb->color($badge['background'], $badge['bg_alpha']), 
          $fill = true, 
          $border = 0);
      }
    }
    // TEXT
    $badge['text_padding'] = (isset($badge['text_padding'])) ? $badge['text_padding'] : 0.2;
    

    $padding = round($badge['width'] * $badge['text_padding']);
   
    $font_size = 10;
    $font = $imagine->font($font_path, $font_size, $rgb->color($badge['text_color']));
    
    while(
      $font->box($badge['text'], 0)->getWidth() < $badge['width'] - $padding && 
      $font->box($badge['text'], 0)->getHeight() < $badge['height'] - $padding ) 
    {    
      $font = $imagine->font($font_path, $font_size, $rgb->color($badge['text_color'], $badge['text_alpha']));
      $font_size++;
    }      
    // ensure text is not out of bounds
    $text_height = min( $font->box($badge['text'], 0)->getHeight(), $badge['height']);
    $text_width = min( $font->box($badge['text'], 0)->getWidth(), $badge['width']);
    
    
    $box->draw()->text($badge['text'], $font, new Point( ($badge['width'] / 2) - ($text_width / 2), ($badge['height'] / 2) - ($text_height / 2)));
    
    if( isset($badge['angle']) && $badge['angle'] ) {
      // rotate and add transparent background
      $box->rotate($badge['angle'], $rgb->color('#fff', 0));
    }
    
    $image = $imagine->open($image_path);
    $image->paste($box, new Point($badge['x'], $badge['y']));
    $image->save();

  }
  public static function fitToSize($image_path, $width, $height, $fill_color = '#fff' ) {
    
    $imagine = new Imagine();
    $fit_size = new Box($width, $height);
    
    $image = $imagine->open($image_path);
    
    $size_org = $image->getSize();
    $width_org = $size_org->getWidth();
    $height_org = $size_org->getHeight();  

    $ratio = $width_org / $height_org;
    
    if(
      $ratio * 1.1 > ($width / $height) &&
      $ratio * 0.9 < ($width / $height ) ) 
    {
      // scale to fit
      $image->resize($fit_size);
      $image->save($image_path);
    }
    else {
      
      if( $width_org >= $width && $height_org >= $height && $ratio < 1.3 && $ratio > 0.7 ) {
        $mode = ImageInterface::THUMBNAIL_OUTBOUND;
        $image
          ->thumbnail($fit_size, $mode)
          ->save($image_path);
      }      
      else {
        // add whitespace (Fill oclor)

        $mode = ImageInterface::THUMBNAIL_INSET;  
        $resize_img = $image->thumbnail($fit_size, $mode);
        $size_r = $resize_img->getSize();
        $width_r = $size_r->getWidth();
        $height_r = $size_r->getHeight();
        $start_x = $start_y = 0;
        if ( $width_r < $width ) {
            $start_x = ( $width - $width_r ) / 2;
        }
        if ( $height_r < $height ) {
            $start_y = ( $height - $height_r ) / 2;
        }

        $preserve = $imagine->create($fit_size, (new RGB())->color($fill_color));
        $preserve->paste($resize_img, new Point($start_x, $start_y));
          
        $preserve->save($image_path);
      }
    }
    
    // echo 'saved ' . $image_path . PHP_EOL;
    
    
  }
  public static function validateImage($image_loc, $min_width = 10, $min_height = 10) {
    if(!$image_loc || empty($image_loc)) {
      throw new \Exception('No image', 400);
    }
    $img = getimagesize($image_loc);
    if(!$img) {
      throw new \Exception('Image not readable', 400);
    }
    if($img['mime'] !== 'image/jpeg' && $img['mime'] !== 'image/png' && $img['mime'] !== 'image/gif' ) {
      throw new \Exception('Image in ' . $img['mime'] . ' format', 400);
    }    
    if($img[0] < $min_width || $img[1] < $min_height ) {
      throw new \Exception('Image not at least ' . $min_width . 'x'.$min_height.' (provided ' . $img[3] . ')'  , 400);
    }
    return true;
  }
  
}