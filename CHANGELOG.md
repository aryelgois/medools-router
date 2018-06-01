# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).


## [Unreleased]

### Added

### Changed
- Move `SSLRequireSSL` directive to Document Root

### Deprecated

### Removed

### Fixed

### Security


## [0.3.2] - 2018-05-31

### Security
- Was leaking implemented and allowed methods when `OPTIONS` is not implemented


## [0.3.1] - 2018-05-30

### Fixed
- Version links in Changelog


## [0.3.0] - 2018-05-30

### Added
- `Router::getAcceptedResourceType()`

### Changed
- Split `Router::getAcceptedType()`


## [0.2.1] - 2018-05-29

### Fixed
- Add `Removed` section in 0.2.0 Changelog


## [0.2.0] - 2018-05-29

### Added
- Repository title
- `from_globals()`
- `Router::getAllowedMethods()`
- `Router::SAFE_METHODS`
- Column types in Authorization
- Support to multipart extensions
- `Router->safe_method`
- `handlers` resource config
- `Router::compareAccept()`
- `Router::getAcceptedType()`
- `Router::parseContentType()`
- `Resouce->payload`
- `Router::externalHandler()`
- `Router::ENABLE_METHOD_OVERRIDE`
- Extending section

### Changed
- Update dependencies
- Update README
- Use `class` keyword in foreign classes
- Update `Router::isPublic()`
- Rewrite `Router::getAuthorizedResources()`
- Update `Router::requestRoot()`
- Rewrite `Router::computeResourceTypes()` as `Router::getAvailableTypes()`
- Split `Router::parceAccept()`
- Update `Router::parseBody()`
- Update `Router::run()`
- Route extensions are only used in `GET` and `HEAD` requests
- Allow using a different Router in the Controller
- `Router::requestRoot()` receive headers and request payload

### Removed
- `content_type` resource config

### Fixed
- Composer description
- Comparison operator
- README
- Example README
- External handler output
- Authentication's invalid type error message


## [0.1.0] - 2018-03-31

### Added
- Classes:
  - Authentication
  - Authorization
  - Controller
  - Router
  - RouterException
  - Resource
  - Response
- Databases:
  - authentication
- Dependencies:
  - [aryelgois/yasql-php]
  - [firebase/php-jwt]
- Documentation
- Example
- [Medools][aryelgois/medools] config file
- Platform requirement: `ext-zlib`

### Changed
- Update dependencies
- Namespace to `aryelgois\MedoolsRouter`


[Unreleased]: https://github.com/aryelgois/medools-router/compare/v0.3.2...develop
[0.3.2]: https://github.com/aryelgois/medools-router/compare/v0.3.1...v0.3.2
[0.3.1]: https://github.com/aryelgois/medools-router/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/aryelgois/medools-router/compare/v0.2.1...v0.3.0
[0.2.1]: https://github.com/aryelgois/medools-router/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/aryelgois/medools-router/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/aryelgois/medools-router/compare/d281bb5dbc8c58b28db680b3700664217a88eb6d...v0.1.0

[aryelgois/medools]: https://github.com/aryelgois/Medools
[aryelgois/yasql-php]: https://github.com/aryelgois/yasql-php
[firebase/php-jwt]: https://github.com/firebase/php-jwt
