<?php

namespace Civi\Coworker;

use Civi\Coworker\Util\IdUtil;
use React\Promise\Deferred;

/**
 * @internal
 */
class Todo {

  /**
   * @var int
   */
  public $id;

  /**
   * @var string
   */
  public $context;

  /**
   * @var string
   */
  public $request;

  /**
   * @var string
   */
  public $deferred;

  /**
   * @param string $context
   * @param string $request
   */
  public function __construct(string $context, string $request) {
    $this->id = IdUtil::next(__CLASS__);
    $this->context = $context;
    $this->request = $request;
    $this->deferred = new Deferred();
  }

}
