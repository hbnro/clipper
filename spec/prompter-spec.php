<?php

describe('Prompt helpers:', function () {
    beforeEach(function () {
        $stdout = '';

        $context = spy('\\Clipper\\Shell')
            ->methods('clear', 'write', 'readln', 'flush')
            ->callOriginalMethods(false)
            ->getMock();

        $context->method('write')
            ->will(returnCallback(function () use (&$stdout) {
                $stdout .= implode('', func_get_args());
            }));

        $prompter = new \Clipper\Prompter($context);

        let('stdout', function () use (&$stdout) {
            return $stdout;
        });

        let('prompter', $prompter);
    });

    it('should wait for any key', function ($stdout, $prompter) {
        $prompter->wait();
        expect($stdout())->toEqual("Press any key\n");
    });

    it('should wait until N seconds', function ($stdout, $prompter) {
        $prompter->wait(1);
        expect($stdout())->toEqual("1s...0s...\n");
    });

    xit('should prompt for any value', function ($context, $prompter) {
        $readln = $context->__spy('readln', 'bar');
        $input = $prompter->prompt('foo');
        $args = $readln();

        expect($input)->toEqual('bar');
        expect(implode('', $args))->toEqual('foo: ');
    });

    xit('should prompt for some value with defaults', function ($context, $prompter) {
        $readln = $context->__spy('readln');
        $input = $prompter->prompt('foo', 'bar');
        $args = $readln();

        expect($input)->toEqual('bar');
        expect(implode('', $args))->toEqual('foo [bar]: ');
    });

    xit('should allow to choice simple options', function ($context, $prompter) {
        $readln = $context->__spy('readln');
        $input = $prompter->choice('foo');
        $args = $readln();

        expect($input)->toEqual('n');
        expect(implode('', $args))->toEqual('foo [y/N]: ');
    });

    xit('should allow to choice custom options with defaults', function ($context, $prompter) {
        $readln = $context->__spy('readln', 'a');
        $input = $prompter->choice('foo', 'abc', 'c');
        $args = $readln();

        expect($input)->toEqual('a');
        expect(implode('', $args))->toEqual('foo [a/b/C]: ');
    });

    xit('should allow to pick values from a simple menu', function ($context, $prompter) {
        $readln = $context->__spy('readln');
        $input = $prompter->menu(array('a', 'b', 'c'));
        $args = $readln();

        expect($input)->toEqual(-1);
        expect($context->__stdout)->toEqual('
  1. a
  2. b
  3. c

');
        expect(implode('', $args))->toEqual('Choose one: ');
    });

    xit('should wrap long text as necessary', function ($context, $prompter) {
        $context->width = 40;
        $prompter->wrap('Lorem ipsum dolor sit amet, consectetur-adipisicing-elit-sed-do-eiusmod');

        expect($context->__stdout)->toEqual('
  Lorem ipsum dolor sit amet,
  consectetur-adipisicing-elit-sed-do-e
  iusmod
');
    });

    xit('.', function ($context, $prompter) {
        $context->width = 10;
        $prompter->progress(2, 5);
        #expect($context->__stdout)->toContain('x'); //, "<c:cyan,cyan>|</c><c:light_gray,light_gray>-</c>  41%");
    });
});
