<?php
// config.example.php — OPTIONAL. Copy this file to config.php (same
// folder) to override where farm data is stored. If config.php does not
// exist, the app just stores everything in the data/ folder next to it,
// which is protected from direct web access by data/.htaccess.
//
// The most common reason to use this: some shared hosts let you create
// folders outside your public web root (e.g. a home directory that isn't
// served over HTTP at all). Pointing FARM_DATA_DIR there is one extra
// layer of protection beyond the .htaccess deny rule, since the folder
// is then not web-reachable by any URL, misconfiguration or not.
//
// If you use this, make sure the PHP process (your hosting account's
// user) has write permission to the folder you point at.

define('FARM_DATA_DIR', '/home/yourusername/farm-data');
