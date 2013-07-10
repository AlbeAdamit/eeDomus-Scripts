<?php

require_once("DropboxClient.php");
require_once("config.php");

function store_token($token, $name)
{
	file_put_contents("tokens/$name.token", serialize($token));
}

function load_token($name)
{
	if(!file_exists("tokens/$name.token")) return null;
	return @unserialize(@file_get_contents("tokens/$name.token"));
}

function delete_token($name)
{
	@unlink("tokens/$name.token");
}

$dropbox = new DropboxClient(array(
	'app_key'      => DROPBOX_APP_KEY, 
	'app_secret'   => DROPBOX_APP_SECRET,
	'app_full_access' => true,
),'fr');

// Gestion de l'authentification Dropbox
$access_token = load_token("access");

if(!empty($access_token)) 
{
	$dropbox->SetAccessToken($access_token);
	print_r($access_token);
}
else
if(!empty($_GET['auth_callback'])) // are we coming from dropbox's auth page?
{
	// then load our previosly created request token
	$request_token = load_token($_GET['oauth_token']);
	if(empty($request_token)) die('Request token not found!');
	
	// get & store access token, the request token is not needed anymore
	$access_token = $dropbox->GetAccessToken($request_token);	
	store_token($access_token, "access");
	delete_token($_GET['oauth_token']);
}

// checks if access token is required
if(!$dropbox->IsAuthorized())
{
	// redirect user to dropbox auth page
	$return_url = "http://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."?auth_callback=1";
	$auth_url = $dropbox->BuildAuthorizeUrl($return_url);
	$request_token = $dropbox->GetRequestToken();
	store_token($request_token, $request_token['t']);
	die("Authentication required. <a href='$auth_url'>Click here.</a>");
}

// Apartir d'ici, nous somme connecté au compte Dropbox !
// On récupére l'identifiant pour construire l'url vers les fichiers images
$user = $dropbox->GetAccountInfo();

$dropbox_url = "https://dl.dropbox.com/u/".$user->uid."/".DROPBOX_UPLOAD_DIR;



// Lecture des paramètres du mail à envoyer
$subject = stripslashes($_GET["subject"]);
$content = stripslashes($_GET["content"]); 
if (isset($_GET["to"])) $to = $_GET["to"]; else $to = DEF_EMAIL_TO;
if (isset($_GET["from"])) $from = $_GET["from"]; else $from = DEF_EMAIL_FROM; 


// Construction du mail
// Partie 1 - Texte du message
$boundary = md5(uniqid(time()));
$headers .= "MIME-Version: 1.0\n";
$headers .="Content-Type: multipart/alternative; boundary=\"$boundary\"\n";
$headers .= "From: ".$from."\r\n";
$multipart = '';
$multipart .= "--$boundary\n";
$kod = "utf8";
$multipart .= "Content-Type: text/html; charset=\"$kod\"\n";
$multipart .= "Content-Transfer-Encoding: quoted-printable\n\n";
$multipart .= "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.0//EN\">\n<html>\n<head>\n</head>\n<body>\n$content<br>\n\n";


for ($i=0; $i<NB_MAX_CAM; $i++)
{
  $multipart .= '<img width="400" src="'.$dropbox_url.$img_name[$i].'" alt="'.$img_name[$i].'"><br>'."\n";
}
$multipart .= "</body>\n</html>\n\n";


// Partie 2 - Les images sont maintenant uploadées sur le compte Dropbox
for ($i=0; $i<NB_MAX_CAM; $i++)
{
  $content = file_get_contents($full_url[$i]);
  $tmpfilename = dirname(__FILE__) ."/tmp/".$img_name[$i];

  file_put_contents($tmpfilename, $content); 
  
  if (file_exists($tmpfilename))
  {
    $dropbox->UploadFile($tmpfilename, "Public/".DROPBOX_UPLOAD_DIR.$img_name[$i]);
  }
  else 
    echo "Impossible d'uploader l'image, elle n'est pas disponible";
  
}

// Le mail est expédié avec les images linkés directement dans le mail
$mail_arr = explode(";", $to);
                    
reset($mail_arr);
foreach($mail_arr as $mail_to)
{
  mail($mail_to, $subject, $multipart, $headers); 
  echo "Mail ok pour : ".$mail_to;
}



?>



