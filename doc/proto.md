# Civi::pipe Protocol

## General terms

The Civi::pipe protocol is a synchronous request-response mechanism with line-oriented messaging and JSON formatting.
Specifically,

1. A client opens a connection to Civi::pipe
2. The server responds with a header (JSON, UTF8, one-line).
3. The client submits a request (JSON, UTF8, one line).
4. The server sends a response (JSON, UTF8, one line).
5. Go back to (3).

The pipe protocol is specifically focused on two-channel communication (`STDIN`/`STDOUT`).  If there is a third channel
(`STDERR`), then the client MAY log or display it for debugging purposes.  However, `STDERR` must be ignored when
parsing requests and responses.

## Example

```
$ cd /var/www/example.com/web
$ cv ev 'Civi::pipe();'
< {"Civi::pipe":"0.1"}
> {"ECHO":1}
< {"OK":1}
> {"ECHO":[1,2,3]}
< {"OK":[1,2,3]}
```

## Formatting

### Line format

Each request and response is a JSON object formatted to fit on a single line. The line-delimiter is `\n`.

The payload data MAY included escaped newlines (`"\n"`).

JSON is rendered in condensed / ugly / non-"pretty-printed" format. JSON MUST NOT include the line-delimiter.

### Request format

A request is a one-line JSON object. It contains a request-type and a request-value, encoded as key-value pair.

The request-type is a string.

The request-parameter may be a string, number, boolean, object, or array.

Examples:

```json
{"ECHO":"hello\nworld\n"}
{"ECHO":[1,2,3]}
{"API3":["Contact","get"]}
{"API4":["Contact","get"]}
```

### Response format

A response is a one-line JSON object. It contains a response-status and a response-value, encoded as a key-value pair.

The response-status is a string - either:

* `OK`: The pipe request was received and processed.
* `ERR`:  The `ERR` status indicates that the `Civi::pipe` system encountered an error with parsing or relaying the request (e.g.  the request was malformed).

The response-values may be a string, number, boolean, object, or array.

Examples:

```json
{"OK":"hello\nworld\n"}
{"OK":[1,2,3]}
{"ERR":"Malformed request"}
{"ERR":{"type":"FooException","message":"There was an unhandled foobar exception."}}
```

Many PHP deployments include misconfigurations, bugs, or add-ons -- which can cause extra noise to be presented on STDOUT.

Clients SHOULD use the [CTRL option `responsePrefix`](#CTRL) to distinguish noise.

## Request types

### `ECHO`: Test message

The `ECHO` message is used for testing. It simply returns the input.

```
> {"ECHO":1}
< {"OK":1}
> {"ECHO":[1,2,3]}
< {"OK":[1,2,3]}
```

### `API3`: Invoke APIv3

The `API3` message is used to invoke APIv3 actions. It receives the standard tuple of entity-action-params.

```
> {"API3":["Contact","get",{"rowCount":1,"check_permissions":false}]}
< {"OK":{"is_error":0,"count":1,values:[...]}}
```

APIv3 requests may encounter normal errors - e.g. insufficient permissions or invalid parameters. For purposes of the `Civi::pipe` protocol, these
responses are `OK`. However, the response-value may include APIv3-specific error data.

```
> {"API3":["Contact","zzz"]}
< {"OK":{"is_error":1,"error_message":"API (Contact, zzz) does not exist"}}
```

By default, APIv3 requests received on `Civi::pipe()` will enforce permission-checks, but you may opt-out via `"check_permissions":false`.

### `API4`: Invoke APIv4

The `API4` message is used to invoke APIv4 actions. It receives the standard tuple of entity-action-params.

```
> {"API4":["Contact","get",{"limit":1,"checkPermissions":false}]}
< {"OK":{"values":[...]}"}}
```

APIv4 requests may encounter normal errors - e.g. insufficient permissions or invalid parameters. For purposes of the `Civi::pipe` protocol, these
responses are `OK`. However, the response-value may include APIv4-specific error data.

```
> {"API4":["Contact","zzz"]}
< {"OK":{"error_message":"Api Contact zzz version 4 does not exist","error_code":0}}
```

By default, APIv4 requests received on `Civi::pipe()` will enforce permission-checks, but you may opt-out via `"checkPermissions":false`.

### `CTRL`: Control

The control (`CTRL`) message is used to investigate or manipulate the connection.

```
> {"CTRL":["get"]}
< {"OK":{"responsePrefix":null,"maxLine":16384}}
> {"CTRL":["set",{"responsePrefix":"\u0001\u0001"}]}
< {"OK":true}
```

## Special cases

* If a request-line is received with an empty-string, it is ignored by the server.
