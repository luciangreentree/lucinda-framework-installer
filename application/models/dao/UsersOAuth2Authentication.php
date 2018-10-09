<?php
require_once("SQL.php");
class UsersOAuth2Authentication implements Lucinda\WebSecurity\OAuth2AuthenticationDAO {

    public function login(Lucinda\WebSecurity\OAuth2UserInformation $userInformation, $accessToken, $createIfNotExists=true)  {     
        // get driver ID
        $driver = get_class($userInformation);
        $driver = str_replace("UserInformation", "", $driver);
        $driverID = SQL("SELECT id FROM oauth2_providers WHERE name=:driver", array(":driver"=>$driver))->toValue();
        if(!$driverID) return;
        
        // get user ID
        $userID = SQL(
            "SELECT id FROM users_oauth2 WHERE remote_user_id=:remote_user AND driver_id=:driver",
            array(":remote_user"=>$userInformation->getId(), ":driver"=>$driverID)
            )->toValue();
        
        // create user
        if(!$userID) {
            if(!$createIfNotExists) return;
            $userID = SQL(
                "INSERT INTO users (name, email) VALUES (:name, :email)",
                array(":name"=>$userInformation->getName(), ":email"=>$userInformation->getEmail())
                )->getInsertId();
            SQL(
                "INSERT INTO users_oauth2 VALUES (:user_id, :remote_user,  :driver, :access_token)",
                array(":user_id"=>$userID, ":remote_user"=>$userInformation->getId(), ":driver"=>$driverID, ":access_token"=>$accessToken)
                )->getInsertId();
        } else {
            SQL("UPDATE users_oauth2 SET access_token=:access_token WHERE id = :user_id", array(":user_id"=>$userID, ":access_token"=>$accessToken));
        }
        
        return $userID;
    }
    
    public function logout($userID) {
        SQL("UPDATE users_oauth2 SET access_token = '' WHERE id = :user_id", array(":user_id"=>$userID));
    }
    
    public function getAccessToken($userID) {
        return SQL("SELECT access_token FROM users_oauth2 WHERE id = :user_id", array(":user_id"=>$userID))->toValue();
    }    
}