<?php

use \Clipper\Params;

describe('Clipper:', function () {
  describe('Parsing arguments:', function () {
    local('argv', explode(' ', 'a/b m n -xYZ c -z -a --foo bar baz --candy=does -f FU! -z nothing'));

    it('should handle named parameters', function ($argv) {
      $params = new Params(array(
        'foo' => array('f', 'foo', Params::PARAM_MULTIPLE),
        'bar' => array('', 'candy'),
      ), $argv);

      expect($params['x'])->toBeNull();
      expect($params['bar'])->toBe('does');
      expect($params['foo'])->toBe(array('bar', 'FU!'));
    });

    it('should handle the rest as values', function ($argv) {
      $params = new Params(array(), $argv);

      expect($params[2])->toBe('c');
      expect($params[-1])->toBeNull();
      expect(sizeof($params))->toBe(4);

      $count = 0;

      foreach ($params as $i => $value) {
        expect($params[$i])->toBe($value);
        $count++;
      }

      expect($count)->toBe(4);
    });

    it('should validate the received parameters', function ($argv) {
      expect(function () use($argv) {
        new Params(array('last' => array('z', 'some', Params::PARAM_NO_VALUE)), $argv);
      })->toThrow();

      expect(function () use($argv) {
        new Params(array('first' => array('a', 'thing', Params::PARAM_REQUIRED)), $argv);
      })->toThrow();
    });
  });
});
