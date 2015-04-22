<?php
require('../includes/constants.php');

// Let's get a resusable database connection
$db = "(DESCRIPTION=(ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = REPLACED_DNS)(PORT = 1521)))(CONNECT_DATA =(SERVICE_NAME = REPLACED_SERVICE)))" ;
$conn = oci_connect('ORCIDWEB','REPLACED_PASSWORD', $db);
if (!$conn) {
	error_log(var_export(oci_error(), true));
	die_with_error_page('500 Database connection error');
}
// If we aren't authenticated with Shibboleth, something is very wrong
if(filter_var($_SERVER['AUTH_TYPE']) !== 'shibboleth') {
	error_log('Invalid AUTH_TYPE: '.(isset($_SERVER['AUTH_TYPE']) ? $_SERVER['AUTH_TYPE'] : ''));
	die_with_error_page('403 Unauthenticated');
}

// Grab the remote user from Shibboleth
$remote_user = filter_var($_SERVER['REMOTE_USER'], FILTER_SANITIZE_STRING);
// Grab variables from Shibboleth
$shib_gn = filter_var($_SERVER['givenName'], FILTER_SANITIZE_STRING);
$shib_mn = filter_var($_SERVER['middleName'], FILTER_SANITIZE_STRING);
$shib_ln = filter_var($_SERVER['sn'], FILTER_SANITIZE_STRING);
$shib_mail = filter_var($_SERVER['mail'], FILTER_SANITIZE_EMAIL); 

// This default success message will be used multiple places
$success_html = array(
	'header' => 'Thanks for getting your ORCID on!',
	'p' => array('You\'re linked and good to go.'),
	'orcid_url' => ORCID_LOGIN,
);

// Check for ORCID sending us an error message
if (isset($_GET['error'])) {
	switch ($_GET['error']) {
		case 'access_denied':
			// user explicitly denied us access (maybe)
			// ORCID's workflow is a little off - a user can click deny without actually logging in
			// TODO: should we re-verify permissions here to prevent clearing a valid permission?
			// Clear the existing token
			execute_query_or_die($conn, 'UPDATE ULS.ORCID_USERS SET MODIFIED = SYSDATE, TOKEN = :token WHERE USERNAME = :shibUser', array('shibUser' => $remote_user, 'token' => ''));
			// Ask if the user meant to do that
			$html = array(
				'p' => array('Did you really mean to deny access to your record?  If not, click here to <a href="/?state=connect">Link your ORCID @ Pitt</a>'),
				'orcid_url' => ORCID_LOGIN,
			);
			require('../includes/template.php');
			exit();
		default:
			// ORCID could send a different error message, but that isn't handled (yet)
			error_log(var_export($_GET, true));
			die_with_error_page('500 Unrecognized ORCID error');
	}
	// The switch should have exit()'d for us
} else if (!isset($_GET['code'])) {
	// If we don't have a CODE from ORCID,
	// We are in the workflow before the redirect to ORCID
	// Check the status of the current user
	// Possible outcomes of this conditional are:
	//   An HTTP error message
	//   A redirect to the success message
	//   A pass through to the sendoff to ORCID
	// Does this user exist?
	$row = execute_query_or_die($conn, 'SELECT ORCID, TOKEN FROM ULS.ORCID_USERS WHERE USERNAME = :shibUser', array('shibUser' => $remote_user));
	if (is_array($row)) {
		// Yes, the user exists.  Do we already have a valid ORCID and token?
		if (isset($row['ORCID']) && isset($row['TOKEN'])) {
			// TODO: pass a variable parsed from Shib indicating the associations of the user.  array('employment') is for testing only!
			if (validate_record($row['ORCID'], $row['TOKEN'], $remote_user, array('employment'))) {
				// Yes, we already have a valid ORCID and token.  Send a success message and exit
				$html = $success_html;
				require('../includes/template.php');
				exit();
			}
		}
	} else {
		// This user doesn't exist yet.  Add them.
		execute_query_or_die($conn, 'INSERT INTO ULS.ORCID_USERS (USERNAME, CREATED, MODIFIED) VALUES (:shibUser, SYSDATE, SYSDATE)', array('shibUser' => $remote_user));
	}

	// If we haven't exited to this point, note that the user has visited and we are going to redirect them to ORCID
	execute_query_or_die($conn, 'INSERT INTO ULS.ORCID_STATUSES (ORCID_USER_ID, ORCID_STATUS_TYPE_ID, STATUS_TIMESTAMP) SELECT ORCID_USERS.ID, ORCID_STATUS_TYPES.ID, SYSDATE FROM ULS.ORCID_USERS JOIN ULS.ORCID_STATUS_TYPES ON (ORCID_USERS.USERNAME = :shibUser AND ORCID_STATUS_TYPES.SEQ = 2) WHERE NOT EXISTS (SELECT ORCID_STATUSES.ID FROM ULS.ORCID_STATUSES WHERE ORCID_STATUSES.ORCID_STATUS_TYPE_ID = ORCID_STATUS_TYPES.ID AND ORCID_STATUSES.ORCID_USER_ID = ORCID_USERS.ID)', array('shibUser' => $remote_user));

	// For the ORCID sandbox, use mailinator URLS
	if (!ORCID_PRODUCTION) {
		$shib_mail = str_replace('@', 'AT', $shib_mail).'@mailinator.com';
	}

	// redirect to ORCID
	$state = bin2hex(openssl_random_pseudo_bytes(16));
	setcookie('oauth_state', $state, time() + 3600, null, null, false, true);
	$url = OAUTH_AUTHORIZATION_URL . '?' . http_build_query(array(
		'response_type' => 'code',
		'client_id' => OAUTH_CLIENT_ID,
		'redirect_uri' => OAUTH_REDIRECT_URI,
		'scope' => OAUTH_SCOPE,
		'state' => $state,
		'given_names' => $shib_gn,
		'family_names' => $shib_ln,
		'email' => $shib_mail,
		'orcid' => isset($row['ORCID']) ? $row['ORCID'] : '',
	));
	header('Location: ' . $url);
	exit();
}

// We handled ORCID errors and initial touches before the ORCID handoff above.
// Since we are here, this must mean we are returning from ORCID and have a CODE
// If we are, we expect a matching session state
if (!isset($_GET['state']) || $_GET['state'] !== $_COOKIE['oauth_state']) {
	error_log(var_export($_GET, true));
	error_log(var_export($_COOKIE, true));
	die_with_error_page('403 Invalid parameters');
}

// 
// fetch the access token
$curl = curl_init();
curl_setopt_array($curl, array(
	CURLINFO_HEADER_OUT => true,
	CURLOPT_URL => OAUTH_TOKEN_URL,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HTTPHEADER => array('Accept: application/json'),
	CURLOPT_POST => true,
	CURLOPT_POSTFIELDS => http_build_query(array(
		'code' => $_GET['code'],
		'grant_type' => 'authorization_code',
		'client_id' => OAUTH_CLIENT_ID,
		'client_secret' => OAUTH_CLIENT_SECRET,
		'redirect_uri' => OAUTH_REDIRECT_URI,
		'scope' => '',
	))
));
$result = curl_exec($curl);
$info = curl_getinfo($curl);
$response = json_decode($result, true);
if (isset($response['orcid'])) {
	// TODO: pass a variable parsed from Shib indicating the associations of the user.  array('employment') is for testing only!
	if (!validate_record($response['orcid'], $response['access_token'], $remote_user, array('employment'))) {
		$html = array(
			'p' => array('Something\'s not quite right.  We couldn\'t access your record.  Can you try to <a href="/?state=connect">Link your ORCID @ Pitt</a> again?'),
			'orcid_url' => ORCID_LOGIN,
		);
		require('../includes/template.php');
		exit();
	}
	// Update ORCID and TOKEN as returned
	execute_query_or_die($conn, 'UPDATE ULS.ORCID_USERS SET MODIFIED = SYSDATE, ORCID = :orcid, TOKEN = :token WHERE USERNAME = :shibUser', array('shibUser' => $remote_user, 'token' => $response['access_token'], 'orcid' => $response['orcid']));
} else {
	die_with_error_page('500 ORCID API connection error');
}

$html = array(
	'header' => 'Thanks for getting your ORCID on!',
	'p' => array('You\'re linked and good to go.'),
	'orcid_url' => ORCID_LOGIN,
);
require('../includes/template.php');
exit();
?>