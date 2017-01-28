<?php

namespace Curler;

class Promise {
    private $completed = false;
    private $resolved = false;

    private $lastResult;
    private $lastError;

    private $ok = [];
    private $error = [];

    public function resolve($result) {
        if (!$this->completed) {
            $this->completed = true;
            reset($this->ok);
            reset($this->error);
        }

        $this->resolved = true;

        $this->lastResult = $result;
        $this->lastError = null;

        foreach ($this->ok as $handler) {
            try {
                $res = $this->fullfill(current($this->ok), "resolved", $this->lastResult);
            }
            catch (Exception $err) {
                $this->reject($err->getMessage());
            }
            if ($res) {
                $this->lastResult = $res;
            }
        }
        if (next($this->ok)) {
            $this->resolve($this->lastResult);
        }
    }

    public function reject($error) {
        if (!$this->completed) {
            $this->completed = true;
            reset($this->error);
        }

        $this->resolved = false;

        $this->lastError = $error;
        $this->lastResult = null;

        try {
            $res = $this->fullfill(current($this->error), "failed", $this->lastError);
        }
        catch (Exception $err) {
            $this->lastError = $err->getMessage();
        }

        if ($res) {
            $this->lastError = $res;
        }

        if (next($this->error)) {
            $this->reject($this->lastError);
        }
    }

    public function then($callback) {
        if ($this->completed) {
            if ($this->resolved) {
                try {
                    $res = $this->fullfill($callback, "resolved", $this->lastResult);
                }
                catch (Exception $err) {
                    $this->reject($err->getMessage());
                }

                if ($res) {
                    $this->lastResult = $res;
                }
            }
        }
        else {
            $this->ok[] = $callback;
        }
        return $this;
    }

    public function fails($callback) {
        if ($this->completed) {
            if (!$this->resolved) {
                try {
                    $res = $this->fullfill($callback, "failed", $this->lastError);
                }
                catch (Exception $err) {
                    $this->lastError = $err->getMessage();
                }

                if ($res) {
                    $this->lastError = $res;
                }
            }
        }
        else {
            $this->error[] = $callback;
        }
        return $this;
    }

    private function fullfill($callback, $method, $param) {
        if (isset($callback)) {
            if (is_object($callback)) {
                if (method_exists($callback, $method)) {
                    $callback = [$callback, $method];
                }
            }
            if (!is_callable($callback)) {
                return null;
            }
            return call_user_func($callback, $param);
        }
    }
}

?>
