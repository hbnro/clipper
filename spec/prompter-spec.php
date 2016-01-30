<?php

require __DIR__.DIRECTORY_SEPARATOR.'context.php';

describe('Prompt helpers:', function () {
    beforeEach(function () {
        $context = new \Context\Shell();
        $prompter = new \Clipper\Prompter($context);

        let('context', $context);
        let('prompter', $prompter);
    });

    it('should wait for any key', function ($context, $prompter) {
        $prompter->wait();
        expect($context->stdout)->toEqual("Press any key\n");
    });

    it('should wait until N seconds', function ($context, $prompter) {
        $prompter->wait(1);
        expect($context->stdout)->toEqual("1s...0s...\n");
    });

    it('should prompt for any value', function ($context, $prompter) {
        $readln = $context->__spy('readln', 'bar');
        $input = $prompter->prompt('foo');
        $args = $readln();

        expect($input)->toEqual('bar');
        expect(implode('', $args))->toEqual('foo: ');
    });

    it('should prompt for some value with defaults', function ($context, $prompter) {
        $readln = $context->__spy('readln');
        $input = $prompter->prompt('foo', 'bar');
        $args = $readln();

        expect($input)->toEqual('bar');
        expect(implode('', $args))->toEqual('foo [bar]: ');
    });

    it('should allow to choice simple options', function ($context, $prompter) {
        $readln = $context->__spy('readln');
        $input = $prompter->choice('foo');
        $args = $readln();

        expect($input)->toEqual('n');
        expect(implode('', $args))->toEqual('foo [y/N]: ');
    });

    it('should allow to choice custom options with defaults', function ($context, $prompter) {
        $readln = $context->__spy('readln', 'a');
        $input = $prompter->choice('foo', 'abc', 'c');
        $args = $readln();

        expect($input)->toEqual('a');
        expect(implode('', $args))->toEqual('foo [a/b/C]: ');
    });

    it('should allow to pick values from a simple menu', function ($context, $prompter) {
        $readln = $context->__spy('readln');
        $input = $prompter->menu(array('a', 'b', 'c'));
        $args = $readln();

        expect($input)->toEqual(-1);
        expect($context->stdout)->toEqual('
  1. a
  2. b
  3. c

');
        expect(implode('', $args))->toEqual('Choose one: ');
    });

    it('should wrap long text as necessary', function ($context, $prompter) {
        $context->width = 40;
        $prompter->wrap('Lorem ipsum dolor sit amet, consectetur-adipisicing-elit-sed-do-eiusmod');

        expect($context->stdout)->toEqual('
  Lorem ipsum dolor sit amet,
  consectetur-adipisicing-elit-sed-do-e
  iusmod
');
    });

    it('.', function ($context, $prompter) {
        $context->width = 10;
        $prompter->progress(2, 5);
        #expect($context->stdout)->toContain('x'); //, "<c:cyan,cyan>|</c><c:light_gray,light_gray>-</c>  41%");
    });
});
