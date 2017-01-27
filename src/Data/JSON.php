<?php

namespace Curler\Data;

class JSON extends \Curler\Data {
    static public function process($data) {
        if (is_array($data) || is_object($data)) {
            $data = json_encode($data);
        }
        return parent::process($data);
    }
}
?>
