<?php

namespace Clipper;

class Params implements \Countable, \ArrayAccess, \IteratorAggregate
{
  private $cmd;
  private $argv;

  private $values = array();
  private $params = array();

  const PARAM_NO_VALUE = 1;
  const PARAM_REQUIRED = 2;
  const PARAM_MULTIPLE = 4;

  public function __construct(array $params, array $argv = array())
  {
    if (func_num_args() === 1) {
      $argv = !empty($_SERVER['argv']) ? $_SERVER['argv'] : array();
    }

    $this->cmd = array_shift($argv);
    $this->argv = $argv ?: array();

    if ($test = $this->parse($params)) {
      $this->values = $test['in'];
      $this->params = $test['args'];
    }
  }

  public function caller()
  {
    return $this->cmd;
  }

  private function parse(array $args)
  {
    $out = array(
      'in' => array(),
      'args' => array(),
    );

    $count = sizeof($this->argv);
    $offset = 0;

    for (; $offset < $count; $offset += 1) {
      $arg = $this->argv[$offset];

      if (preg_match('/^(?:--(\w+)(?:=(\S+))?|-([a-zA-Z])(\S+)?)$/', $arg, $match)) {
        $next = !empty($this->argv[$offset + 1]) ? $this->argv[$offset + 1] : null;
        $key = !empty($match[3]) ? $match[3] : $match[1];

        if ((null === $next) || (substr($next, 0, 1) === '-')) {
          $value = !empty($match[4]) ? $match[4] : (!empty($match[2]) ? $match[2] : null);
          $value = null === $value ? true : (strlen($value) ? $value : true);
        } elseif (!empty($match[4])) {
          $value = $match[4];
        } else {
          $value = $next;
          $offset++;
        }

        $out['args'] []= array($key => $value);
      } else {
        $out['in'] []= $arg;
      }
    }

    $out['args'] = $this->validate($args, $out['args']);

    return $out;
  }

  private function validate(array $raw, array $args = array())
  {
    $out = array();

    foreach ($raw as $key => $val) {
      $out += $this->params($key, $val, $args);
    }

    return $out;
  }

  private function params($key, array $field, array $args)
  {
    $out = array();

    @list($short, $long, $opt) = $field;

    foreach ($args as $one) {
      $sub = key($one);
      $val = $one[$sub];

      if (($sub === $short) || ($sub === $long)) {
        if ((self::PARAM_REQUIRED & $opt) && (!strlen($val) || (true === $val))) {
          throw new \Exception("Missing required value for parameter '$sub'");
        } elseif ((self::PARAM_NO_VALUE & $opt) && (true !== $val)) {
          throw new \Exception("Unexpected value '$val' for parameter '$sub'");
        }

        if (self::PARAM_MULTIPLE & $opt) {
          if ((true === $val) || (null === $val)) {
            throw new \Exception("Missing value for parameter '$sub'");
          } else {
            isset($out[$key]) || $out[$key] = array();
            $out[$key] []= $val;
          }
        } else {
          $out[$key] = $val;
        }
      }
    }

    return $out;
  }

  public function count()
  {
    return sizeof($this->values);
  }

  public function getIterator()
  {
    return new \ArrayIterator($this->values);
  }

  public function offsetSet($offset, $value)
  {
    $this->$offset = $value;
  }

  public function offsetExists($offset)
  {
    return isset($this->$offset);
  }

  public function offsetUnset($offset)
  {
    unset($this->$offset);
  }

  public function offsetGet($offset)
  {
    return $this->$offset;
  }

  public function __unset($key)
  {
    unset($this->params[$key]);
  }

  public function __isset($key)
  {
    if (is_numeric($key)) {
      return isset($this->values[(int) $key]);
    } else {
      return isset($this->params[$key]);
    }
  }

  public function __set($key, $value)
  {
    if (is_numeric($key)) {
      $this->values[(int) $key] = $value;
    } else {
      $this->params[$key] = $value;
    }
  }

  public function __get($key)
  {
    if (is_numeric($key)) {
      return isset($this->values[(int) $key]) ? $this->values[(int) $key] : null;
    } else {
      return isset($this->params[$key]) ? $this->params[$key] : null;
    }
  }
}
