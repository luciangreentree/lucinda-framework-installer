<?php
require_once("FeaturesSelection.php");
require_once("XMLInstallation.php");
require_once("CodeInstallation.php");
require_once("SQLInstallation.php");

class Installer {
    public function __construct() {
        $features = $this->getSelectedFeatures();
        $this->checkRequirements($features);
        $this->writeFiles($features);
        echo "COMPLETE!\n";
    }
    
    private function getSelectedFeatures() {
        $selection = new FeaturesSelection();
        return $selection->getChoices();
    }
    
    private function checkRequirements($features) {
        $installationFolder = $features->documentRoot.DIRECTORY_SEPARATOR.$features->siteName;
        if(file_exists($installationFolder)) {
            die("FATAL ERROR: project already exists\n");
        }
        
        exec("which composer", $output);
        if(empty($output)) {
            die("FATAL ERROR: composer is required\n");
        }
    }
    
    private function writeFiles($features) {                
        echo "Cloning project\n";
        $installationFolder = $features->documentRoot.DIRECTORY_SEPARATOR.$features->siteName;
        exec("git clone -b development https://github.com/aherne/lucinda-framework.git ".$installationFolder);
        if(!file_exists($installationFolder)) {
            die("FATAL ERROR: project could not be saved (".json_encode($output).")\n");
        }
        chdir($installationFolder);
        exec("composer update");
        chdir(__DIR__);
        
        echo "Setting up XML file\n";
        new XMLInstallation($features);
        
        echo "Setting up php dependencies\n";
        new CodeInstallation($features);
        
        if($features->security && $features->security->authorizationMethod=="database") {
            echo "Setting up tables\n";
            new SQLInstallation($features);
        }
    }
}