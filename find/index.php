<?php
// This URI will be referenced as an EXTERNAL_WEBHOOK (external-id-url) in the data pushed to orcid.org
// We may want to do something interesting with it someday; for now, redirect to find.pitt.edu
header('Location: http://find.pitt.edu/');
?>
