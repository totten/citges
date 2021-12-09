<?php

namespace Civi\Citges\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command {

  use ConfigurationTrait;

  protected function configure() {
    $this
      ->setName('run')
      ->setDescription('Monitor queue for tasks and execute them.')
      ->addOption('channel', NULL, InputOption::VALUE_REQUIRED, 'Preferred communication channel (web,pipe). May give multiple for hybrid communication.')
      ->addOption('web', NULL, InputOption::VALUE_REQUIRED, 'Connect via web URL (HTTP base URL)')
      ->addOption('pipe', NULL, InputOption::VALUE_REQUIRED, 'Connect via pipe (launcher command)')
      ->setHelp(
        "Monitor queue for tasks and execute them.\n" .
        "\n" .
        "<comment>Examples: Web (HTTPS):</comment>\n" .
        "\n" .
        "  citges run --web='https://user:pass@example.com/civicrm/queue'\n" .
        "\n" .
        "<comment>Examples: Pipe (Shell/SSH/etc):</comment>\n" .
        "  citges run\n" .
        "  citges run --pipe='cv ev \"Civi::pipe();\"'\n" .
        "  citges run --pipe='drush ev \"civicrm_initialize(); Civi::pipe();\"' \n" .
        "  citges run --pipe='wp eval \"civicrm_initialize(); Civi::pipe();\"'\n" .
        //  "\n" .
        //  "<comment>Examples: Hybrid (HTTPS+SSH):</comment>\n" .
        //  "  citges run --channel=web,pipe \\\n" .
        //  "    --web='https://user:pass@example.com/civicrm/queue' \\\n" .
        //  "    --pipe='ssh worker@example.com cv ev \"Civi::pipe();\"'\n" .
        "\n"
      );
    $this->configureCommonOptions();
    parent::configure();
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);
    if (empty($input->getOption('pipe')) && empty($input->getOption('web'))) {
      $input->setOption('pipe', 'cv ev "Civi::pipe();"');
    }
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $config = $this->createConfiguration($input, $output);
    $log = $this->createLogger($input, $output, $config);
    [$ctlChannel, $workChannel] = $this->pickChannels($input, $output);
    $log->info('Setup channels (control={ctl}, work={work})', [
      'ctl' => $ctlChannel,
      'work' => $workChannel,
    ]);
  }

  protected function pickChannels(InputInterface $input, OutputInterface $output): array {
    if (!$input->getOption('channel')) {
      if ($input->getOption('pipe') && !$input->getOption('web')) {
        return ['pipe', 'pipe'];
      }
      elseif ($input->getOption('web') && !$input->getOption('pipe')) {
        return ['web', 'web'];
      }
    }

    switch ($input->getOption('channel')) {
      case 'web':
        return ['web', 'web'];

      case 'pipe':
        return ['pipe', 'pipe'];

      case 'pipe,web':
        return ['pipe', 'web'];

      case 'web,pipe':
        return ['web', 'pipe'];

      default:
        throw new \RuntimeException("Please set --channel options");
    }
  }

}
