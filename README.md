# silverstripe-openapi-export

Describe your Silverstripe API controllers in code and export an OpenAPI 3.1 document with a build task. The output feeds typed client generators such as hey-api.

## Install

```sh
composer require bradtipper/silverstripe-openapi-export
```

## Use

Give each routed controller a static `describe` method:

```php
use BradTipper\RestfulFramework\OpenApi\Spec;

public static function describe(Spec $spec): void
{
    $spec->schema('Widget', Spec::object(['id' => Spec::string('uuid'), 'name' => Spec::string()]));
    $spec->operation('get', '/api/v1/widgets', 'listWidgets', [
        'tags' => ['Widgets'],
        'summary' => 'List widgets',
        'responses' => [200 => Spec::arrayOf(Spec::ref('Widget'))],
    ]);
}
```

Export:

```sh
vendor/bin/sake tasks:openapi-export --output=openapi.json --title="My API"
```

Only controllers routed in `Director.rules` are described. See `src/OpenApi/Spec.php` for the schema and operation helpers.

## Config

```yaml
BradTipper\RestfulFramework\OpenApi\OpenApiExportTask:
  server_url: 'https://example.com'      # optional; defaults to the site base URL
  schema_providers:                      # classes with a static register(Spec $spec) for shared schemas
    - App\Api\CommonSchemas
```

## Licence

BSD-3-Clause
