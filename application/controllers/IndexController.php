<?php
class IndexController extends Lucinda\MVC\STDOUT\Controller {
    public function run() {
        $this->response->attributes()->set("features", json_decode('{FEATURES}',true));
        $this->response->attributes()->set("status", $this->getStatusMessage());
    }
    
    private function getStatusMessage() {
        if($this->request->parameters()->contains("status")) {
            switch($this->request->parameters()->contains("status")) {
                case "login_ok":
                    return "Login successful";
                    break;
                case "forbidden":
                    return "You are not allowed to access that page";
                    break;
                case "not_found":
                    return "No authorization policy is set for requested page";
                    break;
            }
        }
    }
}