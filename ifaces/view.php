<?php
include_once 'utils/logger.php';
abstract class View 
{
    public const MENU_GROUP_NONE = "none";
    
    abstract public function show ();

    public function isMulticol (){
        return false;
    }
    public function leftMenu (){
        return;
    }
    public function rightMenu (){
        return;
    }
    public function loadStyles (){
        return;
    }
    public function addJavascript (){
        return;
    }

    public function getMenuGroup (){
        return self::MENU_GROUP_NONE;
    }

    public function doInit (){
        return;
    }

    public function addHead (){
        return;
    }

}
function isView ($viewobject){
    if ($viewobject instanceof View){
        return true;
    }
    $name = get_class ($viewobject);
    logMessage (LOGGER_ERROR, "Class {$name} does not implement View");
    return false;
}
function showView ($view){
    if (isView ($view)){
		$view->loadStyles ();
        $name = get_class ($view);
		if ($view->isMulticol ()){
			logMessage (LOGGER_DEBUG, "Loading multicols for {$name}");
			echo ('<div class="row">');
			$view->leftMenu ();
			$view->show ();
			$view->rightMenu ();
			echo ('</div>');
		}
		else {
			logMessage (LOGGER_DEBUG, "Loading multicols for {$name}");
			$view->show ();
		}
	}
}

class MainView extends View {
    function show (){

    if (Config::$maincontent != "")
        echo (Config::$maincontent);
     
    else
        echo ("<h1>Este es el contenido de main</h1>\n");
    }
}

function showMain (){
    $main = new MainView;
    $main->show ();
}