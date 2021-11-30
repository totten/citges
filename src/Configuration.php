<?php

namespace Civi\Citges;

class Configuration {

  /**
   * @var int
   */
  public $maxWorkers = 2;

  /**
   * @var int
   */
  public $maxRequests = 10;

  /**
   * @var int
   */
  public $maxDuration = 10 * 60;

  /**
   * Whenever we hit the maximum, we have to remove some old workers.
   * How many should we try to remove?
   *
   * @var int
   */
  public $gcWorkers = 1;

  /**
   * External command used to start the pipe.
   *
   * @var string
   *   Ex: 'cv ev "Civi::pipe();"'
   */
  public $pipeCommand;

}
