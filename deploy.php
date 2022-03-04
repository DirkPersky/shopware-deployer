<?php

namespace Deployer;
require_once 'recipe/common.php';

set('application', 'Shopware 6');
set('allow_anonymous_stats', false);
set('default_timeout', 3600); // Increase the `default_timeout`, if needed, when tasks take longer than the limit.
set('ssh_multiplexing', false);
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
 * Hosts Konfiguration
 */
host('###SERVER_HOST###')
    ->stage('production')
    ->user('###SSH_USER###')
    ->set('deploy_path', '/html/shopware-deploy') // This is the path, where deployer will create its directory structure
    ->set('writable_mode', 'chmod');

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
    run('cd {{release_path}} && bin/console theme:compile');
});
task('sw:cache:clear', static function () {
    run('cd {{release_path}} && bin/console cache:clear');
});
task('sw:cache:warmup', static function () {
    run('cd {{release_path}} && bin/console cache:warmup');
    run('cd {{release_path}} && bin/console http:cache:warm:up');
});
task('sw:database:migrate', static function () {
    run('cd {{release_path}} && bin/console database:migrate --all');
});
task('sw:plugin:refresh', function () {
    run('cd {{release_path}} && bin/console plugin:refresh');
});
task('sw:plugin:update:all', static function () {
    $plugins = getPlugins();
    foreach ($plugins as $plugin) {
        if ($plugin['Installed'] === 'Yes') {
            writeln("<info>Running plugin update for " . $plugin['Plugin'] . "</info>\n");
            run("cd {{release_path}} && bin/console plugin:update " . $plugin['Plugin']);
        }
    }
});
task('sw:writable:jwt', static function () {
    run('cd {{release_path}} && chmod -R 660 config/jwt/*');
});

/**
 * Shopware Local Tasks
 */
task('sw-build-without-db:get-remote-config', static function () {
    if (!test('[ -d {{current_path}} ]')) {
        return;
    }
    within('{{deploy_path}}/current', function () {
        run('./bin/console bundle:dump');
        download('{{deploy_path}}/current/var/plugins.json', './var/');

        run('./bin/console theme:dump');
        download('{{deploy_path}}/current/files/theme-config', './files/');

        // Temporary workaround to remove absolute file paths in Shopware <6.4.6.0
        // See https://github.com/shopware/platform/commit/01c8ff86c7d8d3bee1888a26c24c9dc9b4529cbc and https://issues.shopware.com/issues/NEXT-17720
        runLocally('sed -i "" -E \'s/\\\\\/var\\\\\/www\\\\\/htdocs\\\\\/releases\\\\\/[0-9]+\\\\\///g\' files/theme-config/* || true');
    });
});
task('sw-build-without-db:build', static function () {
    runLocally('CI=1 SHOPWARE_SKIP_BUNDLE_DUMP=1 ./bin/build.sh');
});
task('sw-unit-test', static function(){
    // TODO implement unit test
    runLocally('php vendor/bin/phpunit');
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

/**
 * add task listeners
 */
after('deploy:failed', 'deploy:unlock');
before('deploy:update_code', 'sw-build-without-db');
