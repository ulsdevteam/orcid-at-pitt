<?php
require('includes/constants.php');
$html = array(
	'header' => 'Testing',
	'p' => array('Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.', '<a href="connect/" class="actionbutton">Link ORCID @ Pitt</a>'),
	'orcid_url' => ORCID_LOGIN,
);
require('includes/template.php');
?>
