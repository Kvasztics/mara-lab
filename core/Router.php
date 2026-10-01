<?php
namespace mara\core;
/*------------------------------------------------------------------------------
** File:        Router.class.php
** Class:       Basic route controller class
** Description:  
** Version:     4.1
** Updated:     2026-07-02
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**-----------------------------------------------------------------------------*/

class Router 
{

/**
 * Route settings
 * @access public
 * @return void
 */
  public static function run() 
    {
      // 1. URL megszerzése és tisztítása
      $requestUri = $_SERVER['REQUEST_URI'];
      if (strpos($requestUri, '?') !== false) 
        {
          $requestUri = explode('?', $requestUri);
          $requestUri = $requestUri[0]; // Biztosítjuk, hogy csak az útvonal maradjon meg
        } 
      $cleanPath = trim($requestUri, '/');      
      // Kiszűrjük az üres elemeket az explode után
      $parts = array_values(array_filter(explode('/', trim($cleanPath, '/'))));
      // 2. OSZTÁLY MEGHATÁROZÁSA (Tűpontosan a $parts tömb 0. indexű eleme!)
      $controllerName = (!empty($parts[0])) ? $parts[0] : 'main';
      // 3. METÓDUS MEGHATÁROZÁSA (Alapértelmezett: 'view' - ha nincs 1. indexű elem)
      $methodName = 'view'; 
      if (isset($parts[1]) && !empty($parts[1])) 
        {
          $methodName = $parts[1];
        }
      // 4. PARAMÉTEREK (Vars) ÖSSZEGYŰJTÉSE
      // Ha a metódust manuálisan beírtad az URL-be, a 2. indextől kezdődnek a változók, ha kihagytad, akkor az 1. indextől
      if (isset($parts[1]) && $parts[1] === $methodName) 
        {
          $vars = array_slice($parts, 2);
        } else 
        {
          $vars = array_slice($parts, 1);
        }

        // 5. AUTOMATIKUS MEGHÍVÁS
        if (str_ends_with($controllerName, '_ajax'))
          {
            $controllerFile  = DIR_ROOT.'/core/ajax/'.$controllerName.'.php';
            $controllerClass = 'mara\core\ajax\\'.$controllerName;
          }
        else
          {
            $controllerFile  = DIR_ROOT.'/core/'.$controllerName.'.php';
            $controllerClass = 'mara\core\\'.$controllerName;
          }

        if (file_exists($controllerFile))
          {
            require_once $controllerFile;

            if (class_exists($controllerClass))
              {
                $controllerObject = new $controllerClass();

                if (method_exists($controllerObject, $methodName))
                  {
                    call_user_func([$controllerObject, $methodName], $vars);
                    return;
                  }
              }
          }

        // Ha a fájl vagy az osztály nem létezik
        header("HTTP/1.0 404 Not Found");
        echo "<h3>Mara hiba: A kért oldal nem található (404)</h3>";
        echo "Keresett fájl: " . $controllerFile . "<br>";
        echo "Keresett osztály: " . $controllerClass . "<br>";
        echo "Keresett metódus: " . $methodName . "()";
      }
}
?>