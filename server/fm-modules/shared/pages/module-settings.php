<?php

/**
 * Processes main page
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

$page_name = 'Settings';

printHeader();
@printMenu($page_name, $page_name_sub);

include(ABSPATH . 'fm-modules/shared/classes/class_settings.php');

echo <<<HTML
<div id="response" style="display: none;"></div>
<div id="body_container">
	<h2>{$_SESSION['module']} Settings</h2>

HTML;

echo $fm_module_settings->printForm();

echo '</div>' . "\n";

printFooter();

?>