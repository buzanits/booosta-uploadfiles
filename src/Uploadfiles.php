<?php
namespace booosta\uploadfiles;

use \booosta\Framework as b;
b::init_module('uploadfiles');

class Uploadfiles extends \booosta\base\Module
{
  use moduletrait_uploadfiles;

  protected $filename;        // name of file (seeme.jpg)
  protected $pathname;        // path of file (files/pics/)
  protected $pathfilename;    // full path to file (files/pics/seeme.jpg")
  protected $extension;       // extension of file (jpg)
  protected $rawname;         // filename without extension (seeme)
  protected $origname;        // original name
  protected $valid, $all_valid;           // is it a valid uploadfile

  public function __construct($postfilename, $path = 'upload/', $preservename = false)
  {
    #\booosta\debug($_FILES);
    parent::__construct();

    if(!is_array($_FILES[$postfilename]['name'])):
      $this->valid = false;
      return;
    endif;

    $this->valid = [];
    $this->all_valid = true;
    foreach($_FILES[$postfilename]['name'] as $key=>$name)
      if($name == '' && $_FILES[$postfilename]['error'][$key] != 0):
        $this->valid[$key] = false;
        $this->all_valid = false;
      endif;

    if($path == '') $path = 'upload/';
    if(substr($path, -1) != '/') $path .= '/';

    $postfiles = $_FILES[$postfilename];
    foreach($postfiles['tmp_name'] as $key=>$tmp_name):
      $this->valid[$key] = true;

      if($tmp_name == 'none' || $tmp_name == ''):
        $this->valid[$key] = false;
      else:
        $tmpfile = array_pop(explode('/', $tmp_name));
        #\booosta\debug("'pathfilename', $tmpfile . '.' . \$this->get_fileextension({$postfiles['name'][$key]}), $key");
        $this->set('pathfilename', $tmpfile . '.' . \booosta\file\File::get_fileextension($postfiles['name'][$key]), $key);
        if(move_uploaded_file($tmp_name, "$path{$this->filename[$key]}") == false) $this->valid[$key] = false;
    
        if($preservename === true) $newfile = $path . $postfiles['name'][$key];
        elseif(is_string($preservename)) $newfile = $path . $preservename;
        else $newfile = $path . uniqid('file_') . '.' . $this->extension[$key];
  
        $filename = $this->filename[$key];
        copy("$path$filename", $newfile);
        if(is_file("$path$filename")) unlink("$path$filename");
        $this->set('pathfilename', $newfile, $key);
        #\booosta\debug("\$this->set('pathfilename', $newfile, $key);");

        $this->origname[$key] = $postfiles['name'][$key];
      endif;
    endforeach;
    
    #\booosta\debug('pathfilename');
    #\booosta\debug($this->pathfilename);
  }

  public function is_valid($key = null) 
  { 
    if($key === null) return $this->all_valid; 
    return $this->valid[$key];
  }

  public function get_valid() { return $this->valid; }

  public function get_url($key = null) 
  { 
    if($key === null) return $this->pathfilename;
    return $this->pathfilename[$key];
  }

  public function get_filename($key = null) 
  { 
    if($key === null) return $this->filename;
    return $this->filename[$key];
  }

  public function get_origname($key = null) 
  { 
    if($key === null) return $this->origname;
    return $this->origname[$key];
  }

  public function get_extension($key = null) 
  { 
    if($key === null) return $this->extension; 
    return $this->extension[$key];
  }

  public function print_html($linktext = 'File', $key = null) { print $this->get_html($linktext, $key); }

  public function destroy($key = null) 
  { 
    if($key === null) foreach($this->pathfilename as $file) unlink($file); 
    else unlink($this->pathfilename[$key]);
  }

  public function check_extension($extensionmap = null, $command = '/usr/bin/file', $key = null) 
  {
    if($key === null) $files = $this->filename;
    else $files = [$key => $this->filename[$key]];

    $result = [];

    foreach($files as $fkey=>$file):
      $command .= ' ' . escapeshellarg($this->get_url($fkey));
      $result = exec($command);
      $result = explode(':', $result);
      $result = trim($result[1]);
    
      if($extensionmap === null)
        $extensionmap = [
          'pdf' => 'PDF document',
          'png' => 'PNG image data',
          'jpg' => 'JPEG image data',
          'jpeg' => 'JPEG image data',
          'gif' => 'GIF image data'
        ];
    
      $expected_string = $extensionmap[strtolower($this->extension[$fkey])];
      if(strstr($result, $expected_string)) $result[$fkey] = true;
      else $result[$fkey] = false;
    endforeach;

    return $result;
  }

  public function get_html($linktext = 'File', $key = null)
  {
    if($key === null):
      $result = '';
      foreach($this->filename as $fkey=>$file) $result .= "<a href='" . $this->get_url($fkey) . "'>$linktext $fkey</a>";
    else:
      $result = "<a href='" . $this->get_url($key) . "'>$linktext</a>";
    endif;

    return $result;
  }

  public function set($var, $val, $key) 
  { 
    $this->{$var}[$key] = $val; 

    switch($var):
      case 'filename':
        $this->pathfilename[$key] = $this->pathname[$key] . "/$val"; 
        $this->extension[$key] = \booosta\file\File::get_fileextension($val);
        $this->rawname[$key] = \booosta\file\File::get_rawname($val);
        break;
      case 'pathname':
        $this->pathfilename[$key] = $val . $this->filename[$key]; 
        break;
      case 'pathfilename':
        $tmp = explode('/', $val);
        $this->filename[$key] = array_pop($tmp);
        $this->pathname[$key] = implode('/', $tmp);
        $this->extension[$key] = \booosta\file\File::get_fileextension($this->filename[$key]);
        $this->rawname[$key] = \booosta\file\File::get_rawname($this->filename[$key]);
        break;
      case 'extension':
        $this->filename[$key] = $this->rawname[$key] . $val;
        $this->pathfilename[$key] = $this->pathname[$key] . $this->filename[$key];
        break;
      case 'rawname':
        $this->filename[$key] = $val . $this->extension[$key];
        $this->pathfilename[$key] = $this->pathname[$key] . $this->filename[$key];
        break;
    endswitch;

  } // function
} // class
