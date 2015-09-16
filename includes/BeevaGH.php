<?php

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ERROR);
//display_errors = on
require_once __DIR__ . '/../github/autoload.php';


//$repositories = $client->api("me")->repositories("beeva-wpwf");
//print_r($repositories);
class BeevaGH {

    private $client=null;
    private $commiter;
    private $user;
    private $repo;
    private $branch;

    public function __construct($token,$user,$repo,$branch){
        $this->client = new \Github\Client();
        $this->client->authenticate($token,false,Github\Client::AUTH_URL_TOKEN);
        $this->user = $user;
        $this->repo = $repo;
        $this->branch = $branch;
        $this->commiter = array('name' => 'David García', 'email' => 'beeva-davidgarcia@beeeva.com');
    }

    private function getMessage(){
        return "Actualización desde WP ".time();
    }

    public static function check($token,$user,$repo,$branch){
        //TODO probar más cosas
        try{
            $client = new \Github\Client();
            $client->authenticate($token,false,Github\Client::AUTH_URL_TOKEN);
        }catch(Exception $e){
            return false;
        }
        return true;
    }

    private function addFile($path,$content){
        try{
            return $this->client->api('repo')->contents()->create($this->user, $this->repo, $path, $content, $this->getMessage(), $this->branch, $this->commiter);
        }catch(Exception $e){
            echo 'Error subiendo el fichero '.$path;
            echo ': '.$e->getMessage();
            exit();
        }
    }

    public function updateFile($path,$content){
        try{
            $oldFile = $this->client->api('repo')->contents()->show($this->user, $this->repo, $path, $this->branch);
            if(!isset($oldFile['sha'])){
                return $this->addFile($path,$content);
            }
        }catch(Exception $e){
            return $this->addFile($path,$content);
        }

        return $this->client->api('repo')->contents()->update($this->user, $this->repo, $path, $content, $this->getMessage(), $oldFile['sha'], $this->branch, $this->commiter);
    }
}