<?php
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

$host = '127.0.1.31'; 
$user = 'root';
$pass = '';
$base = 'rekruiting';

$link = mysqli_connect($host, $user, $pass, $base);
if (!$link) {
    die('Ошибка подключения: ' . mysqli_connect_error());
}
mysqli_set_charset($link, 'utf8mb4');

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function current_user($link) {
    if (!is_logged_in()) return null;
    $id = (int)$_SESSION['user_id'];
    $res = mysqli_query($link, "SELECT * FROM users WHERE id = $id");
    return mysqli_fetch_assoc($res);
}