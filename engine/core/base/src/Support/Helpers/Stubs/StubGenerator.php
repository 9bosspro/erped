<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Stubs;

use Illuminate\Support\Str;

/**
 * StubGenerator — สร้างไฟล์ PHP จาก stub templates
 *
 * ใช้สำหรับ dev tooling — generate Model, Controller, Request
 * จาก stubs ใน resources/stubs/{type}.stub
 *
 * ตัวอย่าง:
 * ```php
 * $gen = new StubGenerator();
 * $gen->model('Product');
 * $gen->controller('Product');
 * $gen->request('Product');
 * ```
 */
final class StubGenerator
{
    /**
     * สร้าง Model file จาก stub
     *
     * @param  string  $name  ชื่อ Model เช่น 'Product'
     */
    public function model(string $name): void
    {
        $template = str_replace(
            ['{{modelName}}'],
            [$name],
            $this->getStub('Model'),
        );

        file_put_contents(app_path("/{$name}.php"), $template);
    }

    /**
     * สร้าง Controller file จาก stub
     *
     * @param  string  $name  ชื่อ Model เช่น 'Product'
     */
    public function controller(string $name): void
    {
        $template = str_replace(
            ['{{modelName}}', '{{modelNamePlural}}', '{{modelNameSingular}}'],
            [$name, Str::plural(strtolower($name)), strtolower($name)],
            $this->getStub('Controller'),
        );

        file_put_contents(app_path("/Http/Controllers/{$name}Controller.php"), $template);
    }

    /**
     * สร้าง Form Request file จาก stub
     *
     * @param  string  $name  ชื่อ Model เช่น 'Product'
     */
    public function request(string $name): void
    {
        $template = str_replace(
            ['{{modelName}}'],
            [$name],
            $this->getStub('Request'),
        );

        $path = app_path('/Http/Requests');
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        file_put_contents("{$path}/{$name}Request.php", $template);
    }

    /**
     * อ่านเนื้อหา stub template
     *
     * @param  string  $type  ชื่อ stub เช่น 'Model', 'Controller', 'Request'
     */
    private function getStub(string $type): string
    {
        return file_get_contents(resource_path("stubs/{$type}.stub"));
    }
}
