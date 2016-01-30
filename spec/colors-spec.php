<?php

\Spectre\Base::customMatchers('toHasFormat', function ($expected, $format) {
    return expect($expected)
        ->toContain("\033[{$format}m")
        ->toContain("\033[0m");
});

describe('Parsing colors:', function () {
    let('colors', new \Clipper\Colors());

    it('should handle basic color formatting', function ($colors) {
        expect($colors->format('Hey <c:red>bro</c>!'))->toHasFormat('31');
    });

    it('should handle highlighted text', function ($colors) {
        expect($colors->format('This <bh:white>text</bh> is that?'))->toHasFormat('1;37;1;7');
    });

    it('should handle underlined text', function ($colors) {
        expect($colors->format('<uc:green,black>green-text</uc> black-bkg'))->toHasFormat('32;4;40');
    });

    it('should handle bold text', function ($colors) {
        expect($colors->format('THE <bc:red>RED</bc> TEXT'))->toHasFormat('31;1');
    });

    it('should handle aliases', function ($colors) {
        $colors->alias('warning', 'c:yellow,red');

        expect($colors->format('<warning>ERROR</warning>'))->toHasFormat('1;33;41');
        expect($colors->format('<unknown>FORMAT</unknown>'))->toBe('FORMAT');
    });

    it('should strip format', function ($colors) {
        expect($colors->strips($colors->format('<c:cyan>cyan</c>')))->toEqual('cyan');
    });
});
