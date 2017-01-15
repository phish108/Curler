<?php

namespace Curler;

class Request {
    function headerName() {
        return null;
    }

    function handle($url, $header) {
        return $header;
    }
}

?>
