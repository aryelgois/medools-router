# Example

This directory would be your `public/` Document Root with the website you are
developing.

There is a `bootstrap.php` which should be required by every `.php` file. It
prepares the environment for your scripts, like requiring composer's autoload
and loading configurations.

This example expects you to create databases in a SQL server and edit the
default server credentials in `../config/medools.php`.

`api/router.json` contains the Router configurations and resources list. It
lists the defaut [Person] model. You can edit it to include more. If you prefer,
you can keep this file outside of your public directory.

`api/.htaccess` does the magic of redirecting the routes to `api/index.php`. It
also has a commented `SSLRequireSSL` directive. It is highly recommended that
you use SSL.

`api/index.php` gathers request data and pass to the Router.

The client needs to request data from `api/`.

If you configure `authentication` in `api/router.json`, the client needs to
authenticate at `api/auth/` and send the JWT in a Bearer `Authorization` Header
to other routes. If `authentication` is not configured, `api/auth/` will return
`204`.

> The best is _to follow_ this example to create an API for a project you
> already have, with some models and a configured database.


[Person]: https://github.com/aryelgois/Medools/blob/master/src/Models/Person.php
