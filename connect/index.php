<?php
require('../includes/constants.php');

// Let's get a resusable database connection
$conn = oci_connect('ORCIDWEB',DB_PASSWD, DB_TNS);
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
$shib_affiliations = explode(';', filter_var($_SERVER['PittAffiliate'], FILTER_SANITIZE_STRING)); 
// Translate Pitt affiliations of student, employee into valid ORCID affiliations of employment, education
// We do not release educational information to ORCID
if (in_array('employee', $shib_affiliations, TRUE) || in_array('faculty', $shib_affiliations, TRUE) || in_array('staff', $shib_affiliations, TRUE)) {
	$orcid_affiliations = array('employment');
} else if (in_array('student', $shib_affiliations, TRUE)) {
	$orcid_affiliations = array();
} else {
	$orcid_affiliations = array();
}

// This default success message will be used multiple places
$success_html = array(
	'header' => 'ORCID@Pitt success!',
	'p' => array('Thank you-you have successfully created your ORCID iD and linked it to the University of Pittsburgh.', 'Now would be a good time to <a href="'.ORCID_LOGIN.'">log into your ORCID Record</a> and invest a few minutes in adding important information to help identify you and your research. ', 'To find out more about the ORCID@Pitt initiative and the benefits of having an ORCID iD, please visit the <a href="http://www.library.pitt.edu/orcid">ORCID@Pitt website</a>.', 'Thank you for participating in this important university initiative.'),
);

// Check for ORCID sending us an error message
if (isset($_GET['error'])) {
	switch ($_GET['error']) {
		case 'access_denied':
			// user explicitly denied us access (maybe)
			// ORCID's workflow is a little off - a user can click deny without actually logging in
			// Clear the existing token if we've lost permission
			$row = execute_query_or_die($conn, 'SELECT ORCID, TOKEN FROM ULS.ORCID_USERS WHERE USERNAME = :shibUser', array('shibUser' => $remote_user));
			if (is_array($row)) {
				// Yes, the user exists.  Do we already have a valid ORCID and token?
				if (isset($row['ORCID']) && isset($row['TOKEN'])) {
					if (!validate_record($row['ORCID'], $row['TOKEN'], $remote_user, $orcid_affiliations)) {
						execute_query_or_die($conn, 'UPDATE ULS.ORCID_USERS SET MODIFIED = SYSDATE, TOKEN = :token WHERE USERNAME = :shibUser', array('shibUser' => $remote_user, 'token' => ''));
					}
				}
			}
			// Ask if the user meant to do that
			$html = array(
				'header' => 'ORCID@Pitt Trusted Party Status',
				'p' => array('Thank you for creating your ORCID iD.', 'You chose not to grant trusted party status to Pitt, thus not allowing the university to access your ORCID iD.', 'Allowing Pitt to be a trusted party will help you and the university maintaining accurate records of your research outputs within Pitt systems such as the Faculty Information System and D-Scholarship@Pitt.', 'If you would like to grant Pitt trusted party status or add information to your ORCID profile, please <a href="/connect">restart this process</a>.', 'To find out more about the ORCID@Pitt initiative and the benefits of having an ORCID iD, please visit the <a href="http://www.library.pitt.edu/orcid">ORCID@Pitt website.</a>'),
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
	$row = execute_query_or_die($conn, 'SELECT ORCID, TOKEN, USERNAME FROM ULS.ORCID_USERS WHERE USERNAME = :shibUser', array('shibUser' => $remote_user));
	if (is_array($row) && $row['USERNAME']) {
		// Yes, the user exists.  Do we already have a valid ORCID and token?
		if (isset($row['ORCID']) && isset($row['TOKEN'])) {
			if (validate_record($row['ORCID'], $row['TOKEN'], $remote_user, $orcid_affiliations)) {
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
		$shib_mail = str_replace('@', '.', $shib_mail).'@mailinator.com';
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
	if (!validate_record($response['orcid'], $response['access_token'], $remote_user, $orcid_affiliations)) {
		die_with_error_page('500 ORCID Validation error');
	}
	// Update ORCID and TOKEN as returned
	execute_query_or_die($conn, 'UPDATE ULS.ORCID_USERS SET MODIFIED = SYSDATE, ORCID = :orcid, TOKEN = :token WHERE USERNAME = :shibUser', array('shibUser' => $remote_user, 'token' => $response['access_token'], 'orcid' => $response['orcid']));
} else {
	die_with_error_page('500 ORCID API connection error');
}

$html = $success_html;
require('../includes/template.php');
exit();
?>
