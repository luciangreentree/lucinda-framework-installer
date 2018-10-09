<?php
class CodeInstallation {
    private $rootFolder;
    private $features;
    
    public function __construct($features) {
        $this->rootFolder = $features->documentRoot.DIRECTORY_SEPARATOR.$features->siteName;
        $this->features = $features;
        $this->copyControllers();
        $this->copyModels();
        $this->copyViews();
    }
    
    private function copyControllers() {        
        $controllers = array();
        $controllers[] = "IndexController";
        if($this->features->security) {
            $controllers[]="LoginController";
        }
        
        $sourceFolder = dirname(__DIR__).DIRECTORY_SEPARATOR."application".DIRECTORY_SEPARATOR."controllers";
        $destinationFolder = $this->rootFolder.DIRECTORY_SEPARATOR."application".DIRECTORY_SEPARATOR."controllers";
        foreach($controllers as $controller) {
            copy($sourceFolder.DIRECTORY_SEPARATOR.$controller.".php", $destinationFolder.DIRECTORY_SEPARATOR.$controller.".php");
            if($controller=="IndexController") {
                $indexController = $destinationFolder.DIRECTORY_SEPARATOR."IndexController.php";
                file_put_contents($indexController, str_replace("{FEATURES}", json_encode($this->features), file_get_contents($indexController)));
            }
        }
    }
    
    private function copyModels() {
        if(!$this->features->security) return;
        $daos = array();
        foreach($this->features->security->authenticationMethods as $authenticationMethod) {
            switch($authenticationMethod) {
                case "database":
                    $daos[]="UsersAuthentication";
                    break;
                case "oauth2 providers":
                    $daos[]="UsersOAuth2Authentication";
                    break;
            }
        }
        switch($this->features->security->authorizationMethod) {
            case "database":
                $daos[]="UsersAuthorization";
                $daos[]="PagesAuthorization";
                break;
        }
        
        if(!empty($daos)) {
            $sourceFolder = dirname(__DIR__).DIRECTORY_SEPARATOR."application".DIRECTORY_SEPARATOR."models".DIRECTORY_SEPARATOR."dao";
            $destinationFolder = $this->rootFolder.DIRECTORY_SEPARATOR."application".DIRECTORY_SEPARATOR."models".DIRECTORY_SEPARATOR."dao";
            mkdir($destinationFolder);
            copy($sourceFolder.DIRECTORY_SEPARATOR."SQL.php", $destinationFolder.DIRECTORY_SEPARATOR."SQL.php");
            foreach($daos as $dao) {
                copy($sourceFolder.DIRECTORY_SEPARATOR.$dao.".php", $destinationFolder.DIRECTORY_SEPARATOR.$dao.".php");
                if($dao=="UsersAuthorization") {
                    $daoFile = $destinationFolder.DIRECTORY_SEPARATOR."UsersAuthorization.php";
                    if($this->features->security->isRoled) {
                        $query="SELECT t1.id FROM roles_resources AS t1 INNER JOIN users_roles AS t2 USING(role_id) WHERE t1.resource_id = :resource AND t2.user_id=:user";
                    } else {
                        $query="SELECT id FROM users_resources WHERE resource_id=:resource AND user_id=:user";
                    }
                    file_put_contents($daoFile, str_replace("{QUERY}", $query, file_get_contents($daoFile)));
                }
            }
        }
    }
    
    private function copyViews() {
        $sourceFolder = dirname(__DIR__).DIRECTORY_SEPARATOR."application".DIRECTORY_SEPARATOR."views_".($this->features->templating?"templated":"basic");
        $destinationFolder = $this->rootFolder.DIRECTORY_SEPARATOR."application".DIRECTORY_SEPARATOR."views";
        $viewExtension = ($this->features->templating?"html":"php");
        
        if($this->features->templating) {
            mkdir($this->rootFolder.DIRECTORY_SEPARATOR."compilations");
            mkdir($this->rootFolder.DIRECTORY_SEPARATOR."application".DIRECTORY_SEPARATOR."taglib");
        }
        
        $views = array();
        $views[] = "index";
        if($this->features->security) {
            $views[] = "login";
            if($this->features->security->isRoled) {
                $views[] = "restricted";
            }
            if(!$this->features->security->isPrivate) {
                $views[] = "members";
            }
        }
        
        foreach($views as $view) {
            copy($sourceFolder.DIRECTORY_SEPARATOR.$view.".".$viewExtension, $destinationFolder.DIRECTORY_SEPARATOR.$view.".".$viewExtension);
        }
    }
}