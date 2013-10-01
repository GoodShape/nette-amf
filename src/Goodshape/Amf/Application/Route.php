<?php
namespace Goodshape\Amf\Application;

use Nette;
use Nette\Application\Request;
use Nette\Application\Routers\flags;
use Nette\Application\Routers\URL;
use Nette\Utils\Strings;
use Nette\Application\Routers\Route as NRoute;



/**
 * Executes deserialization of packet and routes the request
 * Uses Manager class in background
 *
 * @author Jan Langer <jan.langer@goodshape.cz>
 * @package App\Core\Amf
 */
class Route implements Nette\Application\IRouter {

    /** @var array supported content types */
    private $contentTypes = [
        'application/x-amf',
    ];

    /** @var Manager */
    private $manager;


    const PRESENTER_KEY = 'presenter';
	const MODULE_KEY = 'module';

	/** flag */
	const CASE_SENSITIVE = 256;

	/** @internal url type */
	const HOST = 1,
		PATH = 2,
		RELATIVE = 3;

	/** key used in {@link Route::$styles} or metadata {@link Route::__construct} */
	const VALUE = 'value';
	const PATTERN = 'pattern';
	const FILTER_IN = 'filterIn';
	const FILTER_OUT = 'filterOut';
	const FILTER_TABLE = 'filterTable';
	const FILTER_STRICT = 'filterStrict';

	/** @internal fixity types - how to handle default value? {@link Route::$metadata} */
	const OPTIONAL = 0,
		PATH_OPTIONAL = 1,
		CONSTANT = 2;

	/** @var int */
	public static $defaultFlags = 0;

	/** @var array */
	public static $styles = [];

	/** @var string */
	private $mask;

	/** @var array */
	private $sequence;

	/** @var string  regular expression pattern */
	private $re;

	/** @var array of [value & fixity, filterIn, filterOut] */
	private $metadata = array();

	/** @var array  */
	private $xlat;

	/** @var int HOST, PATH, RELATIVE */
	private $type;

	/** @var int */
	private $flags;


	/**
	 * @param  string  URL mask, e.g. '<presenter>/<action>/<id \d{1,3}>'
	 * @param  array|string   default values or metadata
	 * @param  int     flags
	 */
	public function __construct(Manager $manager, $mask, $metadata = array(), $flags = 0)
	{
		if (is_string($metadata)) {
			$a = strrpos($metadata, ':');
			if (!$a) {
				throw new Nette\InvalidArgumentException("Second argument must be array or string in format Presenter:action, '$metadata' given.");
			}
			$metadata = array(
				self::PRESENTER_KEY => substr($metadata, 0, $a),
				'action' => $a === strlen($metadata) - 1 ? NULL : substr($metadata, $a + 1),
			);
		} elseif ($metadata instanceof \Closure || $metadata instanceof Nette\Callback) {
			$metadata = array(
				self::PRESENTER_KEY => 'Nette:Micro',
				'callback' => $metadata,
			);
		}

		$this->flags = $flags | static::$defaultFlags;
		$this->setMask($mask, $metadata);
        $this->manager = $manager;
	}


	/**
	 * Maps HTTP request to a Request object.
	 * @return Nette\Application\Request|NULL
	 */
	public function match(Nette\Http\IRequest $httpRequest)
	{
		// combine with precedence: mask (params in URL-path), fixity, query, (post,) defaults

		// 1) URL MASK
		$url = $httpRequest->getUrl();
		$re = $this->re;

		if ($this->type === self::HOST) {
			$path = '//' . $url->getHost() . $url->getPath();
			$host = array_reverse(explode('.', $url->getHost()));
			$re = strtr($re, array(
				'/%basePath%/' => preg_quote($url->getBasePath(), '#'),
				'%tld%' => $host[0],
				'%domain%' => isset($host[1]) ? "$host[1]\\.$host[0]" : $host[0],
			));

		} elseif ($this->type === self::RELATIVE) {
			$basePath = $url->getBasePath();
			if (strncmp($url->getPath(), $basePath, strlen($basePath)) !== 0) {
				return NULL;
			}
			$path = (string) substr($url->getPath(), strlen($basePath));

		} else {
			$path = $url->getPath();
		}

		if ($path !== '') {
			$path = rtrim($path, '/') . '/';
		}

		if (!$matches = Strings::match($path, $re)) {
			// stop, not matched
			return NULL;
		}

        if(!in_array($httpRequest->getHeader('Content-type'), $this->contentTypes)) {
            return null;
        }


        return $this->manager->createApplicationRequest();

	}


	/**
	 * Constructs absolute URL from Request object.
	 * @return string|NULL
	 */
	public function constructUrl(Request $appRequest, Nette\Http\Url $refUrl)
	{
		NULL;
	}


	/**
	 * Parse mask and array of default values; initializes object.
	 * @param  string
	 * @param  array
	 * @return void
	 */
	private function setMask($mask, array $metadata)
	{
		$this->mask = $mask;

		// detect '//host/path' vs. '/abs. path' vs. 'relative path'
		if (substr($mask, 0, 2) === '//') {
			$this->type = self::HOST;

		} elseif (substr($mask, 0, 1) === '/') {
			$this->type = self::PATH;

		} else {
			$this->type = self::RELATIVE;
		}

		foreach ($metadata as $name => $meta) {
			if (!is_array($meta)) {
				$metadata[$name] = array(self::VALUE => $meta, 'fixity' => self::CONSTANT);

			} elseif (array_key_exists(self::VALUE, $meta)) {
				$metadata[$name]['fixity'] = self::CONSTANT;
			}
		}

		// PARSE MASK
		// <parameter-name[=default] [pattern] [#class]> or [ or ] or ?...
		$parts = Strings::split($mask, '/<([^>#= ]+)(=[^># ]*)? *([^>#]*)(#?[^>\[\]]*)>|(\[!?|\]|\s*\?.*)/');

		$this->xlat = array();
		$i = count($parts) - 1;

		// PARSE QUERY PART OF MASK
		if (isset($parts[$i - 1]) && substr(ltrim($parts[$i - 1]), 0, 1) === '?') {
			// name=<parameter-name [pattern][#class]>
			$matches = Strings::matchAll($parts[$i - 1], '/(?:([a-zA-Z0-9_.-]+)=)?<([^># ]+) *([^>#]*)(#?[^>]*)>/');

			foreach ($matches as $match) {
				list(, $param, $name, $pattern, $class) = $match;  // $pattern is not used

				if ($class !== '') {
					if (!isset(static::$styles[$class])) {
						throw new Nette\InvalidStateException("Parameter '$name' has '$class' flag, but Route::\$styles['$class'] is not set.");
					}
					$meta = static::$styles[$class];

				} elseif (isset(static::$styles['?' . $name])) {
					$meta = static::$styles['?' . $name];

				} else {
					$meta = static::$styles['?#'];
				}

				if (isset($metadata[$name])) {
					$meta = $metadata[$name] + $meta;
				}

				if (array_key_exists(self::VALUE, $meta)) {
					$meta['fixity'] = self::OPTIONAL;
				}

				unset($meta['pattern']);
				$meta['filterTable2'] = empty($meta[self::FILTER_TABLE]) ? NULL : array_flip($meta[self::FILTER_TABLE]);

				$metadata[$name] = $meta;
				if ($param !== '') {
					$this->xlat[$name] = $param;
				}
			}
			$i -= 6;
		}

		// PARSE PATH PART OF MASK
		$brackets = 0; // optional level
		$re = '';
		$sequence = array();
		$autoOptional = TRUE;
		do {
			array_unshift($sequence, $parts[$i]);
			$re = preg_quote($parts[$i], '#') . $re;
			if ($i === 0) {
				break;
			}
			$i--;

			$part = $parts[$i]; // [ or ]
			if ($part === '[' || $part === ']' || $part === '[!') {
				$brackets += $part[0] === '[' ? -1 : 1;
				if ($brackets < 0) {
					throw new Nette\InvalidArgumentException("Unexpected '$part' in mask '$mask'.");
				}
				array_unshift($sequence, $part);
				$re = ($part[0] === '[' ? '(?:' : ')?') . $re;
				$i -= 5;
				continue;
			}

			$class = $parts[$i]; $i--; // validation class
			$pattern = trim($parts[$i]); $i--; // validation condition (as regexp)
			$default = $parts[$i]; $i--; // default value
			$name = $parts[$i]; $i--; // parameter name
			array_unshift($sequence, $name);

			if ($name[0] === '?') { // "foo" parameter
				$name = substr($name, 1);
				$re = $pattern ? '(?:' . preg_quote($name, '#') . "|$pattern)$re" : preg_quote($name, '#') . $re;
				$sequence[1] = $name . $sequence[1];
				continue;
			}

			// check name (limitation by regexp)
			if (preg_match('#[^a-z0-9_-]#i', $name)) {
				throw new Nette\InvalidArgumentException("Parameter name must be alphanumeric string due to limitations of PCRE, '$name' given.");
			}

			// pattern, condition & metadata
			if ($class !== '') {
				if (!isset(static::$styles[$class])) {
					throw new Nette\InvalidStateException("Parameter '$name' has '$class' flag, but Route::\$styles['$class'] is not set.");
				}
				$meta = static::$styles[$class];

			} elseif (isset(static::$styles[$name])) {
				$meta = static::$styles[$name];

			} else {
				$meta = static::$styles['#'];
			}

			if (isset($metadata[$name])) {
				$meta = $metadata[$name] + $meta;
			}

			if ($pattern == '' && isset($meta[self::PATTERN])) {
				$pattern = $meta[self::PATTERN];
			}

			if ($default !== '') {
				$meta[self::VALUE] = (string) substr($default, 1);
				$meta['fixity'] = self::PATH_OPTIONAL;
			}

			$meta['filterTable2'] = empty($meta[self::FILTER_TABLE]) ? NULL : array_flip($meta[self::FILTER_TABLE]);
			if (array_key_exists(self::VALUE, $meta)) {
				if (isset($meta['filterTable2'][$meta[self::VALUE]])) {
					$meta['defOut'] = $meta['filterTable2'][$meta[self::VALUE]];

				} elseif (isset($meta[self::FILTER_OUT])) {
					$meta['defOut'] = call_user_func($meta[self::FILTER_OUT], $meta[self::VALUE]);

				} else {
					$meta['defOut'] = $meta[self::VALUE];
				}
			}
			$meta[self::PATTERN] = "#(?:$pattern)\\z#A" . ($this->flags & self::CASE_SENSITIVE ? '' : 'iu');

			// include in expression
			$re = '(?P<' . str_replace('-', '___', $name) . '>(?U)' . $pattern . ')' . $re; // str_replace is dirty trick to enable '-' in parameter name
			if ($brackets) { // is in brackets?
				if (!isset($meta[self::VALUE])) {
					$meta[self::VALUE] = $meta['defOut'] = NULL;
				}
				$meta['fixity'] = self::PATH_OPTIONAL;

			} elseif (!$autoOptional) {
				unset($meta['fixity']);

			} elseif (isset($meta['fixity'])) { // auto-optional
				$re = '(?:' . $re . ')?';
				$meta['fixity'] = self::PATH_OPTIONAL;

			} else {
				$autoOptional = FALSE;
			}

			$metadata[$name] = $meta;
		} while (TRUE);

		if ($brackets) {
			throw new Nette\InvalidArgumentException("Missing closing ']' in mask '$mask'.");
		}

		$this->re = '#' . $re . '/?\z#A' . ($this->flags & self::CASE_SENSITIVE ? '' : 'iu');
		$this->metadata = $metadata;
		$this->sequence = $sequence;
	}


	/**
	 * Returns mask.
	 * @return string
	 */
	public function getMask()
	{
		return $this->mask;
	}


	/**
	 * Returns default values.
	 * @return array
	 */
	public function getDefaults()
	{
		$defaults = array();
		foreach ($this->metadata as $name => $meta) {
			if (isset($meta['fixity'])) {
				$defaults[$name] = $meta[self::VALUE];
			}
		}
		return $defaults;
	}


	/**
	 * Returns flags.
	 * @return int
	 */
	public function getFlags()
	{
		return $this->flags;
	}




}