<?php

namespace Curler\Data;

class Form extends \Curler\Data {
    static public function process($data) {
        if (is_array($data)) {
            $param = [];
            foreach ($data as $k => $v) {
                if (empty($k)) {
                    continue;
                }
                $param[] = urlencode($k) . "=" . urlencode($v);
            }
            $data = join('&', $param);
        }
        return parent::process($data);
    }
}
?>
