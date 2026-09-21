![OffsetWP Twig Bundle](https://raw.githubusercontent.com/offsetwp/offsetwp.github.io/refs/heads/main/public/common/cover/cover-twig-bundle-light.png#gh-light-mode-only)
![OffsetWP Twig Bundle](https://raw.githubusercontent.com/offsetwp/offsetwp.github.io/refs/heads/main/public/common/cover/cover-twig-bundle-dark.png#gh-dark-mode-only)

<h1 align="center">
    OffsetWP Twig Bundle
</h1>

<p align="center">
	Twig, integrated into the OffsetWP Framework. Zero configuration to start, every option when you need it.
</p>

<p align="center">
	<a href="https://github.com/offsetwp/twig-bundle/actions/workflows/ci.yml"><img src="https://github.com/offsetwp/twig-bundle/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
	<a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT"></a>
</p>

<br/>

- 🌿 Zero configuration — a working Twig out of the box
- 🎛️ Every option — the full Twig environment surface, with Twig's own defaults
- 🏷️ Attributes first — a filter is one method and one attribute
- 💤 Lazy by construction — nothing unused is ever instantiated
- 🧩 Extend anything — extensions, filters, functions, tests, tags, globals, loaders
- 📦 Works with every `twig/*-extra` package, with no change to this bundle
- 🧭 Errors that name the file, the service or the key at fault

## Installation

**requirements:**
- PHP: 8.5+

**command:**
```bash
composer require offsetwp/twig-bundle
```

**register bundle:**
```php
// config/bundles.php
return array(
	\OffsetWP\Bundle\TwigBundle\TwigBundle::class => array( 'all' => true ),
);
```

## Rendering a template

Where each file goes:

```
my-project/
├─ app/
│  └─ Twig/                  # your filters, extensions, loaders — ordinary classes
│     └─ PriceExtension.php
├─ config/
│  ├─ bundles.php            # where this bundle is registered
│  ├─ services.php           # where your classes become services
│  └─ packages/
│     └─ twig.php            # optional — every key of the reference below
└─ templates/                # the default directory, searched with no configuration
   ├─ pages/
   │  └─ front-page.twig     # rendered as 'pages/front-page.twig'
   └─ emails/
      └─ welcome.twig        # rendered as 'emails/welcome.twig'
```

### The twig() helper

One token, no `use` statement, available anywhere after the kernel booted — a theme file, a
plugin, a mu-plugin. It hands back the environment itself, a real `Twig\Environment`, so
every method Twig documents is one step away:

```php
twig()->render( 'pages/front-page.twig', array( 'name' => 'Jérôme' ) );
twig()->display( 'pages/front-page.twig', array( 'name' => 'Jérôme' ) );
twig()->addGlobal( 'site', get_bloginfo( 'name' ) );   // before the first render
twig()->getLoader();
twig()->getCharset();
twig()->isDebug();
```

Given a template name it renders and returns the string, which is the one shorthand it adds:

```php
// front-page.php
echo twig( 'pages/front-page.twig', array( 'name' => get_bloginfo( 'name' ) ) );
```

### The Twig facade

The same environment, without touching the global namespace:

```php
use OffsetWP\Bundle\TwigBundle\Twig;

Twig::environment()->render( 'pages/front-page.twig', array( 'name' => 'Jérôme' ) );
Twig::environment()->addGlobal( 'site', get_bloginfo( 'name' ) );
Twig::environment()->getLoader();
```

With two shorthands on top of it, and one question to ask before either:

```php
echo Twig::render( 'pages/front-page.twig', array( 'name' => 'Jérôme' ) );
Twig::display( 'pages/front-page.twig' );

// Would a shortcut call find Twig? Answered without risking the failure it asks about.
if ( Twig::booted() ) { /* … */ }
```

### From the container

```php
$kernel->service( 'twig' )->render( 'emails/welcome.twig' );
```

The same object the helper hands back, with no facade and no global.

### Several kernels in one request

A mu-plugin and a theme can each boot a kernel in one request. Each compiles its own
container and owns its own environment, sharing nothing — not even a compiled template. The
shortcut designates **the last kernel that booted**. Name one when that is not what you want:

```php
$kernel = Kernel::configure( __DIR__ )->config( __DIR__ . '/config' )->boot();

Twig::of( $kernel )->render( 'emails/welcome.twig' );
$kernel->service( 'twig' )->render( 'emails/welcome.twig' );   // the same object
```

A kernel that never registered this bundle, or has not booted, is refused by name — its own
root path is in the message.

## Template paths

### Zero configuration and the default directory

`templates/` at the root of the project is searched with no configuration at all, and only
when it exists:

```php
echo twig( 'pages/front-page.twig' );   // templates/pages/front-page.twig
```

### Adding paths

One call per directory, each under a namespace or under the main one:

```php
// config/packages/twig.php
use OffsetWP\Bundle\TwigBundle\Configuration\TwigConfig;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	TwigConfig::create()
		->path( '%kernel.root_path%/templates/emails', 'emails' )
		->path( '%kernel.root_path%/vendor-views', 'shop' )

		// The main namespace is searched in the order written, and templates/ comes
		// last — so this one answers first for a filename they share.
		->path( '/abs/legacy-templates' )
		->apply( $container );
};
```

`%kernel.root_path%` is the root of the project, and is resolved before this bundle sees the
value — in a YAML file as readily as in a PHP one. Any other absolute path works too. A
relative one is refused when the container builds, and so is a directory that does not exist.

### Namespaced paths

```twig
{% include '@emails/welcome.twig' %}
{% extends '@shop/layout.twig' %}
```

An unknown namespace raises Twig's own error, which names the namespaces that are defined.

## Configuration reference

Every key below goes in one file, under `twig`. The sections around this one show each key
in place; this is the list of all of them.

```php
// config/packages/twig.php
use OffsetWP\Bundle\TwigBundle\Configuration\Escaping;
use OffsetWP\Bundle\TwigBundle\Configuration\TwigConfig;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	TwigConfig::create()
		->strictVariables( true )
		->autoescape( Escaping::ByTemplateName )
		->apply( $container );
};
```

One method per key, so an editor completes the whole surface and a mistyped key is a compile
error. It invents no default and validates nothing: what it builds goes through the same tree
as the array below, and raises the same messages.

That array is the native form, and the one every example in the rest of this file could have
been written in:

```php
// config/packages/twig.php
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	$container->extension(
		'twig',
		array(
			'strict_variables' => true,
			'autoescape'       => 'name',
		)
	);
};
```

The kernel reads `config/packages/*.yaml` as readily, if you `composer require symfony/yaml`.
A YAML file has no methods to call, so it writes the keys:

```yaml
# config/packages/twig.yaml
twig:
    strict_variables: true
    autoescape: name
```

### The full table

Eighteen keys: two template directories, then **every** option `Twig\Environment`'s
constructor accepts, then `globals` and `extensions`, then six settings of Twig's core
extension. Every default is Twig's own except the two marked.

| Key | Type | Default | Where the default comes from |
|---|---|---|---|
| `paths` | `array<string, ?string>` — absolute directory ⇒ namespace | `array()` | — |
| `default_path` | `string` | `%kernel.root_path%/templates` | this bundle |
| `debug` | `bool` | `%kernel.is_debug%` | **this bundle** — Twig's own default is `false` |
| `charset` | `string` | `%kernel.charset%` | **the kernel** — whose own default is `UTF-8`, the value Twig uses |
| `strict_variables` | `bool` | `false` | Twig |
| `autoescape` | `false\|string\|callable` | `'html'` | Twig |
| `cache` | `false\|string\|'@service_id'` | `false` | Twig |
| `auto_reload` | `?bool` | `null` | Twig — `null` means "follow `debug`" |
| `optimizations` | `int` | `-1` | Twig — `-1` all on, `0` all off |
| `use_yield` | `bool` | `false` | Twig |
| `globals` | `array<string, mixed>` — name ⇒ value, `@service` or `@@literal` | `array()` | — |
| `extensions` | `list<class-string>` | `array()` | — |
| `date.format` | `string` | `'F j, Y H:i'` | Twig's core extension |
| `date.interval_format` | `string` | `'%d days'` | Twig's core extension |
| `date.timezone` | `?string` | `null` → PHP's own default | Twig's core extension |
| `number_format.decimals` | `int` | `0` | Twig's core extension |
| `number_format.decimal_point` | `string` | `'.'` | Twig's core extension |
| `number_format.thousands_separator` | `string` | `','` | Twig's core extension |

An unknown key, a value of the wrong type and a boolean key written with nothing after it
are each refused when the container builds.

### debug and charset

These two follow the kernel rather than Twig: `charset` takes `%kernel.charset%`, whose own
default is `UTF-8` — Twig's value — and `debug` takes `%kernel.is_debug%`, so a project
booted with `->debug( true )` gets template names and line numbers in its errors. Set either
one and yours is used.

`auto_reload` follows `debug` unless you say otherwise. Set it to `false` to keep the
diagnostics of debug mode without Twig stat-ing every template on every request:

```php
->debug( true )
->autoReload( false )
```

### autoescape

`false`, the name of any strategy Twig knows, `'name'` to pick one from each template's name,
or a PHP callable:

```php
->autoescape( Escaping::Html )             // the default
->autoescape( Escaping::Js )
->autoescape( Escaping::ByTemplateName )   // .txt.twig renders raw, everything else as HTML
->autoescape( 'a_strategy_you_registered' )
->noAutoescape()                           // escape nothing
->autoescapeWith( EscapingStrategy::class, 'guess' )
```

`Escaping` names the six strategies Twig ships — `Html`, `Js`, `Css`, `Url`, `HtmlAttribute`,
`HtmlAttributeRelaxed` — plus `ByTemplateName`.

```php
// app/Twig/EscapingStrategy.php
final class EscapingStrategy {
	public static function guess( string $name ): string|false {
		return str_ends_with( $name, '.txt.twig' ) ? false : 'html';
	}
}
```

Two strings rather than a callable: every extension configuration is serialised while the
container compiles, and a closure cannot be.

### cache

Twig's *compilation* cache — where Twig writes the PHP class it compiles each template into.
This bundle passes the key through and creates no directory, writes no file and ships no
cache warmer.

```php
->noCache()                                              // the default
->cacheDirectory( '%kernel.root_path%/var/cache/twig' )
->cacheDirectory( '%env(TWIG_CACHE_DIR)%' )              // resolved while the container compiles
->cacheService( 'app.twig_cache' )                       // a service implementing Twig\Cache\CacheInterface
```

A directory has to be absolute, and a relative one is refused when the container builds. An
environment variable works here and in `path()` and `defaultPath()`; its value is substituted
after this bundle has run, so the directory is Twig's to check at the first render.

### date and number formats

Settings of Twig's core extension rather than constructor options, so that a site needing a
comma and `Europe/Paris` has them on its first day:

```php
->dateFormat( 'd/m/Y H:i' )
->dateIntervalFormat( '%d jours' )   // a literal percent sign is written %%
->dateTimezone( 'Europe/Paris' )
->numberDecimals( 2 )
->numberDecimalPoint( ',' )
->numberThousandsSeparator( ' ' )
```

```twig
{{ post.published_at|date }}   {# 01/01/2026 13:00 #}
{{ 1234.5|number_format }}     {# 1 234,50       #}
```

`timezone` left at `null` follows PHP's own. One PHP does not recognise is refused when the
container builds. The container reads `%name%` as one of its own parameters, so the canonical
interval format `%H:%I:%S` is written `%%H:%%I:%%S`.

### use_yield

```php
->useYield( true )
```

A compiled template then yields its output instead of building the whole string in memory,
which matters for a page that streams. Everything Twig ships is ready for it; a custom node
of yours has to `yield` rather than return, and carry `#[YieldReady]` — see
[Adding a tag](#adding-a-tag).

## Adding a filter, a function or a test

### With attributes

A public method and one attribute. No interface, no base class, no registration of its own:

```php
// app/Twig/PriceExtension.php
namespace App\Twig;

use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Attribute\AsTwigTest;

final class PriceExtension {
	public function __construct( private CurrencyRates $rates ) {
	}

	// Not static: compiles to a call through Twig's runtime loader, so this class and
	// its dependency are built the first time a rendered template reaches the filter,
	// and never otherwise.
	#[AsTwigFilter( name: 'price' )]
	public function price( int $cents, string $currency = 'EUR' ): string {
		return $this->rates->format( $cents, $currency );
	}

	#[AsTwigFunction( name: 'rate' )]
	public function rate( string $currency ): float {
		return $this->rates->rate( $currency );
	}

	// Static: compiles to a direct call, and constructs nothing at all. Use one when the
	// method needs nothing from the object.
	#[AsTwigTest( name: 'free' )]
	public static function isFree( int $cents ): bool {
		return 0 === $cents;
	}
}
```

```twig
{{ 1250|price }}                              {# 12,50 EUR #}
{{ rate( 'USD' ) }}                           {# 1.1       #}
{% if product.cents is free %}Free{% endif %}
```

Nothing registers that class with Twig. Your services file does, once, for every class in
the namespace — and the same file covers a filter, a function, a test, an extension, a tag
and a loader alike:

```php
// config/services.php
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	$services = $container->services();

	$services
		->defaults()
			->autowire()
			->autoconfigure();   // this is what reads the attributes

	$services->load( 'App\\', '../app/*' );
};
```

With `autoconfigure()` off, the same class works with a tag written by hand:

```php
	$services->set( PriceExtension::class )
		->tag( 'twig.attribute_extension' )
		->tag( 'twig.runtime' );
```

An attributed method has to be public. A private one is refused when the container builds,
naming the class and the method.

### Receiving the environment

Type-hint the first parameter and Twig passes it rather than expecting it from the template:

```php
	#[AsTwigFunction( name: 'charset' )]
	public function charset( Environment $environment ): string {
		return $environment->getCharset();
	}
```

The attributes repeat, so one implementation can answer to two names:

```php
	#[AsTwigFilter( name: 'shout' )]
	#[AsTwigFilter( name: 'yell' )]
	public function shout( string $text ): string {
		return strtoupper( $text ) . '!';
	}
```

## Adding a full extension

### By interface

Implement Twig's extension interface — usually by extending `AbstractExtension` — and nothing
else is needed:

```php
// app/Twig/ShopExtension.php
namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class ShopExtension extends AbstractExtension {
	public function getFilters(): array {
		return array( new TwigFilter( 'vat', array( $this, 'vat' ) ) );
	}

	public function vat( float $amount, float $rate = 0.2 ): float {
		return $amount * ( 1 + $rate );
	}
}
```

### By tag, and priorities

Without autoconfiguration, or to control the order, tag it:

```php
$services->set( ShopExtension::class )->tag( 'twig.extension', array( 'priority' => 10 ) );
```

Higher priority reaches Twig first, the default is `0`, and ties keep the order you declared.
Twig lets the **last** declaration of a filter name win, so the lower priority is the one
whose implementation survives — which is the knob for overriding a filter. Attributes and
extensions are ordered against each other, and at equal priority a class carrying attributes
is the one Twig receives last.

### By configuration

An extension class that takes no constructor argument needs no service declaration at all,
only a line in the configuration file:

```php
// config/packages/twig.php
use OffsetWP\Bundle\TwigBundle\Configuration\TwigConfig;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Twig\Extension\DebugExtension;
use Twig\Extra\Intl\IntlExtension;

return static function ( ContainerConfigurator $container ): void {
	TwigConfig::create()
		->extension( IntlExtension::class )
		->extension( DebugExtension::class )
		->apply( $container );
};
```

`->extensions( IntlExtension::class, DebugExtension::class )` says the same in one call.

A class that does not exist, that is not a Twig extension, or that arrives both here and as a
tagged service, is refused when the container builds — and that last message names both
sources.

## Adding a tag

A tag is one class for the parser and one for the node. Nothing is registered anywhere.

### The token parser

```php
// app/Twig/TokenParser/GreetTokenParser.php
namespace App\Twig\TokenParser;

use App\Twig\Node\GreetNode;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

final class GreetTokenParser extends AbstractTokenParser {
	// Parses {% greet <expression> %}.
	public function parse( Token $token ): Node {
		$name = $this->parser->parseExpression();

		$this->parser->getStream()->expect( Token::BLOCK_END_TYPE );

		return new GreetNode( $name, $token->getLine() );
	}

	public function getTag(): string {
		return 'greet';
	}
}
```

### The node

```php
// app/Twig/Node/GreetNode.php
namespace App\Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;

// #[YieldReady] says this node hands its output back rather than printing it. A node
// written today should carry it: it costs nothing with use_yield off, and it is what the
// option needs when it is on.
#[YieldReady]
final class GreetNode extends Node {
	public function __construct( AbstractExpression $name, int $line ) {
		parent::__construct( array( 'name' => $name ), array(), $line );
	}

	public function compile( Compiler $compiler ): void {
		$compiler
			->addDebugInfo( $this )
			->write( 'yield "Hello, " . ' )
			->subcompile( $this->getNode( 'name' ) )
			->raw( ";\n" );
	}
}
```

```twig
{% greet user.first_name %}
```

### Node visitors

A node visitor reaches compilation the same way, by its interface alone:

```php
// app/Twig/UppercaseNodeVisitor.php
namespace App\Twig;

use Twig\Environment;
use Twig\Node\Node;
use Twig\Node\TextNode;
use Twig\NodeVisitor\NodeVisitorInterface;

final class UppercaseNodeVisitor implements NodeVisitorInterface {
	public function enterNode( Node $node, Environment $environment ): Node {
		return $node;
	}

	// Returning null here would remove the node from the tree.
	public function leaveNode( Node $node, Environment $environment ): ?Node {
		return $node;
	}

	public function getPriority(): int {
		return 0;
	}
}
```

Twig sorts visitors by the priority they report themselves. Two reporting the same one are
decided by the order they were added, which is the `twig.node_visitor` tag priority.

## Globals

A variable every template can read without being handed it:

```php
// config/packages/twig.php
use App\Service\MenuBuilder;
use OffsetWP\Bundle\TwigBundle\Configuration\TwigConfig;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	TwigConfig::create()
		->global( 'year', 2026 )                      // a scalar
		->global( 'site_name', '%app.name%' )         // a container parameter
		->globalService( 'menu', MenuBuilder::class ) // a service
		->globalLiteral( 'handle', '@offsetwp' )      // the string, "@" and all
		->apply( $container );
};
```

```twig
{{ site_name }} — {{ year }}
```

A global naming a service that does not exist is refused when the container builds, naming
the global and the service id.

**A global that is a service is built eagerly**, on every request that renders anything at
all — Twig wants the value before its extensions initialise. When that matters, expose a
function instead: it is called only by the templates that use it, and its class is
constructed only then.

```php
// app/Twig/Menu.php
final class Menu {
	#[AsTwigFunction( name: 'menu' )]
	public function menu(): array {
		return $this->builder->build();
	}
}
```

```twig
{% for item in menu() %}…{% endfor %}
```

## Custom loaders

### One loader

Implement Twig's loader interface — a loader is one class, like every other way into Twig:

```php
// app/Twig/DatabaseLoader.php
namespace App\Twig;

use Twig\Loader\LoaderInterface;

final class DatabaseLoader implements LoaderInterface {
	/** … getSourceContext, getCacheKey, isFresh, exists … */
}
```

With autoconfiguration on, nothing else is needed. Register it under an id of your own — its
class name does the job; `Twig\Loader\LoaderInterface` is the id this bundle points at
whichever loader it settles on, and a loader registered there is refused.

With exactly one loader registered it is used directly, with no chain around it.

### Several loaders and the chain

The built-in filesystem loader carries priority `0`. Give yours a higher one and it is
consulted first, with the filesystem as the fallback:

```php
$services->set( DatabaseLoader::class )->tag( 'twig.loader', array( 'priority' => 10 ) );
```

### Replacing the filesystem loader

The filesystem loader exists only when it has somewhere to look. Configure no `paths` and
keep no `templates/` directory, and yours is the only loader there is:

```php
// config/packages/twig.php — no path, so no filesystem loader
TwigConfig::create()->apply( $container );
```

```php
// config/services.php — no templates/ directory in the project
$services->set( DatabaseLoader::class );
```

With no paths, no `templates/` directory and no loader of your own, the environment still
builds and the first render names the directory it looked for.

## The `twig/*-extra` packages

None of these is special-cased: this bundle holds no list of them, no `class_exists()` probe
and no recipe. Each is an extension class like any other, and a package published tomorrow
works the same way. A unit test asserts that no file under `src/` mentions any of their
names.

### Summary

| Package | What it gives you | Needs a runtime | Also pulls in |
|---|---|---|---|
| `twig/intl-extra` | `format_currency`, `format_date`, `format_number`, `country_name`, … | no | the intl component, and PHP's `intl` extension at runtime |
| `twig/string-extra` | `u`, `slug`, `plural`, … | no | the string component, translation contracts |
| `twig/html-extra` | `html_classes`, `data_uri` | no | the mime component |
| `twig/cssinliner-extra` | `inline_css` | no | `tijsverkoyen/css-to-inline-styles` |
| `twig/inky-extra` | `inky_to_html` | no | `lorenzo/pinky` |
| `twig/markdown-extra` | `markdown_to_html`, `html_to_markdown` | **yes** | nothing — you pick the Markdown library |
| `twig/cache-extra` | `{% cache %}` | **yes** | the cache component |

Five install in one line. The other two ship a runtime whose constructor takes an argument
no autowiring can guess, so they take three.

### intl, string, html, cssinliner and inky

```bash
composer require twig/intl-extra
```

```php
// config/packages/twig.php
->extension( \Twig\Extra\Intl\IntlExtension::class )
```

```twig
{{ 1234.5|format_currency( 'EUR', {}, 'fr_FR' ) }}   {# 1 234,50 € #}
```

The other four are the same single line with their own class name:

```twig
{{ 'Un Été'|slug }}                                  {# Un-Ete #}
{{ html_classes( 'a', { 'b': true, 'c': false } ) }} {# a b    #}
{% apply inline_css %}…{% endapply %}
{% apply inky_to_html %}…{% endapply %}
```

`intl` needs PHP's `intl` extension enabled on the server, which Composer cannot install for
you — check `php -m | grep intl` before blaming a template.

### markdown

```bash
composer require twig/markdown-extra league/commonmark
```

```php
// config/services.php
use Twig\Extra\Markdown\DefaultMarkdown;
use Twig\Extra\Markdown\MarkdownExtension;
use Twig\Extra\Markdown\MarkdownRuntime;

$services->set( MarkdownExtension::class )->tag( 'twig.extension' );
$services->set( DefaultMarkdown::class );
$services->set( MarkdownRuntime::class )
	->args( array( service( DefaultMarkdown::class ) ) )
	->tag( 'twig.runtime' );
```

```twig
{{ '# Titre'|markdown_to_html }}   {# <h1>Titre</h1> #}
```

Three lines rather than one. `MarkdownRuntime` takes an argument no autowiring can guess —
which Markdown implementation you want — and implements no interface, so **its
`->tag( 'twig.runtime' )` is not optional**. Without it the filter compiles and then finds
nothing to call:

```
Unable to load the "Twig\Extra\Markdown\MarkdownRuntime" runtime in "post.twig" at line 1.
```

`DefaultMarkdown` picks whichever Markdown library is installed, and names what to install
when none is:

```
You cannot use the "markdown_to_html" filter as no Markdown library is available;
try running "composer require league/commonmark".
```

The sibling filter `html_to_markdown` is static and needs no runtime, but does need
`composer require league/html-to-markdown`.

### cache

```bash
composer require twig/cache-extra symfony/cache
```

```php
// config/services.php
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Twig\Extra\Cache\CacheExtension;
use Twig\Extra\Cache\CacheRuntime;

$services->set( 'app.twig_cache', FilesystemTagAwareAdapter::class )
	->args( array( 'twig', 0, '%kernel.root_path%/var/cache/twig-fragments' ) );

$services->set( CacheExtension::class )->tag( 'twig.extension' );
$services->set( CacheRuntime::class )
	->args( array( service( 'app.twig_cache' ) ) )
	->tag( 'twig.runtime' );
```

```twig
{% cache "sidebar" ttl( 300 ) %}…{% endcache %}
```

Three lines again, and for the same reasons: `CacheRuntime` takes a tag-aware cache pool —
which is what lets you point it at Redis without asking anyone — and implements no interface,
so its `twig.runtime` tag is written by hand.

**This is not Twig's `cache` option.** The names invite the confusion and the two have
nothing to do with each other:

| | `twig.cache` | `{% cache %}` |
|---|---|---|
| caches | compiled templates | rendered output |
| comes from | Twig itself | `twig/cache-extra` |
| configured in | `config/packages/twig.php` | `config/services.php` |
| invalidated by | the template changing | the ttl, or a tag |

Either can be on while the other is off.

### Any other package

The rule is the shape of what the package ships:

- an extension class with no constructor argument → add it to `extensions`;
- an extension class with constructor arguments → declare it as a service, tag it
  `twig.extension`;
- a companion runtime class → declare it as a service, tag it `twig.runtime`, whether or not
  it implements Twig's runtime interface.

## Extensions Twig ships but this bundle does not enable

Two extensions Twig ships are not loaded here: this package adds nothing Twig itself would
not add. Both are one line.

### The dump extension

```php
->extension( \Twig\Extension\DebugExtension::class )
```

```twig
{{ dump( post ) }}
```

Not enabled by `debug`, tempting as that is.

### The string loader extension

```php
->extension( \Twig\Extension\StringLoaderExtension::class )
```

```twig
{{ include( template_from_string( post.meta.layout ) ) }}
```

Renders a template held in a string rather than in a file — which is what you want when the
template lives in the database, and exactly why it is opt-in.

## Bundles that provide their own templates

A bundle ships templates by prepending a namespaced path into this extension. Prepending
merges with whatever the host configured; it does not replace it.

```php
// src/ShopBundle.php
namespace App\Bundle;

use OffsetWP\Bundle\TwigBundle\Configuration\TwigConfig;
use OffsetWP\Framework\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class ShopBundle extends Bundle {
	public function prependExtension( ContainerConfigurator $container, ContainerBuilder $builder ): void {
		TwigConfig::create()
			->path( __DIR__ . DIRECTORY_SEPARATOR . 'templates', 'shop' )
			->apply( $container, prepend: true );
	}
}
```

```twig
{% include '@shop/cart.twig' %}
```

`__DIR__` and not `%kernel.root_path%`: the directory wanted here is the bundle's own, and
that parameter holds the host's.

The host keeps its own `templates/` directory and its own `paths`, and every namespace
reaches the loader.

## Troubleshooting

### Template not found

Twig names every directory it searched:

```
Unable to find template "pages/front-page.twig" (looked into: /abs/templates).
```

If the list is empty or missing a directory you expected, the `paths` you wrote never
reached the loader. If the message is instead

```
Twig has no template source. The default directory "/abs/templates" does not exist and no
"twig.paths" entry or "twig.loader" service is configured. Create that directory, or
configure "twig.paths".
```

then nothing provides templates at all: create that directory, or configure `twig.paths`.

### My filter is not found

`Unknown "price" filter` means the class carrying the attribute never reached Twig. In
order of likelihood:

1. **The class is not a registered service.** Attributes on a class the container has never
   heard of do nothing, and nothing can report it. Check that your `config/services.php`
   loads the namespace it lives in.
2. **`autoconfigure` is off** in your `_defaults`. Without it neither the attributes nor the
   interfaces are scanned. Everything still works through explicit tags.
3. **The method is not public.** That one is refused when the container builds, with a
   message naming the class and the method — so if you are seeing "unknown filter" instead,
   this is not it.

### My class is never instantiated, or is instantiated too early

Never instantiated is usually correct: a class behind an attribute is built on the first
template call that reaches it, and never otherwise.

Too early has one usual cause: a `globals` entry pointing at a service, which Twig wants
before its extensions initialise. Expose a function instead — see [Globals](#globals).

### Twig ignores my configuration

A build saying a service of this bundle is defined by your project as well means your own
`config/services.php` redefined one of its ids, and everything the `twig` configuration had
put on it — options, paths, globals, extensions — went with it. The message names the id.
Configure `twig` instead, or register your own service under an id of your own.

### Nothing happens at all

The kernel was not built in configuration mode, so it registered no bundles — see
[Installation](#installation). `twig()` in that state raises "No kernel carrying the Twig
bundle has booted", and names both reasons a kernel that did boot can be carrying none: a
`config/bundles.php` that does not enable this bundle for the environment, and a kernel built
the other way.

### Performance notes

The framework recompiles its container on every request. With Twig's compilation cache off as
well, a page render recompiles both — so setting `cache` is the single highest-impact change
a production site can make here:

```php
->cacheDirectory( '%kernel.root_path%/var/cache/twig' )
```

Nothing else in this package is eager: booting constructs nothing, building the environment
constructs no extension behind an attribute, and a request that renders no template touches
none of it.

## Licence

MIT. See [LICENSE](LICENSE).
