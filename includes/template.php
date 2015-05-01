<?php
  if (!isset($html['title'])) {
    $html['title'] = 'ORCID @ Pitt';
  }
  if (!isset($html['error'])) {
    $html['error'] = array();
  }
  if (!isset($html['p'])) {
    $html['p'] = array();
  }
  if (!isset($html['orcid_url'])) {
    $html['orcid_url'] = '';
  }
?><!DOCTYPE html>
<head>
  <meta charset="UTF-8" />
<title><?php echo (isset($html['subtitle']) ? $html['subtitle'].' - ' : '').$html['title']; ?></title>
<link href="/styles/default.css" rel="stylesheet" type="text/css" />
</head>
<body>
<div id="wrapper">
  <header>
    <h1><?php echo (isset($html['subtitle']) ? $html['subtitle'].' - ' : '').$html['title']; ?></h1>
  </header>
  <section>
    <?php
    if (isset($html['header'])) {
    ?>
    <h2><?php echo $html['header']; ?></h2>
    <?php
    }
    foreach ($html['error'] as $e) {
    ?>
      <p class="errormessage"><?php echo $e; ?></p>
    <?php
    }
    foreach ($html['p'] as $p) {
    ?>
      <p><?php echo $p; ?></p>
    <?php
    }
    ?>
  </section>
<footer>
  <div class="foot-col">
    <h2>What is ORCID?</h2>
    <p class="descr">ORCID provides a unique, persistent identifier that can help make your scholarship easier to find and attribute.</p>
    <p class="linker"><a class="actionbutton" href="https://orcid.org/">Learn more about ORCID</a></p>
  </div>
  <div class="foot-col">
    <h2>ORCID@Pitt</h2>
    <p class="descr">Find out more about the benefits of an ORCID iD and the university’s effort to encourage Pitt researchers to create an ORCID iD, use it with their scholarship, and connect their ORCID iD with Pitt.</p>
    <p class="linker"><a class="actionbutton" href="http://www.library.pitt.edu/orcid">Discover ORCID@Pitt</a></p>
  </div>
  <div class="foot-col">
    <h2>Get Help.</h2>
    <p class="descr">If you need help with creating your ORCID iD or have further questions, please contact us.</p>
    <p class="linker"><a class="actionbutton" href="mailto:orcidcomm@mail.pitt.edu">orcidcomm@mail.pitt.edu</a></p>
  </div>
</footer>
</div><!-- /end wrapper -->
</body>
</html>
