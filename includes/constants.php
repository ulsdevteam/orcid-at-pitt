<?php
// We will use the UA's server protocol in responses if sane
$server_protocol = preg_filter('|^HTTP/[012].[0-9]$|', "$0", $_SERVER['SERVER_PROTOCOL']);
// If the provided server protocol is not sane, default to HTTP 1.1
if (!$server_protocol) {
	$server_protocol = 'HTTP/1.1';
}
define('SERVER_PROTOCOL', $server_protocol);

define ('PITT_EXTID_NAME', 'Pitt ID');
define ('PITT_AFFILIATION_KEY', 'RINGGOLD');
define ('PITT_AFFILIATION_ID', '6614');

// Construct sendoff to ORCID
define('OAUTH_SCOPE', '/orcid-profile/read-limited /orcid-bio/external-identifiers/create /affiliations/create');
define('ORCID_PRODUCTION', false); // sandbox; change to true when ready to leave the sandbox

if (ORCID_PRODUCTION) {
	// production credentials
	define('OAUTH_CLIENT_ID', 'REPLACED_KEY');
	define('OAUTH_CLIENT_SECRET', 'REPLACED_TOKEN');
	// production endpoints
	define('OAUTH_AUTHORIZATION_URL', 'https://orcid.org/oauth/authorize');
	define('OAUTH_TOKEN_URL', 'https://api.orcid.org/oauth/token'); // members
	define('OAUTH_API_URL', 'https://api.orcid.org/v1.2/'); // members
	define('ORCID_LOGIN', 'https://orcid.org/my-orcid');
	// production values
	define('OAUTH_REDIRECT_URI', 'https://orcid.pitt.edu/connect'); // URL of the target script
	define('DB_TNS', '(DESCRIPTION=(ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = REPLACED_DNS)(PORT = 1521)))(CONNECT_DATA =(SERVICE_NAME = REPLACED_SERVICE)))'); // TNS for the Oracle Connection
	define('DB_PASSWD', 'REPLACED_PASSWORD'); // Oracle Database password
} else {
	// sandbox credentials
	define('OAUTH_CLIENT_ID', 'REPLACED_KEY');
	define('OAUTH_CLIENT_SECRET', 'REPLACED_TOKEN');
	// sandbox endpoints
	define('OAUTH_AUTHORIZATION_URL', 'https://sandbox.orcid.org/oauth/authorize');
	define('OAUTH_TOKEN_URL', 'https://api.sandbox.orcid.org/oauth/token'); // members
	define('OAUTH_API_URL', 'https://api.sandbox.orcid.org/v1.2/'); // members
	define('ORCID_LOGIN', 'https://sandbox.orcid.org/my-orcid');
	// development values
	define('OAUTH_REDIRECT_URI', 'https://orcid-dev.pitt.edu/connect'); // URL of the target script
	define('DB_TNS', '(DESCRIPTION=(ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = REPLACED_DNS)(PORT = 1521)))(CONNECT_DATA =(SERVICE_NAME = REPLACED_SERVICE)))'); // TNS for the Oracle Connection
	define('DB_PASSWD', 'REPLACED_PASSWORD'); // Oracle Database password
}

/**
 * Read an ORCID record, returning XML
 * 
 * @param string $orcid ORCID Id
 * @param string $token ORCID access token
 * @return string XML on success
 */
function read_profile($orcid, $token) {
	$curl = curl_init();
	curl_setopt_array(
		$curl,
		array(
			CURLINFO_HEADER_OUT => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_URL => OAUTH_API_URL.$orcid.'/orcid-profile',
			CURLOPT_HTTPHEADER => array('Content-Type: application/vdn.orcid+xml', 'Authorization: Bearer '.$token),
		)
	);
	$result = curl_exec($curl);
	$info = curl_getinfo($curl);
	if ($info['http_code'] == 200) {
		return $result;
	} else {
		return false;
	}
}

/**
 * Write the External ID to ORCID
 * 
 * @param string $orcid ORCID Id
 * @param string $token ORCID access token
 * @param string $id External ID
 * @return boolean success
 */
function write_extid($orcid, $token, $id) {
	$payload = '<?xml version="1.0" encoding="UTF-8"?>
	<orcid-message xmlns="http://www.orcid.org/ns/orcid" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="https://raw.github.com/ORCID/ORCID-Source/master/orcid-model/src/main/resources/orcid-message-1.2.xsd">
		<message-version>1.2</message-version>
		<orcid-profile>
			<orcid-bio>
				<external-identifiers>
					<external-identifier>
						<external-id-common-name>'.PITT_EXTID_NAME.'</external-id-common-name>
						<external-id-reference>'.$id.'</external-id-reference>
					</external-identifier>
				</external-identifiers>
			</orcid-bio>
		</orcid-profile>
	</orcid-message>';
	$curl = curl_init();
	curl_setopt_array(
		$curl,
		array(
			CURLINFO_HEADER_OUT => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_URL => OAUTH_API_URL.$orcid.'/orcid-bio/external-identifiers',
			CURLOPT_HTTPHEADER => array('Content-Type: application/orcid+xml', 'Content-Length: '.strlen($payload), 'Authorization: Bearer '.$token),
		)
	);
	$result = curl_exec($curl);
	$info = curl_getinfo($curl);
	// why is this code usually 200?
	return (($info['http_code'] == 201 || $info['http_code'] == 200) && read_extid($result));
}

/**
 * Write the Affiliation to ORCID
 * 
 * @param string $orcid ORCID Id
 * @param string $token ORCID access token
 * @param string $type Affiliation type
 * @return boolean success
 */
function write_affiliation($orcid, $token, $type) {
	if ($type !== 'employment' && $type !== 'education') {
		return true;
	}
	$payload = '<?xml version="1.0" encoding="UTF-8"?>
	<orcid-message xmlns="http://www.orcid.org/ns/orcid" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="https://raw.github.com/ORCID/ORCID-Source/master/orcid-model/src/main/resources/orcid-message-1.2.xsd">
		<message-version>1.2</message-version>
		<orcid-profile>
			<orcid-activities>
				<affiliations>
					<affiliation visbility="public">
						<type>'.$type.'</type>
						<organization>
							<name>University of Pittsburgh</name>
							<address>
								<city>Pittsburgh</city>
								<region>PA</region>
								<country>US</country>
							</address>
							<disambiguated-organization>
								<disambiguated-organization-identifier>'.PITT_AFFILIATION_ID.'</disambiguated-organization-identifier>
								<disambiguation-source>'.PITT_AFFILIATION_KEY.'</disambiguation-source>
							</disambiguated-organization>
						</organization>
					</affiliation>
				</affiliations>
			</orcid-activities>
		</orcid-profile>
	</orcid-message>';
	$curl = curl_init();
	curl_setopt_array(
		$curl,
		array(
			CURLINFO_HEADER_OUT => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_URL => OAUTH_API_URL.$orcid.'/affiliations',
			CURLOPT_HTTPHEADER => array('Content-Type: application/orcid+xml', 'Content-Length: '.strlen($payload), 'Authorization: Bearer '.$token),
		)
	);
	$result = curl_exec($curl);
	$info = curl_getinfo($curl);
	// why is the result sometimes blank?
	return (($info['http_code'] == 201 || $info['http_code'] == 200) && ($result === '' || read_affiliation($result)));
}

/**
 * Check whether the Pitt Affiliation exists in the ORCID profile
 * 
 * @param string $xml
 * @return boolean
 */
function read_affiliation($xml, $type) {
	try {
		$doc = new DOMDocument();
		$doc->loadXML($xml);
		$xpath = new DOMXPath($doc);
		$xpath->registerNamespace('o', "http://www.orcid.org/ns/orcid");
		// Check on an affiliation with matches the $type and which has a disambiguation source matching our key/value pair
		$elements = $xpath->query('//o:affiliation[o:type[text()="'.$type.'"] and o:organization/o:disambiguated-organization[o:disambiguation-source[text()="'.PITT_AFFILIATION_KEY.'"] and o:disambiguated-organization-identifier[text()="'.PITT_AFFILIATION_ID.'"]]]');
	} catch (Exception $e) {
		error_log($e);
		return false;
	}
	return ($elements && $elements->length);
}


/**
 * Check whether the Pitt ID exists in the ORCID profile
 * 
 * @param string $xml
 * @return boolean
 */
function read_extid($xml) {
	try {
		$doc = new DOMDocument();
		$doc->loadXML($xml);
		$xpath = new DOMXPath($doc);
		$xpath->registerNamespace('o', "http://www.orcid.org/ns/orcid");
		// Check on an external ID with our common name
		$elements = $xpath->query('//o:external-id-common-name[text()="'.PITT_EXTID_NAME.'"]');
	} catch (Exception $e) {
		error_log($e);
		return false;
	}
	return ($elements && $elements->length);
}

/**
 * Verify that an ORCID has our custom fields set.  If unset, set them.
 * 
 * @param string $orcid
 * @param string $token
 * @param array $affiliations
 * @return true if record could be validated; false if any error occurred
 */
function validate_record($orcid, $token, $user, $affiliations = array()) {
	$profile = read_profile($orcid, $token);
	if ($profile) {
		if (!read_extid($profile)) {
			if (!write_extid($orcid, $token, $user)) {
				return false;
			}
		}
		foreach ($affiliations as $affiliation) {
			if (!read_affiliation($profile, $affiliation)) {
				if (!write_affiliation($orcid, $token, $affiliation)) {
					return false;
				}
			}
		}
		return true;
	}
	return false;
}
/**
 * Generate an error page based on a HTTP error code and message
 * @param string $error
 */
function die_with_error_page($error) {
	header(SERVER_PROTOCOL.' '.$error);
	$html = array(
		'header' => 'ORCID@Pitt Problem',
		'p' => array('Our apologies. Something went wrong and we were unable to create an ORCID iD for you and link it to the University of Pittsburgh.', 'Please <a href="/connect">try again</a>.', 'If you need assistance with creating your ORCID iD, please contact the ORCID Communications Group (<a href="mailto:orcidcomm@mail.pitt.edu">orcidcomm@mail.pitt.edu</a>).', 'Thank you for your patience.'),
		'error' => array($error.' - '.date("Y-m-d H:i:s")),
	);
	require('../includes/template.php');
	exit();
}

/**
 * Execute an SQL query or die trying
 * 
 * @param object $conn Oracle SQL connection
 * @param string $sql query
 * @param array $binder keyed array of bind variables
 * @return mixed boolean true (UPDATE, DELETE, INSERT) or associative array (SELECT) if successful
 */
function execute_query_or_die($conn, $sql, $binder) {
	$stmt = oci_parse($conn, $sql);
	if ($stmt) {
		foreach ($binder as $k => $v) {
			if (!oci_bind_by_name($stmt, ':'.$k, $binder[$k])) {
				error_log(var_export(oci_error(), true));
				die_with_error_page('500 Database connection error');
			}
		}
		if (oci_execute($stmt)) {
			if (preg_match('/^SELECT/i', $sql)) {
				$rs = oci_fetch_array($stmt, OCI_ASSOC);
				if (is_array($rs)) {
					return $rs;
				} else {
					return array();
				}
			}
		} else {
			error_log(var_export(oci_error(), true));
			die_with_error_page('500 Database connection error');
		}
	} else {
		error_log(var_export(oci_error(), true));
		die_with_error_page('500 Database connection error');
	}
	return true;
}

?>
