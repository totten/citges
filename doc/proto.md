# Civi::pipe JSON-RPC (2.0) Interface

## General terms

The Civi::pipe transport provides a request-response mechanism with line-oriented messaging and [JSON-RPC v2.0](https://www.jsonrpc.org/specification) messages.
Specifically,

1. A client opens a connection to `Civi::pipe()`
2. The server responds with a header (UTF-8, one-line, JSON).
3. The client submits a request (UTF-8, one-line, JSON-RPC).
4. The server sends a response (UTF-8, one-line, JSON-RPC).
5. Go back to (3).

The protocol is synchronous with a single thread of operation (*one request then one response*). This parallels the PHP-HTTP
architecture ordinarily used by CiviCRM (*one process handles one request at a time*). However, it expands the lifespan of
the PHP process to serve multiple requests. This avoids redundant bootstraps, but it is only suitable for a series of
requests with a common context (*eg several requests by the same user*).

The pipe protocol is specifically focused on two-channel communication (`STDIN`/`STDOUT`). If there is a third (`STDERR`)
channel, then the client MAY log or display it for debugging purposes. However, `STDERR` must be ignored when parsing
requests and responses.

## Example

```
$ cd /var/www/example.com/web
$ cv ev 'Civi::pipe();'
< {"Civi::pipe":{"v":"5.46.0","t":"trusted","l":"login"}}
> {"jsonrpc":"2.0","method":"echo","params":["hello world"],"id":null}
< {"jsonrpc":"2.0","result":["hello world"],"id":null}
> {"jsonrpc":"2.0","method":"echo","params":[1,2,3],"id":null}
< {"jsonrpc":"2.0","result":[1,2,3],"id":null}
```

## Formatting

Each request and response is a JSON object formatted to fit on a single line. The line-delimiter is `\n`.

The payload data MAY included escaped newlines (`"\n"`).

JSON is rendered in condensed / ugly / non-"pretty-printed" format. JSON MUST NOT include the line-delimiter.

Each request-line and each response-line is formatted according to [JSON-RPC v2.0](https://www.jsonrpc.org/specification).

Many PHP deployments include misconfigurations, bugs, or add-ons -- which can cause extra noise to be presented on STDOUT.
Clients SHOULD use the [session option `responsePrefix`](#options) to detect and discard noise.

## Header negotiation

The `Civi::pipe()` method supports negotiation-flags for a few common connection options. The result of
setting these flags is reflected in the header line.

For example, `Civi::pipe("vtl")` requests three flags (`v`, `t`, `l`). Each flag will be listed in the header, eg

```js
{"Civi::pipe":{"v":"5.48.0","t":"trusted","l":["login"]}
```

Valid flags are:

* `v` (*version*): Report the CiviCRM version. Ex: `"v":"5.48.0"`
* `j` (*json-rpc*): Report supported flavors of JSON-RPC. Ex: `"j":["jsonrpc-2.0"]`
* `l` (*login*): Report login options. Ex: `"l":["login"]` and `"l":["nologin"]`
* `t` (*trusted*): Mark session as trusted. Logins do not require credentials. API calls may execute with or without permission-checks.
* `u` (*untrusted*): Mark session as untrusted. Logins require credentials. API calls may only execute with permission-checks.

Unrecognized flags must not cause a failure. They must report as `null`. In this example, `x` is an unrecognized flag:

```php
Civi::pipe("uxv");
```
```javascript
{"Civi::pipe":{"u":"untrusted","x":null,"v":"5.48.0"}
```

## Methods

### `api3`

The `api3` method is used to invoke APIv3 actions. It receives the standard tuple of entity-action-params.

For example, to send a request for `Contact.get` with `rowCount=4` and `check_permissions=false`:

```
> {"jsonrpc":"2.0","method":"api3","params":["Contact","get",{"rowCount":4,"check_permissions":false}],"id":null}
< {"jsonrpc":"2.0","result":{"is_error":0,"count":4,values:[...]}}
```

APIv3 requests may encounter normal errors - e.g. insufficient permissions or invalid parameters. APIv3
responses are reported as regular results in APIv3 result-format. (Errors are not converted to JSON-RPC error format.)

```
> {"jsonrpc":"2.0","method":"api3","params":["Contact","zz"],"id":null}
< {"jsonrpc":"2.0","result":{"error_code":"not-found","entity":"Contact","action":"zz","is_error":1,"error_message":"API (Contact, zz) does not exist (join the API team and implement it!)"},"id":null}
```

By default, APIv3 requests received on `Civi::pipe()` will enforce permission-checks, but you may opt-out via `"check_permissions":false`.

### `api4`

The `api4` method is used to invoke APIv4 actions. It receives the standard tuple of entity-action-params.

```
> {"jsonrpc":"2.0","method":"api4","params":["Contact","get",{"limit":4,"checkPermissions":false}],"id":null}
< {"jsonrpc":"2.0","result":[{"id":1,"contact_type":"Organization",...}]}
```

APIv4 requests may encounter normal errors - e.g. insufficient permissions or invalid parameters. APIv4
responses are reported as regular results in APIv4 result-format. (Errors are not converted to JSON-RPC error format.)

```
> {"jsonrpc":"2.0","method":"api4","params":["Contact","zz"],"id":null}
< {"jsonrpc":"2.0","result":{"error_code":"not-found","entity":null,"action":null,"is_error":1,"error_message":"Api Contact zz version 4 does not exist."},"id":null}
```

By default, APIv4 requests received on `Civi::pipe()` will enforce permission-checks, but you may opt-out via `"checkPermissions":false`.

### `echo`

The `echo` message is used for testing. It simply returns the input.

```
> {"jsonrpc":"2.0","method":"echo","params":["hello world"],"id":null}
< {"jsonrpc":"2.0","result":["hello world"],"id":null}
> {"jsonrpc":"2.0","method":"echo","params":[1,2,3],"id":null}
< {"jsonrpc":"2.0","result":[1,2,3],"id":null}
```

### `login`

Set the active user/contact.

```
> {"jsonrpc":"2.0","method":"login","params":{"contactId":202},"id":null}
< {"jsonrpc":"2.0","result":{"contactId":202,"userId":1},"id":null}
```

Note: Only trusted parties are allowed to connect over the pipe medium. The `login` method
sets the active user but does not authenticate credentials.

The login principal may be specified with any one of the following:

* `contactId` (`int`)
* `userId` (`int`)
* `user` (`string`)

### `options`

The `options` method manages connectivity options. By default, it will return a list of all known options:

```
> {"jsonrpc":"2.0","method":"options","id":null}
< {"jsonrpc":"2.0","result":{"responsePrefix":null,"bufferSize":524288},"id":null}
```

Alternatively, you may use it to update an existing option. Any modified options will be returned.

```
> {"jsonrpc":"2.0","method":"options","params":{"responsePrefix":"\u0001\u0001"},"id":null}
< {"jsonrpc":"2.0","result":{"responsePrefix":"\u0001\u0001"},"id":null}
```

The following options are defined:

* `apiError` (`string`): Specify how CiviCRM APIs should report their errors. Either:
    * `array`: Errors are reported in their canonical array format. Useful for precise+generic handling.
    * `exception`: Errors are converted to exceptions and then to JSON-RPC errors. Improves out-of-the-box DX on stricter JSON-RPC clients.
* `bufferSize` (`int`): The maximum length of a line in the control session, measured in bytes.
  This determines the maximum request size. (The default value is deployment-specific/implementation-specific.
  The default must be at least 64kb. At time of writing, the default for civicrm-core is 512kb.)
* `responsePrefix` (`string`): Before sending any response (but after evaluating the request), send
  an extra prefix or delimiter. (Defensively-coded clients may set a prefix and watch for it. If any
  output comes before the prefix, then the client may infer that the server is misbehaved - eg a debug
  hack or a bad plugin is creating interference. Disregard output before the prefix.)

## Special cases

* If a request-line is received with an empty-string, it is ignored by the server.
