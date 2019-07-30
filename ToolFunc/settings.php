<?php

class Settings
{
    var $_settings = array();

    function get($keyPath)
    {
        $keyPath = explode(".", $keyPath);
        $result = $this->_settings;
        foreach ($keyPath as $key) {
            if (!isset($result[$key])) {
                return false;
            }
            $result = $result[$key];
        }
        return $result;
    }

    function load($file)
    {
        trigger_error("Not yet implemented", E_USER_ERROR);
    }
}

class Settings_INI extends Settings
{

    function load($file)
    {
        // $path = dirname(__FILE__)."/".$file;
        if (is_file($file) == false) {
            echo "Can't find file [".$file."]\n";
            return false;
        }
        $this->_settings = parse_ini_file($file, true);
        return true;
    }
}

class Settings_XML extends Settings
{

    function to_array($xmlIter)
    {
        $array = array();
        for ($xmlIter->rewind(); $xmlIter->valid(); $xmlIter->next()) {
            if (!array_key_exists($xmlIter->key(), $array)) {
                $array[$xmlIter->key()] = array();
            }
            if ($xmlIter->hasChildren()) {
                $array[$xmlIter->key()] = $this->to_array($xmlIter->current());
            } else {
                $array[$xmlIter->key()] = strval($xmlIter->current());
            }
        }
        return $array;
    }

    /**
     * $file: 需要使用相对路径（相对settings.php文件的路径），否则会找不到文件
     */
    function load($file)
    {
        // $path = dirname(__FILE__)."/".$file;
        if (is_file($file) == false) {
            echo "Can't find file [".$file."]\n";
            return false;
        }

        $xml = new SimpleXmlIterator($file, null, true);
        $data = $this->to_array($xml);
        $this->_settings = $data;
        return true;
    }
}

?>
