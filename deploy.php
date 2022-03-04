<?php

namespace Deployer;
require_once 'recipe/common.php';

$mittwaldProjectID = 'pXXXXXX';

/**
 * Hosts Konfiguration
 */
host('production')
    ->hostname("{$mittwaldProjectID}.mittwaldserver.info")
    ->user($mittwaldProjectID)
    ->set('deploy_path', "/home/www/{$mittwaldProjectID}/html/shopware-deploy") // This is the path, where deployer will create its directory structure
    ->set('writable_mode', 'chmod');

set('application', 'Shopware 6');
set('allow_anonymous_stats', false);
set('default_timeout', 3600); // Increase the `default_timeout`, if needed, when tasks take longer than the limit.
//set('ssh_multiplexing', false);
// For more information, please visit the Deployer docs: https://deployer.org/docs/configuration.html#shared_files
set('shared_files', [
    '.env',
]);
// For more information, please visit the Deployer docs: https://deployer.org/docs/configuration.html#shared_dirs
set('shared_dirs', [
    'custom/plugins',
    'config/jwt',
    'files',
    'var/log',
    'public/media',
    'public/thumbnail',
    'public/sitemap',
]);
// For more information, please visit the Deployer docs: https://deployer.org/docs/configuration.html#writable_dirs
set('writable_dirs', [
    'custom/plugins',
    'config/jwt',
    'files',
    'public/bundles',
    'public/css',
    'public/fonts',
    'public/js',
    'public/media',
    'public/sitemap',
    'public/theme',
    'public/thumbnail',
    'var',
]);

/**
 * uploads the whole workspace to the target server
 */
task('deploy:update_code', static function () {
    upload('.', '{{release_path}}');
});

/**
 * Shopware Remote Tasks
 */
task('sw:touch_install_lock', static function () {
    run('cd {{release_path}} && touch install.lock');
});
task('sw:theme:compile', static function () {
    run('cd {{release_path}} && php bin/console theme:compile');
});
task('sw:cache:clear', static function () {
    run('cd {{release_path}} && php bin/console cache:clear');
});
task('sw:cache:warmup', static function () {
    run('cd {{release_path}} && php bin/console cache:warmup');
    run('cd {{release_path}} && php bin/console http:cache:warm:up');
});
task('sw:database:migrate', static function () {
    run('cd {{release_path}} && php bin/console database:migrate --all');
});
task('sw:plugin:refresh', function () {
    run('cd {{release_path}} && php bin/console plugin:refresh');
});
task('sw:plugin:update:all', static function () {
    $plugins = getPlugins();
    foreach ($plugins as $plugin) {
        if ($plugin['Installed'] === 'Yes') {
            writeln("<info>Running plugin update for " . $plugin['Plugin'] . "</info>\n");
            run("cd {{release_path}} && php bin/console plugin:update " . $plugin['Plugin']);
        }
    }
});
task('sw:writable:jwt', static function () {
    run('cd {{release_path}} && chmod -R 660 config/jwt/*');
});

function getPlugins(): array
{
    $output = explode("\n", run('cd {{release_path}} && php bin/console plugin:list'));

    // Take line over headlines and count "-" to get the size of the cells.
    $lengths = array_filter(array_map('strlen', explode(' ', $output[4])));
    $splitRow = function ($row) use ($lengths) {
        $columns = [];
        foreach ($lengths as $length) {
            $columns[] = trim(substr($row, 0, $length));
            $row = substr($row, $length + 1);
        }
        return $columns;
    };
    $headers = $splitRow($output[5]);
    $splitRowIntoStructure = function ($row) use ($splitRow, $headers) {
        $columns = $splitRow($row);
        return array_combine($headers, $columns);
    };

    // Ignore first seven lines (headline, title, table, ...).
    $rows = array_slice($output, 7, -3);

    $plugins = [];
    foreach ($rows as $row) {
        $pluginInformation = $splitRowIntoStructure($row);
        $plugins[] = $pluginInformation;
    }

    return $plugins;
}

/**
 * Shopware Local Tasks
 */
task('sw-build-without-db:get-remote-config', static function () {
    if (!test('[ -d {{current_path}} ]')) {
        return;
    }
    within('{{deploy_path}}/current', function () {
        run('php bin/console bundle:dump');
        download('{{deploy_path}}/current/var/plugins.json', './var/');

        run('php bin/console theme:dump');
        download('{{deploy_path}}/current/files/theme-config', './files/');

        // Temporary workaround to remove absolute file paths in Shopware <6.4.6.0
        // See https://github.com/shopware/platform/commit/01c8ff86c7d8d3bee1888a26c24c9dc9b4529cbc and https://issues.shopware.com/issues/NEXT-17720
        runLocally('sed -i "" -E \'s/\\\\\/var\\\\\/www\\\\\/htdocs\\\\\/releases\\\\\/[0-9]+\\\\\///g\' files/theme-config/* || true');
    });
});
task('sw-build-without-db:build', static function () {
    runLocally('chmod +x ./bin/*.sh');
    runLocally('CI=1 SHOPWARE_SKIP_BUNDLE_DUMP=1 ./bin/build-js.sh');
});
task('sw-unit-test', static function () {
    // TODO implement unit test
    runLocally('php vendor/bin/phpunit');
});
task('sw-first-install', static function () {
    run('cd {{release_path}} && php bin/console assets:install');
});

/**
 * Grouped SW deploy tasks
 */
task('sw:deploy', [
    'sw:touch_install_lock',
    'sw:database:migrate',
    'sw:plugin:refresh',
    'sw:theme:compile',
    'sw:cache:clear',
    'sw:plugin:update:all',
    'sw:cache:clear',
]);
task('sw-build-without-db', [
    'sw-build-without-db:get-remote-config',
    'sw-build-without-db:build',
]);

/**
 * Main task
 */
task('deploy', [
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'sw-build-without-db',
    'deploy:shared',
    'sw:deploy',
    'deploy:writable',
    'deploy:clear_paths',
    'sw:cache:warmup',
    'sw:writable:jwt',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success',
])->desc('Deploy your project');

task('install', [
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',
    'deploy:clear_paths',
    'sw-first-install',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success',
])->desc('Install your project for first run');
/**
 * add task listeners
 */
after('deploy:failed', 'deploy:unlock');
