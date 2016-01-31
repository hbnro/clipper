<?php

namespace Clipper;

class Shell
{
    public $params;
    public $colors;

    public $width = 40;
    public $height = 13;

    private $loop = 0;
    private $start = 0;

    private $prompter;

    public function __construct(array $argv = array())
    {
        $this->prompter = new Prompter($this);
        $this->params = new Params($argv);
        $this->colors = new Colors();

        $cols = 0;
        $lines = 0;

        if ('WIN' !== strtoupper(substr(PHP_OS, 0, 3))) {
            $cols = @exec('tput cols');
            $lines = @exec('tput lines');
        } else {
            @exec('mode', $output);

            preg_match('/\s+Columns:\s*(\d+)/', implode("\n", $output), $columns);
            preg_match('/\s+Lines:\s*(\d+)/', implode("\n", $output), $lines);

            $cols = $columns[1];
            $lines = $lines[1];
        }

        $this->width = max(getenv('COLUMNS'), $cols, $this->width);
        $this->height = max(getenv('ROWS'), $lines, $this->height);
    }

    public function __call($method, $arguments)
    {
        return call_user_func_array(array($this->prompter, $method), $arguments);
    }

    public function main(\Closure $callback)
    {
        $this->loop = 0;
        $this->start = time();

        do {
            ++$this->loop;
            $callback($this, $this->loop, time() - $this->start);
        } while ($this->loop);
    }

    public function quit()
    {
        $this->loop = 0;
    }

    public function format($text, $strips = false)
    {
        return $strips ? $this->colors->strips($text) : $this->colors->format($text);
    }

    public function sprintf($text)
    {
        return $this->format(func_get_args() > 2 ? vsprintf($text, array_slice(func_get_args(), 1)) : $text);
    }

    public function printf($text)
    {
        fwrite(STDOUT, call_user_func_array(array($this, 'sprintf'), func_get_args()));

        return $this;
    }

    public function readln($text = "\n")
    {
        if (function_exists('readline')) {
            return trim(readline(implode('', func_get_args())));
        }

        $this->write(implode('', func_get_args()));

        return trim(fgets(STDIN, 128));
    }

    public function writeln($text = "\n")
    {
        $args = func_get_args();
        $args [] = "\n";

        $this->write(implode('', $args));
        $this->flush();

        return $this;
    }

    public function write($text)
    {
        fwrite(STDOUT, implode('', func_get_args()));

        return $this;
    }

    public function error($text)
    {
        fwrite(STDERR, $this->format("$text\n"));

        $this->flush();

        return $this;
    }

    public function clear($num = 0)
    {
        if ($num > 0) {
            $this->write(str_repeat("\x08", $num));
        } elseif ($this->colors->is_atty()) {
            $this->write("\033[H\033[2J");
        } else {
            $c = $this->height;

            while ($c -= 1) {
                $this->writeln();
            }
        }

        return $this;
    }

    public function flush($test = 0)
    {
        if ($test > 0) {
            fwrite(STDOUT, str_repeat("\n", $test));
        }

        ob_get_level() && ob_flush();

        flush();
    }
}
