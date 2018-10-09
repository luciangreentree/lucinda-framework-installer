<?php
class SQLInstallation {
    private $features;
    private $PDO;
    
    public function __construct($features) {
        $this->features = $features;
        $this->setDriver();
        $this->cleanUp();
        $this->setUsersTable();
        $this->setResourcesTable();
        if($features->security->isRoled) {
            $this->setRolesTable();
            $this->setUsersRolesTable();
            $this->setRolesResourcesTable();
        } else {
            $this->setUsersResourcesTable();
        }
        foreach($this->features->security->authenticationMethods as $method) {
            switch($method) {
                case "oauth2 providers":
                    $this->setOauth2ProvidersTable();
                    $this->setUsersOauth2Table();
                    break;
                case "database":
                    $this->setUsersFormTable();
                    break;
            }
        }
    }
    
    private function setDriver() {
        $pdo = new PDO($this->features->sqlServer->driver.":dbname=".$this->features->sqlServer->schema.";host=".$this->features->sqlServer->host, $this->features->sqlServer->user, $this->features->sqlServer->password);
        $statement = $pdo->query("SHOW GRANTS");
        $found = false;
        while($value = $statement->fetch(PDO::FETCH_COLUMN)) {
            if(!(strpos($value, "ALL PRIVILEGES") || (strpos($value, "CREATE") && strpos($value, "DROP")))) {
                $found = true;
            }
        }
        if(!$found) {
            echo "ERROR: User '".$this->features->sqlServer->user."' must have CREATE and DROP rights on '".$this->features->sqlServer->schema."' for tables to be installed!\n";
            return $this->setDriver();
        }
        $this->pdo = $pdo;
    }
    
    private function cleanUp() {
        $tables = array("users_form", "users_oauth2", "oauth2_providers", "users_resources", "roles_resources", "users_roles", "roles", "resources", "users");
        foreach($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS ".$table);
        }
    }
    
    private function setUsersTable() {
        $this->pdo->exec("
        CREATE TABLE users
        (
        id int unsigned not null auto_increment,
        name varchar(255) not null,
        email varchar(255) not null,
        date_created timestamp not null default current_timestamp,
        date_modified timestamp not null default current_timestamp on update current_timestamp,
        primary key(id)
        ) Engine=INNODB
        ");
        $this->pdo->exec("
        INSERT INTO users (id, name, email) VALUES
        (1, 'John Doe', 'john@doe.com'),
        (2, 'Jane Doe', 'jane@doe.com')
        ");
    }
    
    private function setResourcesTable() {
        $this->pdo->exec("
        CREATE TABLE resources
        (
        id smallint unsigned not null auto_increment,
        url varchar(255) not null,
        is_public boolean not null default false,
        primary key(id),
        unique(url)
        ) Engine=INNODB
        ");
        $routesPossible=array(
            "index"=>  ($this->features->security->isPrivate?0:1),
            "login"=>0,
            "logout"=>1
        );
        if(!$this->features->security->isPrivate) {
            $routesPossible["members"] = 1;
        }
        if($this->features->security->isRoled) {
            $routesPossible["restricted"] = 1;
        }
        foreach($this->features->security->oauth2Providers as $oauth2DriverInfo) {
            $routesPossible["login/".strtolower($oauth2DriverInfo->driver)] = 0;
        }
        foreach($routesPossible as $route=>$isPublic) {
            $this->pdo->exec("
            INSERT INTO resources (url, is_public) VALUES
            ('".$route."', ".$isPublic.")");
        }
    }
    
    public function setRolesTable() {
        $this->pdo->exec("
        CREATE TABLE roles
        (
        id tinyint unsigned not null auto_increment,
        name varchar(255) not null,
        primary key(id),
        unique(name)
        ) Engine=INNODB
        ");
        $this->pdo->exec("
        INSERT INTO roles (id, name) VALUES
        (1, 'Member'),
        (2, 'Administrator')
        ");
    }
    
    public function setUsersRolesTable() {
        $this->pdo->exec("
        CREATE TABLE users_roles
        (
        id int unsigned not null auto_increment,
        user_id int unsigned not null,
        role_id tinyint unsigned not null,
        primary key(id),
        foreign key(user_id) references users(id) on delete cascade,
        foreign key(role_id) references roles(id) on delete cascade
        ) Engine=INNODB
        ");
        $this->pdo->exec("
        INSERT INTO users_roles (user_id, role_id) VALUES
        (1, 1),
        (2, 1),
        (2, 2)
        ");
    }
    
    public function setRolesResourcesTable() {
        $this->pdo->exec("
        CREATE TABLE roles_resources
        (
        id int unsigned not null auto_increment,
        role_id tinyint unsigned not null,
        resource_id smallint unsigned not null,
        primary key(id),
        foreign key(role_id) references roles(id) on delete cascade,
        foreign key(resource_id) references resources(id) on delete cascade
        ) Engine=INNODB
        ");
        $rights=array(
            3=>array(1,2)
        );
        if(!$this->features->security->isPrivate) {
            $rights[4] = array(1,2);
            if($this->features->security->isRoled) {
                $rights[5] = array(2);
            }
        } else {
            $rights[1] = array(1,2);
            if($this->features->security->isRoled) {
                $rights[4] = array(2);
            }
        }
        foreach($rights as $resourceID=>$roles) {
            foreach($roles as $roleID) {
                $this->pdo->exec("
                INSERT INTO roles_resources (role_id, resource_id) VALUES
                ('".$roleID."', ".$resourceID.")");
            }
        }
    }
    
    public function setUsersResourcesTable() {
        $this->pdo->exec("
        CREATE TABLE users_resources
        (
        id int unsigned not null auto_increment,
        user_id int unsigned not null,
        resource_id smallint unsigned not null,
        primary key(id),
        foreign key(user_id) references users(id) on delete cascade,
        foreign key(resource_id) references resources(id) on delete cascade
        ) Engine=INNODB
        ");
        $users =array(1,2);
        $resources[]=3;
        if(!$this->features->security->isPrivate) {
            $resources[]=4;
            if($this->features->security->isRoled) {
                $resources[]=5;
            }
        } else {
            $rights[1] = array(1,2);
            if($this->features->security->isRoled) {
                $resources[]=4;
            }
        }
        foreach($resources as $resourceID) {
            foreach($users as $userID) {
                $this->pdo->exec("
                INSERT INTO users_resources (user_id, resource_id) VALUES
                ('".$userID."', ".$resourceID.")");
            }
        }
    }
    
    public function setOauth2ProvidersTable() {
        $this->pdo->exec("
        CREATE TABLE oauth2_providers
        (
        id tinyint unsigned not null auto_increment,
        name varchar(255) not null,
        primary key(id),
        unique(name)
        ) Engine=INNODB
        ");
        $this->pdo->exec("
        INSERT INTO oauth2_providers (id, name) VALUES
        (1, 'Facebook'),
        (2, 'Google'),
        (3, 'GitHub'),
        (4, 'Instagram'),
        (5, 'LinkedIn'),
        (6, 'VK'),
        (7, 'Yandex')
        ");
    }    
    
    public function setUsersOauth2Table() {
        $this->pdo->exec("
        CREATE TABLE users_oauth2
        (
        id int unsigned not null,
        remote_user_id long unsigned not null,
        driver_id tinyint unsigned not null,
        access_token varchar(255) not null,
        primary key(id),
        foreign key(id) references users(id) on delete cascade,
        unique(remote_user_id, driver_id)
        ) Engine=INNODB
        ");
    }
    
    public function setUsersFormTable() {
        $this->pdo->exec("
        CREATE TABLE users_form
        (
        id int unsigned not null,
        username varchar(255) not null,
        password char(60) not null,
        primary key(id),
        foreign key(id) references users(id) on delete cascade
        ) Engine=INNODB
        ");
        $this->pdo->exec("
        INSERT INTO users_form (id, username, password) VALUES
        (1, 'user', '".password_hash("lucinda", PASSWORD_BCRYPT)."'),
        (1, 'admin', '".password_hash("lucinda", PASSWORD_BCRYPT)."')");
    }
}