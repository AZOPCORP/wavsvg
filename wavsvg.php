<?php

class Svgwav {




public function  makewav($mp3_name){





	 ini_set("max_execution_time", "30000");
  
  // how much detail we want. Larger number means less detail
  // (basically, how many bytes/frames to skip processing)
  // the lower the number means longer processing time
  define("DETAIL", 40);
  
  define("DEFAULT_WIDTH", 500);
  define("DEFAULT_HEIGHT", 100);
  define("DEFAULT_FOREGROUND", "#FF0000");
  define("DEFAULT_BACKGROUND", "#FFFFFF");
  
   $dir = 'assets/audio/';


   $mymp3 = $dir.$mp3_name;


  /**
   * GENERAL FUNCTIONS
   */
  function findValues($byte1, $byte2){
    $byte1 = hexdec(bin2hex($byte1));                        
    $byte2 = hexdec(bin2hex($byte2));                        
    return ($byte1 + ($byte2*256));
  }
  





  if (file_exists($mymp3)) {
  
    /**
     * PROCESS THE FILE
     */
  
    // temporary file name
    $tmpname = $dir.substr(md5(time()), 0, 10);
    
    // copy from temp upload directory to current
    copy($mymp3, "{$tmpname}_o.mp3");
    
		// support for stereo waveform?
   
   
		// array of wavs that need to be processed
    $wavs_to_process = array();
    
    /**
     * convert mp3 to wav using lame decoder
     * First, resample the original mp3 using as mono (-m m), 8 bit (-b 8), and 8 KHz (--resample 8)
     * Secondly, convert that resampled mp3 into a wav
     * We don't necessarily need high quality audio to produce a waveform, doing this process reduces the WAV
     * to it's simplest form and makes processing significantly faster
     */
    
 exec("lame {$tmpname}_o.mp3 -m m -S -f -b 8 --resample 8 {$tmpname}.mp3 && lame -S --decode {$tmpname}.mp3 {$tmpname}.wav");
      $wavs_to_process[] = "{$tmpname}.wav";
   
   
    
    
    // delete temporary files
    unlink("{$tmpname}_o.mp3");
    unlink("{$tmpname}.mp3");
    
    // Could just print to the output buffer, but saving to a variable
    // makes it easier to display the SVG and dump it to a file without
    // any messy ob_*() hassle
    $svg  = "<?xml version=\"1.0\"?>\n";
    $svg .= "<?xml-stylesheet href=\"../css/waveform.css\" type=\"text/css\"?>\n";
    $svg .= "<!DOCTYPE svg PUBLIC \"-//W3C//DTD SVG 1.1//EN\" \"//www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd\">\n";
    $svg .= "<svg width=\"100%\" height=\"100%\" version=\"1.1\" xmlns=\"//www.w3.org/2000/svg\" xmlns:xlink=\"//www.w3.org/1999/xlink\"   style=\"stroke:rgb(0,255,0);stroke-width: 3px;\">\n";
		// rect for background color
    $svg .= "<rect width=\"100%\" height=\"100%\" fill=\"rgb(0,0,0)\"/>\n";
    
    $y_offset = floor(1 / sizeof($wavs_to_process) * 100);
    
    // process each wav individually
    for($wav = 1; $wav <= sizeof($wavs_to_process); $wav++) {
    
      $svg .= "<svg y=\"" . ($y_offset * ($wav - 1)) . "%\" width=\"100%\" height=\"{$y_offset}%\">";
 
      $filename = $wavs_to_process[$wav - 1];
    
      /**
       * Below as posted by "zvoneM" on
       * //forums.devshed.com/php-development-5/reading-16-bit-wav-file-318740.html
       * as findValues() defined above
       * Translated from Croation to English - July 11, 2011
       */
      $handle = fopen($filename, "r");
      // wav file header retrieval
      $heading[] = fread($handle, 4);
      $heading[] = bin2hex(fread($handle, 4));
      $heading[] = fread($handle, 4);
      $heading[] = fread($handle, 4);
      $heading[] = bin2hex(fread($handle, 4));
      $heading[] = bin2hex(fread($handle, 2));
      $heading[] = bin2hex(fread($handle, 2));
      $heading[] = bin2hex(fread($handle, 4));
      $heading[] = bin2hex(fread($handle, 4));
      $heading[] = bin2hex(fread($handle, 2));
      $heading[] = bin2hex(fread($handle, 2));
      $heading[] = fread($handle, 4);
      $heading[] = bin2hex(fread($handle, 4));
      
      // wav bitrate 
      $peek = hexdec(substr($heading[10], 0, 2));
      $byte = $peek / 8;
      
      // checking whether a mono or stereo wav
      $channel = hexdec(substr($heading[6], 0, 2));
      
      $ratio = ($channel == 2 ? 40 : 80);
      
      // start putting together the initial canvas
      // $data_size = (size_of_file - header_bytes_read) / skipped_bytes + 1
      $data_size = floor((filesize($filename) - 44) / ($ratio + $byte) + 1);
      $data_point = 0;

      while(!feof($handle) && $data_point < $data_size){
        if ($data_point++ % DETAIL == 0) {
          $bytes = array();
          
          // get number of bytes depending on bitrate
          for ($i = 0; $i < $byte; $i++)
            $bytes[$i] = fgetc($handle);
          
          switch($byte){
            // get value for 8-bit wav
            case 1:
              $data = findValues($bytes[0], $bytes[1]);
              break;
            // get value for 16-bit wav
            case 2:
              if(ord($bytes[1]) & 128)
                $temp = 0;
              else
                $temp = 128;
              $temp = chr((ord($bytes[1]) & 127) + $temp);
              $data = floor(findValues($bytes[0], $temp) / 256);
              break;
          }
          
          // skip bytes for memory optimization
          fseek($handle, $ratio, SEEK_CUR);
          
          // draw this data point
          // data values can range between 0 and 255        
          $x1 = $x2 = number_format($data_point / $data_size * 100, 2);
          $y1 = number_format($data / 255 * 100, 2);
          $y2 = 100 - $y1;
          // don't bother plotting if it is a zero point
          if ($y1 != $y2)
            $svg .= "<line x1=\"{$x1}%\" y1=\"{$y1}%\" x2=\"{$x2}%\" y2=\"{$y2}%\" />";   
          
        } else {
          // skip this one due to lack of detail
          fseek($handle, $ratio + $byte, SEEK_CUR);
        }
      }
      
      $svg .= "</svg>\n";
      
      // close and cleanup
      fclose($handle);

      // delete the processed wav file
      unlink($filename);
      
    }
    
    $svg .= "\n</svg>";
    
    //header("Content-Type: image/svg+xml");
    $thedir = "assets/audio/";
    $thename = explode(".", $mp3_name);

    file_put_contents($thedir.$thename[0].'.svg', $svg);

    //header('location: assets/audio/wav.svg');


}else{

die('shit it didn\'t made the svg');

}

 


}



}
