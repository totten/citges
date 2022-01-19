<?php

namespace Civi\Coworker\Command;

use Civi\Coworker\CiviPipeConnection;
use Civi\Coworker\CiviQueueWatcher;
use Civi\Coworker\PipeConnection;
use Civi\Coworker\PipePool;
use React\Promise\Deferred;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function Clue\React\Block\await;
use function React\Promise\reject;

class RunCommand extends Command {

  use ConfigurationTrait;

  /**
   * @var \Monolog\Logger
   */
  private $logger;

  protected function configure() {
    $this
      ->setName('run')
      ->setDescription('Monitor queue for tasks and execute them.')
      ->addOption('channel', NULL, InputOption::VALUE_REQUIRED, 'Preferred communication channel (web,pipe). May give multiple for hybrid communication.')
      ->addOption('web', NULL, InputOption::VALUE_REQUIRED, 'Connect via web URL (HTTP base URL)')
      ->setHelp(
        "Monitor queue for tasks and execute them.\n" .
        "\n" .
        "<comment>Examples: Web (HTTPS):</comment>\n" .
        "\n" .
        "  coworker run --web='https://user:pass@example.com/civicrm/queue'\n" .
        "\n" .
        "<comment>Examples: Pipe (Shell/SSH/etc):</comment>\n" .
        "  coworker run\n" .
        "  coworker run --pipe='cv ev \"Civi::pipe();\"'\n" .
        "  coworker run --pipe='drush ev \"civicrm_initialize(); Civi::pipe();\"' \n" .
        "  coworker run --pipe='wp eval \"civicrm_initialize(); Civi::pipe();\"'\n" .
        //  "\n" .
        //  "<comment>Examples: Hybrid (HTTPS+SSH):</comment>\n" .
        //  "  coworker run --channel=web,pipe \\\n" .
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
    $this->logger = $this->createLogger($input, $output, $config);
    // [$ctlChannel, $workChannel] = $this->pickChannels($input, $output);
    // $this->logger->info('Setup channels (control={ctl}, work={work})', [
    //   'ctl' => $ctlChannel,
    //   'work' => $workChannel,
    // ]);

    $ctl = new CiviPipeConnection(
      new PipeConnection($config, 'ctl', $this->logger->withName('CtlPipe')),
      $this->logger->withName('CtlConn'));
    $work = new PipePool($config, $this->logger->withName('WorkPool'));

    await($ctl->start()
      // ->then(...PromiseUtil::dump())
      ->then(function (array $welcome) use ($ctl) {
        if (($welcome['t'] ?? NULL) === 'trusted') {
          // OK, we can execute Queue APIs.
          return $ctl->options(['apiCheckPermissions' => FALSE]);
        }
        else {
          return reject(new \Exception("coworker requires trusted connection"));
          // Alternatively, if $header['l']==='login' and you have login-credentials,
          // then perform a login.
        }
      })
      // ->then(...PromiseUtil::dump())
      ->then(function () use ($ctl, $config, $work) {
        $waitForStop = new Deferred();
        $watcher = new CiviQueueWatcher($config, $ctl, $work, $this->logger->withName('CiviQueueWatcher'));
        $watcher->on('stop', function() use ($waitForStop) {
          $waitForStop->resolve();
        });
        $watcher->start();
        return $waitForStop->promise();

        // Loop::addTimer(5, function() use ($watcher) {
        //   fwrite(STDERR, "Time to stop!\n");
        //   $watcher->stop();
        // });

      })
    );
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
