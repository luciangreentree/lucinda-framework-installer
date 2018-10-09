<?php
require_once("Prompter.php");
require_once("Features.php");
require_once("FeaturesSelectionProgress.php");

class FeaturesSelection {
    const IP_REGEX = "/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/";
    const HOSTNAME_REGEX = "/^(([a-zA-Z]|[a-zA-Z][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z]|[A-Za-z][A-Za-z0-9\-]*[A-Za-z0-9])$/";
    
    private $choices = [];
    private $prompt;
    
    public function __construct() {
        $this->prompt = new Prompter();
        $progress = new FeaturesSelectionProgress();
        if($progress->exists()) {
            $resume = $this->prompt->singleSelect("Choose whether you want you want to resume installation from previous state", array("yes","no"), 0)=="yes";
            if($resume) {
                $this->choices = $progress->get();
            }
        }
        if(empty($this->choices)) {
            $this->choices = new Features();
        }
        
        $steps = array_keys(get_object_vars(new Features()));
        foreach($steps as $step) {
            if(isset($this->choices->$step) && $this->choices->$step !== null) continue;
            $methodName = "get".ucwords($step);
            $this->choices->$step = $this->$methodName();
            $progress->save($this->choices);
        }
    }
    
    public function getChoices() {
        return $this->choices;
    }
    
    private function getSiteName() {
        return $this->prompt->text("Write your site name (eg: 'google' if domain name is 'www.google.com)", null, function($result) {
            preg_match(self::HOSTNAME_REGEX, $result, $matches);
            return !empty($matches);
        });
    }
    
    private function getSiteType() {
        return $this->prompt->singleSelect("Choose your site type", array("normal site", "web service site"), 0);
    }
    
    private function getLogging() {
        return $this->prompt->singleSelect("Choose whether you want to use loggers", array("yes","no"), 0)=="yes";
    }
    
    private function getTemplating() {
        if($this->choices->siteType=="web service site") return false;
        return $this->prompt->singleSelect("Choose whether you want to use templating of HTML output", array("yes","no"), 0)=="yes";
    }
    
    private function getInternationalization() {
        if($this->choices->siteType=="web service site") return false;
        $choice = $this->prompt->singleSelect("Choose whether you want to customize HTML output by user language", array("yes","no"), 1)=="yes";
        if(!$choice) return false;
        
        $defaultLocale = $this->prompt->text("Write default locale (lowercase ISO language followed by uppercase ISO country codes separated by underscore) your site will be using or hit enter to confirm 'en_US'", "en_US", function($result){
            preg_match("/^([a-z]{2}_[A-Z]{2})$/", $result, $matches);
            return !empty($matches);
        });
        $detectionMethod = $this->prompt->text("Choose how user locale will be detected (1: via 'locale' query string param; 2: via same param read from session; 3: via 'Accept-Language' request header) or hit enter to confirm #3", 3, function($result) {
            return in_array($result,array(1,2,3));
        });
        
        $features = new InternationalizationFeatures();
        $features->defaultLocale = $defaultLocale;
        $features->detectionMethod = $detectionMethod;
        return $features;
    }
    
    private function getCaching() {
        return $this->prompt->singleSelect("Choose whether you want site response to be cacheable via ETAGs", array("yes","no"), 0)=="yes";
    }
    
    private function getSqlServer() {
        $choice = $this->prompt->singleSelect("Choose whether you want site use an SQL-type database, for example MySQL", array("yes","no"), 0)=="yes";
        if(!$choice) return false;
        
        $driverName = $this->prompt->text("Write SQL vendor that will be used in connections or hit enter to confirm 'mysql'", "mysql", function($result) {
            preg_match("/^([a-zA-Z0-9_\-]+)$/", $result, $matches1);
            return !empty($matches1);
        });
        $hostName = $this->prompt->text("Write hostname or hit enter to confirm '127.0.0.1'", "127.0.0.1", function($result) {
            preg_match(self::HOSTNAME_REGEX, $result, $matches1);
            preg_match(self::IP_REGEX, $result, $matches2);
            return !empty($matches1) || !empty($matches2);
        });
        $schema = $this->prompt->text("Write name of schema (database) where your application tables are saved into", null, function($result) {
            preg_match("/^([0-9a-zA-Z_]+)$/", $result, $matches);
            return !empty($matches);
        });
        $userName = $this->prompt->text("Write database user name allowed to access that schema or hit enter to confirm 'root' (NOTE: using root account is not recommended!)", "root", function($result) {
            preg_match("/^([0-9a-zA-Z_]+)$/", $result, $matches);
            return !empty($matches);
        });
        $userPassword = $this->prompt->text("Write database user password allowed to access that schema or hit enter to confirm '' (NOTE: using empty password is not recommended!)", "", function($result) {
            return true; // any password is ok, including none
        });
        try {
            $dbh = new PDO($driverName.":dbname=".$schema.";host=".$hostName, $userName, $userPassword);
            
            $features = new SQLServerFeatures();
            $features->driver = $driverName;
            $features->host = $hostName;
            $features->user = $userName;
            $features->password = $userPassword;
            $features->schema = $schema;
            return $features;
        } catch (PDOException $e) {
            $this->prompt->error("Connection to ".$driverName." failed with message '". $e->getMessage()."'");
            return $this->step_7();
        }
    }
    
    private function getNosqlServer() {
        $choice = $this->prompt->singleSelect("Choose whether you want site use an NoSQL-type database, for example Memcached", array("yes","no"), 0)=="yes";
        if(!$choice) return false;
        
        
        $driverName = $this->prompt->singleSelect("Choose NoSQL vendor that will be used in connections", array("apc","apcu","memcache","memcached","couchbase","redis"), null);
        if(!extension_loaded($driverName)) {
            $this->prompt->error("PHP extension ".$driverName." is not installed!");
            return $this->step_8();
        }
        
        $features = new NoSQLServerFeatures();
        $features->driver = $driverName;
        if(!in_array($driverName, array("apc","apcu"))) {
            $features->host = $this->prompt->text("Write hostname or hit enter to confirm '127.0.0.1", "127.0.0.1", function($result) {
                preg_match(self::HOSTNAME_REGEX, $result, $matches1);
                preg_match(self::IP_REGEX, $result, $matches2);
                return !empty($matches1) || !empty($matches2);
            });
        }        
        if($driverName == "couchbase") {    
            $features->user = $this->prompt->text("Write user name to be used in couchbase connection", null, function($result) {
                preg_match("/^([0-9a-zA-Z_]+)$/", $result, $matches);
                return !empty($matches);
            });
            $features->password = $this->prompt->text("Write user password to be used in couchbase connection", null, function($result) {
                return true; // any password is ok, excluding none
            });
            $features->bucket = $this->prompt->text("Write name of couchbase bucket that stores your data or hit enter to confirm 'default'", "default", function($result) {
                preg_match("/^([0-9a-zA-Z_]+)$/", $result, $matches);
                return !empty($matches);
            });
        }
        
        // test connection
        switch($driverName) {
            case "redis":
                $redis = new Redis();
                $result = $redis->connect($features->host);
                if(!$result) {
                    $this->prompt->error("Connection to redis server failed!");
                    return $this->step_8();
                }
                break;
            case "memcache":
                $memcache = new Memcache();
                $result = $memcache->connect($features->host);
                if(!$result) {
                    $this->prompt->error("Connection to memcache server failed!");
                    return $this->step_8();
                }
                break;
            case "memcached":
                $memcached = new Memcached();
                $memcached->addServer($features->host);
                $result = $memcached->set("test", 1);
                if(!$result) {
                    $this->prompt->error("Connection to memcached server failed!");
                    return $this->step_8();
                }
                $memcached->delete("test");
                break;
            case "couchbase":
                try {
                    $authenticator = new \Couchbase\PasswordAuthenticator();
                    $authenticator->username($features->user)->password($features->password);
                    
                    $cluster = new \CouchbaseCluster("couchbase://".$features->host);
                    $cluster->authenticate($authenticator);
                    
                    $bucket = $cluster->openBucket($features->bucket);
                } catch(\CouchbaseException $e) {
                    $this->prompt->error("Connection to couchbase server failed with error '".$e->getMessage()."'");
                    return $this->step_8();
                }
                break;
        }
        
        return $features;
    }
    
    private function getSecurity() {
        $choice = $this->prompt->singleSelect("Choose whether you want site requires authentication & authorization", array("yes","no"), 1)=="yes";
        if(!$choice) return false;
        
        $features = new SecurityFeatures();
        $features->isPrivate = $this->prompt->singleSelect("Choose whether your site is private, requiring users to be registered to have access to homepage", array("yes","no"), 1)=="yes";
        
        $features->isRoled = $this->prompt->singleSelect("Choose whether all registered users in your site have same access rights to access pages", array("yes","no"), ($features->isPrivate?1:0))=="no";
        
        $features->persistenceDrivers = $this->prompt->multipleSelect(
            "Choose ways how authenticated state is persisted across requests",
            ($this->choices->siteType == "normal site"?array("session","remember me"):array("synchronizer token","json web token")),
            0);
        
        $features->authenticationMethods = $this->prompt->multipleSelect(
            "Choose authentication method",
            array("database","oauth2 providers","access control list"),
            0);
        
        if(in_array("access control list",$features->authenticationMethods) && sizeof($features->authenticationMethods)!=1) {
            $this->prompt->error("Access control list is incompatible with other authentication methods");
            return $this->step_9();
        }
        
        if(in_array("oauth2 providers", $authenticationMethods)) {
            $oauth2Providers = $this->prompt->multipleSelect(
                "Choose oauth2 providers you want to support",
                array("Facebook","Google","Instagram","GitHub","LinkedIn","VK","Yandex"),
                null);
            foreach($oauth2Providers as $provider) {
                $info = new OAuth2Provider();
                $info->driver = $provider;
                $info->clientID = $this->prompt->text("Please write your client id setup in ".$provider." site", null, function($result) {
                    return !empty($result);
                });
                $info->clientSecret = $this->prompt->text("Please write your client secret setup in ".$provider." site", null, function($result) {
                    return !empty($result);
                });
                if($provider=="GitHub") {
                    $info->applicationName = $this->prompt->text("Please write your application name setup in ".$provider." site", null, function($result) {
                        return !empty($result);
                    });
                }
                $features->oauth2Providers[] = $info;
            }
        }
        
        $features->authorizationMethod = (in_array("access control list", $features->authenticationMethods)?"access control list":"database");
        
        return $features;
    }
    
    private function getDocumentRoot() {
        return $this->prompt->text("Please write your document root (aka folder) http://localhost points to (eg: /var/www/html)", null, function($result) {
            if(!file_exists($result) || !is_dir($result) || !is_writable($result)) {
                $this->prompt->error("Folder must exist and be writable!");
                return false;
            }
            
            $response = true;
            file_put_contents($result."/temp.php","<?php echo 'OK';");
            if(!$this->pageExists("http://localhost/temp.php") || file_get_contents("http://localhost/temp.php")!="OK") {
                $this->prompt->error("Folder is not document root!");
                $response = false;
            }
            unlink($result."/temp.php");
            return $response;
        });
    }
    
    private function pageExists($url){
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($code == 200){
            $status = true;
        }else{
            $status = false;
        }
        curl_close($ch);
        return $status;
    }
}

