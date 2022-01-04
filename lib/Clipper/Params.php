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

    public function getTail()
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
            $short = !empty($param[0]) ? "-{$param[0]}, " : '    ';
            $usage = !empty($param[3]) ? "  {$param[3]}" : "  $name";
            $hints = !empty($param[2]) ? (($hint = $this->hint($param[2])) ? "  ($hint)" : '') : '';

            $out [] = $indent.str_pad($short.$long, $length).$usage.$hints;
        }

        return implode("\n", $out);
    }

    public function parse(array $params, $validate = false)
    {
        $args = $this->argv;

        $this->_ = array();
        $this->raw = array();
        $this->flags = array();
        $this->params = $params;

        if (false !== ($raw_key = array_search('--', $args))) {
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

                if (null !== $left['value']) {
                    $value = $this->cast($left);

                    if (self::PARAM_NO_VALUE & $left['opts']) {
                        if (null !== $left['value'] && $left['long'] && (self::PARAM_NO_VALUE & $left['opts'])) {
                            throw new \Exception("Unexpected value '{$left['value']}' for parameter '{$left['key']}'");
                        }

                        $this->add($left, $left['short'] ? null : $value);
                        array_splice($args, $offset--, 1, '-'.$left['value']);
                    } else {
                        $this->add($left, $value);
                    }
                } elseif ((null !== $right['value']) && !$right['key']) {
                    if (self::PARAM_NO_VALUE & $left['opts']) {
                        $this->_ [] = $right['value'];
                        $this->add($left);
                        $offset += 1;
                    } else {
                        $this->add($left, $this->cast($right, $left['type']));

                        if ($left['long']) {
                            $offset += 1;
                        }
                    }
                } else {
                    $this->add($left);
                }
            } elseif ($left['value']) {
                $this->_ [] = $left['value'];
            }
        }

        if ($validate) {
            foreach ($this->params as $key => $param) {
                $opts = !empty($param[2]) ? $param[2] : null;
                $type = !empty($param[4]) ? $param[4] : null;

                $debug_msg = sprintf("Invalid value '%s' for parameter '$key' ($type expected)", trim(print_r($this->$key, 1)));
                $exists = isset($this->flags[$key]);
                $value = $this->$key;

                if ($exists) {
                    switch ($type) {
                        case 'boolean';
                            if (!is_bool($value)) {
                                throw new \Exception($debug_msg);
                            }
                        break;

                        case 'number';
                            if (!is_integer($value)) {
                                throw new \Exception($debug_msg);
                            }
                        break;

                        case 'array';
                            if (!is_array($value)) {
                                throw new \Exception($debug_msg);
                            }
                        break;
                    }
                } elseif (self::PARAM_REQUIRED & $opts) {
                    throw new \Exception("Missing required parameter '$key'");
                }
            }
        }
    }

    private function add($param, $value = null)
    {
        if (self::PARAM_MULTIPLE & $param['opts']) {
            $this->flags[$param['key']]['value'] [] = (null !== $value ? $value : 1);
        } else {
            $this->flags[$param['key']] = (null !== $value ? $value : true);
        }
    }

    private function cast($param, $type = null)
    {
        if (!is_array($param)) {
            return $param;
        }

        if ($type) {
            $param['type'] = $type;
        }

        if (is_array($param['value'])) {
            if ('number' == $param['type']) {
                return array_sum($param['value']);
            } elseif ('array' == $param['type']) {
                return $param['value'];
            }

            return implode('', $param['value']);
        }

        if ('boolean' == $param['type']) {
            if (in_array($param['value'], $this->falsy)) {
                return false;
            }

            if (in_array($param['value'], $this->truthy)) {
                return true;
            }
        }

        if ('number' == $param['type']) {
            return (int) $param['value'];
        }

        return $param['value'];
    }

    private function hint($opts)
    {
        $out = array();

        if (self::PARAM_REQUIRED & $opts) {
            $out [] = 'required';
        }

        return implode(', ', $out);
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
            'type' => !empty($param[4]) ? $param[4] : null,
            'opts' => !empty($param[2]) ? $param[2] : null,
            'usage' => !empty($param[3]) ? $param[3] : null,
        ));
    }

    private function parts($arg)
    {
        preg_match('/^(?:--([-\w]+)(?:=(\S+))?|-(\w)(.+?|)|(.+?))$/', $arg ?: '', $matches);

        $long_flag = !empty($matches[1]) ? $matches[1] : null;
        $short_flag = !empty($matches[3]) ? $matches[3] : null;
        $inline_value = !empty($matches[4]) ? $matches[4] : (!empty($matches[2]) ? $matches[2] : (!empty($matches[5]) ? $matches[5] : null));

        if ($long_flag !== null && 'no-' === substr($long_flag, 0, 3)) {
            $long_flag = substr($long_flag, 3);
            $inline_value = false;
        }

        return $this->prop(array(
            'long' => $long_flag,
            'short' => $short_flag,
            'value' => $inline_value,
        ));
    }

    public function count(): int
    {
        return $this->_ ? sizeof($this->_) : 0;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->_);
    }

    public function offsetSet($offset, $value): void
    {
        $this->_[$offset] = $value;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->_[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->_[$offset]);
    }

    public function offsetGet($offset): mixed
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
