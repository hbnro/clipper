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

      unset($params['bar']);
      expect(isset($params['bar']))->toBeFalsy();

      $params['bar'] = 'does nothing';

      expect($params['x'])->toBeNull();
      expect($params['bar'])->toBe('does nothing');
      expect($params['foo'])->toBe(array('bar', 'FU!'));
    });

    it('should handle the rest as values', function ($params) {
      $params->parse();

      unset($params[1]);
      expect(isset($params[1]))->toBeFalsy();

      $params[1] = 'overwrite';

      expect($params[2])->toBe('c');
      expect($params[-1])->toBeNull();
      expect(sizeof($params))->toBe(4);
      expect($params[1])->toBe('overwrite');

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

      expect(function () use($params) {
        $params->parse(array('ultimate' => array('z', 'candy', Params::PARAM_MULTIPLE)));
      })->toThrow();
    });

    it('should return the command caller', function ($params) {
      expect($params->caller())->toBe('a/b');
    });

    it('should return the rest of values', function ($params) {
      $params->parse(array('example' => array('f', 'fuu')));

      expect($params->args())->toBe(array('example' => 'FU!'));
      expect($params->values())->toBe(array('m', 'n', 'c', 'baz'));
    });

    it('should use $argv by default', function () {
      $test = new Params();
      $argv =$_SERVER['argv'];

      expect($test->caller())->toBe(array_shift($argv));
    });
  });
});
