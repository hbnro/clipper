<?php

namespace Clipper;

class Params implements \Countable, \ArrayAccess, \IteratorAggregate
{
  private $cmd;
  private $argv;

  private $_;
  private $raw;
  private $flags;
  private $params;

  private $falsy = array('-1', '0', 'no', 'off', 'false');
  private $truthy = array('1', 'ok', 'yes', 'on', 'true');

  const PARAM_NO_VALUE = 1;
  const PARAM_REQUIRED = 2;
  const PARAM_MULTIPLE = 4;

  const AS_ARRAY = 32;
  const AS_NUMBER = 64;
  const AS_BOOLEAN = 128;

  public function __construct(array $argv = array())
  {
    if (!func_num_args()) {
      $argv = !empty($_SERVER['argv']) ? $_SERVER['argv'] : array();
    }

    $this->cmd = array_shift($argv);
    $this->argv = $argv ?: array();
  }

  public function getCommand()
  {
    return $this->cmd;
  }

  public function getObject()
  {
    $out = new \stdClass();

    foreach ($this->flags as $key => $val) {
      $out->$key = $this->cast($val);
    }

    return $out;
  }

  public function getArray()
  {
    return $this->_;
  }

  public function getRaw()
  {
    return $this->raw;
  }

  public function usage($indent = 0)
  {
    $out = array();
    $max = array_map(function ($param) {
      return strlen($param[0]) + strlen($param[1]);
    }, $this->params);

    sort($max);

    $length = array_pop($max) + 5;
    $indent = is_numeric($indent) ? str_repeat(' ', $indent) : $indent;

    foreach ($this->params as $name => $param) {
      $long = !empty($param[1]) ? "--{$param[1]}" : '';
      $short = !empty($param[0]) ? "-{$param[0]}" : '';
      $usage = !empty($param[3]) ? "  {$param[3]}" : "  $name";
      $hints = !empty($param[2]) ? '  ' . $this->hint($param[2]) : '';

      $out []= $indent . str_pad(join(', ', array_filter(array($short, $long))), $length) . $usage . $hints;
    }

    return join("\n", $out);
  }

  public function parse(array $params)
  {
    $args = $this->argv;

    $this->_ = array();
    $this->raw = array();
    $this->flags = array();
    $this->params = $params;

    if ($raw_key = array_search('--', $args)) {
      $this->raw = array_slice($args, $raw_key + 1);
      $args = array_slice($args, 0, $raw_key);
    }

    for ($offset = 0; $offset < sizeof($args); $offset += 1) {
      $left = $this->parts($args[$offset]);
      $right = $this->parts(isset($args[$offset + 1]) ? $args[$offset + 1] : null);

      if ($left['key']) {
        if ((self::PARAM_MULTIPLE & $left['opts']) && !isset($this->flags[$left['key']])) {
          $this->flags[$left['key']] = array_merge($left, array(
            'value' => array(),
          ));
        }

        if ($left['value']) {
          if (self::PARAM_NO_VALUE & $left['opts']) {
            if (self::PARAM_MULTIPLE & $left['opts']) {
              $this->flags[$left['key']]['value'] []= 1;
            } else {
              $this->flags[$left['key']] = true;
            }

            $args []= '-' . $left['value'];
          } elseif (self::PARAM_MULTIPLE & $left['opts']) {
            $this->flags[$left['key']]['value'] []= $this->cast($left);
          } else {
            $this->flags[$left['key']] = $left;
          }
        } else if ($right['value'] && !$right['key']) {
          if (self::PARAM_NO_VALUE & $left['opts']) {
            $this->flags[$left['key']] = true;
            $this->_ []= $right['value'];

            $offset += 1;
          } else {
            $this->flags[$left['key']] = $right;
          }
        } else {
          if (self::PARAM_MULTIPLE & $left['opts']) {
            $this->flags[$left['key']]['value'] []= 1;
          } else {
            $this->flags[$left['key']] = $left;
          }
        }
      } elseif ($left['value']) {
        $this->_ []= $left['value'];
      }
    }
  }

  private function cast($param)
  {
    if (!is_array($param)) {
      return $param;
    }

    if (is_array($param['value'])) {
      if (self::AS_NUMBER & $param['opts']) {
        return array_sum($param['value']);
      } elseif (self::AS_ARRAY & $param['opts']) {
        return $param['value'];
      }

      return join('', $param['value']);
    }

    if (self::AS_BOOLEAN & $param['opts']) {
      if (in_array($param['value'], $this->falsy)) {
        return false;
      }

      if (in_array($param['value'], $this->truthy)) {
        return true;
      }

      throw new \Exception("Must be a valid boolean-value: {$param['value']}");
    }

    if (self::AS_NUMBER & $param['opts']) {
      return (int) $param['value'];
    }

    return !(self::PARAM_NO_VALUE & $param['opts']) ? $param['value'] : true;
  }

  private function hint($opts)
  {
  }

  private function prop($params)
  {
    $name = $params['long'] ?: $params['short'];
    $param = array();

    foreach ($this->params as $key => $value) {
      if (($params['long'] === $value[1]) || ($params['short'] === $value[0])) {
        $param = $value;
        $name = $key;
        break;
      }
    }

    return array_merge($params, array(
      'key' => $name,
      'opts' => !empty($param[2]) ? $param[2] : null,
      'usage' => !empty($param[3]) ? $param[3] : null,
    ));
  }

  private function parts($arg)
  {
    preg_match('/^(?:--([-\w]+)(?:=(\S+))?|-(\w)(.+?|)|(.+?))$/', $arg, $matches);

    $long_flag = !empty($matches[1]) ? $matches[1] : null;
    $short_flag = !empty($matches[3]) ? $matches[3] : null;
    $inline_value = !empty($matches[4]) ? $matches[4] : (!empty($matches[2]) ? $matches[2] : (!empty($matches[5]) ? $matches[5] : null));

    return $this->prop(array(
      'long' => $long_flag,
      'short' => $short_flag,
      'value' => $inline_value,
    ));
  }

  public function count()
  {
    return sizeof($this->_);
  }

  public function getIterator()
  {
    return new \ArrayIterator($this->_);
  }

  public function offsetSet($offset, $value)
  {
    $this->_[$offset] = $value;
  }

  public function offsetExists($offset)
  {
    return isset($this->_[$offset]);
  }

  public function offsetUnset($offset)
  {
    unset($this->_[$offset]);
  }

  public function offsetGet($offset)
  {
    return $this->_[$offset];
  }

  public function __unset($key)
  {
    unset($this->flags[$key]);
  }

  public function __isset($key)
  {
    return isset($this->flags[$key]);
  }

  public function __set($key, $value)
  {
    $this->flags[$key] = $value;
  }

  public function __get($key)
  {
    return isset($this->flags[$key]) ? $this->cast($this->flags[$key]) : null;
  }
}
