<?php
class Features {    
    /**
     * @var string
     */
    public $siteName;
    /**
     * @var string
     */
    public $documentRoot;
    /**
     * @var string
     */
    public $siteType;
    /**
     * @var boolean|null
     */
    public $logging;
    /**
     * @var boolean|null
     */
    public $templating;
    /**
     * @var InternationalizationFeatures|null
     */
    public $internationalization;
    /**
     * @var boolean|null
     */
    public $caching;
    /**
     * @var SQLServerFeatures|null
     */
    public $sqlServer;
    /**
     * @var NoSQLServerFeatures|null
     */
    public $nosqlServer;
    /**
     * @var SecurityFeatures|null
     */
    public $security;
}

class InternationalizationFeatures {
    public $defaultLocale;
    public $detectionMethod;
}

class SQLServerFeatures {
    public $driver;
    public $host;
    public $port;
    public $user;
    public $password;
    public $schema;
}

class NoSQLServerFeatures {
    public $driver;
    public $host;
    public $port;
    public $user;
    public $password;
    public $bucket;
}

class SecurityFeatures {
    public $isPrivate;
    public $isRoled;
    public $persistenceDrivers;
    public $authenticationMethods;
    public $authorizationMethod;
    /**
     * @var OAuth2Provider[]
     */
    public $oauth2Providers=array();
}

class OAuth2Provider {
    public $driver;
    public $clientID;
    public $clientSecret;
    public $applicationName;
    public $scopes = array(); // TODO: add scopes support
}