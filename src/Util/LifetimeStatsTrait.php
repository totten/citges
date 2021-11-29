<?php

namespace Civi\Citges\Util;

trait LifetimeStatsTrait {

  /**
   * @var double|null
   */
  protected $startTime = NULL;

  /**
   * @var double|null
   */
  protected $endTime = NULL;

  /**
   * @var int
   */
  protected $requestCount = 0;

  /**
   * @return float|null
   */
  public function getStartTime(): ?float {
    return $this->startTime;
  }

  /**
   * @return float|null
   */
  public function getEndTime(): ?float {
    return $this->endTime;
  }

  /**
   * @return int
   */
  public function getRequestCount(): int {
    return $this->requestCount;
  }

}
