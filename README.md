# Medools Router

Index:

- [Intro]
- [Routes]
  - [Root Route]
- [Install]
- [Setup]
  - [Configurations]
  - [Resources list]
- [Usage]
- [HTTP Methods]
- [Query parameters]
  - [Collection request]
  - [Resource request]
  - [Collection or Resource]
- [Cache]
- [Authentication and Authorization]
- [Errors]
- [Changelog]


# Intro

A Router framework to bootstrap RESTful APIs based on
[Medools][aryelgois/medools].


# Routes

Basically, all routes are formatted as:

- `/resource`: Requests a collection of resources

- `/resource/id`: Requests a specific resource

[See bellow][Resources list] details about the resource name.

The resource `id` depends on the [PRIMARY_KEY][medools_primary_key] defined in
the model. Usually it is an integer, but if there is a composite `PRIMARY_KEY`,
each column must be in the `id`, separated by `primary_key_separator`, e.g.
`/resource/1-1`.

Also, resources can be nested:

- `/resource/id/resource_1`: Requests a collection of `resource_1` with
  `resource(id)` in the appropriate foreign column \*

- `/resource/id/resource_1/offset`: Requests a specific resource from the
  collection in the previous topic. `offset` works as `collection[offset - 1]`,
  i.e. it **is not** `resource_1`'s id, and has 1-based index

Nesting is only allowed in resources (not in collections: `/resource/resource_1`
is wrong), but the route can end in a collection. And there is no hard limit of
nesting levels, the only requirement is that the last resource (or collection)
has a foreign key to the previous resource, that has a foreign key to the
previous one, and so on. The `id` is only used in the first resource.

> \* If `resource_1` has multiple foreign columns for `resource`, only the first
> one is used


## Root Route

When requesting the `/` route, a count of all resources is returned. If the
`meta` config is not empty, it is also included. An `OPTIONS` request just lists
all implemented methods.


# Install

Open a terminal in your project directory and run:

`composer require aryelgois/medools-router`


# Setup

[Medools][aryelgois/medools] requires a config file to connect to your database.
[(see more here)][medools_setup]

Additionally, you will need some configurations and a resources list for your
API.

Also, if using authentication, you will need a secret file (or multiple files)
and a Sign up system (or an administrator will add valid credentials).


## Configurations

The Router accepts an array of configurations that will be passed to properties
in it. The array can contain:

- `always_cache` _(boolean)_: If `GET` and `HEAD` requests always check cache
  headers (default: `true`)

- `always_expand` _(boolean)_: If foreign models are always expanded in a `GET`
  resource request (default: `false`)

- `authentication` _(mixed[]|null)_: Specify how to authenticate the requests.
  If `null`, the authentication is disabled. If set, can contain the keys:

    - `secret` _(string)_ **required**: Path to secret used to sign the
      [JWT][firebase/php-jwt] tokens. It can be a file or a directory

      If it is a directory, the Router expects it to contain secrets named after
      their expiration time, in a unix timestamp. Only the first file is used,
      and the tokens expire at maximum in the same stamp as the secret. You can
      use a cron job to keep generating more secrets

      > **DO NOT** keep the secret in your web server's public directory,
      > neither let git track it

    - `realm` _(string)_: Sent in `WWW-Authenticate` Header

    - `algorithm` _(string)_: Hash algorithm used to sign the tokens (default:
      `HS256`)

    - `claims` _(mixed[])_: Static JWT claims to be included in every token.
      Some claims are already defined by the Router: `iss`, `iat`, `exp` and
      `user`

    - `expiration` _(int)_: Expiration duration (in seconds) used to calculate
      the `exp` claim. It is limited by the secret timestamp (when `secret` is a
      directory)

    - `verify` _(boolean)_: If the authentication's email must be verified
      (default: `false`)

- `cache_method` _(string)_: Function used to hash the Response Body, for HTTP
  caching `GET` and `HEAD` requests. It receives a string with the body
  serialized (default: `md5`)

- `default_content_type` _(mixed[])_: Default content type for `GET` requests.
  It is combined with resource's content types

  The default is `application/json` with internal handlers and priority 1

- `default_filters` _(string|string[]|null)_: Default value for resources
  filters. See more in [Resources list] `filters` option

- `default_publicity` _(boolean|string[]|string)_: Default value for resources
  `public` option

  - `false`: All resources are private by default. It only has effect if
    `authentication` is not `null`

  - `true`: All resources are public by default. It has the same effect as not
    defining `authentication`. If it is defined, is useful to make most
    resources public and some private

  - `string[]`: List of methods that are always public

  - `string`: The same as an array with one item

- `extensions` _(string[])_: Map of extensions and their related content type

  It allows overriding browser's `Accept` header with an extension in the URL.
  Fill it with extensions for custom content types your resources use

  **NOTE**: Unknown extensions may invalidate the route

- `implemented_methods` _(string[])_: List of HTTP methods implemented by the
  API. You can limit which methods can be used, but to add more methods you
  would need to extend or modify the Router class

- `meta` _(mixed[]|null)_: Contains information to be included in the response
  for the [root route]. You can use it to add links to your project or
  documentation

- `per_page` _(integer)_: Limit how many resources can be returned in a
  collection request. If `0`, no pagination is done (default: `20`)

- `primary_key_separator` _(string)_: Separator used in composite
  [PRIMARY_KEY][medools_primary_key] (default: `-`)

  It does not need to be a single character, since it is used as [explode]
  delimiter. But it must not be contained in the id itself, and can not contain
  forward slash `/`

- `zlib_compression` _(boolean)_: If should enable zlib compression when
  appropriate (default: `true`)


## Resources list

A map of resource names to Medools Models, and optionally to specific
configurations for that resource.

The resource names should be plural. You should choose the casing convention to
be `camelCase` or `snake_case`. Prefer keeping consistent with the columns
casing in your models.

The value mapped can be either a string with the Fully Qualified Model Class, or
an array with:

- `model` _(string)_ **required**: Fully Qualified Model Class

- `methods` _(string|string[])_: Allowed HTTP methods. Defaults to
  `implemented_methods`. `OPTIONS` is implicitly included

- `content_type` _(mixed[])_: Map of special content types and their external
  handlers. The value can be a string or an array:

  - `handler` _(string|string[])_ **required**: External function or method that
    accepts a `Resource` and is capable of generating all the output (both
    Headers and Body) for the Response

    It can be a string or map different handlers for `resource` and `collection`
    requests. If any of these is omitted or set to null, is considered not
    acceptable

  - `priority` _(string|string[])_: Multiplies with the quality factor for a
    corresponding content type in `Accept`. It is also used to decide the
    preferred content type when `Accept` does not match any. (default: `1`)

  Note that these content types are only used in `GET` and `HEAD` requests. The
  handler does not need to worry about `HEAD` requests

- `filters` _(string|string[])_: List of columns that can be used as query
  parameters to filter collection requests. It also accepts a string of a
  special fields group ([see fields query parameter][Collection or Resource]),
  or `ALL` to allow filtering on any column. It replaces the `default_filters`
  config

- `cache` _(boolean)_: If caching Headers should be sent. It overrides the
  `always_cache` config

- `max_age` _(int)_: `Cache-Control` max-age (seconds). Tells how long the cache
  is considered fresh until it becomes stale and the client needs to validate
  with a request to the server

- `public` _(boolean|string[]|string)_: Behaves like `default_publicity`, and is
  only used if `authentication` config is set


# Usage

Follow the [example] to see how to use this framework in your web server.

When a request is done, your web server must redirect to a php script. If you
are using Apache, there is already a `.htaccess` in the example that does the
job.

First, the script requires Composer's autoloader and loads Medools config. Then
it gathers request data:

- `Method`: A HTTP method

- `URI`: Requested route. It comes with the path to the api directory, which
  must be removed. Query parameters must remain in the URI

- `Headers`: Headers in the request. Used headers are:

  - `Authorization`: Contains credentials for authenticating the request.
    Possible types are Basic and Bearer

  - `X-HTTP-Method-Override`: If your clients can not work with `PUT`, `PATCH`
    or `DELETE`, they can use it to replace `POST` method

  - `Content-Type`: Data sent in the payload is expected to be
    `application/json`

  - `Accept`: The Router responses, by default, with `application/json`. But
    resources may define specific content types, associated to external handlers

  - `If-None-Match`: When using caching Headers, it is checked to see if a stale
    cache can still be used

- `Body`: Raw data from the payload that will be parsed

- `URL`: URL that access the Router. It is used to create links to other
  resources

Finally, a Router object is created with a [resources list], optional
[configurations] and all that data from the request. It will do its best to
solve the route and give a response.

The resources list and configurations can be stored in a external file, like
`.json` or `.yml` or something else. Remember to add a library to parse that
file before passing to the Router.

You can also configure a subdomain like `api.example.com` to handle the routes.
It is up to you.

> **SECURITY NOTE**: It is highly recommended that you use SSL to protect your
> data transactions


# HTTP Methods

The following HTTP methods are implemented by the Router class:

- `GET`: By default, responses contain a JSON representation of the requested
  route

  - Resource requests receive a `Link` header listing the location of foreign
    models

  - Collection requests include a `Link` header for pagination and a
    `X-Total-Count` counting all resources (ignoring pagination, but considering
    filters), unless `per_page` is 0

  - Also, caching headers are sent, if enabled

  Different content types can be [configured per resource][Resources list] and
  it is chosen based on request's `Accept` header. They will not send the
  headers listed previously

- `HEAD`: Does the same processing for `GET`, but only send headers

- `OPTIONS`: Lists allowed methods for the requested route in `Allow` header. It
  is a special method that is always allowed, if implemented

- `POST`: Creates a new resource inside the collection with data in the request
  payload. A route to the new resource is in `Location` header. This method is
  not allowed for resource routes

- `PATCH`: Sets one or more columns to a specific value, then responses with a
  `GET` of the current state

- `PUT`: Replaces the requested resource with data in the request payload (all
  required columns must be passed), or create a new one if it does not exist.
  The response is a `GET` of the current state. This method is not allowed for
  collection routes

  The `PRIMARY_KEY` columns can be omitted if they are in the route, i.e. a
  route without nested resources. In this case, the same columns in the payload
  have preference over the route

- `DELETE`: Removes the resource or collection's resources from the Database

Some notes:

- Pagination is disabled by default for a collection `PATCH` or `DELETE`, but
  the client can explicitly use it with `page` or `per_page` query parameters

- A `Content-Location` Header can be sent, implying an updated or better route
  to the requested entity


# Query parameters

The client can use some query parameters for a more precise request:


## Collection request

- `sort`: List of comma separated columns to be sorted. A possible unary
  negative implies descending sort order

  Example: `?sort=-priority,stamp` sorts in descending order of priority.
  Within a specific priority, older entries are ordered first

- `page`: Collection page the client wants to access. It has 1-based index

- `per_page`: An integer limiting how many entries should be returned. It
  overrides `per_page` config. As syntax sugar, `all` is the same as `0`

- filters: Defined in [resources list], allows a fine control of the collection.
  Additional operators can make advanced filters:

Operator | Medoo counterpart | Name
:-------:|:-----------------:|:----
gt       | >                 | Greater Than
ge       | >=                | Greater or Equal to
lt       | <                 | Lesser Than
le       | <=                | Lesser or Equal to
ne       | !                 | Not Equal
bw       | <>                | BetWeen
nw       | ><                | Not betWeen
lk       | ~                 | LiKe
nk       | !~                | Not liKe
rx       | REGEXP            | RegeXp

Examples:

- A simple string represents a list of comma separated values:

  `?id=1,2,3` &rarr;
  `['id' => ['1', '2', '3']]`

- A numeric array can also be used:

  `?filter[]=1&filter[]=2&filter[]=foo,bar` &rarr;
  `['filter' => ['1', '2', 'foo,bar']]`

  Helps including a comma in the item itself

- `bw` and `nw` accept two values separated by comma. `ne`, `lk` and `nk` accept
  one or more values separated in the same way:

  `?id[bw]=100,200` &rarr;
  `['id[<>]' => ['100', '200']]`

- They also accept their multiple values in an array:

  `?filter[ne][]=1&filter[ne][]=2&filter[ne][]=foo,bar` &rarr;
  `['filter[!]' => ['1', '2', 'foo,bar']]`

- Passing `NULL` to `ne` or directly to the filter will work:

  `?active=NULL` &rarr;
  `['active' => null]`

  `?active[ne]=NULL` &rarr;
  `['active[!]' => null]`

Notes:

- Filters must be enabled by `default_filters` or in resource's `filters`

- Filters affect the pagination and the `X-Total-Count` Header

- Avoid columns with the same name as other query parameters


## Resource request

- `expand`: Its existence forces foreign models to be expanded, unless its value
  is `false`, which is useful if `always_expand` is `true`


## Collection or Resource

- `fields`: List of comma separated columns to include in the response. It also
  limits which foreign models are listed in `Link` Header, if not expanding them

  There are special fields group that are replaced by the corresponding resource
  columns. They have the same names as the Medools constants:

  - `PRIMARY_KEY`
  - `AUTO_INCREMENT`
  - `STAMP_COLUMNS`
  - `OPTIONAL_COLUMNS`
  - `FOREIGN_KEYS`
  - `SOFT_DELETE`

  Notes:

  - Omitting or giving an empty `fields` parameter results in all fields in the
    response. But passing a query that solves to nothing (like
    `?fields=FOREIGN_KEYS` in a resource without foreigns, or simply
    `?fields=,`) results in no fields being sent

  - It does not affect the order the fields are sent _(also, in JSON objects are
    unordered)_ and repeated fields (maybe because of the groups) are ok


# Cache

If enabled, caching Headers `ETag` and `Cache-Control` are sent with successful
responses that have a body.

This functionality can be enabled globally or per resource.

If the client sends `If-None-Match` Header, it is tested to provide a
`304 Not Modified` response.


# Authentication and Authorization

By default, all resources are public. Defining the `authentication` config makes
all resources private and to access them the client must authenticate. In this
case, some resources may define themselves as public, or allow a few methods for
unauthenticated requests.

To authenticate, first the client must provide some credentials to the Router
with a Basic `Authorization` Header. In the [example], it is at the `/auth`
route. If authenticated, the server sends back a JWT token. This token must be
sent to all the other routes with a Bearer `Authorization` Header until it
expires. When it happens, the client must authenticate again.

Authentication credentials ("Accounts") are stored in the `authentications`
table, with the columns:

- `id`: Unique id sent in the token

- `username`: Because of HTTP Basic Authentication limitations, it must not
  contain the colon character (`:`)

- `password`: Contains an encrypted representation of the account's password

- `email`: For contacting the account user

- `verified`: Tells if the email was verified

- `enabled`: Useful to block an account but keep its data

- `update` and `stamp`: Just some timestamps

Another table, `authorizations`, maps accounts to authorized resources. It can
also list authorized methods and can include a filter passed directly to Medoo,
to limit which entries are authorized.

These two tables are be filled by your Sign up system, and controlled by an
user panel or an administrator.


# Errors

When possible, the Router will return an error response with an appropriate HTTP
Status code and a JSON payload with `code` and `message` keys. Other keys may
appear, but are optional.

Error table:

 Code | Name                             | Status | Description
-----:|:---------------------------------|:------:|:-----------
0     | `ERROR_UNKNOWN_ERROR`            |   500  | Unknown error placeholder
1     | `ERROR_INTERNAL_SERVER`          |   500  | The Router detected a Server error
2     | `ERROR_INVALID_CREDENTIALS`      |   401  | Authorization Header is invalid
3     | `ERROR_UNAUTHENTICATED`          |   401  | Request could not be authenticated
4     | `ERROR_INVALID_TOKEN`            |   401  | Authorization token is invalid
5     | `ERROR_METHOD_NOT_IMPLEMENTED`   |   501  | Requested Method is not implemented in the Router
6     | `ERROR_UNAUTHORIZED`             |   403  | Requested resource is not public and the client does not have authorization to access it
7     | `ERROR_INVALID_RESOURCE`         |   400  | Route contains invalid resource
8     | `ERROR_INVALID_RESOURCE_ID`      |   400  | Route contains invalid resource id
9     | `ERROR_INVALID_RESOURCE_OFFSET`  |   400  | Route contains invalid resource offset
10    | `ERROR_INVALID_RESOURCE_FOREIGN` |   400  | Nested resource does not have foreign key to previous resource
11    | `ERROR_RESOURCE_NOT_FOUND`       |   404  | Requested resource does not exist in the Database
12    | `ERROR_UNSUPPORTED_MEDIA_TYPE`   |   415  | Request's Content-Type is not supported
13    | `ERROR_INVALID_PAYLOAD`          |   400  | Request's Content-Type has syntax error or is invalid
14    | `ERROR_METHOD_NOT_ALLOWED`       |   405  | Requested Method is not allowed for Route
15    | `ERROR_NOT_ACCEPTABLE`           |   406  | Requested resource can not generate content complying to Accept header
16    | `ERROR_INVALID_QUERY_PARAMETER`  |   400  | Query parameter has invalid data
17    | `ERROR_UNKNOWN_FIELDS`           |   400  | Resource does not have requested fields
18    | `ERROR_READONLY_RESOURCE`        |   400  | Resource can not be modified
19    | `ERROR_FOREIGN_CONSTRAINT`       |   400  | Foreign constraint fails

If an error or exception was not handled correctly, the response body is
unpredictable and may depend on [error_reporting].


# [Changelog]


[Intro]: #intro
[Routes]: #routes
[Root Route]: #root-route
[Install]: #install
[Setup]: #setup
[Configurations]: #configurations
[Resources list]: #resources-list
[Usage]: #usage
[HTTP Methods]: #http-methods
[Query parameters]: #query-parameters
[Collection request]: #collection-request
[Resource request]: #resource-request
[Collection or Resource]: #collection-or-resource
[Cache]: #cache
[Authentication and Authorization]: #authentication-and-authorization
[Errors]: #errors

[example]: example
[changelog]: CHANGELOG.md

[aryelgois/medools]: https://github.com/aryelgois/Medools
[firebase/php-jwt]: https://github.com/firebase/php-jwt

[medools_setup]: https://github.com/aryelgois/Medools#setup
[medools_primary_key]: https://github.com/aryelgois/Medools#primary_key

[error_reporting]: http://php.net/manual/en/function.error-reporting.php
[explode]: http://php.net/manual/en/function.explode.php
