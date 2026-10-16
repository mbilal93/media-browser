<?php
// Require from app/Config/Routes.php after registering the package namespace.
// Keep automatic routing disabled; the controller enforces host authorization.
$routes->match(['get', 'post'], 'media-browser/api', '\MediaBrowser\Bridge\CodeIgniter\MediaController::index', ['filter' => 'csrf']);
