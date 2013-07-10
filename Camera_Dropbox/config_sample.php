<?php

// Modifier ces paramètres pour correspondre à votre adresse email et au nombre de caméra à mettre dans l'email
define("DEF_EMAIL_TO", "adresse@gmail.com"); 
define("DEF_EMAIL_FROM", "adresse@gmail.com");
define("NB_MAX_CAM", 2);

// you have to create an app at https://www.dropbox.com/developers/apps and enter details below:
define("DROPBOX_APP_KEY", "xxxxxxxxxx");
define("DROPBOX_APP_SECRET", "xxxxxxxxxxx");

// Repertoire sur la dropbox. Basé sur le repertoire Public. Le répertoire doit exister.
define("DROPBOX_UPLOAD_DIR", "Camera/");

///////////////////////////////////////////////////////////////////////////////////////////////////
//// Exemple d'url de caméra "standard"
// *** Foscam / Apexis / Heden type
// $full_url[x] = "http://".$cam_ip.":".$cam_port."/snapshot.cgi?user=".$cam_user."&pwd=".$cam_pwd;
//
// *** Edimax type
// $full_url[x] = "http://".$cam_user.":".$cam_pwd."@".$cam_ip.":".$cam_port."/jpg/image.jpg";
//
// *** Trendnet type
// $full_url[x] = "http://".$cam_user.":".$cam_pwd."@".$cam_ip.":".$cam_port."/cgi/jpg/image.cgi";


// Définition des paramètres de chaque caméra

// Caméra 0 - Edimax
$cam_user  = "admin";
$cam_pwd   = "1234";
$cam_ip    = "000.000.000.000";
$cam_port  = "80";

$imagetype[0] = "jpg";
$full_url[0]  = "http://".$cam_user.":".$cam_pwd."@".$cam_ip.":".$cam_port."/jpg/image.jpg";
$img_name[0]="image0_".date('Ymd_his').".".$imagetype[0];


// Caméra 1 - Foscam
$cam_user  = "admin";
$cam_pwd   = "1234";
$cam_ip    = "000.000.000.000";
$cam_port  = "80";

$imagetype[1] = "jpg";
$full_url[1]  = "http://".$cam_ip.":".$cam_port."/snapshot.cgi?user=".$cam_user."&pwd=".$cam_pwd;
$img_name[1]="image1_".date('Ymd_his').".".$imagetype[1];
