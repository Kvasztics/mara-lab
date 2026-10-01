<?php
namespace mara\core;
/*------------------------------------------------------------------------------
** File:        Template.class.php
** Class:       Template
** Description: Template manager class
** Version:     3.0
** Updated:     2019-09-16
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2019 SOOS TAMAS
**-----------------------------------------------------------------------------*/ 
class Template 
{
    public array $vars;                         
    public string $file;                       

public function __construct($_file = null, $_vars = null) 
	{
    $this->file = $_file;
    $this->vars = $_vars;
  }
/**
 * Assigning values ​​to template variables
 * @access public
 * @param  string $name
 * @param  string $value
 * @return void
 */
public function set($name, $value) 
	{
    $this->vars[$name] = is_object($value) ? $value->fetch() : $value;
  }
/**
 *Drawing a template
 * @access public
 * @param  string $_file
 * @return string
 */
public function fetch($_file = null) 
	{
    if(!$_file) $_file = $this->file;
    if(is_array($this->vars) && count($this->vars) > 0) 
    extract($this->vars, EXTR_PREFIX_SAME, "wddx");   
    ob_start();                                      
    include($_file);                                  
    $contents = ob_get_contents();                    
    ob_end_clean();                                   
    return $contents;                               
    }
/**
 * Render SVG icon
 * @param string $name
 * @return string
 */
public function icon(string $name): string
  {
      $file = DIR_ROOT . '/public/assets/icons/' . $name . '.svg';

      if (!is_file($file)) {
          return '';
      }

      return file_get_contents($file) ?: '';
  }    
}
?>