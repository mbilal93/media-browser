<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Media Browser · Demo &amp; samples</title>
  <link rel="stylesheet" href="assets/media-browser.css">
  <link rel="stylesheet" href="assets/demo-guide.css">
</head>
<body>
<main class="demo-shell">
  <header class="masthead"><a class="brand" href="./">Media Browser</a><span>PHP · Laravel · CodeIgniter 4</span></header>
  <section class="hero">
    <span class="eyebrow">DEMO &amp; INTEGRATION SAMPLES</span>
    <h1>Your images.<br>Your PHP project.</h1>
    <p>Upload, pick and reorder images in a normal form. Choose a sample below to see the picker in action or connect it to your application.</p>
    <a class="demo-link" href="playground.php">Open interactive demo →</a>
  </section>
  <nav class="sample-grid" aria-label="Integration samples">
    <article class="sample-card"><span class="step">01 / LIVE FORM</span><h2>Core PHP</h2><p>A working form with a sortable gallery, hidden image IDs and a PHP array returned on submit. No framework required.</p><a class="demo-link" href="form-example.php">Try core PHP form →</a><a href="sample.php?framework=core-php">Setup &amp; source code</a></article>
    <article class="sample-card"><span class="step">02 / FRAMEWORK GUIDE</span><h2>Laravel</h2><p>Connect the service provider, protected API route and Blade image field to your existing Laravel application.</p><a class="demo-link" href="sample.php?framework=laravel">View Laravel sample →</a><span class="sample-note">Includes setup, Blade source and form submission.</span></article>
    <article class="sample-card"><span class="step">03 / FRAMEWORK GUIDE</span><h2>CodeIgniter 4</h2><p>Configure the adapter, CSRF route and reusable gallery field in your existing CodeIgniter application.</p><a class="demo-link" href="sample.php?framework=codeigniter">View CodeIgniter sample →</a><span class="sample-note">Includes setup, view source and form submission.</span></article>
  </nav>
  <section class="demo-guide" aria-labelledby="start-title">
    <span class="eyebrow">QUICK START</span><h2 id="start-title">Prepare the live demos</h2>
    <ol><li>Edit <code>config.php</code> to choose SQLite or MySQL and private storage.</li><li>Run the following commands from the package directory.</li><li>Open the interactive demo, choose images, reorder them and submit the form.</li></ol>
    <pre><code>php bin/setup.php
php bin/seed.php
php -S 127.0.0.1:8088 -t public</code></pre>
    <p>Serve <code>public/</code> and open <code>http://127.0.0.1:8088/</code>. For a password-protected library, sign in through the <a href="playground.php">interactive demo</a> first. Framework samples show code to install in a host application; the core PHP form runs here.</p>
  </section>
  <footer>Media Browser · PHP 8.1+ · SQLite &amp; MySQL</footer>
</main>
</body>
</html>
