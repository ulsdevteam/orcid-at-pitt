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
	die_with_error_page('500 Database connection error');
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

header('Content-type: application/json');
print json_encode($result);

?>
