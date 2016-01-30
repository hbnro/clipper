<?php

describe('Prompt helpers:', function () {
    beforeEach(function () {
        $context = new \ContextSpec();
        $prompter = new \Clipper\Prompter($context);

        let('context', $context);
        let('prompter', $prompter);
    });

    // wait prompt choice menu wrap progress

    it('should wait for any key', function ($context, $prompter) {
        $prompter->wait();
        expect($context->value)->toEqual("Press any key\n");
    });

    it('should wait until N seconds', function ($context, $prompter) {
        $prompter->wait(1);
        expect($context->value)->toEqual("1s...0s...\n");
    });

    it('should prompt for any value', function ($context, $prompter) {
        $context->readln = 'bar';
        $input = $prompter->prompt('foo');

        expect($input)->toEqual('bar');
        expect($context->value)->toEqual('foo: ');
    });

    it('should prompt for some value with defaults', function ($context, $prompter) {
        $context->readln = '';
        $input = $prompter->prompt('foo', 'bar');

        expect($input)->toEqual('bar');
        expect($context->value)->toEqual('foo [bar]: ');
    });

    it('should allow to choice simple options', function ($context, $prompter) {
        $context->readln = '';
        $input = $prompter->choice('foo');

        expect($input)->toEqual('n');
        expect($context->value)->toEqual('foo [y/N]: ');
    });

    it('should allow to choice custom options with defaults', function ($context, $prompter) {
        $context->readln = 'a';
        $input = $prompter->choice('foo', 'abc', 'c');

        expect($input)->toEqual('a');
        expect($context->value)->toEqual('foo [a/b/C]: ');
    });

    it('should allow to pick values from a simple menu', function ($context, $prompter) {
        $context->readln = '';
        $input = $prompter->menu(array('a', 'b', 'c'));

        expect($input)->toEqual(-1);
        expect($context->value)->toEqual('
  1. a
  2. b
  3. c

Choose one: ');
    });
});

class ContextSpec {
    public $value = '';

    public function __call($method, $arguments) {
        if (isset($this->methods[$method])) {
            return call_user_func_array($this->methods[$method], $arguments);
        }

        if (substr($method, 0, 1) !== '_') {
            return call_user_func_array(array($this, '_' . $method), $arguments);
        }
    }

    public function __set($method, $value) {
        if (!($value instanceof \Closure)) {
            $_write = array($this, '_write');
            $this->methods[$method] = function () use ($_write, $value) {
                call_user_func_array($_write, func_get_args());
                return $value;
            };
        } else {
            $this->methods[$method] = $value;
        }
    }

    public function _error() {
    }

    public function _write() {
        $this->value .= implode('', func_get_args());
    }

    public function _readln() {
        return -1;
    }

    public function _format() {
        return implode('', func_get_args());
    }

    public function _writeln() {
        $this->_write(implode('', func_get_args()) . "\n");
    }

    public function _sprintf() {
        return call_user_func_array('sprintf', func_get_args());
    }
}
