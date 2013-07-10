<?php
/** 
 * DropPHP - A simple Dropbox client that works without cURL.
 *
 * http://fabi.me/en/php-projects/dropphp-dropbox-api-client/
 * 
 * @author     Fabian Schlieper <fabian@fabi.me>
 * @copyright  Fabian Schlieper 2012
 * @version    1.0
 * @license    See license.txt
 *
 */
 
require_once(dirname(__FILE__)."/OAuthSimple.php");

class DropboxClient {
	
	const API_URL = "https://api.dropbox.com/1/";
	const API_CONTENT_URL = "http://api-content.dropbox.com/1/";
	
	const BUFFER_SIZE = 524288;

	private $appParams;	
	private $consumerToken;
	
	private $requestToken;
	private $accessToken;
	
	private $locale;
	private $rootPath;
	
	function __construct ($app_params, $locale = "en"){
		$this->appParams = $app_params;
		$this->consumerToken = array('t' => $this->appParams['app_key'], 's' => $this->appParams['app_secret']);		
		$this->locale = $locale;
		$this->rootPath = empty($app_params['app_full_access']) ? "sandbox" : "dropbox";
		
		$this->requestToken = null;
		$this->accessToken = null;
	}	
	
	// ##################################################
	// Authorization
	
	function GetRequestToken()
	{
		if(!empty($this->requestToken))
			return $this->requestToken;
		
		$rt = $this->authCall("oauth/request_token");
		if(empty($rt))
			throw new DropboxException('Could not get request token!');

		return ($this->requestToken = array('t'=>$rt['oauth_token'], 's'=>$rt['oauth_token_secret']));
	}
	
	function GetAccessToken($request_token)
	{
		if(!empty($this->accessToken)) return $this->accessToken;
		
		if(empty($request_token)) throw new DropboxException('Passed request token is invalid.');	
		
		$at = $this->authCall("oauth/access_token", $request_token);
		if(empty($at))
			throw new DropboxException('Could not get access token!');
		
		return ($this->accessToken = array('t'=>$at['oauth_token'], 's'=>$at['oauth_token_secret']));
	}
	
	function SetAccessToken($token)
	{
		if(empty($token['t']) || empty($token['s'])) throw new DropboxException('Passed invalid access token.');
			$this->accessToken = $token;
	}	
	
	function IsAuthorized()
	{
		if(empty($this->accessToken)) return false;		
		return true;
	}
	
	function BuildAuthorizeUrl($return_url)
	{
		$rt = $this->GetRequestToken();		
		if(empty($rt)) return false;		
		return "https://www.dropbox.com/1/oauth/authorize?oauth_token=".$rt['t']."&oauth_callback=".urlencode($return_url);
	}
			
	function GetAccountInfo()
	{
		return $this->apiCall("account/info", "GET", array('hash'=>true));
	}
	
	
	// ##################################################
	// API Functions
	
	
    /** 
	 * Get file list of a dropbox folder.
	 * 
	 * @access public
	 * @param string $dropbox_path Dropbox path of the folder
	 */		
	public function GetFiles($dropbox_path='', $recursive=false)
	{
		if(is_object($dropbox_path) && !empty($dropbox_path->path)) $dropbox_path = $dropbox_path->path;
		return $this->getFileTree($dropbox_path, $recursive ? 1000 : 0);
	}
	
    /** 
	 * Get file or folder metadata
	 * 
	 * @access public
	 * @param dropbox_path (String) Dropbox path of the file or folder
	 */		
	public function GetMetadata($dropbox_path)
	{
		if(is_object($dropbox_path) && !empty($dropbox_path->path)) $dropbox_path = $dropbox_path->path;
		return $this->apiCall("metadata/$this->rootPath/$dropbox_path", "GET");
	}
	
    /** 
	 * Download a file to the webserver
	 * 
	 * @access public
	 * @param string|object $dropbox_file Dropbox path or metadata object of the file to download. 
	 * @param string $dest_path Local path for destination
	 * @param int $revision Optional. The revision of the file to retrieve. This defaults to the most recent revision.
	 * @return object Dropbox file metadata
	 */	
	public function DownloadFile($dropbox_file, $dest_path='', $revision=-1)
	{
		if(is_object($dropbox_file) && !empty($dropbox_file->path)) $dropbox_file = $dropbox_file->path;
		
		if(empty($dest_path)) $dest_path = basename($dropbox_file);
		
		$url = $this->cleanUrl(self::API_CONTENT_URL."/files/$this->rootPath/$dropbox_file");
		$content = ($revision != -1) ? http_build_query(array('rev' => $revision)) : null;
		$context = $this->createRequestContext($url, "GET", $content);

		$rh = @fopen($url, 'rb', false, $context); // read binary
		if($rh === false)
			throw new DropboxException("HTTP request to $url failed!");
		$fh = @fopen($dest_path, 'wb'); // write binary
		if($fh === false) {
			@fclose($rh);
			throw new DropboxException("Could not create file $dest_path !");
		}
		
		$size = 0;
		while (!feof($rh)) {
		  if(($s=fwrite($fh, fread($rh, self::BUFFER_SIZE))) === false) {
			@fclose($rh);
			@fclose($fh);
			throw new DropboxException("Writing to file $dest_path failed!'");
		  }
		  $size += $s;
		}
		
		// get file meta from HTTP header
		$s_meta = stream_get_meta_data($rh);		
		$meta = json_decode(substr(reset(array_filter($s_meta['wrapper_data'], create_function('$s', 'return strpos($s, "x-dropbox-metadata:") === 0;'))), 20));
				
		fclose($rh);
		fclose($fh);
		
		if($meta->bytes != $size)
			throw new DropboxException("Download size mismatch!");
			
		return $meta;
	}
	
    /** 
	 * Upload a file to dropbox
	 * 
	 * @access public
	 * @param src_path (String) Local file to upload
	 * @param dropbox_path (String) Dropbox path for destionation
	 * @return object Dropbox file metadata
	 */	
	public function UploadFile($src_file, $dropbox_path='')
	{
		if(empty($dropbox_path)) $dropbox_path = basename($src_file);
		elseif(is_object($dropbox_path) && !empty($dropbox_path->path)) $dropbox_path = $dropbox_path->path;
				
		//$params = array('locale' => $this->locale); //, 'overwrite' => $overwrite ? 'true' : 'false');
		//$query = http_build_query($params);
		$url = $this->cleanUrl(self::API_CONTENT_URL."/files_put/$this->rootPath/$dropbox_path"); // ?$query TODO causes a 403 forbidden error..
		$content =& file_get_contents($src_file);
		if(strlen($content) == 0) throw new DropboxException("Could not read file $src_file !");
		$context = $this->createRequestContext($url, "PUT", $content);
		return json_decode(file_get_contents($url, false, $context));
	}
	
	
	
	function getFileTree($path="", $max_depth = 0, $depth=0)
	{
		static $files;
		if($depth == 0) $files = array();
		
		$dir = $this->apiCall("metadata/$this->rootPath/$path", "GET");
		
		foreach($dir->contents as $item)
		{
			$files[trim($item->path,'/')] = $item;
			if($item->is_dir && $depth < $max_depth)
			{
				$this->getFileTree($item->path, $max_depth, $depth+1);
			}
		}
		
		return $files;
	}
	
	function createRequestContext($url, $method, &$content, $oauth_token=-1)
	{
		if($oauth_token === -1)
			$oauth_token = $this->accessToken;  
		
		$oauth = new OAuthSimple($this->consumerToken['t'],$this->consumerToken['s']);
				
		$params = array();
		
		if(empty($oauth_token) && !empty($this->accessToken))
			$oauth_token = $this->accessToken;
			
		if(!empty($oauth_token)) {
			$oauth->signatures(array('oauth_secret'=>$oauth_token['s']));
			$params['oauth_token'] = $oauth_token['t'];	
		}
		
		$signed = $oauth->sign(array(
			'action' => $method,
      'path'=> $url,
			'parameters'=> $params));
		
		$header = "Authorization: ".$signed['header']."\r\n";
		//echo "<br><br>SBS: $signed[sbs]<br><br>";
		
		$http_context = array(
			'method'=>$method,
			'header'=> $header);
		if(!empty($content)) {
			$http_context['header'] .= "Content-Length: ".strlen($content)."\r\n";
			$http_context['header'] .= "Content-Type: application/octet-stream\r\n";			
			$http_context['content'] =& $content;
		}
				
		return stream_context_create(array('http'=>$http_context));
	}
	
	function authCall($path, $request_token=null)
	{
		$url = $this->cleanUrl(self::API_URL.$path);
		$dummy = null;
		$context = $this->createRequestContext($url, "POST", $dummy, $request_token);	
		$data = array();
		parse_str(file_get_contents($url, false, $context), $data);
		return $data;
	}
	
	
	function apiCall($path, $method, $params=array())
	{
		$url = $this->cleanUrl(self::API_URL.$path);               

		$content = http_build_query(array_merge(array('locale'=>$this->locale), $params));

		$context = $this->createRequestContext($url, $method, $content);
		
		$val = file_get_contents($url, false, $context);

		return json_decode($val);
	}
		

	function cleanUrl($url) {
		return str_replace(' ', '%20', substr($url,0,8).str_replace('//','/', str_replace('\\','/',substr($url,8))));
	}
}

class DropboxException extends Exception {
	
	public function __construct($err, $isDebug = FALSE) 
	{
		$this->message = $err;
		self::log_error($err);
		if ($isDebug)
		{
			self::display_error($err, TRUE);
		}
	}
	
	public static function log_error($err)
	{
		error_log($err, 0);		
	}
	
	public static function display_error($err, $kill = FALSE)
	{
		print_r($err);
		if ($kill === FALSE)
		{
			die();
		}
	}
}
