<?php

describe('Parsing argvs:', function () {
  $params = new \Clipper\Params(array(
    'vendor/bin/cmd',
    '-w',
    'a',
    '-yo',
    '-xyz',
    '--make=off',
    '-s1024',
    'raw value',
    'and',
    'a',
    '--a',
    'mixed value',
    '--option=value',
    '-ccc',
    '-d13',
    '-d20',
    '-mmn15',
    '--',
    '-this',
    '--will',
    'not get parsed',
  ));

  $params->parse(array(
    'boolean_value' => array('w', '', \Clipper\Params::PARAM_NO_VALUE),
    'mixed_value' => array('x', '', \Clipper\Params::PARAM_NO_VALUE),
    'arr_value' => array('y', '', \Clipper\Params::PARAM_MULTIPLE, '', 'array'),
    'do_make' => array('k', 'make', null, '', 'boolean'),
    'number_value' => array('s', '', null, '', 'number'),
    'acc_value' => array('c', '', \Clipper\Params::PARAM_NO_VALUE | \Clipper\Params::PARAM_MULTIPLE, '', 'number'),
    'sum_value' => array('d', '', \Clipper\Params::PARAM_MULTIPLE, '', 'number'),
    'multi_num' => array('m', '', \Clipper\Params::PARAM_NO_VALUE | \Clipper\Params::PARAM_MULTIPLE, '', 'number'),
    'single_num' => array('n', '', null, '', 'number'),
  ));

  let('params', $params);

  it('should parse nothing after --', function ($params) {
    expect($params->getTail())->toBe(array('-this', '--will', 'not get parsed'));
  });

  it('should parse non-flags as strings', function ($params) {
    expect($params->getArray())->toBe(array('a', 'raw value', 'and', 'a', 'mixed value'));
  });

  it('should parse --option=value as string', function ($params) {
    expect($params->option)->toBe('value');
  });

  it('should parse --make "off" as boolean', function ($params) {
    expect($params->do_make)->toBeBoolean();
  });

  it('should parse --a "mixed value" as string', function ($params) {
    expect($params->a)->toBe('mixed value');
  });

  it('should parse -s1024 as integer', function ($params) {
    expect($params->number_value)->toBeInteger();
  });

  it('should parse -ccc as integer', function ($params) {
    expect($params->acc_value)->toBe(3);
  });

  it('should parse -w as boolean', function ($params) {
    expect($params->boolean_value)->toBe(true);
  });

  describe('parsing -d13 -d20', function () {
    it('should parse both values as integer', function ($params) {
      expect($params->sum_value)->toBe(33);
    });
  });

  describe('parsing -mmn15', function () {
    it('should parse -mm as integer', function ($params) {
      expect($params->multi_num)->toBe(2);
    });

    it('should parse -n15 as integer', function ($params) {
      expect($params->single_num)->toBe(15);
    });
  });

  describe('parsing -yo -xyz', function () {
    it('should parse -x as boolean', function ($params) {
      expect($params->mixed_value)->toBe(true);
    });

    it('should parse -yo -yz as array', function ($params) {
      expect($params->arr_value)->toBe(array('o', 'z'));
    });
  });

  describe('About params:', function () {
    it('should return an array for arguments', function ($params) {
      expect($params->getArray())
        ->toBeArray()
        ->toContain('mixed value')
        ->not->toHaveKey('mixed_value');

      expect($params[0])->toBe('a');
    });

    it('should return an object for params', function ($params) {
      expect($params->getObject())
        ->toBeObject()
        ->toContain('mixed value')
        ->toHaveKey('mixed_value');

      expect($params->mixed_value)->toBe(true);
    });

    it('should validate missing params', function () {
      expect(function ($params) {
        $params->parse(array(
          'some_value' => array('P', '', \Clipper\Params::PARAM_REQUIRED),
        ), true);
      })->toThrow();
    });

    it('should validate empty params', function () {
      expect(function ($params) {
        $params->parse(array(
          'empty_value' => array('', 'make', \Clipper\Params::PARAM_NO_VALUE),
        ), true);
      })->toThrow();
    });

    it('should provide usage info', function ($params) {
      $params->parse(array(
        'README' => array('h', 'help', null, 'Show this help'),
        'inputFile' => array('f', 'file', \Clipper\Params::PARAM_REQUIRED, null, 'string'),
        'outputFile' => array('o', 'output', \Clipper\Params::PARAM_REQUIRED, null, 'string'),
        'tempDirectory' => array('', 'dir', \Clipper\Params::PARAM_NO_VALUE),
        'filterGlobPattern' => array('', 'glob', null, 'Glob pattern to filter input', 'string'),
      ));

      expect($params->usage())->toBe(join("\n", array(
        '-h, --help    Show this help',
        '-f, --file    inputFile  [required]',
        '-o, --output  outputFile  [required]',
        '    --dir     tempDirectory  [no-value]',
        '    --glob    Glob pattern to filter input',
      )));
    });
  });
});