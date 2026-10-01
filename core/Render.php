<?php
namespace mara\core;
/*------------------------------------------------------------------------------
** File:        Render.class.php
** Class:       Render
** Description: Page rendering control
** Version:     3.12
** Updated:     2026-03-03
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------
** 
**-----------------------------------------------------------------------------*/

trait Render
{
/**
 * Oldal rajzolása
 * 
 * @param array $options
 * @return string
 */    
  public function show($options)
    {
      if (isset($options['header'])) {$header = $options['header'];} else {$header = '';} 
      if (isset($options['footer'])) {$footer = $options['footer'];} else {$footer = '';}
      if (isset($options['html']))   {$ishtml = $options['html'];} else {$ishtml = false;}
      if (isset($options['page']))   {$page   = $options['page'];} else {$page = '';}
      $html_header = '';
      $html_page   = '';
      $html_footer = '';
      $html        = '';
      if (isset($options['pagedata']))
          {
            if (isset($options['admin']))  {$options['pagedata']['admin'] = $options['admin'];} else {$options['pagedata']['admin'] = false;}
              if ($header != '')
                  {
                      $HTEMPLATE = new Template($header, $options['pagedata']);
                      $html_header = $HTEMPLATE->fetch();
                  }
              if ($page!= '')
                  {
                      $TEMPLATE = new Template($page, $options['pagedata']);
                      $html_page = $TEMPLATE->fetch();
                  }
              if ($footer != '')
                  {
                      if (isset($_SESSION['message'])) 
                          {
                              $options['pagedata']['message'] = $_SESSION['message'];
                              $_SESSION['message'] = '';
                          } else {$options['pagedata']['message'] = '';}
                      $FTEMPLATE = new Template($footer, $options['pagedata']);
                      $html_footer = $FTEMPLATE->fetch();
                  }
              $html = $html_header.$html_page.$html_footer;    
              if ($ishtml)
                  {
                      return $html;
                  } else
                  {
                      echo $html;
                  }                                                   

          } else {echo "No pagedata";}           
    }    
}
?>