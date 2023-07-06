<?php

include "config.php";

$mysqli = mysqli_init();
$mysqli->ssl_set(NULL, NULL, "/etc/ssl/certs/ca-certificates.crt", NULL, NULL);
if(!$mysqli->real_connect(getCfg("database.host"), getCfg("database.user"), getCfg("database.pass"), getCfg("database.name"))) {
    die("Connection Error (" . mysqli_connect_error() . ") " . mysqli_connect_error());
}

$tbl_rustwipes = "CREATE TABLE IF NOT EXISTS 'rust_wipes' (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `server` VARCHAR(255) NOT NULL,
    `address` VARCHAR(16) NOT NULL,,
    `size` INT(11) NOT NULL,
    `seed` VARCHAR(255) NOT NULL,
    `action` TEXT NOT NULL,
    `duration` VARCHAR(32) NOT NULL
)";

$result = $mysqli->query($tbl_rustwipes);

$mysqli->close();
