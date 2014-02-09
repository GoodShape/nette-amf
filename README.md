Action Message Format Protocol for Nette Framework
=========

This extension provides integration of Action Message Format protocol to
Nette Framework. Messages encoding and decoding is based on AMFPHP library.

Requirements
------------

 - PHP 5.4 or higher
 - [Nette Framework](https://github.com/nette/nette) 2.1 or higher (or @dev)


Installation
------------

The best way to install the extension is using  [Composer](http://getcomposer.org/):

```sh
$ composer require goodshape/nette-amf:@dev
```

After installation, enable the extension in config.neon:

```yml
extensions:
    # add this line
	amf: Goodshape\Amf\DI\AmfExtension
```

After this, your application will accept AMF client call, decodes it and sends it to appropriate presenter.

Configuration options
---------------------
 - **requestNamespaces:**
    - If you want to send typed objects from client, you need to specify namespace(s) where the delerializer should look for.
 - **mappings**
  -   You can specify mapping between client service call and actual presenter name (if they differ).

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
  * Decoding/encoding magic properties (Nette\Object support) **experimental!**

Missing features
----------------

  * AMF header support

-----

This is early development release. We actively use this implementation, but we know the implementation is not perfect and lacks some features. Please feel free to contribute by creating issue or sending pull request.