<?php
class XMLInstallation {
    const DEFAULT_ENVIRONMENT = "local";
    /**
     * @var SimpleXMLElement
     */
    private $xmlSTDOUT;
    /**
     * @var SimpleXMLElement
     */
    private $xmlSTDERR;
    /**
     * @var Features
     */
    private $features;
    
    public function __construct($features) {
        $this->features = $features;
        
        // compile stdout xml and save
        $this->xmlSTDOUT = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8" ?><!DOCTYPE xml><xml></xml>');
        $this->setApplicationTag();
        $this->setFormatsTag();
        $this->setListeners();
        $this->setLoggersTag();
        $this->setInternationalizationTag();
        $this->setCachingTag();
        $this->setServersTag();
        $this->setSecurityTag();
        $this->setRoutes();
        $this->setUsers();
        $this->saveFile("configuration.xml", $this->xmlSTDOUT);
        
        // compile stderr xml and save
        $this->xmlSTDERR = simplexml_load_file($features->documentRoot.DIRECTORY_SEPARATOR.$features->siteName.DIRECTORY_SEPARATOR."errors.xml");
        $this->setReporters();
        $this->setDisplayErrors();
        $this->saveFile("errors.xml", $this->xmlSTDERR);
    }
    
    private function setApplicationTag() {
        $application = $this->xmlSTDOUT->addChild("application");
        $application->addAttribute("version","0.0.1");
        $application->addAttribute("auto_routing","0");
        $application->addAttribute("default_format", ($this->features->siteType=="normal site"?"html":"json"));
        if($this->features->siteType=="normal site") {
            $application->addAttribute("default_page","");
        }
        if($this->features->templating) {
            $application->addAttribute("templates_extension","html");
        }
        
        $paths = $application->addChild("paths");
        $paths->addChild("controllers", "application/controllers");
        $paths->addChild("resolvers", "application/resolvers");
        $paths->addChild("listeners", "application/listeners");
        if($this->features->siteType=="normal site") {
            $paths->addChild("views","application/views");
        }
        if($this->features->templating) {
            $paths->addChild("compilations","compilations");
            $paths->addChild("taglib","application/taglib");
        }
    }
    
    private function setFormatsTag() {
        $application = $this->xmlSTDOUT->addChild("formats");
        
        if($this->features->siteType=="normal site") {
            $html = $application->addChild("format");
            $html->addAttribute("name", "html");
            $html->addAttribute("content_type", "text/html");
            if(!$this->features->templating) {
                $html->addAttribute("class", "HtmlResolver");
            }
            $html->addAttribute("charset", "UTF-8");
        }
        
        $json = $application->addChild("format");
        $json->addAttribute("name", "json");
        $json->addAttribute("content_type", "application/json");
        $json->addAttribute("class", "JsonResolver");
        $json->addAttribute("charset", "UTF-8");
    }
    
    private function setListeners() {
        $listeners = $this->xmlSTDOUT->addChild("listeners");
        
        $eventListeners[] = "EnvironmentDetector";
        if($this->features->logging) $eventListeners[] = "LoggingListener";
        if($this->features->sqlServer) $eventListeners[] = "SQLDataSourceInjector";
        if($this->features->nosqlServer) $eventListeners[] = "NoSQLDataSourceInjector";
        if($this->features->security) $eventListeners[] = "SecurityListener";
        if($this->features->siteType=="normal site") $eventListeners[] = "ErrorListener";
        if($this->features->internationalization) $eventListeners[] = "LocalizationListener";
        if($this->features->templating) $eventListeners[] = "ViewLanguageResolver";
        if($this->features->caching) $eventListeners[] = "HttpCachingListener";
        
        foreach($eventListeners as $className) {
            $listener = $listeners->addChild("listener");
            $listener->addAttribute("class", $className);
        }
    }
    
    private function setLoggersTag() {
        if(!$this->features->logging) return;
        
        $loggers = $this->xmlSTDOUT->addChild("loggers");
        $loggers->addAttribute("path", "application/models/loggers");
        
        $logger = $loggers->addChild(self::DEFAULT_ENVIRONMENT)->addChild("logger");
        $logger->addAttribute("class", "FileLoggerWrapper");
        $logger->addAttribute("path", "messages");
        $logger->addAttribute("format", "%d %f %l %m");
    }
    
    private function setInternationalizationTag() {
        if(!$this->features->internationalization) return;
        
        $internationalization = $this->xmlSTDOUT->addChild("internationalization");
        $internationalization->addAttribute("locale", $this->features->internationalization->defaultLocale);
        $internationalization->addAttribute("method", $this->features->internationalization->detectionMethod);
        $internationalization->addAttribute("folder", "locale");
        $internationalization->addAttribute("domain", "messages");        
    }
    
    private function setCachingTag() {
        if(!$this->features->caching) return;
        
        $caching = $this->xmlSTDOUT->addChild("http_caching");
        $caching->addAttribute("class", "DefaultCacheableDriver");
        $caching->addAttribute("secret", $this->generateSecret());
        $caching->addAttribute("drivers_path", "application/models/cacheables");
    }
    
    private function setServersTag() {
        if(!$this->features->sqlServer || !$this->features->nosqlServer) return;
        
        $servers = $this->xmlSTDOUT->addChild("servers");
        if($this->features->sqlServer) {
            $server = $servers->addChild("sql")->addChild(self::DEFAULT_ENVIRONMENT)->addChild("server");
            $server->addAttribute("driver", $this->features->sqlServer->driver);
            $server->addAttribute("host", $this->features->sqlServer->host);
            $server->addAttribute("username", $this->features->sqlServer->user);
            $server->addAttribute("password", $this->features->sqlServer->password);
            $server->addAttribute("schema", $this->features->sqlServer->schema);
            $server->addAttribute("charset", "UTF8");
        }
        if($this->features->nosqlServer) {
            $server = $servers->addChild("nosql")->addChild(self::DEFAULT_ENVIRONMENT)->addChild("server");
            $server->addAttribute("driver", $this->features->nosqlServer->driver);
            switch($this->features->nosqlServer->driver) {
                case "redis":
                case "memcache":
                case "memcached":
                    $server->addAttribute("host", $this->features->nosqlServer->host);
                    break;
                case "couchbase":
                    $server->addAttribute("host", $this->features->nosqlServer->host);
                    $server->addAttribute("username", $this->features->nosqlServer->user);
                    $server->addAttribute("password", $this->features->nosqlServer->password);
                    $server->addAttribute("bucket_name", $this->features->nosqlServer->bucket);
                    break;
            }
        }
    }
    
    private function setSecurityTag() {
        if(!$this->features->security) return;
        
        $security = $this->xmlSTDOUT->addChild("security");
        $security->addAttribute("dao_path", "application/models/dao");
        
        $csrf = $security->addChild("csrf");
        $csrf->addAttribute("secret", $this->generateSecret());
        
        $persistenceDrivers = $security->addChild("persistence");
        foreach($this->features->security->persistenceDrivers as $driverName) {
            switch($driverName) {
                case "session":
                    $persistenceDrivers->addChild("session");
                    break;
                case "remember me":
                    $rememberMe =$persistenceDrivers->addChild("remember_me");
                    $rememberMe->addAttribute("secret", $this->generateSecret());
                    break;
                case "synchronizer token":
                    $synchronizerToken =$persistenceDrivers->addChild("synchronizer_token");
                    $synchronizerToken->addAttribute("secret", $this->generateSecret());
                    break;
                case "json web token":
                    $jsonWebToken =$persistenceDrivers->addChild("json_web_token");
                    $jsonWebToken->addAttribute("secret", $this->generateSecret());
                    break;
            }
        }
        
        $authentication = $security->addChild("authentication");
        foreach($this->features->security->authenticationMethods as $driverName) {
            switch($driverName) {
                case "database":
                case "access control list":
                    $form = $authentication->addChild("form");
                    if($driverName == "database") {
                        $form->addAttribute("dao", "UsersAuthentication");
                    }
                    $form->addChild("login");
                    $form->addChild("logout");
                    break;
                case "oauth2 providers":
                    $oauth2 = $authentication->addChild("oauth2");
                    $oauth2->addAttribute("dao", "UsersOAuth2Authentication");
                    foreach($this->features->security->oauth2Providers as $oauth2DriverInfo) {
                        $driverTag = $oauth2->addChild("driver");
                        $driverTag->addAttribute("name", $oauth2DriverInfo->driver);
                        $driverTag->addAttribute("callback", "/login/".strtolower($oauth2DriverInfo->driver));
                        $driverTag->addAttribute("client_id", $oauth2DriverInfo->clientID);
                        $driverTag->addAttribute("client_secret", $oauth2DriverInfo->clientSecret);
                        if($oauth2DriverName=="GitHub") {
                            $driverTag->addAttribute("application_name", $oauth2DriverInfo->applicationName);
                        }
                        // TODO: set scopes
                    }
                    break;
             }
        }  
        
        $authorization = $security->addChild("authorization");
        switch($this->features->security->authorizationMethod) {
            case "database":
                $dao = $authorization->addChild("by_dao");
                $dao->addAttribute("page_dao", "PagesAuthorization");
                $dao->addAttribute("user_dao", "UsersAuthorization");
                break;
            case "access control list":
                $authorization->addChild("by_route");
                break;
        }
    }
    
    private function setRoutes() {
        $routes = $this->xmlSTDOUT->addChild("routes");
        
        $routesPossible = array();
        if($this->features->security) {
            foreach($this->features->security->authenticationMethods as $authenticationMethod) {
                switch($authenticationMethod) {
                    case "database":
                        $routesPossible[] = array("url"=>"index", "controller"=>"IndexController", "view"=>"index");
                        $routesPossible[] = array("url"=>"login", "controller"=>"LoginController", "view"=>"login");
                        $routesPossible[] = array("url"=>"logout");
                        if(!$this->features->security->isPrivate) {
                            $routesPossible[] = array("url"=>"members", "view"=>"members");
                        }
                        if($this->features->security->isRoled) {
                            $routesPossible[] = array("url"=>"restricted", "view"=>"restricted");
                        }
                        break;
                    case "access control list":
                        $defaultRoles = ($this->features->security->isRoled?"MEMBER,ADMINISTRATOR":"MEMBER");
                        $routesPossible[] = array("url"=>"index", "controller"=>"IndexController", "view"=>"index", "roles"=>($this->features->security->isPrivate?$defaultRoles:"GUEST"));
                        $routesPossible[] = array("url"=>"login", "controller"=>"LoginController", "view"=>"login", "roles"=>"GUEST");
                        $routesPossible[] = array("url"=>"logout", "roles"=>$defaultRoles);
                        if(!$this->features->security->isPrivate) {
                            $routesPossible[] = array("url"=>"members", "view"=>"members", "roles"=>$defaultRoles);
                        }
                        if($this->features->security->isRoled) {
                            $routesPossible[] = array("url"=>"restricted", "view"=>"restricted", "roles"=>"ADMINISTRATOR");
                        }
                        break;
                    case "oauth2 providers":
                        $routesPossible[] = array("url"=>"index", "controller"=>"IndexController", "view"=>"index");
                        $routesPossible[] = array("url"=>"login", "controller"=>"LoginController", "view"=>"login");
                        $routesPossible[] = array("url"=>"logout");
                        if(!$this->features->security->isPrivate) {
                            $routesPossible[] = array("url"=>"members", "view"=>"members");
                        }
                        if($this->features->security->isRoled) {
                            $routesPossible[] = array("url"=>"restricted", "view"=>"restricted");
                        }
                        foreach($this->features->security->oauth2Providers as $oauth2DriverInfo) {
                            $routesPossible[] = array("url"=>"login/".strtolower($oauth2DriverInfo->driver));
                        }
                        break;
                }
            }
        } else {
            $routesPossible[] = array("url"=>"index", "view"=>"index", "controller"=>"IndexController");
        }
        
        foreach($routesPossible as $routeInfo) {
            $route = $routes->addChild("route");
            foreach($routeInfo as $key=>$value) {
                $route->addAttribute($key, $value);
            }
        }
    }
    
    private function setUsers() {
        if(!$this->features->security || !in_array("access control list", $this->features->security->authenticationMethods)) return;
        
        $users = $this->xmlSTDOUT->addChild("users");
        
        $usersPossible = array();
        if($this->features->security->isRoled) {
            $usersPossible[] = array("id"=>1, "username"=>"admin", "password"=>password_hash("admin", PASSWORD_BCRYPT), "roles"=>"MEMBER,ADMINISTRATOR");
            $usersPossible[] = array("id"=>1, "username"=>"member", "password"=>password_hash("member", PASSWORD_BCRYPT), "roles"=>"MEMBER");
        } else {
            $usersPossible[] = array("id"=>1, "username"=>"admin", "password"=>password_hash("admin", PASSWORD_BCRYPT), "roles"=>"ADMINISTRATOR");
        }
        foreach($usersPossible as $userInfo) {
            $route = $routes->addChild("user");
            foreach($userInfo6 as $key=>$value) {
                $route->addAttribute($key, $value);
            }
        }
    }
    
    private function setReporters() {
        $reporter = $this->xmlSTDERR->addChild("reporters")->addChild(self::DEFAULT_ENVIRONMENT)->addChild("reporter");
        $reporter->addAttribute("class", "FileReporter");
        $reporter->addAttribute("path", "errors");
        $reporter->addAttribute("format", "%d %f %l %m");
    }
    
    private function setDisplayErrors() {
        $this->xmlSTDERR->application->addChild("display_errors")->addChild(self::DEFAULT_ENVIRONMENT, 1);
    }
    
    private function generateSecret($secretLength = 32) {
        return substr(password_hash(uniqid(), PASSWORD_BCRYPT), rand(7, 60-$secretLength), $secretLength);
    }
    
    private function saveFile($xmlFileName, SimpleXMLElement $xml) {
        $domxml = new DOMDocument('1.0');
        $domxml->preserveWhiteSpace = false;
        $domxml->formatOutput = true;
        $domxml->loadXML($xml->asXML());
        $domxml->save($this->features->documentRoot.DIRECTORY_SEPARATOR.$this->features->siteName.DIRECTORY_SEPARATOR.$xmlFileName);
    }
}