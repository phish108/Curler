<?php

namespace Curler;

class Data {
    static public function process($data) {
        if (is_string($data)) {
            return $data;
        }
    }
}
?>
