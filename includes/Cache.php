<?php
use Aws\S3\S3Client;


class Cache{
	private $s3_bucket = "";
	
	private $domain = "";
	private $not_found_page = "";
	
	private $home_url;
	
	private $aws;
	private $s3_client;
	private $gcs_client;
	private $gcs_storage;
	private $gh_client;


	private $images = array();
	private $css = array();
	private $js = array();
	private $links = array();

	private $files = array();

	private $message = "";

	function __construct($aws_options, $gh_options) {
		// AWS
		if(isset($aws_options['aws_run']) && $aws_options['aws_run']){
			$this->s3_client = S3Client::factory(array(
				'credentials' => array(
					'key'    => $aws_options['aws_id'],
					'secret' => $aws_options['aws_key']
				),
				'region' => $aws_options['aws_region']
			));

			$this->s3_bucket = $aws_options['aws_bucket'];
			$this->not_found_page = $aws_options['404_page'];
		}

		// GCS
		if(isset($gh_options['gh_run']) && $gh_options['gh_run']){
			$this->gh_client=new BeevaGH($gh_options['gh_token'],$gh_options['gh_user'],$gh_options['gh_repo'],$gh_options['gh_branch']);
		}

		// HOME URL
		$this->home_url = get_site_url();
	}

	public static function check_credentials($key, $secret, $region, $bucket){
		$s3_client = S3Client::factory(array(
			'credentials' => array(
				'key'    => $key,
				'secret' => $secret,
			),
			'region' => $region
		));
		try{
			return $s3_client->doesBucketExist($bucket, false);
		}catch(Exception $e){
			if(is_a($e, "Aws\Common\Exception\InstanceProfileCredentialsException")){
				return array("error" => "InstanceProfileCredentialsException");
			}
		}
	}

	public function get_all_page_links(){
		$this->links[$this->home_url] = 1;
		$this->get_page_links($this->home_url);

		$this->links[$this->home_url."/".$this->not_found_page] = 1;
		$this->get_page_links($this->home_url."/".$this->not_found_page);

		return $this->files;
	}

	public function cache_all(){
		/*
		$can = $this->canonicalize("/wp-content/themes/sentence/css/base.css/../images/layout/icon-video.png");
		echo "/wp-content/themes/sentence/css/base.css/../images/layout/icon-video.png<br>";
		echo $can.": sdfdsf";
		return;
		*/
		//$this->s3_client->clearBucket($this->s3_bucket);

		$this->links[$this->home_url] = 1;
		$this->get_page_links($this->home_url);

		$this->links[$this->home_url."/".$this->not_found_page] = 1;
		$links = $this->get_page_links($this->home_url."/".$this->not_found_page);

		return $links;

		$this->message .= "<b>Uploading pages to S3 bucket -> ".$this->s3_bucket."</b></br>";

		foreach($this->files as $file => $value){
			$this->upload_page_contents($file, $value);
		}
		$this->message .= "</br>SUCCESS!";

		return $this->message;
	}

	public function upload_page_contents_S3($urls){
		set_time_limit(0);
		ignore_user_abort(true);
		foreach($urls as $url){
			$file = $url['url'];
			$type = $url['type'];

			$this->message .= " -> ".$file."</br>";

			$link = ltrim(str_replace($this->home_url,"",$file), "/");

			$file = preg_replace('/index\.html$/', '', $file);

			if($link == ""){
				$link = "index.html";
			}

			if($this->s3_client){
				$content = $this->cache_page($file);
				if($content){
					$this->upload_to_s3($link, $content, $type);
				}
			}
		}
	}

	public function upload_page_contents_GH($urls){
		set_time_limit(0);
		ignore_user_abort(true);
		foreach($urls as $file){

			$this->message .= " -> ".$file."</br>";

			$link = ltrim(str_replace($this->home_url,"",$file), "/");

			$file = preg_replace('/index\.html$/', '', $file);

			if($link == ""){
				$link = "index.html";
			}

			if($this->gh_client){
				$content = $this->cache_page($file);
				if($content){
					$this->gh_client->updateFile($link,$content);
				}
			}
		}
	}

	private function get_page_links($url){

		if($url !== "/" && substr($url, 0, 1) === '/'){
			$url = $this->home_url.$url;
		}

		$content = @file_get_contents($url);
		if($content === false){
			return;
		}else{
			if (substr($url, -1) == '/') { // if ends with .../ convert to  .../index.html
				$index_url = $url."index.html";
			}
			if(!array_key_exists($url, $this->files)){
				$ext_url = rtrim(strtok($url, "#?"), "'\"");

				switch($ext = pathinfo($ext_url, PATHINFO_EXTENSION)){
					case "jpg":
					case "png":
					case "gif":
						$this->files[$url] = 'image/'.$ext;
						if(isset($index_url)){ $this->files[$index_url] = 'image/'.$ext;}
						break;
					case "js":
						$this->files[$url] = 'text/javascript';
						if(isset($index_url)){ $this->files[$index_url] = 'text/javascript';}
						break;
					case "css":
						$this->files[$url] = 'text/css';
						if(isset($index_url)){ $this->files[$index_url] = 'text/css';}
						break;
					default:
						$this->files[$url] = 'text/html';
						if(isset($index_url)){ $this->files[$index_url] = 'text/html';}
				}
			}
		}

		//preg_match_all("#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#", $content, $match);
		preg_match_all("#(?i)\b((?:https?://|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:'\".,<>?������]))#", $content, $match);

		// get absolute links
		/*
		$regex = "((https?|ftp)\:\/\/)?"; // SCHEME
		$regex .= "([a-z0-9+!*(),;?&=\$_.-]+(\:[a-z0-9+!*(),;?&=\$_.-]+)?@)?"; // User and Pass
		$regex .= "([a-z0-9-.]*)\.([a-z]{2,4})"; // Host or IP
		$regex .= "(\:[0-9]{2,5})?"; // Port
		$regex .= "(\/([a-z0-9+\$_-]\.?)+)*\/?"; // Path
		$regex .= "(\?[a-z+&\$_.-][a-z0-9;:@&%=+\/\$_.-]*)?"; // GET Query
		$regex .= "(#[a-z_.-][a-z0-9+\$_.-]*)?"; // Anchor

		preg_match_all("/$regex/", $content, $match);
		*/
		foreach($match[0] as $link){
			if(strpos($link, $this->home_url) !== false){
				$link_url = rtrim(strtok($link, "#"), "'\"");

				if(!array_key_exists($link_url, $this->files)){
					//echo $link_url."<br>";
					//$this->files[$link_url] = 'text/html';
					$this->get_page_links($link_url);
				}
			}
		}


		// get relative links
		$regex = "\/wp\-content(\/([a-z0-9+\$_-]\.?)+)*\/?"; // Path
		$regex .= "(\?[a-z+&\$_.-][a-z0-9;:@&%=+\/\$_.-]*)?"; // GET Query
		$regex .= "(#[a-z_.-][a-z0-9+\$_.-]*)?"; // Anchor

		preg_match_all("/$regex/", $content, $match);

		foreach($match[0] as $link){
			if($link){
				//echo $link."<br>";

				$link_url = rtrim(strtok($link, "#"), "'\"");

				if(!array_key_exists($link_url, $this->files)){
					//echo $link_url."<br>";
					//$this->files[$link_url] = 'text/html';
					$this->get_page_links($link_url);
				}
			}
		}

		// get css URLs

		preg_match_all('#url\([\'"]?.*\.(png|jpg|jpeg|gif)[\'"]?\)#', $content, $match);

		foreach($match[0] as $link){
			if($link){
				if (substr($link, 0, strlen("url(")) == "url(") {
					$link = substr($link, strlen("url("));
				}

				$link = trim($link, "'\")");
				//echo $url."/../".$link."<br>";
				if (substr($url, 0, strlen($this->home_url)) == $this->home_url) {
					$url = substr($url, strlen($this->home_url));
				}
				//echo $url."/../".$link."<br>";
				$link_url = $this->canonicalize($url."/../".$link);
				//echo "CSS -> ".$this->home_url.$link_url."<br>";


				if($link_url && !array_key_exists($link_url, $this->files)){
					//echo $link_url."<br>";
					//$this->files[$link_url] = 'text/html';
					$this->get_page_links($link_url);
				}
			}
		}

	}

	private function myUrlEncode($string) {
		$entities = array('%21', '%2A', '%27', '%28', '%29', '%3B', '%3A', '%40', '%26', '%3D', '%2B', '%24', '%2C', '%2F', '%3F', '%25', '%23', '%5B', '%5D');
		$replacements = array('!', '*', "'", "(", ")", ";", ":", "@", "&", "=", "+", "$", ",", "/", "?", "%", "#", "[", "]");
		return str_replace($entities, $replacements, urlencode($string));
	}

	private function canonicalize($address)
	{
		$address = explode('/', $address);
		$keys = array_keys($address, "..");

		/*
		echo "<pre>";
		var_dump($address);
		var_dump($keys);
		echo "</pre>";
		*/

		foreach($keys AS $keypos => $key){
			array_splice($address, $key - ($keypos * 2 + 1), 2);
		}

		/*
		echo "<pre>";
		var_dump($address);
		echo "</pre>";
		*/

		$address = implode('/', $address);
		$address = str_replace('./', '', $address);

		return $address;
	}

	private function cache_page($url){
		$content = file_get_contents($url);

		if($content === false){
			echo $url." -> NO CONTENT";
			return false;
		}else{
			$content = preg_replace_callback(
				"#(?i)\b((?:https?://|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:'\".,<>?������]))#",
				//"#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[\?\&]\s]|/))#",
				function($match){
					if(strpos($match[0], $this->home_url) !== false){
						//echo $match[0]."</br>";

						//$pos = strrpos($match[0], '/') + 1;
						//$new_link = substr($match[0], 0, $pos) . urlencode(substr($match[0], $pos));
						$new_link = str_replace($this->home_url, "", $match[0]);
						$new_link_array = explode('/', $new_link);
						$new_link_array[sizeof($new_link_array)-1] = rawurlencode($new_link_array[sizeof($new_link_array)-1]);
						$new_link = implode('/', $new_link_array);

						//echo "<b>".$new_link."</b></br>";
						return $new_link;
					}else{
						return $match[0];
					}
				},
				$content
			);

			return $content;
		}
	}

	private function upload_to_s3($url, $content, $mime){
		return $this->s3_client->putObject(
			array(
				'Bucket' 		=> $this->s3_bucket,
				'Key'    		=> $url,
				'Body'   		=> $content,
				'ContentType' 	=> $mime,
				'GrantRead'		=> 'uri="http://acs.amazonaws.com/groups/global/AllUsers"'
			)
		);
	}
}
?>