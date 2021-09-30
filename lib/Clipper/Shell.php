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

        $cols = 80;
        $lines = 25;

        if ('WIN' !== strtoupper(substr(PHP_OS, 0, 3))) {
            $TERM_SIZE = getenv('TERM_SIZE');

            if ($this->colors->is_atty()) {
                $parts = @explode(' ', $TERM_SIZE ?: '');
                $cols = !empty($parts[0]) ?: @exec('tput cols');
                $lines = !empty($parts[1]) ?: @exec('tput lines');
            }

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

    public function wait($text = 'Press ENTER to continue')
    {
        if (is_numeric($text)) {
            $length = strlen($text) + 3;
            while (1) {
                if ($text <= 0) {
                    break;
                }

                $this->clear(-$length)
                    ->write("$text...");
                $text -= 1;
                sleep(1);
            }
            $this->clear(-$length);
        } else {
            $this->save()
                ->writeln($text)
                ->readln();
            $this->back()
                ->clear();
        }
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

    public function clear($nth = null)
    {
        if ($nth < 0) {
            $chars = abs($nth);
            $this->write("\033[${chars}D\033[K");
        } else if ($nth === true) {
            if ($this->colors->is_atty()) {
                $this->write("\033[H\033[2J");
            } else {
                $c = $this->height;

                while ($c -= 1) {
                    $this->writeln();
                }
            }
        } else if ($nth > 0) {
            while ($nth -= 1) {
                $this->write("\033[1A\033[K");
            }
        } else {
            $this->write("\033[K");
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

        return $this;
    }

    public function save()
    {
        return $this->write("\033[s");
    }

    public function back()
    {
        return $this->write("\033[u");
    }
}
