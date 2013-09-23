<?php

function installfmDNSSchema($link = null, $database, $module, $noisy = true) {
	/** Include module variables */
	@include(ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'variables.inc.php');
	
	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}acls` (
  `acl_id` INT(11) NOT NULL AUTO_INCREMENT ,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(11) NOT NULL DEFAULT '0',
  `acl_name` VARCHAR(255) NOT NULL ,
  `acl_predefined` ENUM( 'none',  'any',  'localhost',  'localnets',  'as defined:') NOT NULL ,
  `acl_addresses` TEXT NOT NULL ,
  `acl_status` ENUM( 'active',  'disabled',  'deleted') NOT NULL DEFAULT  'active',
  PRIMARY KEY (`acl_id`)
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLE;
	
	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` (
  `cfg_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(11) NOT NULL DEFAULT '0',
  `cfg_type` varchar(255) NOT NULL DEFAULT 'global',
  `cfg_view` int(11) NOT NULL DEFAULT '0',
  `cfg_isparent` enum('yes','no') NOT NULL DEFAULT 'no',
  `cfg_parent` int(11) NOT NULL DEFAULT '0',
  `cfg_name` varchar(50) NOT NULL,
  `cfg_data` text NOT NULL,
  `cfg_status` enum('hidden','active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`cfg_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` (
  `domain_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `domain_name` varchar(255) NOT NULL DEFAULT '',
  `domain_name_servers` varchar(255) NOT NULL DEFAULT '0',
  `domain_view` varchar(255) NOT NULL DEFAULT '0',
  `domain_mapping` enum('forward','reverse') NOT NULL DEFAULT 'forward',
  `domain_type` enum('master','slave','forward') NOT NULL DEFAULT 'master',
  `domain_check_names` enum('warn','fail','ignore') DEFAULT NULL,
  `domain_notify_slaves` enum('yes','no') DEFAULT NULL,
  `domain_multi_masters` enum('yes','no') DEFAULT NULL,
  `domain_transfers_from` varchar(255) DEFAULT NULL,
  `domain_updates_from` varchar(255) DEFAULT NULL,
  `domain_clone_domain_id` int(11) NOT NULL DEFAULT '0',
  `domain_master_servers` varchar(255) DEFAULT NULL,
  `domain_forward_servers` varchar(255) DEFAULT NULL,
  `domain_reload` enum('yes','no') NOT NULL DEFAULT 'no',
  `domain_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`domain_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` (
  `def_function` enum('options','logging','key','view') NOT NULL,
  `def_option` varchar(255) NOT NULL,
  `def_type` varchar(200) NOT NULL,
  `def_multiple_values` enum('yes','no') NOT NULL DEFAULT 'no',
  `def_view_support` enum('yes','no') NOT NULL DEFAULT 'no',
  UNIQUE KEY `def_option` (`def_option`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}keys` (
  `key_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `key_name` varchar(255) NOT NULL,
  `key_algorithm` enum('hmac-md5') NOT NULL DEFAULT 'hmac-md5',
  `key_secret` varchar(255) NOT NULL,
  `key_view` int(11) NOT NULL DEFAULT '0',
  `key_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`key_id`)
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}options` (
  `option_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `option_name` varchar(255) NOT NULL,
  `option_value` varchar(255) NOT NULL,
  PRIMARY KEY (`option_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}records` (
  `record_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `domain_id` int(11) NOT NULL DEFAULT '0',
  `record_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `record_name` varchar(255) DEFAULT '@',
  `record_value` varchar(255) DEFAULT NULL,
  `record_ttl` varchar(50) NOT NULL DEFAULT '',
  `record_class` enum('IN','CH') NOT NULL DEFAULT 'IN',
  `record_type` enum('A','AAAA','CNAME','TXT','MX','PTR','SRV','NS') NOT NULL DEFAULT 'A',
  `record_priority` int(4) DEFAULT NULL,
  `record_weight` int(4) DEFAULT NULL,
  `record_port` int(4) DEFAULT NULL,
  `record_comment` varchar(200) NOT NULL,
  `record_append` enum('yes','no') NOT NULL DEFAULT 'yes',
  `record_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`record_id`)
) ENGINE=INNODB DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}servers` (
  `server_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(10) NOT NULL,
  `server_name` varchar(255) NOT NULL,
  `server_os` varchar(50) DEFAULT NULL,
  `server_key` int(11) NOT NULL,
  `server_type` enum('bind9') NOT NULL DEFAULT 'bind9',
  `server_version` varchar(150) DEFAULT NULL,
  `server_run_as_predefined` enum('named','bind','daemon','as defined:') NOT NULL,
  `server_run_as` varchar(50) DEFAULT NULL,
  `server_root_dir` varchar(255) NOT NULL,
  `server_zones_dir` varchar(255) NOT NULL,
  `server_config_file` varchar(255) NOT NULL DEFAULT '/etc/named.conf',
  `server_update_method` enum('http','https','cron') NOT NULL DEFAULT 'http',
  `server_update_port` int(5) NOT NULL DEFAULT  '0',
  `server_build_config` enum('yes','no') NOT NULL DEFAULT 'no',
  `server_update_config` enum('yes','no') NOT NULL DEFAULT 'no',
  `server_installed` enum('yes','no') NOT NULL DEFAULT 'no',
  `server_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'disabled',
  PRIMARY KEY (`server_id`),
  UNIQUE KEY `server_serial_no` (`server_serial_no`)
) ENGINE = MYISAM  DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` (
  `soa_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `domain_id` int(11) NOT NULL DEFAULT '0',
  `soa_master_server` varchar(50) NOT NULL DEFAULT '',
  `soa_append` enum('yes','no') NOT NULL DEFAULT 'yes',
  `soa_email_address` varchar(50) NOT NULL DEFAULT '',
  `soa_serial_no` tinyint(2) NOT NULL DEFAULT '10',
  `soa_refresh` varchar(50) DEFAULT '21600',
  `soa_retry` varchar(50) DEFAULT '7200',
  `soa_expire` varchar(50) DEFAULT '604800',
  `soa_ttl` varchar(50) DEFAULT '1200',
  `soa_status` enum('active','disabled','deleted') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`soa_id`),
  KEY `domain_id` (`domain_id`,`soa_master_server`,`soa_email_address`,`soa_refresh`,`soa_retry`,`soa_expire`,`soa_ttl`)
) ENGINE=INNODB DEFAULT CHARSET=utf8 ;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}track_builds` (
  `domain_id` int(11) NOT NULL,
  `server_serial_no` int(11) NOT NULL
) ENGINE=INNODB DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}track_reloads` (
  `domain_id` int(11) NOT NULL,
  `server_serial_no` int(11) NOT NULL,
  `soa_id` int(11) NOT NULL
) ENGINE = INNODB DEFAULT CHARSET=utf8;
TABLE;

	$table[] = <<<TABLE
CREATE TABLE IF NOT EXISTS $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}views` (
  `view_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
  `account_id` int(11) NOT NULL DEFAULT '1',
  `server_serial_no` int(11) NOT NULL DEFAULT '0',
  `view_name` VARCHAR(255) NOT NULL ,
  `view_status` ENUM( 'active',  'disabled',  'deleted') NOT NULL DEFAULT  'active'
) ENGINE = MYISAM DEFAULT CHARSET=utf8;
TABLE;


	/** fm_prefix_config inserts */
	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '0', '0', 'directory', '\$ROOT', 'hidden' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE account_id = '0');
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'version', 'none', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'version' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'hostname', 'none', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'hostname' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'recursion', 'no', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'recursion' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'statistics-file', '"\$ROOT/named.stats"', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'statistics-file' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'zone-statistics', 'yes', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'zone-statistics' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'pid-file', '"\$ROOT/named.pid"', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'pid-file' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'dump-file', '"\$ROOT/named.dump"', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'dump-file' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'auth-nxdomain', 'no', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'auth-nxdomain' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'cleaning-interval', '120', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'cleaning-interval' AND server_serial_no = '0'
	);
INSERT;

	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` (account_id, cfg_parent, cfg_name, cfg_data, cfg_status) 
	SELECT '1', '0', 'interface-interval', '0', 'active' FROM DUAL
WHERE NOT EXISTS
	(SELECT * FROM $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}config` WHERE 
	account_id = '1' AND cfg_parent = '0' AND cfg_name = 'interface-interval' AND server_serial_no = '0'
	);
INSERT;
	
	
	
	/** fm_prefix_functions inserts*/
	$inserts[] = <<<INSERT
INSERT IGNORE INTO  $database.`fm_{$__FM_CONFIG['fmDNS']['prefix']}functions` (
`def_function` ,
`def_option` ,
`def_type` ,
`def_multiple_values` ,
`def_view_support`
)
VALUES 
('key', 'algorithm', 'string', 'no', 'no'),
('key', 'secret', 'quoted_string', 'no', 'no'),
('options',  'avoid-v4-udp-ports',  '( port )',  'yes',  'no'), 
('options',  'avoid-v6-udp-ports',  '( port )',  'yes',  'no'),
('options',  'blackhole',  '( address_match_element )',  'yes',  'no'),
('options',  'coresize',  '( size_in_bytes )',  'no',  'no'),
('options',  'datasize',  '( size_in_bytes )',  'no',  'no'),
('options',  'dump-file',  '( quoted_string )',  'no',  'no'),
('options',  'files',  '( size_in_bytes )',  'no',  'no'),
('options',  'heartbeat-interval',  '( integer )',  'no',  'no'),
('options',  'hostname',  '( quoted_string | none )',  'no',  'no'),
('options',  'interface-interval',  '( integer )',  'no',  'no'),
('options',  'listen-on',  '( address_match_element )',  'yes',  'no'),
('options',  'listen-on-v6',  '( address_match_element )',  'yes',  'no'),
('options',  'match-mapped-addresses',  '( yes | no )',  'no',  'no'),
('options',  'memstatistics-file',  '( quoted_string )',  'no',  'no'),
('options',  'pid-file',  '( quoted_string | none )',  'no',  'no'),
('options',  'port',  '( integer )',  'no',  'no'),
('options',  'querylog',  '( yes | no )',  'no',  'no'),
('options',  'recursing-file',  '( quoted_string )',  'no',  'no'),
('options',  'random-device',  '( quoted_string )',  'no',  'no'),
('options',  'recursive-clients',  '( integer )',  'no',  'no'),
('options',  'serial-query-rate',  '( integer )',  'no',  'no'),
('options',  'server-id',  '( quoted_string | none )',  'no',  'no'),
('options',  'stacksize',  '( size_in_bytes )',  'no',  'no'),
('options',  'statistics-file',  '( quoted_string )',  'no',  'no'),
('options',  'tcp-clients',  '( integer )',  'no',  'no'),
('options',  'tcp-listen-queue',  '( integer )',  'no',  'no'),
('options',  'transfers-per-ns',  '( integer )',  'no',  'no'),
('options',  'transfers-in',  '( integer )',  'no',  'no'),
('options',  'transfers-out',  '( integer )',  'no',  'no'),
('options',  'use-ixfr',  '( yes | no )',  'no',  'no'),
('options',  'version',  '( quoted_string | none )',  'no',  'no'),

('options',  'allow-recursion',  '( address_match_element )',  'yes',  'yes'),
('options',  'sortlist',  '( address_match_element )',  'yes',  'yes'),
('options',  'auth-nxdomain',  '( yes | no )',  'no',  'yes'),
('options',  'minimal-responses',  '( yes | no )',  'no',  'yes'),
('options',  'recursion',  '( yes | no )',  'no',  'yes'),
('options',  'provide-ixfr',  '( yes | no )',  'no',  'yes'),
('options',  'request-ixfr',  '( yes | no )',  'no',  'yes'),
('options',  'additional-from-auth',  '( yes | no )',  'no',  'yes'),
('options',  'additional-from-cache',  '( yes | no )',  'no',  'yes'),
('options',  'query-source',  'address ( ipv4_address | * ) [ port ( ip_port | * ) ]',  'no',  'yes'),
('options',  'query-source-v6',  'address ( ipv6_address | * ) [ port ( ip_port | * ) ]',  'no',  'yes'),
('options',  'cleaning-interval',  '( integer )',  'no',  'yes'),
('options',  'lame-ttl',  '( seconds )',  'no',  'yes'),
('options',  'max-ncache-ttl',  '( seconds )',  'no',  'yes'),
('options',  'max-cache-ttl',  '( seconds )',  'no',  'yes'),
('options',  'transfer-format',  '( many-answers | one-answer )',  'no',  'yes'),
('options',  'max-cache-size',  '( size_in_bytes )',  'no',  'yes'),
('options',  'check-names',  '( master | slave | response) ( warn | fail | ignore )',  'no',  'yes'),
('options',  'cache-file',  '( quoted_string )',  'no',  'yes'),
('options',  'preferred-glue',  '( A | AAAA )',  'no',  'yes'),
('options',  'edns-udp-size',  '( size_in_bytes )',  'no',  'yes'),
('options',  'dnssec-enable',  '( yes | no )',  'no',  'yes'),
('options',  'dnssec-lookaside',  'domain trust-anchor domain',  'no',  'yes'),
('options',  'dnssec-must-be-secure',  'domain ( yes | no )',  'no',  'yes'),
('options',  'dialup',  '( yes | no | notify | refresh | passive | notify-passive )',  'no',  'yes'),
('options',  'ixfr-from-differences',  '( yes | no )',  'no',  'yes'),
('options',  'allow-query',  '( address_match_element )',  'yes',  'yes'),
('options',  'allow-transfer',  '( address_match_element )',  'yes',  'yes'),
('options',  'allow-update-forwarding',  '( address_match_element )',  'yes',  'yes'),
('options',  'notify',  '( yes | no | explicit )',  'no',  'yes'),
('options',  'notify-source',  '( ipv4_address | * )',  'no',  'yes'),
('options',  'notify-source-v6',  '( ipv6_address | * )',  'no',  'yes'),
('options',  'also-notify',  '( ipv4_address | ipv6_address )',  'yes',  'yes'),
('options',  'allow-notify',  '( address_match_element )',  'yes',  'yes'),
('options',  'forward',  '( first | only )',  'no',  'yes'),
('options',  'forwarders',  '( ipv4_address | ipv6_address )',  'yes',  'yes'),
('options',  'max-journal-size',  '( size_in_bytes )',  'no',  'yes'),
('options',  'max-transfer-time-in',  '( minutes )',  'no',  'yes'),
('options',  'max-transfer-time-out',  '( minutes )',  'no',  'yes'),
('options',  'max-transfer-idle-in',  '( minutes )',  'no',  'yes'),
('options',  'max-transfer-idle-out',  '( minutes )',  'no',  'yes'),
('options',  'max-retry-time',  '( seconds )',  'no',  'yes'),
('options',  'min-retry-time',  '( seconds )',  'no',  'yes'),
('options',  'max-refresh-time',  '( seconds )',  'no',  'yes'),
('options',  'min-refresh-time',  '( seconds )',  'no',  'yes'),
('options',  'multi-master',  '( yes | no )',  'no',  'yes'),
('options',  'sig-validity-interval',  '( integer )',  'no',  'yes'),
('options',  'transfer-source',  '( ipv4_address | * )',  'no',  'yes'),
('options',  'transfer-source-v6',  '( ipv6_address | * )',  'no',  'yes'),
('options',  'alt-transfer-source',  '( ipv4_address | * )',  'no',  'yes'),
('options',  'alt-transfer-source-v6',  '( ipv6_address | * )',  'no',  'yes'),
('options',  'use-alt-transfer-source',  '( yes | no )',  'no',  'yes'),
('options',  'zone-statistics',  '( yes | no )',  'no',  'yes'),
('options',  'key-directory',  '( quoted_string )',  'no',  'yes'),
('options',  'match-clients',  '( address_match_element )',  'yes',  'yes'),
('options',  'match-destinations',  '( address_match_element )',  'yes',  'yes'),
('options',  'match-recursive-only',  '( yes | no )',  'no',  'yes')
;
INSERT;
	
	
	/** fm_options inserts */
	$inserts[] = <<<INSERT
INSERT INTO $database.`fm_options` (option_name, option_value) 
	SELECT '{$module}_version', '{$__FM_CONFIG[$module]['version']}' FROM DUAL
WHERE NOT EXISTS
	(SELECT option_name FROM $database.`fm_options` WHERE option_name = '{$module}_version');
INSERT;


	/** Create table schema */
	foreach ($table as $schema) {
		if ($link) {
			$result = mysql_query($schema, $link);
		} else {
			global $fmdb;
			$result = $fmdb->query($schema);
		}
	}

	/** Insert site values if not already present */
	$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}config";
//	$temp_result = mysql_query($query, $link);
//	if (!@mysql_num_rows($temp_result)) {
		foreach ($inserts as $query) {
			if ($link) {
				$result = mysql_query($query, $link);
			} else {
				$result = $fmdb->query($query);
			}
		}
//	}

//	$current_value = getOption('enable_named_checks', 1, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'options');
//	$command = ($current_value === false) ? 'insert' : 'update';
//	setOption('enable_named_checks', 'no', $command, 1, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'options');

	if (function_exists('displayProgress')) {
		return displayProgress($module, $result, $noisy);
	} else {
		if ($result) {
			return 'Success';
		} else {
			return 'Failed';
		}
	}
}

?>