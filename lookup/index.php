<?php
/**
 * Given query input of ?user=username
 * Lookup username and return the associated ORCID, if applicable
 * 
 * Returns JSON
**/
require('../includes/constants.php');

// Let's get a resusable database connection
$conn = oci_connect('ORCIDWEB',DB_PASSWD, DB_TNS);
if (!$conn) {
	error_log(var_export(oci_error(), true));
	header('500 Database connection error');
	die('The database service is unavailable.');
}
$user = filter_var($_GET['user'], FILTER_SANITIZE_STRING);

$result = array('user' => $user, 'orcid' => '');
$row = execute_query_or_die($conn, 'SELECT ORCID FROM ULS.ORCID_USERS WHERE USERNAME = :shibUser', array('shibUser' => strtoupper($user)));
if (is_array($row)) {
	// Yes, the user exists.  Do we already have a valid ORCID
	if (isset($row['ORCID'])) {
		$result['orcid'] = $row['ORCID'];
	}
}

if (isset($_GET['callback'])) {
	// Sanity check JSONp callback
	$callback = 'callback';
	if ($_GET['callback']) {
		// skips valid characters U+200C and U+200D
		if (preg_match('/^[\$\w\p{Ll}\p{Lu}\p{Lt}\p{Lm}\p{Lo}\p{Nl}][\$\w\p{Ll}\p{Lu}\p{Lt}\p{Lm}\p{Lo}\p{Nl}\p{Mn}\p{Mc}\p{Nd}\p{Pc}]*$/u', $_GET['callback'])) {
			$callback = $_GET['callback'];
		} else {
			error_log('callback failed sanity check: '.$_GET['callback']);
			header('400 Bad callback in request');
			die('The callback failed the sanity check.');
		}
	}
	header('Content-type: application/javascript');
	print $callback.'('.json_encode($result).')';
} else {
	header('Content-type: application/json');
	print json_encode($result);
}

?>
