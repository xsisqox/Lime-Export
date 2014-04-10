<?php 

// Block direct includes
if ( !defined('WPINC') ) {
	header("HTTP/1.0 404 Not Found");
	exit;
}

function wple_snapshot_dir() {
	$upload_dir = wp_upload_dir();
	$upload_dir = $upload_dir['basedir'] . '/wple-snapshots';
	
	return $upload_dir;
}

function wple_create_snapshot_dir() {
	$upload_dir = wple_snapshot_dir();

	if ( !is_dir( $upload_dir ) ) {
		if ( !preg_match('~apache~i', $_SERVER['SERVER_SOFTWARE']) ) {
			throw new WPLE_Exception( __('This feature requires Apache Web Server.', 'lime-export') );
		}

		if ( !@mkdir($upload_dir, 0777, true) ) {
			throw new WPLE_Exception( sprintf(
				__('Unable to create directory <code>%s</code>. Is its parent directory writable by the server?', 'lime-export'),
				str_replace(ABSPATH, '/', $upload_dir)
			));
		}

		touch($upload_dir . '/list.csv');
		
		// create dummy index.php
		$index = fopen($upload_dir . '/index.php', 'w');
		if ( !$index ) {
			throw new WPLE_Exception( sprintf(
				__('Cannot open file <code>%s</code>', 'lime-export'), 
				str_replace(ABSPATH, '/', $upload_dir . '/index.php')
			));
		}
		fwrite($index, "<?php");
		fclose( $index );
	}
}

function wple_add_admin_notice($notice) {
	global $wple_admin_notices;

	if ( !is_array($wple_admin_notices) ) {
		$wple_admin_notices = array();
	}
	
	$wple_admin_notices[] = $notice;
}

function wple_get_admin_notices() {
	global $wple_admin_notices;

	if ( !is_array($wple_admin_notices) ) {
		$wple_admin_notices = array();
	}

	return $wple_admin_notices;
}

function wple_addslashes($str = '', $is_like = false, $line_endings = false) {
	if ($is_like) {
		$str = str_replace('\\', '\\\\\\\\', $str);
	} else {
		$str = str_replace('\\', '\\\\', $str);
	}

	if ($line_endings) {
		$str = str_replace("\n", '\n', $str);
		$str = str_replace("\r", '\r', $str);
		$str = str_replace("\t", '\t', $str);
	}

	$str = str_replace('\'', '\'\'', $str);

	return $str;
}

function wple_escape_bit($value, $length) {
	$printable = '';
	for ($i = 0, $len_ceiled = ceil($length / 8); $i < $len_ceiled; $i++) {
		$printable .= sprintf('%08d', decbin(ord(substr($value, $i, 1))));
	}
	$printable = substr($printable, -$length);
	return $printable;
}

function wple_get_existing_tables() {
	global $wpdb;

	return $wpdb->get_col('
		SHOW TABLES LIKE "' . $wpdb->prefix . '%"
	', 0);
}

function wple_get_checked( $name, $default='', $value='') {
	if ( isset($_POST[$name]) && ( empty($value) || $_POST[$name] == $value ) ) {
		return 'checked="checked"';
	} elseif ( isset($_POST[$name]) && is_array($_POST[$name]) && in_array($value, $_POST[$name]) ) {
		return 'checked="checked"';
	} elseif ( empty($value) || (!isset($_POST[$name]) || !is_array($_POST[$name])) ) {
		return $default;
	}
	return '';
}

function wple_get_postval( $name, $default='') {
	if ( isset($_POST[$name]) ) {
		return $_POST[$name];
	}
	return $default;
}

function wple_format_bytes($size) {
    $units = array(' B', ' KB', ' MB', ' GB', ' TB');
    for ($i = 0; $size >= 1024 && $i < 4; $i++) 
    	$size /= 1024;

    return round($size, 2) . $units[$i];
}

class WPLE_Exception extends Exception { }


