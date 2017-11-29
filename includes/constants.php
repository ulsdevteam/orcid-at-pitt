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
define('OAUTH_SCOPE', '/read-limited /person/update /activities/update');
define('ORCID_PRODUCTION', false); // sandbox; change to true when ready to leave the sandbox

if (ORCID_PRODUCTION) {
	// production credentials
	define('OAUTH_CLIENT_ID', 'REPLACED_KEY');
	define('OAUTH_CLIENT_SECRET', 'REPLACED_TOKEN');
	// production endpoints
	define('OAUTH_AUTHORIZATION_URL', 'https://orcid.org/oauth/authorize');
	define('OAUTH_TOKEN_URL', 'https://orcid.org/oauth/token'); // members
	define('OAUTH_API_URL', 'https://api.orcid.org/v2.0/'); // members
	define('ORCID_LOGIN', 'https://orcid.org/my-orcid');
	// production values
	define('OAUTH_REDIRECT_URI', 'REPLACED_URL'); // URL of the target script
	define('EXTERNAL_WEBHOOK', 'REPLACED_URL); // URL of the target script
	define('DB_TNS', '(DESCRIPTION=(ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = REPLACED_DNS)(PORT = 1521)))(CONNECT_DATA =(SERVICE_NAME = REPLACED_SERVICE)))'); // TNS for the Oracle Connection
	define('DB_PASSWD', 'REPLACED_PASSWORD'); // Oracle Database password
} else {
	// sandbox credentials
	define('OAUTH_CLIENT_ID', 'REPLACED_KEY');
	define('OAUTH_CLIENT_SECRET', 'REPLACED_TOKEN');
	// sandbox endpoints
	define('OAUTH_AUTHORIZATION_URL', 'https://sandbox.orcid.org/oauth/authorize');
	define('OAUTH_TOKEN_URL', 'https://sandbox.orcid.org/oauth/token'); // members
	define('OAUTH_API_URL', 'https://api.sandbox.orcid.org/v2.0/'); // members
	define('ORCID_LOGIN', 'https://sandbox.orcid.org/my-orcid');
	// development values
	define('OAUTH_REDIRECT_URI', 'REPLACED_URL'); // URL of the target script
	define('EXTERNAL_WEBHOOK', 'REPLACED_URL'); // URL of the target script
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
			CURLOPT_URL => OAUTH_API_URL.$orcid.'/record',
			CURLOPT_HTTPHEADER => array('Content-Type: application/vdn.orcid+xml', 'Authorization: Bearer '.$token),
		)
	);
	$result = curl_exec($curl);	 //fetches all the records of a user
	$info = curl_getinfo($curl);
	if ($info['http_code'] == 200) {
		return $result;
	} else {
		return false;
	}
}

/**
 * Write the External ID to ORCID if external id was not created earlier
 * 
 * @param string $orcid ORCID Id
 * @param string $token ORCID access token
 * @param string $id External ID
 * @return boolean success
 */
function write_extid($orcid, $token, $id) {
	$payload = '<?xml version="1.0" encoding="UTF-8"?>
	<external-identifier:external-identifier 
		xmlns:external-identifier="http://www.orcid.org/ns/external-identifier" 
		xmlns:common="http://www.orcid.org/ns/common" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
		xsi:schemaLocation="http://www.orcid.org/ns/external-identifier ../person-external-identifier-2.0.xsd">				
			<common:external-id-type>'.PITT_EXTID_NAME.'</common:external-id-type>
			<common:external-id-value>'.$id.'</common:external-id-value>
			<common:external-id-url>'.EXTERNAL_WEBHOOK.'?id='.$id.'</common:external-id-url>
			<common:external-id-relationship>self</common:external-id-relationship>
	</external-identifier:external-identifier>';
	$curl = curl_init();
	curl_setopt_array(
		$curl,
		array(
			CURLINFO_HEADER_OUT => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_URL => OAUTH_API_URL.$orcid.'/external-identifiers',
			CURLOPT_HTTPHEADER => array('Content-Type: application/orcid+xml', 'Content-Length: '.strlen($payload), 'Authorization: Bearer '.$token),
		)
	);
	$result = curl_exec($curl);
	$info = curl_getinfo($curl);
	// why is this code usually 200?
	return ($info['http_code'] == 201 || $info['http_code'] == 200);
}

/**
 * Write the Affiliation to ORCID if affiliations were not created earlier
 * 
 * @param string $orcid ORCID Id
 * @param string $token ORCID access token
 * @param string $type Affiliation type (invalid types will be ignored)
 * @return boolean success
 */
function write_affiliation($orcid, $token, $type) {
	if ($type !== 'employment' && $type !== 'education') {
		return true;
	}
	$commonParams = '<common:name>University of Pittsburgh</common:name>
			 <common:address>
				<common:city>Pittsburgh</common:city>
				<common:region>PA</common:region>
				<common:country>US</common:country>
			 </common:address>
			 <common:disambiguated-organization>
				<common:disambiguated-organization-identifier>'.PITT_AFFILIATION_ID.'</common:disambiguated-organization-identifier>
				<common:disambiguation-source>'.PITT_AFFILIATION_KEY.'</common:disambiguation-source>
			 </common:disambiguated-organization>';
		
	if($type == 'employment') {
		$payload = '<?xml version="1.0" encoding="UTF-8"?>
		<employment:employment visibility="public"		  
		 xmlns:employment="http://www.orcid.org/ns/employment" xmlns:common="http://www.orcid.org/ns/common"
		 xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		 xmlns="http://www.orcid.org/ns/orcid"		 
		 xsi:schemaLocation="http://www.orcid.org/ns/employment ../employment-2.0.xsd">
		<employment:organization>
			'.$commonParams.'
		</employment:organization>
		 </employment:employment>';
	} else {
		$payload = '<?xml version="1.0" encoding="UTF-8"?>
		<education:education visibility="public" 
		 xmlns:education="http://www.orcid.org/ns/education"
		 xmlns:common="http://www.orcid.org/ns/common"
		 xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"		 
		 xmlns="http://www.orcid.org/ns/orcid"
		 xsi:schemaLocation="http://www.orcid.org/ns/education ../education-2.0.xsd">
		<education:organization>
			'.$commonParams.'
		</education:organization>
		</education:education>';
	}
	$curl = curl_init();
	curl_setopt_array(
		$curl,
		array(
			CURLINFO_HEADER_OUT => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_URL => OAUTH_API_URL.$orcid.'/'.$type,
			CURLOPT_HTTPHEADER => array('Content-Type: application/vnd.orcid+xml', 'Content-Length: '.strlen($payload), 'Authorization: Bearer '.$token),
		)
	);
	$result = curl_exec($curl);
	$info = curl_getinfo($curl);
	return ($info['http_code'] == 201 || $info['http_code'] == 200);
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
		$xpath->registerNamespace('o', "http://www.orcid.org/ns/activities");
		$xpath->registerNamespace('e', "http://www.orcid.org/ns/".$type);
		$xpath->registerNamespace('c', "http://www.orcid.org/ns/common");
		// Check if disambiguation source exists for education or employment by matching with our disambiguation-source and disambiguation-organization-identifier
		$elements = $xpath->query('//e:'.$type.'-summary[e:organization/c:disambiguated-organization[c:disambiguation-source[text()="'.PITT_AFFILIATION_KEY.'"] and c:disambiguated-organization-identifier[text()="'.PITT_AFFILIATION_ID.'"]]]'); 
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
		$xpath->registerNamespace('o', "http://www.orcid.org/ns/common");
		// Check on an external ID with our common name
		$elements = $xpath->query('//o:external-id-type[text()="'.PITT_EXTID_NAME.'"]');
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
		// The profile should have an External ID unless the the FERPA flag is present on a student-only record
		if ((!in_array('FERPA', $affiliations) || in_array('employment', $affiliations)) && !read_extid($profile)) {
			if (!write_extid($orcid, $token, $user)) {
				return false;
			}
		}
		// The profile should have each affiliation, unless the FERPA flag blocks a student affiliation
		// It is up to the caller to only pass the desired affilaitions (e.g. writing employment but not education)
		// write_affiliation will filter only valid ORCID profile affiliations (e.g. ignoring FERPA as a key)
		foreach ($affiliations as $affiliation) {
			if ($affiliation == 'education' && in_array('FERPA', $affiliations)) {
				continue;
			}
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
