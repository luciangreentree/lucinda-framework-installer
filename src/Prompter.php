<?php
class Prompter {
    public function error($message) {
        echo "ERROR: ".$message."\n";
    }
    
    public function text($message, $defaultValue=null, $validator = null) {
        $result = readline($message.": ");
        if(!$result) {
            if($defaultValue!==null) {
                $result = $defaultValue;
            } else {
                $this->error("Value cannot be empty!");
                return $this->text($message, $defaultValue, $validator);
            }
        }
        if(!$validator($result)) {
            $this->error("Value is invalid!");
            return $this->text($message, $defaultValue, $validator);
        }
        return $result;
    }
    
    public function multipleSelect($message, $availableOptions, $defaultOption=null) {
        $result = readline($message." separated by comma (supported: ".implode(", ", $availableOptions).")".($defaultOption!=null?" or hit enter to confirm '".$availableOptions[$defaultOption]."'":"").": ");
        if(!$result) {
            if($defaultOption!==null) {
                $result = $availableOptions[$defaultOption];
            } else {
                $this->error("Value cannot be empty!");
                return $this->multipleSelect($message, $defaultOption, $availableOptions);
            }
        }
        $tmp = explode(",", $result);
        $selectedOptions = array();
        foreach($tmp as $option) {
            $option = trim($option);
            if(!in_array($option,$availableOptions)) {
                $this->error("Value '".$option."' is not supported!");
                return $this->multipleSelect($message, $defaultOption, $availableOptions);
            }
            $selectedOptions[] = $option;
        }
        return $selectedOptions;
    }
    
    public function singleSelect($message, $availableOptions, $defaultOption=null) {
        $result = readline($message." (supported: ".implode(", ", $availableOptions).")".($defaultOption!==null?" or hit enter to confirm '".$availableOptions[$defaultOption]."'":"").": ");
        if(!$result) {
            if($defaultOption!==null) {
                $result = $availableOptions[$defaultOption];
            } else {
                $this->error("Value cannot be empty!");
                return $this->singleSelect($message, $defaultOption, $availableOptions);
            }
        }
        if(!in_array($result, $availableOptions)) {
            $this->error("Value '".$result."' is not supported!");
            return $this->singleSelect($message, $defaultOption, $availableOptions);
        }
        return $result;
    }
}