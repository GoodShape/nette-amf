Action Message Format Protocol for Nette Framework
=========

This extension provides integration of Action Message Format protocol to
Nette Framework. Messages encoding and decoding is based on AMFPHP library.

Requirements
------------

Nette AMF requires PHP 5.4 or higher.

- [Nette Framework 2.1/@dev]


Installation
------------

The best way to install the extension is using  [Composer](http://getcomposer.org/):

```sh
$ composer require goodshape/nette-amf:@dev
```

After installation, enable the extension in config.neon:

```yml
extensions:
	# add theese four lines
	amf: Goodshape\Amf\DI\AmfExtension
```


Configuration options
---------------------

```yml
    requestNamespaces:
        - Project\Remote\Request
    mappings:
        FooService/BarMethod: Foo:Bar
```

Features
--------

  * Incoming packets are decoded and Nette\Application request is created with correct presenter and action.
  * Supports multiple message packet in one request

Missing features
----------------

  * AMF header support
  * Decoding/encoding magic properties (Nette\Object support)

-----

This is early development release. We activelly use this and so far it suits our needs. If you are missing some features,
please feel free to fork & contribute by sending pull requests.
