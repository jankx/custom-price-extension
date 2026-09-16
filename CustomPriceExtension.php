<?php
namespace Jankx\Extensions;

use Jankx\Extensions\AbstractExtension;

class CustomPriceExtension extends AbstractExtension
{
    public function __construct()
    {
        $this->register_autoloader();
        parent::__construct();
    }

    protected function register_autoloader()
    {
        spl_autoload_register(function ($class) {
            $prefix = 'Jankx\\Extensions\\CustomPrice\\';
            $base_dir = __DIR__ . '/src/';

            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

            if (file_exists($file)) {
                require $file;
            }
        });
    }

    public function init(): void
    {
    }

    public function register_hooks(): void
    {
        add_action('init', [$this, 'registerBlock']);
    }

    public function registerBlock()
    {
        register_block_type($this->get_extension_path() . '/block');
    }
}
