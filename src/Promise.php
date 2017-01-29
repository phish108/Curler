<?php

namespace Curler;

class Promise {
    private $completed = false;
    private $resolved = false;

    private $lastResult;
    private $lastError;

    private $ok = [];
    private $error = [];

    public function __construct($resolver) {
        $self = $this;
        $resolve = function($res) use ($self) {$self->resolve($res);};
        $reject  = function($res) use ($self) {$self->reject($res);};

        call_user_func($resolver, $resolve, $reject);
    }

    private function resolve($result) {
        if (!$this->completed) {
            $this->completed = true;
            reset($this->ok);
            reset($this->error);
        }

        $this->resolved = true;

        $this->lastResult = $result;
        $this->lastError = null;

        if (!empty($this->ok)) {
            try {
                $this->lastResult = $this->fullfill(current($this->ok), "resolved", $this->lastResult);
            }
            catch (Exception $err) {
                $this->reject($err->getMessage());
                $this->lastResult = null;
            }
            if (next($this->ok)) {
                $this->resolve($this->lastResult);
            }
        }
    }

    private function reject($error) {
        if (!$this->completed) {
            $this->completed = true;
            reset($this->error);
        }

        $this->resolved = false;

        $this->lastError = $error;
        $this->lastResult = null;

        if (!empty($this->error)) {
            try {
                $this->lastError = $this->fullfill(current($this->error), "failed", $this->lastError);
            }
            catch (Exception $err) {
                $this->lastError = $err->getMessage();
            }

            if (next($this->error) && $this->lastError) {
                $this->reject($this->lastError);
            }
        }
    }

    public function then($callback) {
        if ($this->completed) {
            if ($this->resolved) {
                try {
                    $this->lastResult = $this->fullfill($callback, "resolved", $this->lastResult);
                }
                catch (Exception $err) {
                    $this->reject($err->getMessage());
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
            // if (!$this->resolved && $this->lastError) {
            if (!$this->resolved && $this->lastError) {
                try {
                    $this->lastError = $this->fullfill($callback, "failed", $this->lastError);
                }
                catch (Exception $err) {
                    $this->lastError = $err->getMessage();
                }
            }
        }
        else {
            $this->error[] = $callback;
        }
        return $this;
    }

    public function forbidden($callback) {
        $self = $this;
        return $this->fails(function ($err) use ($self,$callback) {
            if ($err instanceof Request && ($err->getStatus() == 401 || $err->getStatus() == 403)) {
                return $self->fullfill($callback, "forbidden", $err);
            }
            return $err;
        });
    }

    public function notFound($callback) {
        $self = $this;
        return $this->fails(function ($err) use ($self,$callback) {
            if ($err instanceof Request && $err->getStatus() == 404) {
                return $self->fullfill($callback, "notFound", $err);
            }
            return $err;
        });
    }

    private function fullfill($callback, $method, $param) {
        if (isset($callback)) {
            if (is_object($callback)) {
                if (method_exists($callback, $method)) {
                    $callback = [$callback, $method];
                }
            }
            if (!is_callable($callback)) {
                throw new Exception("invalid callback");
            }
            return call_user_func($callback, $param);
        }
    }
}

?>
