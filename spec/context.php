<?php

namespace Context;

class Shell extends \Spectre\Context\ProxyClass {
    public $__stdout = '';
    public $__stderr = '';

    public function __error() {
        $this->__stderr .= implode('', func_get_args());
    }

    public function __write() {
        $this->__stdout .= implode('', func_get_args());
    }

    public function __readln() {
        return -1;
    }

    public function __format() {
        return implode('', func_get_args());
    }

    public function __writeln() {
        $this->__write(implode('', func_get_args()) . "\n");
    }

    public function __sprintf() {
        return call_user_func_array('sprintf', func_get_args());
    }
}
