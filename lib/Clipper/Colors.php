<?php

namespace Clipper;

class Colors
{
  private $atty;

  private $bgcolors = array(
                      'black' => 40, 'red' => 41,
                      'green' => 42, 'yellow' => 43,
                      'blue' => 44, 'magenta' => 45,
                      'cyan' => 46, 'light_gray' => 47,
                    );

  private $fgcolors = array(
                      'black' => 30, 'red' => 31, 'green' => 32,
                      'brown' => 33, 'blue' => 34, 'purple' => 35,
                      'cyan' => 36, 'light_gray' => 37,
                      'dark_gray' => '1;30', 'light_red' => '1;31',
                      'light_green' => '1;32', 'yellow' => '1;33',
                      'light_blue' => '1;34', 'light_purple' => '1;35',
                      'light_cyan' => '1;36', 'white' => '1;37',
                    );

  private $fmt_regex = '/<([cubh]{1,3}):([^<>]+)>(\s*)([^<>]+?)(\s*)<\/\\1>/';
  private $strip_regex = "/<[cubh]:[^<>]+>|\033\[[\d;]*m|<\/[cubh]>/";

  public function __construct()
  {
    $this->atty = (false !== getenv('ANSICON')) || (function_exists('posix_isatty') && @posix_isatty(STDOUT));
  }

  public function format($text)
  {
    if (!$this->is_atty()) {
      return $this->strips($text);
    }

    while (preg_match_all($this->fmt_regex, $text, $match)) {
      foreach ($match[0] as $i => $val) {
        @list($fg, $bg) = explode(',', $match[2][$i], 2); // fg,bg

        $out  = array();
        $out []= $this->fgcolors[$fg];

        if (strstr($match[1][$i], 'b')) {
          $out []= 1;
        }

        if (strstr($match[1][$i], 'u')) {
          $out []= 4;
        }

        if (strstr($match[1][$i], 'h')) {
          $out []= 7;
        }

        if ($bg) {
          $out []= $this->bgcolors[$bg];
        }

        $color = "\033[" . ($out ? join(';', $out) : 0) . 'm';
        $color = "{$match[3][$i]}{$color}{$match[4][$i]}\033[0m{$match[5][$i]}";
        $text  = str_replace($val, $color, $text);
      }
    }

    return $text;
  }

  public function strips($test)
  {
    return preg_replace($this->strip_regex, '', $test);
  }

  public function is_atty()
  {
    return $this->atty;
  }
}
