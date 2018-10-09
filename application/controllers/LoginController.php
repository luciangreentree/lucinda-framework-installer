<?php
class LoginController extends Lucinda\MVC\STDOUT\Controller {
    public function run() {
        $this->response->attributes()->set("status", $this->getStatusMessage());
    }
    
    private function getStatusMessage() {
        if($this->request->parameters()->contains("status")) {
            switch($this->request->parameters()->contains("status")) {
                case "logout_ok":
                    return "Logout successful";
                    break;
                case "logout_failed":
                    return "You are not logged in to be logged out";
                    break;
                case "unauthorized":
                    return "You need to be logged in to access that page";
                    break;
                case "not_found":
                    return "No authorization policy is set for requested page";
                    break;
            }
        }
    }
}