<?php

use \Clipper\Params;

describe('Clipper:', function () {
  describe('Parsing arguments:', function () {
    local('params', new Params(explode(' ', 'a/b m n -xYZ c -z -a --foo bar baz --candy=does -f FU! -z nothing')));

    it('should handle named parameters', function ($params) {
      $params->parse(array(
        'foo' => array('f', 'foo', Params::PARAM_MULTIPLE),
        'bar' => array('', 'candy'),
      ));

      expect($params['x'])->toBeNull();
      expect($params['bar'])->toBe('does');
      expect($params['foo'])->toBe(array('bar', 'FU!'));
    });

    it('should handle the rest as values', function ($params) {
      $params->parse();

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

    it('should validate the received parameters', function ($params) {
      expect(function () use($params) {
        $params->parse(array('last' => array('z', 'some', Params::PARAM_NO_VALUE)));
      })->toThrow();

      expect(function () use($params) {
        $params->parse(array('first' => array('a', 'thing', Params::PARAM_REQUIRED)));
      })->toThrow();
    });

    it('should return the command caller', function ($params) {
      expect($params->caller())->toBe('a/b');
    });
  });
});
