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

  protected $moribund = FALSE;

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

  /**
   * @return bool
   */
  public function isMoribund(): bool {
    return $this->moribund;
  }

  /**
   * @param bool $moribund
   */
  public function setMoribund(bool $moribund): void {
    $this->moribund = $moribund;
  }

  public function isExhausted(array $policy): bool {
    if ($this->moribund) {
      return TRUE;
    }
    if ($this->requestCount > $policy['maxRequests']) {
      return TRUE;
    }
    if ($this->startTime + $policy['maxDuration'] > microtime(TRUE)) {
      return TRUE;
    }
    return FALSE;
  }

}
