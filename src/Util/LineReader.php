<?php

namespace Civi\Coworker\Util;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;

/**
 * LineReader is a wrapper for ReadableStreamInterface. It buffers and filters
 * the input stream - and ultimately emits `readline` events whenever a "\n"
 * is received.
 *
 * ```php
 * $lineReader = new LineReader($myOriginalReader);
 * $lineReader->on('readline', function($line) {...});
 * ```
 */
class LineReader extends EventEmitter implements ReadableStreamInterface {

  /**
   * @var \React\Stream\ReadableStreamInterface
   */
  protected $reader;

  /**
   * @var string
   */
  protected $delimiter;

  /**
   * @param \React\Stream\ReadableStreamInterface $reader
   * @param string $delimiter
   *   The line-delimiter (eg "\n").
   */
  public function __construct(ReadableStreamInterface $reader, string $delimiter = "\n") {
    $this->reader = $reader;
    $this->delimiter = $delimiter;

    foreach (['error', 'end', 'close', 'data'] as $passthruEvent) {
      $reader->on($passthruEvent, function () use ($passthruEvent) {
        $this->emit($passthruEvent, func_get_args());
      });
    }

    $buf = '';
    $reader->on('data', function ($newData) use (&$buf) {
      //      fprintf(STDERR, 'LineReader: %s', $newData);
      $buf .= $newData;
      $pos = strpos($buf, $this->delimiter);
      while ($pos !== FALSE) {
        $nextLine = substr($buf, 0, $pos);
        $buf = substr($buf, 1 + $pos);
        $this->emit('readline', [$nextLine]);

        $pos = strpos($buf, $this->delimiter);
      }
    });
  }

  public function isReadable() {
    return $this->reader->isReadable();
  }

  public function pause() {
    $this->reader->pause();
  }

  public function resume() {
    $this->reader->resume();
  }

  public function pipe(WritableStreamInterface $dest, array $options = []) {
    return $this->reader->pipe($dest, $options);
  }

  public function close() {
    $this->reader->close();
  }

}
