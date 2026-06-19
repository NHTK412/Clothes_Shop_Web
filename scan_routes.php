<?php
require __DIR__ . '/vendor/autoload.php';
try {
    $openapi = \OpenApi\scan([__DIR__.'/routes/api.php']);
    if (isset($openapi->info)) {
        echo "FOUND INFO\n";
        var_export($openapi->info);
    } else {
        echo "NO INFO\n";
    }
} catch (\Exception $e) {
    echo 'ERR: '.$e->getMessage()."\n";
}
