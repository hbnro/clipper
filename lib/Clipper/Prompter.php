<?php

namespace Clipper;

class Prompter
{
    private $cli;

    public function __construct($instance)
    {
        $this->cli = $instance;
    }

    public function prompt($text, $default = '')
    {
        $default && $text .= " [$default]";

        return $this->cli->readln($text, ': ') ?: $default;
    }

    public function choice($text, $value = 'yn', $default = 'n')
    {
        $value = str_replace($default, strtoupper($default), strtolower($value));
        $value = str_replace('\\', '/', trim(addcslashes($value, $value), '\\'));

        $out = $this->cli->readln(sprintf('%s [%s]: ', $text, $value)) ?: $default;

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
            $this->cli->write("$out\n");
            $val = $this->cli->readln($title, ': ');

            if (!$val) {
                return $default;
            } elseif (!is_numeric($val)) {
                $this->cli->error($warn);
            } elseif (isset($old[$val -= 1])) {
                return array_search($old[$val], $set);
            } elseif ($val < 0 || $val >= sizeof($old)) {
                $this->cli->error($warn);
            }
        }
    }

    public function wrap($text, $width = -1, $align = 1, $margin = 2, $separator = ' ')
    {
        if (is_array($text)) {
            $text = implode("\n", $text);
        }

        $max = $width > 0 ? $width : $this->cli->width + $width;
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

        $this->cli->writeln("\n", implode("\n", array_map('rtrim', explode("\n", "$left$test"))));
    }

    public function progress($current, $total = 100, $title = '')
    {
        $perc = str_pad(min(100, round(($current / $total) * 100) + 1), 4, ' ', STR_PAD_LEFT);
        $dummy = $this->cli->format($title = $this->cli->format($title), true);
        $length = $this->cli->width - (strlen($dummy) + 7);
        $finish = $current + 1 === $total;

        if ($current < $total) {
            $line = "\r";
            $line .= $title ? "$title " : '';

            $left = ceil($current / $total * $length);
            $right = $length - $left;

            $line .= $this->cli->sprintf('<c:cyan,cyan>%s</c>', str_repeat('|', $left));
            $line .= $this->cli->sprintf('<c:light_gray,light_gray>%s</c>', str_repeat('-', $right));

            $this->cli->write(sprintf('%s %3d%%%s', $line, $perc, $finish ? "\n" : ''));
        }
    }

    public function table(array $set, array $heads = array())
    {
      // TODO: make it work on large amount of data?
      $set  = array_values($set);
      $max  = $this->cli->width / sizeof($set[0]);
      //$max -= sizeof($set[0]);

      $head =
      $sep  =
      $col  = array();

      foreach ($set as $test) {// columns
        $key = 0;

        foreach (array_values($test) as $one) {
          $old = isset($col[$key]) ? $col[$key] : strlen($key);

          if ( ! isset($col[$key])) {
            $col[$key] = strlen($key);
          }

          if (strlen($one) > $old) {
            $num = strlen($one);
            $col[$key] = $num < $max ? $num : $max;
          }
          $key += 1;
        }
      }

      reset($set);

      $out = array();

      foreach (array_values($heads) as $key => $one) {
        $head []= str_pad($one, $col[$key], ' ', STR_PAD_RIGHT);
        $head []= ' ';

        if (strlen($one) > $col[$key]) {
          $col[$key] = strlen($one);
        }
      }

      $glue   = '';

      $this->cli->writeln();

      if ( ! empty($heads)) {
        $heads = join('', $head);
        $heads = preg_replace('/\b\w+\b/', '\cpurple(\\0)\c', $heads);

        $this->cli->write($this->cli->format(" $heads"));
        $this->cli->writeln();
      }

      foreach ($set as $test) { // data
        $key = 0;
        $row = array('');

        foreach ($test as $one) {
          $one   = substr($one, 0, strlen($one) > $max ? $max - 3 : $max) . (strlen($one) > $max ? '...' : '');
          $one   = str_pad($one, $col[$key], ' ', is_numeric($one) ? STR_PAD_LEFT : STR_PAD_RIGHT);
          $row []= preg_replace('/[\r\n\t]/', ' ', $one);
          $key  += 1;
        }

        $row []= '';
        $out []= ' ' . trim(join(' ', $row));
      }

      $this->cli->write("\n" . join("\n", $out));
      $this->cli->write("\n\n");
      $this->cli->flush();
    }
}
