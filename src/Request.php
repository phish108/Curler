<?php

namespace Curler;

class Request {
    private $curl;
    private $debugMode = 0;

    private $protocol;
    private $host;
    private $base_url;
    private $port;
    private $path_info;
    private $verifySSL = 1;

    private $param;
    private $body;

    private $status;
    private $out_header = [];
    private $in_header = [];

    private $next_url;
    private $next_method;

    private $complexRequest = 1;

    private $outputHeaderHandler = [];
    private $inputHeaderHandler = [];

    private $basicCredentials;
    private $handlerCalled = false;

    private $reject;
    private $accept;

    private $wantBody = false;

    private $dataMapper = [
        "application/json" => "\\Curler\\Data\\JSON",
        "application/x-www-form-urlencoded" => "\\Curler\\Data\\Form",
        "text/plain" => "\\Curler\\Data",
        "text/yaml"  => "\\Curler\\Data\\YAML"
    ];

    public function __construct($options="") {
        if (empty($options)) {
                $options = [];
        }
        $this->setUrl($options);
        $this->param = [];
        $this->out_header = [];
    }

    /**
 	 * activate Curl Debugging
	 */
	public function debugConnection() {
        $this->debugMode = 1;
    }

    /**
 	 * treat 204 (No Content) responses as errors.
	 */
	public function ignoreEmptyResponses() {
        $this->wantBody = true;
    }

    /**
 	 * change the base URL for the next request.
 	 *
 	 * @param mixed $url
	 */
	public function setLocation($url) {
        $this->setUrl($url);
    }

    public function setUrl($options) {
        if (!empty($options)) {
            if (is_string($options)) {
                $options = parse_url($options);
            }
            $this->protocol   = array_key_exists("scheme", $options)    ? $options["scheme"]    : "http";
            $this->host       = array_key_exists("host", $options)      ? $options["host"]      : "";
            $this->base_url   = array_key_exists("path", $options)      ? $options["path"]      : "";
            $this->path_info  = array_key_exists("path_info", $options) ? $options["path_info"] : "";
            $this->port       = array_key_exists("port", $options)      ? $options["port"]      : "";
        }
    }

    // ask curl to return the actual response and not to follow 302 redirects
    public function avoidRedirect() {
        $this->complexRequest = 0;
    }

    public function ignoreSSLCertificate() {
        $this->verifySSL = 0;
    }

    public function setPath($path) {
        if (!empty($path) && is_string($path)) {
            $this->base_url = $path;
        }
        else {
            $this->base_url = "/";
        }
    }

    public function setPathInfo($pi="") {
        $this->path_info = $pi;
    }

    public function setGetParameter($p) {
        $this->param = $p;
    }

    public function getLastUri(){
        return $this->next_url;
    }
    public function setHeader($p, $d=null) {
        if (is_array($p)) {
            foreach ($p as $k => $v) {
                $this->out_header[$k] = $v;
            }
        }
        elseif (is_string($p) && $d === null) {
            list($k, $v) = explode(':', $p);

            $this->out_header[trim($k)] = trim($v);
        }
        else if (is_string($p) && is_string($d)) {
            $this->out_header[trim($p)] = trim($d);
        }
    }

    public function resetHeader() {
        $this->out_header = [];
    }

    public function addHeaderHandler($handler, $type = "output") {
        if ($handler instanceof \Curler\HeaderHandler) {
            switch ($type) {
                case "output":
                    $this->outputHeaderHandler[] = $handler;
                    break;
                default:
                    break;
            }
        }
    }

    public function setCredentials($username, $password) {
        $this->basicCredentials = [];
        $this->basicCredentials[$username] = $password;
    }

    private function prepareUri($data=[]) {
        $this->path_info = ltrim($this->path_info, "/");
        $this->next_url  = $this->protocol . "://" . $this->host;
        if (!empty($this->port)) {
            $this->next_url .= ":".$this->port;
        }
        $this->next_url .= $this->base_url;

        if (!empty($this->path_info)) {
            $this->next_url = rtrim($this->next_url, "/");
            $this->next_url .= "/" . $this->path_info;
        }

        $this->next_url .= $this->prepareQueryString($data);
    }

    private function prepareOutHeader($type="") {
        $th = [];

        // preprocess the headers
        $outh = [];
        foreach ($this->outputHeaderHandler as $handler) {
            $headerName = $handler->headerName();
            if (!empty($headerName)) {
                $outh[$headerName] = $handler->handle($this->next_url, $this->out_header[$headerName]);
            }
        }

        if (!empty($type)) {
            $th[] = "Content-Type: $type";
        }

        if (!empty($this->out_header)) {
            foreach ($this->out_header as $k => $v) {
                if (array_key_exists($k, $outh)) {
                    $th[] = $k . ": " . $outh[$k];
                    unset($outh[$k]);
                }
                else {
                    $th[] = $k . ": " . $v;
                }
            }

            foreach ($outh as $k => $v) {
                $th[] = $k . ": " . $v;
            }
        }
        if (!empty($th)) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $th);
        }
    }

    // helper function passed to curl as callback
    public function __CallBackInHeader($c, $header_line) {
        $pos = strpos($header_line, ":");
        if ($pos !== false && $pos > 0) {
            list($k, $v) = explode(":", trim($header_line), 2);
            $k = str_replace('-', '_', strtolower(trim($k)));
            $this->in_header[$k] = trim($v);
        }
        return strlen($header_line);
    }

    private function request($promise) {
        $this->in_header = [];
        $this->handlerCalled = false;

        curl_setopt($this->curl, CURLOPT_HEADERFUNCTION, [$this, "__CallBackInHeader"]);

        $res = curl_exec($this->curl);

        foreach ($this->inputHeaderHandler as $handler) {
            $header = $handler->headerName();
            if (!empty($header)) {
                $this->in_header[$header] = $handler->handle($this->next_url, $this->in_header[$header]);
            }
        }

        $this->status = curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);

        $this->body = $res;

        switch($this->status) {
            case 204:
                if ($this->wantBody) {
                    call_user_func($this->reject, $this);
                    break;
                }
            case 200:
                call_user_func($this->accept, $this);
                break;
            default:
                call_user_func($this->reject, $this);
                break;
        }
        return $promise;
    }

    private function prepareQueryString($data) {
        $qs = "";
        $aQ = [];

        if (!empty($data)) {

            if (is_string($data)) {
                $aQ = [$data];
            }
            elseif (is_array($data)) {
                $aQ = array_map(function ($k) use ($data){
                    return join("=", [urlencode($k), urlencode($data[$k])]);
                }, array_keys($data));
            }
        }
        if (!empty($this->param)) {
            if (is_array($this->param)) {
                $aQ = array_merge($aQ,array_map(function ($k){
                    return join("=", [urlencode($k), urlencode($this->param[$k])]);
                }, array_keys($this->param)));
            }
            elseif (is_string($this->param)) {
                $aQ = array_merge($aQ,[urlencode($this->param)]);
            }
        }

        if (!empty($aQ)) {
            $qs = "?".implode("&", $aQ);
        }

        return $qs;
    }

    private function prepareRequest() {
        if ($this->curl) {
            curl_close($this->curl);
        }

        $c = curl_init($this->next_url);

        curl_setopt($c, CURLOPT_SSL_VERIFYHOST, $this->verifySSL ? 2 : 0);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, $this->verifySSL ? 1 : 0);

        curl_setopt($c, CURLOPT_VERBOSE, $this->debugMode);
        curl_setopt($c, CURLOPT_FOLLOWLOCATION, $this->complexRequest);

//        curl_setopt($c, CURLOPT_FORBID_REUSE, true);
//        curl_setopt($c, CURLOPT_FRESH_CONNECT, true);

        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_CUSTOMREQUEST, $this->next_method );


        $pwd = "";
        if ($this->basicCredentials) {
            foreach ($this->basicCredentials as $u => $p) {
                $pwd = "$u:$p";
            }
        }
        if (!empty($pwd)) {
            curl_setopt($c, CURLOPT_USERPWD, $pwd);
        }

        $this->curl = $c;
        $self = $this;
        return new Promise(function ($resolve, $reject) use ($self) {
            $self->accept = $resolve;
            $self->reject = $reject;
        });
    }

    public function get($data="") {
        $this->next_method = "GET";
        $this->prepareUri($data);
        $promise = $this->prepareRequest();

        // curl_setopt($c, CURLOPT_HEADER, true);
        $this->prepareOutHeader();

        return $this->request($promise);
    }

    public function post($data, $type) {
        $this->next_method = "POST";
        $this->prepareUri();
        $promise = $this->prepareRequest();
        $this->prepareOutHeader($type);

        if (array_key_exists($type, $this->dataMapper)) {
            $classname = $this->dataMapper[$type];
            $data = $classname::process($data);
        }

        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);

        return $this->request($promise);
    }

    public function put($data, $type) {
        $this->next_method = "PUT";
        $this->prepareUri();
        $promise = $this->prepareRequest();
        $this->prepareOutHeader($type);

        if (array_key_exists($type, $this->dataMapper)) {
            $classname = $this->dataMapper[$type];
            $data = $classname::process($data);
        }

        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);

        return $this->request($promise);
    }

    public function patch($data, $type) {
        $this->next_method = "PATCH";
        $this->prepareUri();
        $promise = $this->prepareRequest();
        $this->prepareOutHeader($type);

        if (array_key_exists($type, $this->dataMapper)) {
            $classname = $this->dataMapper[$type];
            $data = $classname::process($data);
        }

        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);

        return $this->request($promise);
    }

    public function delete($data=""){
        $this->next_method = "DELETE";
        $this->prepareUri($data);
        $promise = $this->prepareRequest();
        $this->prepareOutHeader();

        return $this->request($promise);
    }

    public function head($data=""){
        $this->next_method = "HEAD";
        $this->prepareUri($data);
        $promise = $this->prepareRequest();
        $this->prepareOutHeader();

        return $this->request($promise);
    }

    public function options($data=""){
        $this->next_method = "OPTIONS";
        $this->prepareUri($data);
        $promise = $this->prepareRequest();
        $this->prepareOutHeader();

        return $this->request($promise);
    }

    public function getStatus() {
        return $this->status;
    }

    public function getHeader() {
        return $this->in_header;
    }

    public function getBody() {
        return $this->body;
    }

    public function getUrl() {
        $this->prepareUri();
        return $this->next_url;
    }
}
?>
