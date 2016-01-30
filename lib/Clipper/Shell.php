<?php

namespace Clipper;

class Shell
{
    public $params;
    public $colors;

    private $loop = 0;
    private $start = 0;

    private $width = 40;
    private $height = 13;

    public function __construct(array $argv = array())
    {
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

    public function wait($text = 'Press any key', $format = '%ds...')
    {
        if (is_numeric($text)) {
            while (1) {
                if ($format) {
                    $nth = sprintf($format, $text);
                    $this->clear(strlen($nth));
                    $this->write($nth . (!$text ? "\n" : ''));
                }

                if (($text -= 1) < 0) {
                    break;
                }

                sleep(1);
            }
        } else {
            $this->writeln($text);
            $this->readln();
        }
    }

    public function format($text, $strips = false)
    {
        return $strips ? $this->colors->strips($text) : $this->colors->format($text);
    }

    public function sprintf($text)
    {
        return vsprintf($this->format($text), array_slice(func_get_args(), 1));
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

        return $this->write(implode('', $args))->flush();
    }

    public function write($text)
    {
        fwrite(STDOUT, implode('', func_get_args()));

        return $this;
    }

    public function error($text)
    {
        fwrite(STDERR, $this->format("$text\n"));

        return $this->flush();
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

    public function prompt($text, $default = '')
    {
        $default && $text .= " [$default]";

        return $this->readln($text, ': ') ?: $default;
    }

    public function choice($text, $value = 'yn', $default = 'n')
    {
        $value = str_replace($default, strtoupper($default), strtolower($value));
        $value = str_replace('\\', '/', trim(addcslashes($value, $value), '\\'));

        $out = $this->readln(sprintf('%s [%s]: ', $text, $value)) ?: $default;

        return ($out && strstr($value, strtolower($out))) ? $out : $default;
    }

    public function menu(array $set, $default = -1, $title = 'Choose one', $warn = 'Unknown option')
    {
        $out = "\n";
        $old = array_values($set);
        $pad = strlen(sizeof($set)) + 2;

        foreach ($old as $i => $val) {
            $test = array_search($val, $set) === $default ? ' [*]' : '';

            $out .= implode('', array(str_pad($i + 1, $pad, ' ', STR_PAD_LEFT), '. ', $val, $test, "\n"));
        }

        while (1) {
            $this->write("$out\n");
            $val = $this->readln($title, ': ');

            if (!$val) {
                return $default;
            } elseif (!is_numeric($val)) {
                $this->error($warn);
            } elseif (isset($old[$val -= 1])) {
                return array_search($old[$val], $set);
            } elseif ($val < 0 || $val >= sizeof($old)) {
                $this->error($warn);
            }
        }
    }

    public function wrap($text, $width = -1, $align = 1, $margin = 2, $separator = ' ')
    {
        if (is_array($text)) {
            $text = implode("\n", $text);
        }

        $max = $width > 0 ? $width : $this->width + $width;
        $max -= $margin * 2;
        $out = array();
        $cur = '';

        $sep = strlen($separator);
        $left = str_repeat(' ', $margin);
        $pad = $align < 0 ? 0 : ($align === 0 ? 2 : 1);
        $test = explode("\n", str_replace(' ', "\n", $text));

        foreach ($test as $i => $str) {
            if (strlen($str) > $max) {
                $cur && $out [] = $cur;

                $out [] = wordwrap($str, $max + 2, "\n$left", true);
                $cur = '';
            } else {
                if ((strlen($cur) + strlen($str) + $sep) >= $max) {
                    $cur = trim($cur, $separator);
                    $out [] = str_pad($cur, $max, ' ', $pad);
                    $cur = '';
                }

                $cur .= "$str$separator";
            }
        }

        $cur && $out [] = $cur;

        $test = implode("\n$left", $out);

        $this->writeln("\n", "$left$test");
    }

    public function progress($current, $total = 100, $title = '')
    {
        $perc = str_pad(min(100, round(($current / $total) * 100) + 1), 4, ' ', STR_PAD_LEFT);
        $dummy = $this->format($title = $this->format($title), true);
        $length = $this->width - (strlen($dummy) + 7);
        $finish = $current + 1 === $total;

        if ($current < $total) {
            $line = "\r";
            $line .= $title ? "$title " : '';

            $left = ceil($current / $total * $length);
            $right = $length - $left;

            $line .= $this->sprintf('<c:cyan,cyan>%s</c>', str_repeat('|', $left));
            $line .= $this->sprintf('<c:light_gray,light_gray>%s</c>', str_repeat('-', $right));

            $this->write(sprintf('%s %3d%%%s', $line, $perc, $finish ? "\n" : ''));
        }
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
}
