# CN Simple Payment for PHP
## Installation
Install via [Composer](https://getcomposer.org/)

```bash
composer require chenwenzi/cn-pay
```

## Getting started
```php
<php?
require_once('vendor/autoload.php');
```

This library need your payment secret

```php
$pay = new PaymentService(
    [], //$payConfig
    [], //$payParams
);

## Version Guidance

|Version | PHP Version      |
|--------|------------------|
|^1.0    | >= 7.2   |

## Available API
* send 
* notify
```

pay.

```php
$pay->send(); // return result
```

notify.

```php
$pay->notify(); // return result
```