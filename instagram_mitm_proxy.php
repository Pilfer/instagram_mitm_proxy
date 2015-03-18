<?php

/*

#Make sure to enable modrewrite and route all of your requests for this virtualhost to this file.

#./.htaccess
RewriteEngine on
RewriteCond %{THE_REQUEST} ^[A-Z]{3,9}\ /.*index\.php
RewriteRule ^index.php/?(.*)$ $1 [R=301,L]
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php/$1?%{QUERY_STRING} [L]

*/

ini_set('display_errors', 1); 
error_reporting(E_ALL);

require 'vendor/autoload.php';

use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\HeadersEvent;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Post\PostFile;

function loggit($text){
	$f = fopen("http.txt","a+");
	fputs($f,"\n" . $text);
	fclose($f);
}

//wall the stuff for live debugging
function wall($text){
	shell_exec("wall '" . $text . "'");
}


/*
* Check cookies, post payload, get query params, and file uploads
*/
if(count($_COOKIE) >= 1){
	$cookies = $_COOKIE;
	//wall(json_encode($cookies));

}else{
	$cookies = false;
}


/*
* Check for POST params
*/
if(count($_POST) >= 1){
	$payload = json_encode($_POST);
	$payload_real = $_POST;
}else{
	$payload = false;
}

/*
* Check for GET params
*/
if(count($_GET) >= 1){
	$params = json_encode($_GET);
	$params_real = $_GET;
}else{
	$params = false;
}


//FIIIIILES!
if(count($_FILES) >= 1){
	$files = array();
	foreach($_FILES as $file){
		$files[] = $file;
	}
	wall(json_encode($_FILES));
}else{
	$files = false;
}

$method = $_SERVER['REQUEST_METHOD'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];
$uri = $_SERVER['REQUEST_URI'];
$host = $_SERVER['HTTP_HOST'];
$headers = getallheaders();


//Log array - json_encode() this and push to another array, then dump that out to a file w/ a+ mode
$req_out = array(
	"method" => $method,
	"host" => $host,
	"user-agent" => $user_agent,
	"headers" => $headers,
	"uri" => $uri,
	"params" => $params,
	"payload" => $payload
);

//Request/response log
$req_log = $req_out;
$resp_log = false;




//Mimick the request
$client = new GuzzleHttp\Client();

if($host == "i.instagram.com" || $host == "instagram.com"){
	$rconfig = array();
	if($payload != false){
		$rconfig['body'] = $payload_real;
	}
	if($params != false){
		$rconfig['query'] = $params_real;
	}
	if($cookies != false){
		//$rconfig['cookies'] = $cookies;
	}
	
	
	
	$rconfig['headers'] = $headers;
	$rconfig['verify'] = false;
	
	ob_start();
	var_dump($rconfig);
	$rc = ob_get_clean();
	wall($rc);
	
	//wall("RCONFIG: " . $rc);
	$url = "https://" . $host . $uri;
	wall("Method: $method URL: $url");


	$rconfig['events'] = array(
		'before' => function (BeforeEvent $e) { wall('Before'); },
		'complete' => function (CompleteEvent $e) { wall('Complete'); },
		'error' => function (ErrorEvent $e) {
			wall("Error" . json_encode($e));
		},
	);
	
	$req = $client->createRequest($method,$url,$rconfig);
	$postBody = $req->getBody();
	if($files != false){
		foreach($_FILES as $file){
			wall($file['name'] . $file['type']);
			$postBody->addFile(new PostFile($file['name'], fopen("@" . $file['tmp_name'], 'r')), $file['type']);
		}
	}
	wall($req);

	wall(json_encode($postBody));

	$response = $client->send($req);
	wall($response->getStatusCode());
	wall("done with response");
	wall($response->getBody());
	
	$resp_log = array(
		"headers" => $response->getHeaders(),
		"body" => $response->json()
	);

	$out = array(
		"request" => $req_log,
		"response" => $resp_log
	);

	//Actually write it.
	$f = fopen("http.txt","a+");
	fputs($f,"\n" . json_encode($out));
	fclose($f);

	
	$body = $response->getBody();
	foreach($response->getHeaders() as $header => $value){
		$hval = "$header: ". $value[0];
		header($hval);
	}
	
	//return it to the client
	echo $body;
	
	//write the body to a temporary file for debugging
	$k = fopen("out.txt","w");
	fputs($k,$body);
	fclose($k);

}
