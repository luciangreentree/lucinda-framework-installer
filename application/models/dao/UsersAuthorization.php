<?php
require_once("SQL.php");
class UsersAuthorization  extends Lucinda\WebSecurity\UserAuthorizationDAO {    
    public function isAllowed(Lucinda\WebSecurity\PageAuthorizationDAO $page, $httpRequestMethod)
    {
        return SQL("{QUERY}", array(":user"=>$this->id, ":resource"=>$page->getID()))->toValue();
    }
}
