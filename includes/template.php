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
  <div id="foot-left">
    <h2>Go to Orcid</h2>
    <p>Sed non massa non dolor volutpat imperdiet. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras pretium aliquam sem eget porta. Etiam ac arcu sem.</p>
    <p><a href="<?php echo $html['orcid_url']; ?>" class="footer-button">ORCID website.</a></p>
  </div>
  <div id="foot-center">
    <h2>Get Help</h2>
    <p>Aenean risus dui, suscipit eget vestibulum quis, fermentum vel est. Proin eget viverra tortor. Nullam dignissim risus eu faucibus congue.</p>
    <p><a href="#" class="footer-button">Get help.</a></p>
  </div>
  <div id="foot-right">
    <h2>Pitt Orcid</h2>
    <p>Sed laoreet lacus non metus iaculis molestie. Sed dictum blandit ante, eu porttitor massa fermentum blandit. </p>
    <p><a href="http://www.library.pitt.edu/orcid" class="footer-button">Pitt Orcid.</a></p>
  </div>
</footer>
</div><!-- /end wrapper -->
</body>
</html>
