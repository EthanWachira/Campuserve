<?php
class View
{
    /** Render app/views/$view.php inside a layout. $data keys become variables. */
    public static function render(string $view, array $data = [], string $layout = 'layouts/main'): void
    {
        $file = BASE_PATH . "/app/views/$view.php";
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        $content = ob_get_clean();
        require BASE_PATH . "/app/views/$layout.php";
    }

    public static function partial(string $name, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require BASE_PATH . "/app/views/partials/$name.php";
    }
}
