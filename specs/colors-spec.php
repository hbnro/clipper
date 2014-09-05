<?php

describe('Parsing colors:', function () {
  let('colors', new \Clipper\Colors());

  it('should handle basic colouring', function ($colors) {
    $code = 'Hey <c:red>bro</c>!';
    $test = $colors->format($code);

    expect($test)->toBe("Hey \033[31mbro\033[0m!");
    expect($colors->strips($test))->toBe('Hey bro!');
    expect($colors->strips($code))->toBe('Hey bro!');

    expect($colors->format('This <bh:white>text</bh> is that?'))->toBe("This \033[1;37;1;7mtext\033[0m is that?");
    expect($colors->format('This <uc:green,black>text</uc> is that?'))->toBe("This \033[32;4;40mtext\033[0m is that?");
  });
});
