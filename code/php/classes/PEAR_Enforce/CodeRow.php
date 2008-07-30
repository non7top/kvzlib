<?php
Class CodeRow {
    private $_codeRow = '';
    private $_length = 0;
    private $_Token = false;
    private $_tokenized = array();
    
    public function CodeRow($codeRow) {
        $this->_codeRow = $codeRow;
        $this->_changed();
    }
    
    private function _changed(){
        $this->_length = strlen($this->_codeRow);
                
        $this->_Token = new TokenSimple($this->_codeRow);
        $this->_tokenized = $this->_Token->getTokenized();
        
    }
    
        
    public function wrap($newLineChar, $at=85, $indentation="") {
        // Some reserve (e.g. for added dot to glue)
        $at = $at -3;
        
        $wrapCode   = "#{NWL+IND}#";
        $wrapPoints = $this->_wrapPoints($wrapCode);
        $wrapPoints = array_reverse($wrapPoints, true);
        
        foreach($wrapPoints as $col=>$data) {
            if ($col > $at) continue; // Still too big!
            
            list($search, $replace) = $data;
            
            $anotherRun = (strlen(substr($this->_codeRow, $at, strlen($this->_codeRow))) > $at);
            
            $this->deleteAt($col, strlen($search));
            $this->insertAt($col, $replace);
            break;
        }
        
        $this->replace($wrapCode, $newLineChar.$indentation);
        
        if ($anotherRun) {
            $this->wrap($newLineChar, $at, $indentation);        
        }
        
        $this->_changed();
        return true;
    }
    
    private function _wrapPoints($wrapCode="#{NWL+IND}#") {
        
        $wrapWhere = array();
        $wrapChars["T_CONSTANT_ENCAPSED_STRING"] = array(' ');
        $wrapChars["T_ALLOTHER"] = array(', ');
        
        foreach ($this->_tokenized as $i=>$token) {
            extract($token);
            $indx = 0;
            
            if (isset($wrapChars[$type]) && is_array($wrapChars[$type])) {
                // What's a good point to wrap this token?
                foreach ($wrapChars[$type] as $wrapChar) {
                    // Find which quote was used
                    if ($type == "T_CONSTANT_ENCAPSED_STRING") {
                        $quote = substr(trim($content), 0, 1);
                        $splitChar = array($wrapChar, " ".$quote." . ".$wrapCode.$quote);
                    } else {
                        $splitChar = array($wrapChar, ", ".$wrapCode);
                    }
                    
                    while(($indx = strpos($content, $wrapChar, $indx+1)) !== false) {
                        $wrapWhere[$col+$indx] = $splitChar;
                    }
                }
            }
        }
        
        return $wrapWhere;
    }
    
    public function getTokenTypes(){
        return $this->_Token->getTypes();
    }
    
    public function getCodeRow() {
        return $this->_codeRow;
    }
    
    /**
     * Shorthand method for replace with $use_regex=true
     *
     * @param string $search
     * @param string $replace
     * @param mixed  $onlyTokenTypes
     * 
     * @return boolean
     */
    public function regplace($search, $replace, $onlyTokenTypes=false) {
        return $this->replace($search, $replace, $onlyTokenTypes, true);
    }
    
    /**
     * Main method for replacing current codeRow.
     * Optionally enforces Token Types, and support for regexes 
     *
     * @param string  $search
     * @param string  $replace
     * @param mixed   $onlyTokenTypes
     * @param boolean $use_regex
     * 
     * @return boolean
     */
    public function replace($search, $replace, $onlyTokenTypes=false, $use_regex=false) {

        // Default to array
        if (!$onlyTokenTypes) $onlyTokenTypes = array();
        
        // Allow for token type to be a string; Put inside array anyway
        if (is_string($onlyTokenTypes)) $onlyTokenTypes = array($onlyTokenTypes);
        
        // Semantics (e.g. reverse the meaning of tokens when prefixed with a: !)
        if (!count($onlyTokenTypes)) {
            $singleLine = true;
        } else {
            $singleLine = false;
            $notTokenTypes = array();
            
            $killOnly = false;
            foreach ($onlyTokenTypes as $i=>$onlyTokenType) {
                if (substr($onlyTokenType, 0, 1) == "!") {
                    $notTokenTypes[$i] = substr($onlyTokenType, 1, strlen($onlyTokenType));
                    
                    // Delete only if NOT is set
                    $killOnly = true;
                } 
            }
            if ($killOnly) {
                $onlyTokenTypes = array();
            }
        }
        
        
        if ($singleLine) {
            // Replace on entire string
            $this->_codeRow = $this->_replace($search, $replace, $this->_codeRow, $use_regex);
        } else {
            // Please note that e.g. whitespace and comma are separate
            // tokens. This means replacing ' ,' with ',' will not work when
            // using $notTokenTypes or $onlyTokenTypes
            
            // Only replace within certain token types
            foreach ($this->_tokenized as $i=>$token) {
                
                if (count($notTokenTypes) && in_array($token["type"], $notTokenTypes)) {
                    continue;
                }
                if (count($onlyTokenTypes) && !in_array($token["type"], $onlyTokenTypes)) {
                    continue;
                }
                
                // Replace this token (a local version)
                $this->_tokenized[$i]["content"] = $this->_replace($search, $replace, $this->_tokenized[$i]["content"], $use_regex);
            }
            
            // Save back to token object
            $this->_Token->setTokenized($this->_tokenized);
            
            // Retrieve content from renewed token
            $this->_codeRow = $this->_Token->getContent();
        }
        
        $this->_changed();
        return true;
    }

    /**
     * Underwater method for the actual replacement of a string
     *
     * @param string  $search
     * @param string  $replace
     * @param string  $subject
     * @param boolean $use_regex
     * 
     * @return string
     */
    private function _replace($search, $replace, $subject, $use_regex=false) {
        if ($use_regex) {
            return preg_replace('#'. $search.'#', $replace, $subject);
        } else {
            return str_replace($search, $replace, $subject);
        }
    }

    
    public function setIndent($spaces) {
        $current = $this->getIndent();
        $needed  = $spaces - $current;
        
        if ($needed > 0) {
            $this->_codeRow = str_repeat(" ", $needed).$this->_codeRow;
        } elseif($needed < 0) {
            $this->deleteAt(1, abs($needed));
        }
        
        $this->_changed();
        return true;
    }
    
    /**
     * Automatically switches to backspaceAt when howmany is negative
     *
     * @param integer $at
     * @param string  $chars
     * @param integer $howmany
     * 
     * @return boolean
     */
    public function insertAt($at, $chars=" ", $howmany=1) {
        if ($howmany < 0) {
            return $this->backspaceAt($at, abs($howmany));
        }
        
        $t = $this->_codeRow;
        // Compensate
        $at--;
        $this->_codeRow = substr($t, 0, $at) . str_repeat($chars, $howmany) . substr($t, $at, strlen($t));
        
        $this->_changed();
        return true;
    }
    
    public function deleteAt($at, $howmany=1) {
        $t = $this->_codeRow;
        // Compensate
        $at--;
        $this->_codeRow = substr($t, 0, $at) . substr($t, $at+$howmany, strlen($t));
        
        $this->_changed();
        return true;
    }
    
    public function backspaceAt($at, $howmany=1) {
        $t = $this->_codeRow;
        // Compensate
        $at--;
        $this->_codeRow = substr($t, 0, $at-$howmany) . substr($t, $at, strlen($t));
        
        $this->_changed();
        return true;
    }
    
    public function getCharAt($at, $howmany=1) {
        // Compensate
        $at++;
        return substr($this->codeRow, $at, $howmany); 
    }
    
    public function getTokenized() {
        return $this->_tokenized;
    }
    
    public function getPosCodeChar($char, $reverse = false) {
        foreach ($this->_tokenized as $i=>$token) {
            extract($token);
            if ($type == 'T_ALLOTHER') {
                if ($reverse) {
                    if (($indx = strrpos($this->_codeRow, $char)) !== false) {
                        return $indx + $col;
                    }
                } else {
                    if (($indx = strpos($this->_codeRow, $char)) !== false) {
                        return $indx + $col;
                    }
                }
            }
        }
        
        return 0;
    }
    
    public function getPosEqual() {
        return $this->getPosCodeChar('=');
    }

    public function getPosToken($tokenType) {
        foreach ($this->_tokenized as $i=>$token) {
             if ($token["type"] == $tokenType) {
                 return $token["col"];
             }
        }
        
        return 0;
    }
    
    public function getPosBraceOpen() {
        return $this->getPosCodeChar('{');
    }

    public function getPosBraceClose() {
        return $this->getPosCodeChar('}', true);
    }
        
    public function getIndentation($extra = 0){
        return str_repeat(" ", $this->getIndent($extra));
    }
    
    public function getIndent($extra = 0) {
        for ($i = 0; $i < $this->_length; $i++) {
            if (substr($this->_codeRow, $i, 1) != " " && substr($this->_codeRow, $i, 1) != "\t") {
                return $i + $extra;
            }
        }
        return false;
    }    
}
?>