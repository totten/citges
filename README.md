# citges

`citges` is a task runner for CiviCRM.  It aims to execute tasks quickly and to enforce resource-limits.  Key features:

* `citges` launches via command-line, crontab, or systemd.
* `citges` runs tasks on local or remote systems.
* `citges` connects to remote systems via SSH or HTTPS.

## Dependencies

* Required: PHP v7.2+
* Required: CiviCRM (*FIXME: Version number*)
* Recommended: `cv`, `drush`, or `wp-cli`

## Download

```
sudo wget 'https://FIXME/citges.phar' -O '/usr/local/bin/citges'
sudo chmod +x /usr/local/bin/citges
```

(*FIXME: composer-require and composer-download*)

## Usage

The `citges run` command connects to CiviCRM, requests pending tasks, and executes them. This requires a communication channel, and each communication
channel has different properties:

| Communication Channel | Description | Compatibility | Latency | RAM/CPU Limits |
| -- | -- | -- | -- | -- |
| `web` | Send HTTP requests to a remote CiviCRM web server. | All web servers | Medium-high latency | Assigned by web-server |
| `pipe` (local) | Send bidirectional messages to a long-running CiviCRM process. | Servers with full sysadmin access | Low latency | Configurable |
| `pipe` (SSH/etc) | Send bidirectional messages to a long-running CiviCRM process. | Remote servers with SSH access (or similar) | Mixed (high-latency setup; low-latency message) | Configurable |

### Usage: HTTP

To run tasks remotely using HTTP:

```
citges run --web='https://user:pass@example.com/civicrm/queue'
```

(*FIXME: discuss credentials management, authx, etc; maybe rework as pure JWT?*)

This method comes with some trade-offs:

* __Strengths__: Works with any web server architecture. If you have multiple web servers, requests will be automatically distributed among them.
* __Weaknesses__: To avoid overloading the web server, polling intervals are fairly long. Every request requires a full bootstrap. The
  resource limits (RAM and CPU time) for web servers are often a bit tight.

### Usage: Pipe

In Unix-style systems, a *pipe* is a flexible and performant mechanism for exchanging data and commands. To allow quicker processing of background tasks,
Coworker may start a CiviCRM process and exchange data through a pipe. However, the setup process may require greater access and more steps.

* __Strengths__: Once a pipe is started, it can be used repeatedly - which enables faster polling and reduced latency. Piped processes may be
  allowed to use additional resources (RAM and CPU time) that are unavailable to web requests. Piped processes use a pooling strategy that
  is optimized for background work.
* __Weaknesses__: Setup requires higher level of sysadmin access (minimally, SSH access; ideally, `sudo` or `root` access).

There are several ways to start `citges` with a CiviCRM pipe.  These may use [cv](https://github.com/civicrm/cv), [drush](https://drush.org), or
[wp-cli](https://wp-cli.org/), as in:

```bash
## Start with cv
cd /var/www/example.com/web
citges run --pipe='cv ev "Civi::pipe();"'

## Start with drush
cd /var/www/example.com/web
citges run --pipe='drush ev "civicrm_initialize(); Civi::pipe();"'

## Start with wp-cli
cd /var/www/example.com/web
citges run --pipe='wp eval "civicrm_initialize(); Civi::pipe();"'
```

There is a common theme in the examples: the `--pipe` parameter specifies a shell command, and the shell command ultimately invokes PHP's `Civi::pipe()`.  As long as the
end result is `Civi::pipe()`, you can develop many more variants of these commands -- allowing support for environment variables, CMS multisite, remote SSH connections,
and so on.  Here are a few more examples of `--pipe` commands:

```bash
## Use cv with multisite
HTTP_HOST=myvhost.example.com cv ev "Civi::pipe();"

## Use drush with multisite
drush -l myvhost.example.com ev "civicrm_initialize(); Civi::pipe();"

## Use wp-cli with multisite
wp --url=myvhost.example.com eval "civicrm_initialize(); Civi::pipe();"

## Use ssh to call cv remotely
ssh webuser@backend.example.com cv ev --cwd=/var/www/example.com/web ev "Civi::pipe();"
```

How do you know if the `--pipe` command is appropriate for your system? Run it manually. If it's working, it will prompt you to send/receive JSON:

```
$ cd /var/www/example.com/web
$ cv ev 'Civi::pipe();'
< ["Civi::pipe","5.50.1"]
> ["PROTO","1.0"]
< ["OK"]
> ["QUIT"]
```

## Usage: Hybrid Pipe-HTTP

In this example, we monitor for new tasks with a long-running SSH pipe, and then
execute specific tasks with medium-latency HTTPS requests.

```bash
citges run --channel=pipe,web \
  --pipe='ssh webuser@backend.example.com cv ev --cwd=/var/www/example.com/web ev "Civi::pipe();"' \
  --web='https://user:pass@example.com/civicrm/queue'
```

## Tests

Tests are organized into two groups:

* `@group unit`: Basic tests that run without much environmental setup/support
* `@group e2e`: End-to-end tests that require a working Civi instances

Running the `unit` tests is straightforward:

```bash
phpunit8 --group unit
```

To run the E2E tests, you need to have a working Civi environment - and pick
an appropriate `Civi::pipe()` command.

```bash
CV_TEST_BUILD='/path/to/site/root' phpunit8 --group e2e
```

Or run all of them:

```bash
CV_TEST_BUILD='/path/to/site/root' phpunit8 --debug
```
