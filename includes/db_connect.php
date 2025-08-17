<?php
$host = '127.0.0.1';
<<<<<<< HEAD
$dbname = 'clinic_demo';
$username = 'noman';
$password = 'noman';
$base_url = 'http://127.0.0.1/pharmacy_management_system/';
=======
$dbname = 'hospital_management_system';
$username = 'noman';
$password = 'noman';
$base_url = 'http://127.0.0.1/Hospital_Management_System/';
>>>>>>> b9c44d5e7e4170886bd4bbc2857f9a4d72a84279


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die();
}
?> 