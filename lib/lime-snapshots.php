<?php  

// Block direct includes
if ( !defined('WPINC') ) {
	header("HTTP/1.0 404 Not Found");
	exit;
}

function wple_supports_snapshots() {
	if ( !is_writable(wple_snapshot_dir()) ) {
		return false;
	}

	if ( !wple_is_snapshot_dir_secure() ) {
		return false;
	}

	return true;
}

function wple_admin_page_snapshots() {
	global $wpdb;

	if ( !wple_supports_snapshots() ) {
		include(WPLE_PATH . '/admin-templates/page-snapshots-no-support.php');
		return;
	}

	try {
		$snapshots = wple_get_snapshots();
	} catch (WPLE_Exception $e) {
		$snapshots = array();

		wple_add_admin_notice($e->getMessage());
		do_action('admin_notices');
	}
	
	$snapshots = array_reverse($snapshots);

	$date_format = get_option('date_format') . ' ' . get_option('time_format');

	include(WPLE_PATH . '/admin-templates/page-snapshots.php');
}

function wple_do_snapshot_download( $filename, $nice_filename ) {
	$full_snapshot_path = wple_snapshot_dir() . '/' . $filename;

	if ( !is_file($full_snapshot_path) ) {
		throw new WPLE_Exception( sprintf(
			__('The snapshot you requested is missing <code>%s</code>.', 'lime-export'), 
			str_replace(ABSPATH, '/', $full_snapshot_path)
		));
	}

	header('Content-Type: text/x-sql');
	header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Content-Disposition: attachment; filename="' . $nice_filename . '"');

    if ( isset($_SERVER['HTTP_USER_AGENT']) && preg_match('~MSIE ([0-9].[0-9]{1,2})~', $_SERVER['HTTP_USER_AGENT']) ) {
    	// IE?
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
    } else {
        header('Pragma: no-cache');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    }

	readfile($full_snapshot_path);

	return true;
}

function wple_get_snapshots() {
	$snapshots = array();
	$dir = wple_snapshot_dir() . '/';
	$csv_filename = $dir . 'list.csv';

	$csv = fopen($csv_filename, 'r');
	if ( !$csv ) {
		throw new WPLE_Exception( sprintf(
			__('Error reading file <code>%s</code>', 'lime-export'), 
			str_replace(ABSPATH, '/', $csv_filename)
		));
	}

	while (($data = fgetcsv($csv, 1000, ",")) !== FALSE) {
		if ( count( $data) < 3 ) {
			// silently ignore invalid line
			continue;
		}
		$snapshots[] = array(
			'filename' => $data[0],
			'tables' => explode('|', $data[1]),
			'created' => intval($data[2]),
			'size' => wple_format_bytes(intval($data[3])),
		);
	}

	fclose($csv);
	return $snapshots;
}

function wple_add_snapshot( $filename, $tables, $time = null ) {
	$time = !$time ? time(): $time;

	$dir = wple_snapshot_dir() . '/';
	$csv_filename = $dir . 'list.csv';

	$csv = fopen($csv_filename, 'a');
	if ( !$csv ) {
		throw new WPLE_Exception( sprintf(
			__('Cannot open file <code>%s</code>', 'lime-export'), 
			str_replace(ABSPATH, '/', $csv_filename)
		));
	}

	fputcsv($csv, array($filename, implode('|', $tables), $time, filesize($dir . $filename)) );
	fclose($csv);
}

function wple_remove_snapshot( $filename ) {
	$snapshots = wple_get_snapshots();

	$dir = wple_snapshot_dir() . '/';
	$csv_filename = $dir . 'list.csv';

	$csv = fopen($dir . 'list.csv', 'w');
	if ( !$csv ) {
		throw new WPLE_Exception( sprintf(
			__('Cannot open file <code>%s</code>', 'lime-export'), 
			str_replace(ABSPATH, '/', $csv_filename)
		));
	}

	foreach ($snapshots as $snapshot) {
		if ( $snapshot['filename'] == $filename ) {
			if ( is_file( $dir . $snapshot['filename'] ) ) {
				unlink( $dir . $snapshot['filename'] );
			}
		} else {
			$snapshot['tables'] = implode('|', $snapshot['tables']);
			fputcsv($csv, $snapshot);
		}
	}
	fclose($csv);
}

function wple_get_snapshot_delete_url($snapshot) {
	$bare_url = add_query_arg(array('wple-delete' => $snapshot['filename']));

	$complete_url = wp_nonce_url( $bare_url, 'wple_delete-snapshot_' . $snapshot['filename'] );

	return $complete_url;
}

function wple_get_snapshot_download_url($snapshot) {
	$bare_url = add_query_arg(array('wple-download' => $snapshot['filename']));

	$complete_url = wp_nonce_url( $bare_url, 'wple_download-snapshot_' . $snapshot['filename'] );

	return $complete_url;
}

function wple_snapshot_dir() {
	$upload_dir = wp_upload_dir();
	$upload_dir = $upload_dir['basedir'] . '/wple-snapshots';
	
	return $upload_dir;
}

function wple_create_snapshot_dir() {
	$upload_dir = wple_snapshot_dir();

	if ( !is_dir( $upload_dir ) ) {
		$success = @mkdir($upload_dir, 0750, true);
		if ( !$success ) {
			throw new WPLE_Exception( sprintf(
				__('Unable to create directory <code>%s</code>. Is its parent directory writable by the server?', 'lime-export'),
				str_replace(ABSPATH, '/', $upload_dir)
			));
		}
	}

	if ( !file_exists($upload_dir . '/list.csv') ) {
		touch($upload_dir . '/list.csv');
		chmod($upload_dir . '/list.csv', 0640);
	}
	
	
	if ( !file_exists($upload_dir . '/index.html') ) {
		touch($upload_dir . '/index.html');
		chmod($upload_dir . '/index.html', 0640);
	}
}

function wple_is_snapshot_dir_secure() {
	$dir = wple_snapshot_dir();
	$dir_perms = fileperms($dir);

	if ( ($dir_perms & 0x0007) != 0 ) {
		return false;
	}

	if ( !file_exists($dir . '/index.html') ) {
		return false;
	}

	return true;
}