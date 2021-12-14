# Civi::pipe JSON-RPC (2.0) Interface

## General terms

The Civi::pipe transport provides a request-response mechanism with line-oriented messaging and [JSON-RPC v2.0](https://www.jsonrpc.org/specification) messages.
Specifically,

1. A client opens a connection to `Civi::pipe()`
2. The server responds with a header (UTF-8, one-line, JSON).
3. The client submits a request (UTF-8, one-line, JSON-RPC).
4. The server sends a response (UTF-8, one-line, JSON-RPC).
5. Go back to (3).

The protocol is synchronous with a single thread of operation (*one request then one response*).  This parallels the PHP-HTTP
architecture ordinarily used by CiviCRM (*one process handles one request at a time*).  However, it expands the lifespan of
the PHP process to serve multiple requests.  This avoids redundant bootstraps, but it is only suitable for a series of
requests with a common context (*eg several requests by the same user*).

The pipe protocol is specifically focused on two-channel communication (`STDIN`/`STDOUT`).  If there is a third (`STDERR`)
channel, then the client MAY log or display it for debugging purposes.  However, `STDERR` must be ignored when parsing
requests and responses.

## Example

```
$ cd /var/www/example.com/web
$ cv ev 'Civi::pipe();'
< {"Civi::pipe":["jsonrpc20"]}
> {"jsonrpc":"2.0","method":"echo","params":"hello world","id":null}
< {"jsonrpc":"2.0","result":"hello world","id":null}
> {"jsonrpc":"2.0","method":"echo","params":[1,2,3],"id":null}
< {"jsonrpc":"2.0","result":[1,2,3],"id":null}
```

## Formatting

Each request and response is a JSON object formatted to fit on a single line. The line-delimiter is `\n`.

The payload data MAY included escaped newlines (`"\n"`).

JSON is rendered in condensed / ugly / non-"pretty-printed" format. JSON MUST NOT include the line-delimiter.

Each request-line and each response-line is formatted according to [JSON-RPC v2.0](https://www.jsonrpc.org/specification).

Many PHP deployments include misconfigurations, bugs, or add-ons -- which can cause extra noise to be presented on STDOUT.
Clients SHOULD use the [session option `responsePrefix`](#CTRL) to distinguish noise.

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
> {"jsonrpc":"2.0","method":"echo","params":"hello world","id":null}
< {"jsonrpc":"2.0","result":"hello world","id":null}
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

The `options` method is used to investigate or manipulate connectivity options.

```
> {"OPTIONS":{}}
< {"OK":{"responsePrefix":null,"maxLine":16384}}
> {"OPTIONS":{"responsePrefix":"\u0001\u0001"}}
< {"OK":true}
```

## Special cases

* If a request-line is received with an empty-string, it is ignored by the server.
