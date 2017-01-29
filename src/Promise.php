<?php

namespace Curler;

/**
 * Implmentation of Javascript Promises
 * It also handles selected  HTTP status codes, so it allows
 * Curler implementers to focus on handling specific errors.
 */
class Promise {
    private $completed = false;
    private $resolved = false;

    private $lastResult;
    private $lastError;

    private $ok = [];
    private $error = [];

    /**
 	 * creates a new Promise
     *
     * a promise expects a resolver function. This function MUST accept
     * two parameters: $resolve and $reject.
     *
     * NOTE: a resolver function can be any callable PHP object.
 	 *
 	 * @param callable $resolver
 	 * @return \Curler\Promise
	 */
	public function __construct($resolver) {
        $self = $this;
        $resolve = function($res) use ($self) {$self->resolve($res);};
        $reject  = function($res) use ($self) {$self->reject($res);};

        call_user_func($resolver, $resolve, $reject);
    }

    /**
 	 * then() handles the success full pipeline.
     *
     * the provided callback is expected to handle the result of the previous
     * result callback. A callback is expected to return its result for the next
     * result callback.
     *
     * In the context of Curler\Requests, the result handler of the first then()
     * call is expected to work with a Request-instance. This will be commonly
     * used for processing content. The following example illustrates this:
     *
     * ```php
     * $curl = new \Curler\Request("http://example.com");
     * $curl->get()
     *      ->then(function($req) {
     *          return json_decode($req->getBody(), true); // parse JSON into an array
     *      })
     *      ->then(function($body) {
     *          if (array_key_exist("error", $body)
     *              throw new Exception($body["error_message"]);
     *          // ...
     *      })
     *      ->fails(function($err){
     *          // all Exceptions from json_decode and the body handling end here
     *          if ($err instanceof Exception)
     *              echo "an error occured " . $err->getMessage();
     *          if ($err instanceof Request)
     *              echo "server error " . $err->getStatus();
     *      });
     * ```
     *
     * Note: all result handler will get called, even if no result has been
     * returned.
     *
     * In the context of Curler\Request result handler are called for, both,
     * 200 and 204 responses. This can be changed by calling
     * $curl->ignoreEmptyResponses() prior to the request.
     *
     * Note: Any Error/Failure leaves the response handler pipeline. There is
     * no way to return to it from the error/failure handling.
     *
 	 * @param callable $callback
 	 * @return Curler\Promise
	 */
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

    /**
 	 * fails() accepts callbacks for error handling.
     *
     * In Javascript this function is called catch(), which is a reserved word
     * for php.
     *
     * errors can be actually pretty much any thing that is thrown or returned
     * from error handlers.
     *
     * NOTE, if an error handler does neither returns something nor throws a new
     * Exception, then the promise assumes that the error handling is complete.
     * Therefore, the order of the error handlers is significant, because an
     * all catching handler will stop any other error handler to process.
     *
     * Tip: by not adding error handlers, it is possible to ignore all
     * errors.
 	 *
 	 * @param callable $callback - your error handler
 	 * @return Curler\Promise
	 */
	public function fails($callback) {
        if ($this->completed) {
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

    /**
 	 * alternative and more tailored error handling for HTTP responses.
 	 */

    public function forbidden($callback) {
        return $this->addHandler($callback, "forbidden", [401, 403]);
    }

    public function created($callback) {
        return $this->addHandler($callback, "created", 201);
    }

    // optional, if you ask curler to accept ONLY 200
    public function noContent($callback) {
        return $this->addHandler($callback, "noContent", 204);
    }

    public function authorizationRequired($callback) {
        return $this->addHandler($callback, "authorizationRequired", 401);
    }

    public function paymentRequired($callback) {
        return $this->addHandler($callback, "paymentRequired", 402);
    }

    public function unauthorized($callback) {
        return $this->addHandler($callback, "unauthorized", 403);
    }

    public function notFound($callback) {
        return $this->addHandler($callback, "notFound", 404);
    }

    public function notAllowed($callback) {
        return $this->addHandler($callback, "notAllowed", 405);
    }

    public function notAcceptable($callback) {
        return $this->addHandler($callback, "notAcceptable", 406);
    }

    public function conflict($callback) {
        return $this->addHandler($callback, "conflict", 409);
    }

    public function gone($callback) {
        return $this->addHandler($callback, "gone", 410);
    }

    public function tooManyRequests($callback) {
        return $this->addHandler($callback, "tooManyRequests", 429);
    }

    public function internalError($callback) {
        return $this->addHandler($callback, "internalError", 500);
    }

    public function notImplemented($callback) {
        return $this->addHandler($callback, "notImplemented", 501);
    }

    public function unavailable($callback) {
        return $this->addHandler($callback, "unavailable", 503);
    }

    /**
 	 * handles a successful request.
 	 *
 	 * @param mixed $result - the result of the operation
	 */
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

    /**
 	 * handles an error.
 	 *
 	 * @param mixed $error - the result of the operation
	 */
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

    private function addHandler($callback, $method, $status) {
        $self = $this;
        return $this->fails(function ($err) use ($self,$callback, $method, $status) {
            if ($err instanceof Request &&
                ((is_array($status) &&
                  in_array($err->getStatus(), $status)) ||
                 $err->getStatus() == $status)) {
                return $self->fullfill($callback, $method, $err);
            }
            return $err;
        });
    }
}

?>
