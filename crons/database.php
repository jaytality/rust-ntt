<?php

include "config.php";

$mysqli = mysqli_init();
$mysqli->ssl_set(NULL, NULL, "/etc/ssl/certs/ca-certificates.crt", NULL, NULL);
if(!$mysqli->real_connect(getCfg("database.host"), getCfg("database.user"), getCfg("database.pass"), getCfg("database.name"))) {
    die("Connection Error (" . mysqli_connect_error() . ") " . mysqli_connect_error());
}

$mysqli->query("CREATE TABLE IF NOT EXISTS 'rust_wipes'");

$mysqli->close();
