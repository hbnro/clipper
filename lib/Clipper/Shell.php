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

    $this->width = max(getenv('COLUMNS'), @exec('tput cols'), $this->width);
    $this->height = max(getenv('ROWS'), @exec('tput lines'), $this->height);
  }

  public function main(\Closure $callback)
  {
    $this->loop = 0;

    do {
      $this->loop++;
      $callback($this, $this->loop);
    } while ($this->loop);
  }

  public function quit()
  {
    $this->loop = 0;
  }

  public function wait($text = 'Press any key')
  {
    if (is_numeric($text)) {
      while (1) {
        if (($text -= 1) < 0) {
          break;
        }

        $this->write($len = strlen("$text..."));
        $this->back($len);

        pause(1);
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
      return trim(readline(join('', func_get_args())));
    }

    $this->write(join('', func_get_args()));

    return trim(fgets(STDIN, 128));
  }

  public function writeln($text = "\n")
  {
    $args = func_get_args();
    $args []= "\n";

    return $this->write(join('', $args))->flush();
  }

  public function write($text)
  {
    fwrite(STDOUT, join('', func_get_args()));

    return $this;
  }

  public function error($text)
  {
    fwrite(STDERR, $this->format("$text\n"));

    return $this->flush();
  }

  public function clear($num = 0)
  {
    if ($num) {
      $this->write(str_repeat("\x08", $num));
    } elseif ($this->is_atty()) {
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
    $value = strtolower(str_replace($default, '', $value)) . strtoupper($default);
    $value = str_replace('\\', '/', trim(addcslashes($value, $value), '\\'));

    $out = $this->readln(sprintf('%s [%s]: ', $text, $value)) ?: $default;

    return ($out && strstr($value, strtolower($out))) ? $out : $default;
  }

  public function menu(array $set, $default = '', $title = 'Choose one', $warn = 'Unknown option')
  {
    $old = array_values($set);
    $pad = strlen(sizeof($set)) + 2;

    foreach ($old as $i => $val) {
      $test = array_search($val, $set) == $default ? ' [*]' : '';

      $this->write("\n", str_pad($i + 1, $pad, ' ', STR_PAD_LEFT), '. ', $val, $test);
    } while (1) {
      $val = $this->readln("\n", $title, ': ');

      if (!is_numeric($val)) {
        return $default;
      } else {
        if (isset($old[$val -= 1])) {
          return array_search($old[$val], $set);
        } elseif ($val < 0 OR $val >= sizeof($old)) {
          return $this->error($warn);
        }
      }
    }
  }

  public function wrap($text, $width = -1, $align = 1, $margin = 2, $separator = ' ')
  {
    if (is_array($text)) {
      $text = join("\n", $text);
    }

    $max = $width > 0 ? $width : $this->width + $width;
    $max -= $margin *2;
    $out = array();
    $cur = '';

    $sep = strlen($separator);
    $left = str_repeat(' ', $margin);
    $pad = $align < 0 ? 0 : ($align === 0 ? 2 : 1);
    $test = explode("\n", str_replace(' ', "\n", $text));

    foreach ($test as $i => $str) {
      if (strlen($str) > $max) {
        $cur && $out []= $cur;

        $out []= wordwrap($str, $max + 2, "\n$left", true);
        $cur = '';
      } else {
        if ((strlen($cur) + strlen($str) + $sep) >= $max) {
          $cur = trim($cur, $separator);
          $out []= str_pad($cur, $max, ' ', $pad);
          $cur = '';
        }

        $cur .= "$str$separator";
      }
    }

    $cur && $out []= $cur;

    $test = join("\n$left", $out);

    $this->writeln("\n", "$left$test");
  }

  public function help($title, array $set = array())
  {
    $this->write("\n$title");

    $max = 0;

    foreach ($set as $one => $val) {
      $cur = !empty($val['args']) ? strlen(join('> <', $val['args'])) : 0;

      if (($cur += strlen($one)) > $max) {
        $max = $cur;
      }
    }

    $max += 4;

    foreach ($set as $key => $val) {
      $args = $key . (!empty($val['args']) ? ' <' . join('> <', $val['args']) . '>' : '');
      $flag = !empty($val['flag']) ? "-$val[flag]  " : '';

      $this->write(sprintf("\n  %-{$max}s %s%s", $args, $flag, $val['title']));
    }

    $this->flush(1);
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
