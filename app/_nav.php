<?php
// Simple site navigation partial with active-state detection
 $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
 $script = basename($path);
 // active detection: treat root and index.php and generate.php as Generate
 $isGenerate = $script === '' || in_array($script, ['index.php','generate.php'], true);
 $isUpload = in_array($script, ['upload.php','upload_submit.php','upload_api.php'], true);
?>
<header class="site-header">
  <nav class="site-nav" aria-label="Main navigation">
    <a class="nav-link <?= $isGenerate ? 'active' : '' ?>" href="/">Generate</a>
    <a class="nav-link <?= $isUpload ? 'active' : '' ?>" href="/upload.php">Upload</a>
  </nav>
</header>
