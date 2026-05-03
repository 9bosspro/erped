<?php
declare(strict_types=1);
namespace Core\Base\Providers;
use Core\Base\Support\Helpers\Cms\Action;
use Illuminate\Support\ServiceProvider as CBaseServiceProvider;
class MinimalTest extends CBaseServiceProvider
{
    public function register(): void {}
    public function boot(): void { $this->test(); }
    protected function test(): void
    {
        /** @var Action $action */
        $action = $this->app->make(Action::class);
        $action->addListener('x', function (): void {
            // use Container::getInstance() directly — bypasses Larastan app() extension
            $x = \Illuminate\Container\Container::getInstance()->make('core.audit');
        }, 1);
    }
}
