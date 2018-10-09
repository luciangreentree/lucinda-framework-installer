<?php
require_once("SQL.php");
class UsersAuthentication  implements Lucinda\WebSecurity\UserAuthenticationDAO
{
    public function login($username, $password)
    {
        $result = SQL("SELECT id, password FROM users_form WHERE username=:user",array(":user"=>$username))->toRow();
        if(!empty($result) || !password_verify($password, $result["password"])) {
            return null; // login failed
        }
        return $result["id"];
    }
    
    public function logout($userID)
    {}
}

