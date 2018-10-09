<?php
class FeaturesSelectionProgress {
    const PROGRESS_LOG = "progress.json";
    
    public function exists() {
        return file_exists(self::PROGRESS_LOG);
    }
    
    public function get() {
        return json_decode(file_get_contents(self::PROGRESS_LOG));
    }
    
    public function save($choices) {
        file_put_contents(self::PROGRESS_LOG, json_encode($choices));
    }
}