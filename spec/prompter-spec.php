<?php

describe('Prompt helpers:', function () {
    beforeEach(function () {
        $shell = spy('Clipper', 'Shell')
            ->methods('clear', 'write', 'readln', 'flush')
            ->callOriginalMethods(false)
            ->getMock();

        $shell->method('clear')->will(returnSelf());
        $shell->method('flush')->will(returnSelf());

        $shell->expects($write = any())
            ->method('write')
            ->will(returnSelf());

        let('shell', $shell);

        let('readln', function ($value = null, $count = 1) use ($shell) {
            $shell->expects($readln = exactly($count))
                ->method('readln')
                ->will(returnValue($value));

            return $readln;
        });

        let('stdout', function () use ($write) {
            return implode('', array_map(function ($call) {
                return implode('', $call->parameters);
            }, $write->getInvocations()));
        });

        let('prompt', new \Clipper\Prompter($shell));
    });

    it('should wait for any key', function ($stdout, $prompt) {
        $prompt->wait();
        expect($stdout())->toEqual("Press any key\n");
    });

    it('should wait until N seconds', function ($stdout, $prompt) {
        fun('Clipper', 'sleep')
            ->expects(exactly(3));

        $prompt->wait(3);

        expect($stdout())->toEqual("3s...2s...1s...0s...\n");
    });

    it('should prompt for any wanted value', function ($readln, $prompt) {
        $stub = $readln('bar');
        $input = $prompt->prompt('foo');
        $calls = $stub->getInvocations();

        expect($input)->toEqual('bar');
        expect(implode('', end($calls)->parameters))->toEqual('foo: ');
    });

    it('should prompt for some value with defaults', function ($readln, $prompt) {
        $stub = $readln();
        $input = $prompt->prompt('foo', 'bar');
        $calls = $stub->getInvocations();

        expect($input)->toEqual('bar');
        expect(implode('', end($calls)->parameters))->toEqual('foo [bar]: ');
    });

    it('should allow to choice simple options', function ($readln, $prompt) {
        $stub = $readln();
        $input = $prompt->choice('foo');
        $calls = $stub->getInvocations();

        expect($input)->toEqual('n');
        expect(implode('', end($calls)->parameters))->toEqual('foo [y/N]: ');
    });

    it('should allow to choice custom options with defaults', function ($readln, $prompt) {
        $stub = $readln('a');
        $input = $prompt->choice('foo', 'abc', 'c');
        $calls = $stub->getInvocations();

        expect($input)->toEqual('a');
        expect(implode('', end($calls)->parameters))->toEqual('foo [a/b/C]: ');
    });

    it('should allow to pick values from a simple menu', function ($readln, $stdout, $prompt) {
        $stub = $readln();
        $input = $prompt->menu(array('a', 'b', 'c'));
        $calls = $stub->getInvocations();

        expect($input)->toEqual(-1);
        expect($stdout())->toEqual("\n  1. a\n  2. b\n  3. c\n\n");
        expect(implode('', end($calls)->parameters))->toEqual('Choose one: ');
    });

    it('should wrap long text as necessary', function ($shell, $stdout, $prompt) {
        $shell->width = 40;
        $prompt->wrap('Lorem ipsum dolor sit amet, consectetur-adipisicing-elit-sed-do-eiusmod');

        expect($stdout())->toEqual("\n  Lorem ipsum dolor sit amet,\n  consectetur-adipisicing-elit-sed-do-e\n  iusmod\n");
    });

    it('should display a progress bar', function ($shell, $stdout, $prompt) {
        $shell->width = 40;
        $prompt->progress(3, 10);

        expect($shell->colors->strips($stdout()))->toEqual("\r||||||||||-----------------------  31%");
    });
});
