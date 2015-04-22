<?php
require('includes/constants.php');
$html = array(
	'header' => 'Create and connect your ORCID ID to the University of Pittsburgh',
	'p' => array('Here you can create a new ORCID ID and connect it to Pitt.', 'If you already have an ORCID ID, please connect it to Pitt.', '<a href="connect/" class="actionbutton">Create and Connect Your ORCID ID</a>'),
	'orcid_url' => ORCID_LOGIN,
);
require('includes/template.php');
?>
