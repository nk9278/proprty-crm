<?php
require_once __DIR__ . '/includes/session.php';
$_SESSION['rate_limits'] = [];
echo "Rate limits reset.\n";
