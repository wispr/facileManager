<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013 The facileManager Team                               |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | facileManager: Easy System Administration                               |
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
 | Processes objects management page                                       |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!isset($type)) header('Location: objects-host.php');
if (isset($_GET['type'])) header('Location: objects-' . sanitize(strtolower($_GET['type'])) . '.php');

if (!currentUserCan(array('manage_objects', 'view_all'), $_SESSION['module'])) unAuth();

/** Ensure we have a valid type */
if (!in_array($type, enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_type'))) header('Location: ' . $GLOBALS['basename']);

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_objects.php');
$response = isset($response) ? $response : null;

if (currentUserCan('manage_objects', $_SESSION['module'])) {
	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'add';
	switch ($action) {
	case 'add':
		if (!empty($_POST)) {
			$result = $fm_module_objects->add($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename'] . '?type=' . $_POST['object_type']);
		}
		break;
	case 'edit':
		if (!empty($_POST)) {
			$result = $fm_module_objects->update($_POST);
			if ($result !== true) {
				$response = $result;
				$form_data = $_POST;
			} else header('Location: ' . $GLOBALS['basename'] . '?type=' . $_POST['object_type']);
		}
		break;
	}
}

printHeader();
@printMenu();

//$allowed_to_add = ($type == 'custom' && currentUserCan('manage_objects', $_SESSION['module'])) ? true : false;
echo printPageHeader($response, null, currentUserCan('manage_objects', $_SESSION['module']), $type);

$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', 'object_name', 'object_', "AND object_type='$type'");
$fm_module_objects->rows($result, $type);

printFooter();

?>
