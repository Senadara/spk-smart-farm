<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=smartfarm", "user", "password");

// Add berat column
$pdo->exec("ALTER TABLE `panen` ADD COLUMN `berat` DECIMAL(10,2) NULL AFTER `jumlah`");
echo "Column berat added successfully.\n";
