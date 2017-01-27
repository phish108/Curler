<?php

namespace Curler\Data;

class YAML extends \Curler\Data {
    static public function process($data) {
        if (is_array($data) || is_object($data)) {
            $data = \yaml_emit($data);
        }
        return parent::process($data);
    }
}
?>
