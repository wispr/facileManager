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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
*/

class fm_module_servers {
	
	/**
	 * Displays the server list
	 */
	function rows($result, $type) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		$bulk_actions_list = null;
//		if (currentUserCan('manage_servers', $_SESSION['module'])) $bulk_actions_list = array('Enable', 'Disable', 'Delete', 'Upgrade');
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$bulk_actions_list[] = _('Upgrade');
		}
		if (currentUserCan('build_server_configs', $_SESSION['module'])) {
			$bulk_actions_list[] = _('Build Config');
		}
		if (is_array($bulk_actions_list)) {
			$title_array[] = array(
								'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'' . rtrim($type, 's') . '_list[]\')" />',
								'class' => 'header-tiny header-nosort'
							);
		}
		
		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="servers">%s</p>', sprintf(_('There are no %s.'), $type));
		} else {
			echo @buildBulkActionMenu($bulk_actions_list, 'server_id_list');
			
			$table_info = array(
							'class' => 'display_results sortable',
							'id' => 'table_edits',
							'name' => 'servers'
						);

			if ($type == 'servers') {
				$title_array[] = array('class' => 'header-tiny header-nosort');
				$title_array = array_merge($title_array, array(array('title' => _('Hostname'), 'rel' => 'server_name'),
					array('title' => _('Method'), 'rel' => 'server_update_method'),
					array('title' => _('Key'), 'class' => 'header-nosort'),
					array('title' => _('Server Type'), 'class' => 'header-nosort'),
					array('title' => _('Version'), 'class' => 'header-nosort'),
					array('title' => _('Run-as'), 'rel' => 'server_run_as_predefined'),
					array('title' => _('Config File'), 'rel' => 'server_config_file'),
					array('title' => _('Server Root'), 'rel' => 'server_root_dir'),
					array('title' => _('Zones Directory'), 'rel' => 'server_zones_dir'),
					));
			} elseif ($type == 'groups') {
				$title_array = array_merge((array)$title_array, array(array('title' => _('Group Name'), 'rel' => 'group_name'),
					array('title' => _('Master Servers'), 'class' => 'header-nosort'),
					array('title' => _('Slave Servers'), 'class' => 'header-nosort'),
					));
			}
			$title_array[] = array(
								'title' => _('Actions'),
								'class' => 'header-actions header-nosort'
							);

			echo displayTableHeader($table_info, $title_array);
			
			for ($x=0; $x<$num_rows; $x++) {
				$this->displayRow($results[$x], $type);
			}
			
			echo "</tbody>\n</table>\n";
		}
	}

	/**
	 * Adds the new server
	 */
	function addServer($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['server_name'])) return _('No server name defined.');
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_name');
		if ($field_length !== false && strlen($post['server_name']) > $field_length) return sprintf(ngettext('Server name is too long (maximum %d character).', 'Server name is too long (maximum %d characters).', 1), $field_length);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', $post['server_name'], 'server_', 'server_name');
		if ($fmdb->num_rows) return _('This server name already exists.');
		
		if (empty($post['server_root_dir'])) $post['server_root_dir'] = $__FM_CONFIG['ns']['named_root_dir'];
		if (empty($post['server_zones_dir'])) $post['server_zones_dir'] = $__FM_CONFIG['ns']['named_zones_dir'];
		if (empty($post['server_config_file'])) $post['server_config_file'] = $__FM_CONFIG['ns']['named_config_file'];
		
		$post['server_root_dir'] = rtrim($post['server_root_dir'], '/');
		$post['server_chroot_dir'] = rtrim($post['server_chroot_dir'], '/');
		$post['server_zones_dir'] = rtrim($post['server_zones_dir'], '/');
		
		/** Process server_run_as */
		$server_run_as_options = enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_run_as_predefined');
		if (!in_array($post['server_run_as_predefined'], $server_run_as_options)) {
			$post['server_run_as'] = $post['server_run_as_predefined'];
			$post['server_run_as_predefined'] = 'as defined:';
		}
		
		/** Set default ports */
		if (!isset($post['server_update_method'])) $post['server_update_method'] = 'http';
		if ($post['server_update_method'] == 'cron') {
			$post['server_update_port'] = 0;
		}
		if (!empty($post['server_update_port']) && !verifyNumber($post['server_update_port'], 1, 65535, false)) return _('Server update port must be a valid TCP port.');
		if (empty($post['server_update_port'])) {
			if ($post['server_update_method'] == 'http') $post['server_update_port'] = 80;
			elseif ($post['server_update_method'] == 'https') $post['server_update_port'] = 443;
			elseif ($post['server_update_method'] == 'ssh') $post['server_update_port'] = 22;
		}
		
		$module = ($post['module_name']) ? $post['module_name'] : $_SESSION['module'];

		/** Get a valid and unique serial number */
		$post['server_serial_no'] = (isset($post['server_serial_no'])) ? $post['server_serial_no'] : generateSerialNo($module);

		/** Process server_key */
		if (!isset($post['server_key']) || !is_numeric($post['server_key'])) $post['server_key'] = 0;

		$sql_insert = "REPLACE INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array('submit', 'action', 'server_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config', 'sub_type');

		foreach ($post as $key => $data) {
			$clean_data = sanitize($data);
			if (($key == 'server_name') && empty($clean_data)) return _('No server name defined.');
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ',';
				$sql_values .= "'$clean_data',";
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return _('Could not add the server because a database error occurred.');

		$tmp_key = $post['server_key'] ? getNameFromID($post['server_key'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_', 'key_id', 'key_name') : 'None';
		$tmp_runas = $post['server_run_as_predefined'] ? $post['server_run_as_predefined'] : $post['server_run_as'];
		addLogEntry("Added server:\nName: {$post['server_name']} ({$post['server_serial_no']})\nKey: {$tmp_key}\nType: {$post['server_type']}\n" .
				"Run-as: {$tmp_runas}\nUpdate Method: {$post['server_update_method']}\nConfig File: {$post['server_config_file']}\n" .
				"Server Root: {$post['server_root_dir']}\nServer Chroot: {$post['server_chroot_dir']}\n" .
				"Zone file directory: {$post['server_zones_dir']}");
		return true;
	}

	/**
	 * Adds the new server group
	 */
	function addGroup($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['group_name'])) return _('No group name defined.');
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'groups', 'group_name');
		if ($field_length !== false && strlen($post['group_name']) > $field_length) return sprintf(ngettext('Group name is too long (maximum %d character).', 'Group name is too long (maximum %d characters).', 1), $field_length);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'server_groups', $post['group_name'], 'group_', 'group_name');
		if ($fmdb->num_rows) return _('This group name already exists.');
		
		/** Process group masters */
		$log_message_master_servers = null;
		foreach ($post['group_masters'] as $val) {
			if ($val == 0) {
				$group_masters = 0;
				break;
			}
			$group_masters .= $val . ';';
			$server_name = getNameFromID($val, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
			$log_message_master_servers .= $val ? "$server_name; " : null;
		}
		$log_message_master_servers = rtrim ($log_message_master_servers, '; ');
		$post['group_masters'] = rtrim($group_masters, ';');
		if (!isset($post['group_masters'])) $post['group_masters'] = 0;

		/** Process group slaves */
		$log_message_slave_servers = null;
		foreach ($post['group_slaves'] as $val) {
			if ($val == 0) {
				$group_slaves = 0;
				break;
			}
			$group_slaves .= $val . ';';
			$server_name = getNameFromID($val, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
			$log_message_slave_servers .= $val ? "$server_name; " : null;
		}
		$log_message_slave_servers = rtrim ($log_message_slave_servers, '; ');
		$post['group_slaves'] = rtrim($group_slaves, ';');
		if (!isset($post['group_slaves'])) $post['group_slaves'] = 0;

		$sql_insert = "REPLACE INTO `fm_{$__FM_CONFIG['fmDNS']['prefix']}server_groups`";
		$sql_fields = '(';
		$sql_values = null;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array('submit', 'action', 'server_id', 'group_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config', 'sub_type');

		foreach ($post as $key => $data) {
			$clean_data = sanitize($data);
			if (($key == 'group_name') && empty($clean_data)) return _('No group name defined.');
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ',';
				$sql_values .= "'$clean_data',";
			}
		}
		$sql_fields = rtrim($sql_fields, ',') . ')';
		$sql_values = rtrim($sql_values, ',');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return _('Could not add the group because a database error occurred.');

		$tmp_key = $post['server_key'] ? getNameFromID($post['server_key'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_', 'key_id', 'key_name') : 'None';
		addLogEntry(_('Added server group') . ":\n" . _('Name') . ": {$post['group_name']}\n" .
				_('Masters') . ": {$log_message_master_servers}\n" .
				_('Slaves') . ": {$log_message_slave_servers}\n");
		return true;
	}

	/**
	 * Updates the selected server
	 */
	function updateServer($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['server_name'])) return _('No server name defined.');
		if (empty($post['server_root_dir'])) $post['server_root_dir'] = $__FM_CONFIG['ns']['named_root_dir'];
		if (empty($post['server_zones_dir'])) $post['server_zones_dir'] = $__FM_CONFIG['ns']['named_zones_dir'];
		if (empty($post['server_config_file'])) $post['server_config_file'] = $__FM_CONFIG['ns']['named_config_file'];
		if (empty($post['server_update_method'])) $post['server_update_method'] = 'cron';
		
		$post['server_root_dir'] = rtrim($post['server_root_dir'], '/');
		$post['server_chroot_dir'] = rtrim($post['server_chroot_dir'], '/');
		$post['server_zones_dir'] = rtrim($post['server_zones_dir'], '/');

		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_name');
		if ($field_length !== false && strlen($post['server_name']) > $field_length) return sprintf(ngettext('Server name is too long (maximum %d character).', 'Server name is too long (maximum %d characters).', 1), $field_length);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', sanitize($post['server_name']), 'server_', 'server_name', "AND server_id!='{$post['server_id']}'");
		if ($fmdb->num_rows) return _('This server name already exists.');
		
		/** Process server_key */
		if (!isset($post['server_key']) || !is_numeric($post['server_key'])) $post['server_key'] = 0;

		/** Set default ports */
		if ($post['server_update_method'] == 'cron') {
			$post['server_update_port'] = 0;
		}
		if (!empty($post['server_update_port']) && !verifyNumber($post['server_update_port'], 1, 65535, false)) return _('Server update port must be a valid TCP port.');
		if (empty($post['server_update_port'])) {
			if ($post['server_update_method'] == 'http') $post['server_update_port'] = 80;
			elseif ($post['server_update_method'] == 'https') $post['server_update_port'] = 443;
			elseif ($post['server_update_method'] == 'ssh') $post['server_update_port'] = 22;
		}
		
		$exclude = array('submit', 'action', 'server_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config', 'SERIALNO');
		
		$post['server_run_as'] = $post['server_run_as_predefined'] == 'as defined:' ? $post['server_run_as'] : null;
		if (!in_array($post['server_run_as_predefined'], enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_run_as_predefined'))) {
			$post['server_run_as'] = $post['server_run_as_predefined'];
			$post['server_run_as_predefined'] = 'as defined:';
		}

		$sql_edit = null;
		
		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "',";
			}
		}
		$sql = rtrim($sql_edit, ',');
		
		/** Update the server */
		$old_name = getNameFromID($post['server_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` SET $sql WHERE `server_id`={$post['server_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return _('Could not update the server because a database error occurred.');
		
		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		setBuildUpdateConfigFlag(getServerSerial($post['server_id'], $_SESSION['module']), 'yes', 'build');
		
		$tmp_key = $post['server_key'] ? getNameFromID($post['server_key'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_', 'key_id', 'key_name') : 'None';
		$tmp_runas = $post['server_run_as_predefined'] == 'as defined:' ? $post['server_run_as'] : $post['server_run_as_predefined'];
		addLogEntry("Updated server '$old_name' to:\nName: {$post['server_name']}\nKey: {$tmp_key}\nType: {$post['server_type']}\n" .
					"Run-as: {$tmp_runas}\nUpdate Method: {$post['server_update_method']}\nConfig File: {$post['server_config_file']}\n" .
					"Server Root: {$post['server_root_dir']}\nServer Chroot: {$post['server_chroot_dir']}\n" .
					"Zone file directory: {$post['server_zones_dir']}");
		return true;
	}
	
	/**
	 * Updates the selected server group
	 */
	function updateGroup($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['group_name'])) return _('No group name defined.');
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'groups', 'group_name');
		if ($field_length !== false && strlen($post['group_name']) > $field_length) return sprintf(ngettext('Group name is too long (maximum %d character).', 'Group name is too long (maximum %d characters).', 1), $field_length);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'server_groups', $post['group_name'], 'group_', 'group_name', "AND group_id!='{$post['server_id']}'");
		if ($fmdb->num_rows) return _('This group name already exists.');
		
		/** Process group masters */
		$log_message_master_servers = null;
		foreach ((array) $post['group_masters'] as $val) {
			if ($val == 0) {
				$group_masters = 0;
				break;
			}
			$group_masters .= $val . ';';
			$server_name = getNameFromID($val, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
			$log_message_master_servers .= $val ? "$server_name; " : null;
		}
		$log_message_master_servers = rtrim ($log_message_master_servers, '; ');
		$post['group_masters'] = rtrim($group_masters, ';');
		if (!isset($post['group_masters'])) $post['group_masters'] = 0;

		/** Process group slaves */
		$log_message_slave_servers = null;
		foreach ((array) $post['group_slaves'] as $val) {
			if ($val == 0) {
				$group_slaves = 0;
				break;
			}
			$group_slaves .= $val . ';';
			$server_name = getNameFromID($val, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
			$log_message_slave_servers .= $val ? "$server_name; " : null;
		}
		$log_message_slave_servers = rtrim ($log_message_slave_servers, '; ');
		$post['group_slaves'] = rtrim($group_slaves, ';');
		if (!isset($post['group_slaves'])) $post['group_slaves'] = 0;
		
		$post['account_id'] = $_SESSION['user']['account_id'];

		$sql_edit = null;
		$exclude = array('submit', 'action', 'server_id', 'group_id', 'compress', 'AUTHKEY', 'module_name', 'module_type', 'config', 'sub_type');

		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . sanitize($data) . "',";
			}
		}
		$sql = rtrim($sql_edit, ',');

		/** Update the server */
		$old_name = getNameFromID($post['server_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'server_groups', 'group_', 'group_id', 'group_name');
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}server_groups` SET $sql WHERE `group_id`={$post['server_id']} AND `account_id`='{$_SESSION['user']['account_id']}'";
		$result = $fmdb->query($query);
		
		if (!$fmdb->result) return _('Could not update the group because a database error occurred.');

		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

		addLogEntry(sprintf(_("Updated server group '%s' to"), $old_name) . ":\n" . _('Name') . ": {$post['group_name']}\n" .
				_('Masters') . ": {$log_message_master_servers}\n" .
				_('Slaves') . ": {$log_message_slave_servers}\n");
		
		return true;
	}
	
	/**
	 * Deletes the selected server/group
	 */
	function delete($server_id, $type) {
		global $fmdb, $__FM_CONFIG;
		
		/** Does the server_id exist for this account? */
		if ($type == 'servers') {
			$server_serial_no = getNameFromID($server_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_serial_no');
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', $server_serial_no, 'server_', 'server_serial_no');
			if ($fmdb->num_rows) {

				/** Update all associated domains */
				$query = "SELECT domain_id,domain_name_servers FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE (`domain_name_servers` LIKE '%;s_{$server_id};%' OR `domain_name_servers` LIKE '%;s_{$server_id}' OR `domain_name_servers` LIKE 's_{$server_id};%' OR `domain_name_servers`='s_{$server_id}') AND `account_id`='{$_SESSION['user']['account_id']}'";
				$fmdb->query($query);
				if ($fmdb->num_rows) {
					$result = $this->updateNameServerAssignments($fmdb->last_result, $fmdb->num_rows, 's_' . $server_id);
					if ($result !== true) {
						return $result;
					}
				}

				/** Delete associated config options */
				if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', $server_serial_no, 'cfg_', 'deleted', 'server_serial_no') === false) {
					return _('The associated server configs could not be deleted because a database error occurred.');
				}

				/** Delete associated records from fm_{$__FM_CONFIG['fmDNS']['prefix']}track_builds */
				if (basicDelete('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'track_builds', $server_serial_no, 'server_serial_no', false) === false) {
					return sprintf(_('The server could not be removed from the %s table because a database error occurred.'), 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'track_builds');
				}

				/** Delete server */
				$tmp_name = getNameFromID($server_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
				if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', $server_id, 'server_', 'deleted', 'server_id')) {
					addLogEntry(sprintf(_("Server '%s' (%s) was deleted"), $tmp_name, $server_serial_no));
					return true;
				}
			}

			return _('This server could not be deleted.');
		} else {
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'server_groups', $server_id, 'group_', 'group_id');
			if ($fmdb->num_rows) {

				/** Update all associated domains */
				$query = "SELECT domain_id,domain_name_servers FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE (`domain_name_servers` LIKE '%;g_{$server_id};%' OR `domain_name_servers` LIKE '%;g_{$server_id}' OR `domain_name_servers` LIKE 'g_{$server_id};%' OR `domain_name_servers`='g_{$server_id}') AND `account_id`='{$_SESSION['user']['account_id']}'";
				$fmdb->query($query);
				if ($fmdb->num_rows) {
					$result = $this->updateNameServerAssignments($fmdb->last_result, $fmdb->num_rows, 'g_' . $server_id);
					if ($result !== true) {
						return $result;
					}
				}

				/** Delete group */
				$tmp_name = getNameFromID($server_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'server_groups', 'group_', 'group_id', 'group_name');
				if (updateStatus('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'server_groups', $server_id, 'group_', 'deleted', 'group_id')) {
					addLogEntry(sprintf(_("Server group '%s' was deleted."), $tmp_name));
					return true;
				}
			}

			return _('This server group could not be deleted.');
		}

		return _('There is something wrong with your request.');
	}


	function displayRow($row, $type) {
		global $fmdb, $__FM_CONFIG;
		
		$class = ($row->server_status == 'disabled' || $row->group_status == 'disabled') ? 'disabled' : null;
		
		$edit_status = $edit_actions = null;
		
		if ($type == 'servers') {
			$os_image = setOSIcon($row->server_os_distro);

			$edit_actions = $row->server_status == 'active' ? '<a href="preview.php" onclick="javascript:void window.open(\'preview.php?server_serial_no=' . $row->server_serial_no . '\',\'1356124444538\',\'width=700,height=500,toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1,left=0,top=0\');return false;">' . $__FM_CONFIG['icons']['preview'] . '</a>' : null;

			$checkbox = (currentUserCan(array('manage_servers', 'build_server_configs'), $_SESSION['module'])) ? '<td><input type="checkbox" name="server_list[]" value="' . $row->server_serial_no .'" /></td>' : null;

			if (currentUserCan('build_server_configs', $_SESSION['module']) && $row->server_installed == 'yes') {
				if ($row->server_build_config == 'yes' && $row->server_status == 'active' && $row->server_installed == 'yes') {
					$edit_actions .= $__FM_CONFIG['icons']['build'];
					$class = 'build';
				}
			}
			if (currentUserCan('manage_servers', $_SESSION['module'])) {
				$edit_status = '<a class="edit_form_link" name="' . $type . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
				if ($row->server_installed == 'yes') {
					$edit_status .= '<a class="status_form_link" href="#" rel="';
					$edit_status .= ($row->server_status == 'active') ? 'disabled' : 'active';
					$edit_status .= '">';
					$edit_status .= ($row->server_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
					$edit_status .= '</a>';
				}
				$query = "SELECT group_id FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}server_groups WHERE account_id='{$_SESSION['user']['account_id']}' AND group_status!='deleted' AND 
					(group_masters='{$row->server_id}' OR group_masters LIKE '{$row->server_id};%' OR group_masters LIKE '%;{$row->server_id};%' OR group_masters LIKE '%;{$row->server_id}'
					OR group_slaves='{$row->server_id}' OR group_slaves LIKE '{$row->server_id};%' OR group_slaves LIKE '%;{$row->server_id};%' OR group_slaves LIKE '%;{$row->server_id}')";
				$result = $fmdb->get_results($query);
				if (!$fmdb->num_rows) {
					$edit_status .= '<a href="#" class="delete" name="' . $type . '">' . $__FM_CONFIG['icons']['delete'] . '</a>';
				}
			}
			if (isset($row->server_client_version) && version_compare($row->server_client_version, getOption('client_version', 0, $_SESSION['module']), '<')) {
				$edit_actions = _('Client Upgrade Available') . '<br />';
				$class = 'attention';
			}
			if ($row->server_installed != 'yes') {
				$edit_actions = _('Client Install Required') . '<br />';
			}
			$edit_status = $edit_actions . $edit_status;

			$edit_name = $row->server_name;
			$key = ($row->server_key) ? getNameFromID($row->server_key, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_', 'key_id', 'key_name') : 'none';
			$runas = ($row->server_run_as_predefined == 'as defined:') ? $row->server_run_as : $row->server_run_as_predefined;

			$port = ($row->server_update_method != 'cron') ? '(tcp/' . $row->server_update_port . ')' : null;

			if ($class) $class = 'class="' . $class . '"';

			echo <<<HTML
		<tr id="$row->server_id" $class>
			$checkbox
			<td>$os_image</td>
			<td title="$row->server_serial_no">$edit_name</td>
			<td>$row->server_update_method $port</td>
			<td>$key</td>
			<td>$row->server_type</td>
			<td>$row->server_version</td>
			<td>$runas</td>
			<td>$row->server_config_file</td>
			<td>$row->server_root_dir</td>
			<td>$row->server_zones_dir</td>
			<td id="edit_delete_img">$edit_status</td>
		</tr>

HTML;
		} elseif ($type == 'groups') {
			$checkbox = (currentUserCan(array('manage_servers', 'build_server_configs'), $_SESSION['module'])) ? '<td><input type="checkbox" name="group_list[]" value="' . $row->group_id .'" /></td>' : null;

			if (currentUserCan('manage_servers', $_SESSION['module'])) {
				$edit_status = '<a class="edit_form_link" name="' . $type . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
				$edit_status .= '<a class="status_form_link" href="#" rel="';
				$edit_status .= ($row->group_status == 'active') ? 'disabled' : 'active';
				$edit_status .= '">';
				$edit_status .= ($row->group_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
				$edit_status .= '</a>';
				$query = "SELECT domain_id FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}domains WHERE account_id='{$_SESSION['user']['account_id']}' AND domain_status!='deleted' AND 
					(domain_name_servers='g_{$row->group_id}' OR domain_name_servers LIKE 'g_{$row->group_id};%' OR domain_name_servers LIKE '%;g_{$row->group_id};%' OR domain_name_servers LIKE '%;g_{$row->group_id}')";
				$result = $fmdb->get_results($query);
				if (!$fmdb->num_rows) {
					$edit_status .= '<a href="#" class="delete" name="' . $type . '">' . $__FM_CONFIG['icons']['delete'] . '</a>';
				}
			}
			
			/** Process group masters */
			foreach (explode(';', $row->group_masters) as $server_id) {
				$masters[] = getNameFromID($server_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
			}
			
			$group_masters = implode('; ', array_map(function($value) {
				return $value == null ? sprintf('<i>%s</i>', _('missing')) : $value;
			}, $masters));
			if (empty($group_masters) || !count($masters) || (count($masters) == 1 && empty($masters[0]))) $group_masters = _('None');
			
			/** Process group slaves */
			foreach (explode(';', $row->group_slaves) as $server_id) {
				$slaves[] = getNameFromID($server_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_id', 'server_name');
			}
			$group_slaves = implode('; ', $slaves);
			if (empty($group_slaves)) $group_slaves = _('None');
			
			if ($class) $class = 'class="' . $class . '"';
			
			$group_masters = wordwrap($group_masters);
			$group_slaves = wordwrap($group_slaves);

			echo <<<HTML
		<tr id="$row->group_id" $class>
			$checkbox
			<td>$row->group_name</td>
			<td>$group_masters</td>
			<td>$group_slaves</td>
			<td id="edit_delete_img">$edit_status</td>
		</tr>

HTML;
		}
	}

	/**
	 * Displays the form to add new server
	 */
	function printForm($data = '', $action = 'add', $type = 'servers') {
		global $__FM_CONFIG;
		
		$server_id = $group_id = 0;
		$server_name = $server_root_dir = $server_zones_dir = $runas = $server_type = $server_update_port = null;
		$server_update_method = $server_key = $server_run_as = $server_config_file = $server_run_as_predefined = null;
		$server_chroot_dir = $group_name = null;
		$ucaction = ucfirst($action);
		$uctype = ucfirst($type);
		$server_installed = false;
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		if ($type == 'groups') $server_id = $group_id;

		$popup_header = buildPopup('header', $ucaction . ' ' . $uctype);
		$popup_footer = buildPopup('footer');
		
		$return_form = <<<FORM
			<form name="manage" id="manage" method="post" action="?type=$type">
			$popup_header
				<input type="hidden" name="action" value="$action" />
				<input type="hidden" name="server_id" value="$server_id" />
				<input type="hidden" name="sub_type" value="$type" />
FORM;

		if ($type == 'servers') {
			/** Show/hide divs */
			if (isset($server_run_as_predefined) && $server_run_as_predefined == 'as defined:') {
				$runashow = 'block';
			} else {
				$runashow = 'none';
				$server_run_as = null;
			}
			$server_update_port_style = ($server_update_method == 'cron') ? 'style="display: none;"' : 'style="display: block;"';

			$disabled = ($server_installed == 'yes') ? 'disabled' : null;

			if ($server_installed == 'yes') {
				if (strpos($server_update_method, 'http') === false) {
					$server_update_method_choices = array($server_update_method);
				} else {
					$server_update_method_choices = array('http', 'https');
				}
			} else {
				$server_update_method_choices = enumMYSQLSelect('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_update_method');
			}

			/** Check name field length */
			$server_name_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_name');
			$server_config_file_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_config_file');
			$server_root_dir_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_root_dir');
			$server_chroot_dir_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_chroot_dir');
			$server_zones_dir_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_zones_dir');

			$server_type = buildSelect('server_type', 'server_type', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_type'), $server_type, 1);
			$server_update_method = buildSelect('server_update_method', 'server_update_method', $server_update_method_choices, $server_update_method, 1);
			$server_key = buildSelect('server_key', 'server_key', $this->availableItems('key'), $server_key);
			$server_run_as_predefined = buildSelect('server_run_as_predefined', 'server_run_as_predefined', enumMYSQLSelect('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_run_as_predefined'), $server_run_as_predefined, 1, '', false, "showHideBox('run_as', 'server_run_as_predefined', 'as defined:')");

			$alternative_help = ($action == 'add' && getOption('client_auto_register')) ? '<p><b>Note:</b> The client installer can automatically generate this entry.</p>' : null;

			$return_form .= <<<FORM
			$alternative_help
			<table class="form-table">
				<tr>
					<th width="33%" scope="row"><label for="server_name">Server Name</label></th>
					<td width="67%"><input name="server_name" id="server_name" type="text" value="$server_name" size="40" placeholder="dns1.local" maxlength="$server_name_length" /></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="server_key">Key</label></th>
					<td width="67%">$server_key</td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="server_type">Server Type</label></th>
					<td width="67%">$server_type</td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="server_run_as_predefined">Run-as Account</label></th>
					<td width="67%">$server_run_as_predefined
					<div id="run_as" style="display: $runashow"><input name="server_run_as" id="server_run_as" type="text" placeholder="Other run-as account" value="$server_run_as" /></div></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="server_update_method">Update Method</label></th>
					<td width="67%">$server_update_method<div id="server_update_port_option" $server_update_port_style><input type="number" name="server_update_port" value="$server_update_port" placeholder="80" onkeydown="return validateNumber(event)" maxlength="5" max="65535" /></div></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="server_config_file">Config File</label></th>
					<td width="67%"><input name="server_config_file" id="server_config_file" type="text" value="$server_config_file" size="40" placeholder="{$__FM_CONFIG['ns']['named_config_file']}" maxlength="$server_config_file_length" /></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="server_root_dir">Server Root</label></th>
					<td width="67%"><input name="server_root_dir" id="server_root_dir" type="text" value="$server_root_dir" size="40" placeholder="{$__FM_CONFIG['ns']['named_root_dir']}" maxlength="$server_root_dir_length" /></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="server_chroot_dir">Server Chroot</label></th>
					<td width="67%"><input name="server_chroot_dir" id="server_chroot_dir" type="text" value="$server_chroot_dir" size="40" placeholder="{$__FM_CONFIG['ns']['named_chroot_dir']}" maxlength="$server_chroot_dir_length" /></td>
				</tr>
				<tr>
					<th width="33%" scope="row"><label for="server_zones_dir">Zone File Directory</label></th>
					<td width="67%"><input name="server_zones_dir" id="server_zones_dir" type="text" value="$server_zones_dir" size="40" placeholder="{$__FM_CONFIG['ns']['named_zones_dir']}" maxlength="$server_zones_dir_length" /></td>
				</tr>
			</table>
		$popup_footer
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					minimumResultsForSearch: 10,
					allowClear: true
				});
			});
		</script>
FORM;
		} elseif ($type == 'groups') {
			$group_masters = (isset($group_masters)) ? explode(';', $group_masters) : null;
			$group_slaves  = (isset($group_slaves)) ? explode(';', $group_slaves) : null;
			
			$group_name_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'server_groups', 'group_name');
			$group_masters = buildSelect('group_masters', 'group_masters', $this->availableItems('server'), $group_masters, 1, null, true, null, null, _('Select master servers'));
			$group_slaves = buildSelect('group_slaves', 'group_slaves', $this->availableItems('server'), $group_slaves, 1, null, true, null, null, _('Select slave servers'));

			$return_form .= sprintf('
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="group_name">%s</label></th>
					<td width="67&#37;"><input name="group_name" id="group_name" type="text" value="%s" size="40" maxlength="%d" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="group_masters">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="group_slaves">%s</label></th>
					<td width="67&#37;">%s</td>
				</tr>
			</table>
		%s
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					minimumResultsForSearch: 10,
					allowClear: true,
					width: "230px"
				});
			});
		</script>', _('Group Name'), $group_name, $group_name_length, _('Master Servers'), $group_masters, _('Slave Servers'), $group_slaves, $popup_footer);
		} else {
			$return_form = buildPopup('header', _('Error'));
			$return_form .= sprintf('<h3>%s</h3><p>%s</p>', _('Oops!'), _('Invalid request.'));
			$return_form .= buildPopup('footer', _('OK'), array('cancel'));
		}

		return $return_form;
	}
	
	function availableItems($type, $default = 'blank') {
		global $fmdb, $__FM_CONFIG;
		
		$return = null;
		
		$j = 0;
		if ($default == 'blank') {
			$return[$j][] = '';
			$return[$j][] = '';
			$j++;
		}
		
		$query = "SELECT {$type}_id,{$type}_name FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}{$type}s WHERE account_id='{$_SESSION['user']['account_id']}' AND {$type}_status='active' ORDER BY {$type}_name ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$type_name = $type . '_name';
				$type_id   = $type . '_id';
				$return[$j][] = $results[$i]->$type_name;
				$return[$j][] = $results[$i]->$type_id;
				$j++;
			}
		}
		
		return $return;
	}

	function buildServerConfig($serial_no, $action = 'buildconf', $friendly_action = 'Configuration Build') {
		global $fmdb, $__FM_CONFIG, $fm_name;
		
		/** Check serial number */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', sanitize($serial_no), 'server_', 'server_serial_no');
		if (!$fmdb->num_rows) return sprintf('<p class="error">%s</p>', _('This server is not found.'));

		$server_details = $fmdb->last_result;
		extract(get_object_vars($server_details[0]), EXTR_SKIP);
		$options[] = $response = null;
		
		$popup_footer = buildPopup('footer', 'OK', array('cancel_button' => 'cancel'));
		
		if ($action == 'buildconf') {
			if (getOption('enable_named_checks', $_SESSION['user']['account_id'], 'fmDNS') == 'yes') {
				global $fm_module_buildconf;
				include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_buildconf.php');

				$data['SERIALNO'] = $server_serial_no;
				$data['compress'] = 0;
				$data['dryrun'] = true;

				basicGet('fm_accounts', $_SESSION['user']['account_id'], 'account_', 'account_id');
				$account_result = $fmdb->last_result;
				$data['AUTHKEY'] = $account_result[0]->account_key;

				list($raw_data, $response) = $fm_module_buildconf->buildServerConfig($data);

				$response .= @$fm_module_buildconf->namedSyntaxChecks($raw_data);
				if (strpos($response, 'error') !== false) return buildPopup('header', $friendly_action . ' Results') . $response . $popup_footer;
			}

			if (getOption('purge_config_files', $_SESSION['user']['account_id'], 'fmDNS') == 'yes') {
				$options[] = 'purge';
			}
		}
		
		switch($server_update_method) {
			case 'cron':
				if ($action == 'buildconf') {
					/* set the server_update_config flag */
					setBuildUpdateConfigFlag($serial_no, 'conf', 'update');
					$response = sprintf('<p>%s</p>'. "\n", _('This server will be updated on the next cron run.'));
				} else {
					$response = sprintf('<p>%s</p>'. "\n", _('This server receives updates via cron - please manage the server manually.'));
				}
				break;
			case 'http':
			case 'https':
				/** Test the port first */
				if (!socketTest($server_name, $server_update_port, 10)) {
					return sprintf('<p class="error">%s</p>'. "\n", sprintf(_('Failed: could not access %s using %s (tcp/%d).'), $server_name, $server_update_method, $server_update_port));
				}
				
				/** Remote URL to use */
				$url = $server_update_method . '://' . $server_name . ':' . $server_update_port . '/' . $_SESSION['module'] . '/reload.php';
				
				/** Data to post to $url */
				$post_data = array('action'=>$action, 'serial_no'=>$server_serial_no, 'options'=>implode(' ', $options));
				
				$post_result = @unserialize(getPostData($url, $post_data));
				
				if (!is_array($post_result)) {
					/** Something went wrong */
					if (empty($post_result)) {
						return sprintf('<p class="error">%s</p>', sprintf(_('It appears %s does not have php configured properly within httpd or httpd is not running.'), $server_name));
					}
					return '<p class="error">' . $post_result . '</p>';
				} else {
					if (count($post_result) > 1) {
						$response .= "<pre>\n";
						
						/** Loop through and format the output */
						foreach ($post_result as $line) {
							$response .= "[$server_name] $line\n";
						}
						
						$response .= "</pre>\n";
					} else {
						$response = "<p>[$server_name] " . $post_result[0] . '</p>';
					}
				}
				break;
			case 'ssh':
				/** Test the port first */
				if (!socketTest($server_name, $server_update_port, 10)) {
					return sprintf('<p class="error">%s</p>'. "\n", sprintf(_('Failed: could not access %s using %s (tcp/%d).'), $server_name, $server_update_method, $server_update_port));
				}
				
				/** Get SSH key */
				$ssh_key = getOption('ssh_key_priv', $_SESSION['user']['account_id']);
				if (!$ssh_key) {
					return sprintf('<p class="error">%s</p>'. "\n", sprintf(_('Failed: SSH key is not <a href="%s">defined</a>.'), getMenuURL('General')));
				}
				
				$temp_ssh_key = sys_get_temp_dir() . '/fm_id_rsa';
				if (file_exists($temp_ssh_key)) @unlink($temp_ssh_key);
				if (@file_put_contents($temp_ssh_key, $ssh_key) === false) {
					return sprintf('<p class="error">%s</p>'. "\n", sprintf(_('Failed: could not load SSH key into %s.'), $temp_ssh_key));
				}
				
				@chmod($temp_ssh_key, 0400);
				
				$ssh_user = getOption('ssh_user');
				if (!$ssh_user) {
					return sprintf('<p class="error">%s</p>'. "\n", sprintf(_('Failed: SSH user is not <a href="%s">defined</a>.'), getMenuURL('General')));
				}
		
				/** Test SSH authentication */
				exec(findProgram('ssh') . " -t -i $temp_ssh_key -o 'StrictHostKeyChecking no' -p $server_update_port -l $ssh_user $server_name 'ls /usr/local/$fm_name/{$_SESSION['module']}/dns.php'", $post_result, $retval);
				if ($retval) {
					/** Something went wrong */
					@unlink($temp_ssh_key);
					return sprintf('<p class="error">%s</p>'. "\n", _('Could not login via SSH.'));
				}
				unset($post_result);
				
				/** Run build */
				exec(findProgram('ssh') . " -t -i $temp_ssh_key -o 'StrictHostKeyChecking no' -p $server_update_port -l $ssh_user $server_name 'sudo php /usr/local/$fm_name/{$_SESSION['module']}/dns.php $action " . implode(' ', $options) . "'", $post_result, $retval);
				
				@unlink($temp_ssh_key);
				
				if ($retval) {
					/** Something went wrong */
					return '<p class="error">' . ucfirst($friendly_action) . ' failed.</p>'. "\n";
				}
				
				if (!count($post_result)) $post_result[] = ucfirst($friendly_action) . ' was successful.';

				if (count($post_result) > 1) {
					$response = "<pre>\n";

					/** Loop through and format the output */
					foreach ($post_result as $line) {
						$response .= "[$server_name] $line\n";
					}

					$response .= "</pre>\n";
				} else {
					$response = "<p>[$server_name] " . $post_result[0] . '</p>';
				}

				break;
		}
		
		if ($action == 'buildconf') {
			/* reset the server_build_config flag */
			if (!strpos($response, strtolower('failed'))) {
				setBuildUpdateConfigFlag($serial_no, 'no', 'build');
			}
		}

		$tmp_name = getNameFromID($serial_no, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'server_', 'server_serial_no', 'server_name');
		addLogEntry(ucfirst($friendly_action) . " was performed on server '$tmp_name'.");

		if (strpos($response, '<pre>') !== false) {
			$response = buildPopup('header', $friendly_action . ' Results') . $response . $popup_footer;
		}
		return $response;
	}
	
	function manageCache($server_id, $action) {
		global $fmdb, $__FM_CONFIG;
		
		/** Check serial number */
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', sanitize($server_id), 'server_', 'server_id');
		if (!$fmdb->num_rows) return _('This server is not found.');

		$server_details = $fmdb->last_result;
		extract(get_object_vars($server_details[0]), EXTR_SKIP);
		$response[] = $server_name;
		
		if ($server_installed != 'yes') {
			$response[] = ' --> ' . _('Failed: Client is not installed.');
		}
		
		if (count($response) == 1 && $server_status != 'active') {
			$response[] = ' --> ' . sprintf(_('Failed: Server is %s.'), $server_status);
		}
		
		if (count($response) == 1) {
			foreach (makePlainText($this->buildServerConfig($server_serial_no, $action, ucfirst(str_replace('-', ' ', $action))), true) as $line) {
				$response[] = ' --> ' . $line;
			}
		}
		
		return implode("\n", $response);
	}
	
	function availableServers($default = 'blank') {
		global $fmdb, $__FM_CONFIG;
		
		$return = null;
		
		$j = 0;
		if ($default == 'blank') {
			$return[$j][] = '';
			$return[$j][] = '';
			$j++;
		}
		
		$query = "SELECT server_id,server_name FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}servers WHERE account_id='{$_SESSION['user']['account_id']}' AND server_status='active' ORDER BY server_name ASC";
		$result = $fmdb->get_results($query);
		if ($fmdb->num_rows) {
			$results = $fmdb->last_result;
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$return[$j][] = $results[$i]->server_name;
				$return[$j][] = $results[$i]->server_id;
				$j++;
			}
		}
		
		return $return;
	}

	/**
	 * Updates the name server assignments
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $result Database results
	 * @param integer $count Number of db results
	 * @param integer $server_id Server ID to remove from assignments
	 * @return boolean
	 */
	function updateNameServerAssignments($result, $count, $server_id) {
		global $fmdb, $__FM_CONFIG;
		
		for ($i=0; $i < $count; $i++) {
			$serverids = null;
			foreach (explode(';', $result[$i]->domain_name_servers) as $server) {
				if ($server == $server_id) continue;
				$serverids .= $server . ';';
			}
			$serverids = rtrim($serverids, ';');

			/** Set new domain_name_servers list */
			$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET `domain_name_servers`='$serverids' WHERE `domain_id`={$result[$i]->domain_id} AND `account_id`='{$_SESSION['user']['account_id']}'";
			$result2 = $fmdb->query($query);
			if (!$fmdb->rows_affected) {
				return _('The associated zones for this server or group could not be updated because a database error occurred.');
			}
		}
		
		return true;
	}

	/**
	 * Looks up the server group IDs
	 *
	 * @since 2.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param integer $server_id Server ID to lookup
	 * @return array
	 */
	function getServerGroupIDs($server_id) {
		global $fmdb, $__FM_CONFIG;
		
		$query = "SELECT group_id FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}server_groups` WHERE `account_id`='{$_SESSION['user']['account_id']}' AND `group_status`='active'
			AND (group_masters='$server_id' OR group_masters LIKE '$server_id;%' OR group_masters LIKE '%;$server_id;%' OR group_masters LIKE '%;$server_id' OR
			group_slaves='$server_id' OR group_slaves LIKE '$server_id;%' OR group_slaves LIKE '%;$server_id;%' OR group_slaves LIKE '%;$server_id')";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) {
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$array[] = $fmdb->last_result[$i]->group_id;
			}
			return $array;
		}
		
		return false;
	}
}

if (!isset($fm_module_servers))
	$fm_module_servers = new fm_module_servers();

?>
