<?php

namespace Core\Base\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

// $RECYCLE.BIN

trait StubsTrait
{
    public function printClass1()
    {
        return __CLASS__;
    }

    protected function getStub($type)
    {
        return file_get_contents(resource_path("stubs/$type.stub"));
    }

    protected function model($name)
    {
        $modelTemplate = str_replace(
            ['{{modelName}}'],
            [$name],
            $this->getStub('Model'),
        );

        file_put_contents(app_path("/{$name}.php"), $modelTemplate);
    }

    protected function controller($name)
    {
        $controllerTemplate = str_replace(
            [
                '{{modelName}}',
                '{{modelNamePluralLowerCase}}',
                '{{modelNameSingularLowerCase}}',
            ],
            [
                $name,
                strtolower(str_plural($name)),
                strtolower($name),
            ],
            $this->getStub('Controller'),
        );

        file_put_contents(app_path("/Http/Controllers/{$name}Controller.php"), $controllerTemplate);
    }

    protected function request($name)
    {
        $requestTemplate = str_replace(
            ['{{modelName}}'],
            [$name],
            $this->getStub('Request'),
        );

        if (! file_exists($path = app_path('/Http/Requests'))) {
            mkdir($path, 0777, true);
        }

        file_put_contents(app_path("/Http/Requests/{$name}Request.php"), $requestTemplate);
    }

    protected function genconfig($name)
    {
        Config::set([
            'a.b.a' => 'http://example.com',
        ]);
        Config::set([
            'a.b.b' => 'http://example.com1',
        ]);
        $pp = Config::get('a.b');
        // var_export($pp);

        // config(['YOUR-CONFIG.YOUR_KEY' => 'NEW_VALUE']);
        $text = '<?php return '.var_export(config('a.b'), true).';';
        file_put_contents(config_path('modules\YOUR-CONFIG.php'), $text);
    }
    /* public function handle()
{
$name = $this->argument('name');

$this->controller($name);
$this->model($name);
$this->request($name);

File::append(base_path('routes/api.php'), 'Route::resource(\'' . str_plural(strtolower($name)) . "', '{$name}Controller');");
}
config(['YOURKONFIG.YOURKEY' => 'NEW_VALUE']);
$fp = fopen(base_path() .'/config/YOURKONFIG.php' , 'w');
fwrite($fp, '<?php return ' . var_export(config('YOURKONFIG'), true) . ';');
fclose($fp);

$l = new FileLoader(
new Illuminate\Filesystem\Filesystem(),
base_path().'/config'
);

$conf = ['mykey' => 'thevalue'];

$l->save($conf, '', 'customconfig');

config(['YOUR-CONFIG.YOUR_KEY' => 'NEW_VALUE']);
$text = '<?php return ' . var_export(config('YOUR-CONFIG'), true) . ';';
file_put_contents(config_path('YOUR-CONFIG.php'), $text);

 */
}
