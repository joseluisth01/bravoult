<?php
require_once('../../../wp-load.php');

if (!session_id()) {
    session_start();
}

echo "Session ID: " . session_id() . "\n";
echo "Session data: " . print_r($_SESSION ?? [], true) . "\n";
echo "User logged: " . (isset($_SESSION['reservas_user']) ? 'YES' : 'NO') . "\n";

if (isset($_SESSION['reservas_user'])) {
    echo "User: " . $_SESSION['reservas_user']['username'] . "\n";
    echo "Role: " . $_SESSION['reservas_user']['role'] . "\n";
}

echo "AJAX URL: " . admin_url('admin-ajax.php') . "\n";
echo "Nonce: " . wp_create_nonce('reservas_nonce') . "\n";
?>