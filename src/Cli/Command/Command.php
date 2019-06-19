<?php

declare(strict_types=1);

namespace Dof\Framework\Cli\Command;

use Throwable;
use Dof\Framework\Kernel;
use Dof\Framework\GWT;
use Dof\Framework\Doc\Generator as DocGen;
use Dof\Framework\DDD\Storage;
use Dof\Framework\DDD\ORMStorage;
use Dof\Framework\Storage\StorageSchema;
use Dof\Framework\ConfigManager;
use Dof\Framework\DomainManager;
use Dof\Framework\ModelManager;
use Dof\Framework\EntityManager;
use Dof\Framework\StorageManager;
use Dof\Framework\RepositoryManager;
use Dof\Framework\CommandManager;
use Dof\Framework\WrapinManager;
use Dof\Framework\PortManager;
use Dof\Framework\EventManager;
use Dof\Framework\Web\Kernel as WebKernel;

class Command
{
    /**
     * @CMD(version)
     * @Desc(Get Dof version)
     * @Option(raw){notes=Get the raw version count of framework}
     */
    public function version($console)
    {
        if ($console->hasOption('raw')) {
            $console->success((string) get_dof_version_raw(), true);
        }

        $console->success(get_dof_version(), true);
    }

    private function header($console)
    {
        $console->line();
        $console->output($console->render('Dof-PHP Framework', 'LIGHT_BLUE'));
        $console->output('  ');
        $console->output(get_dof_version());
        $console->output('  ');
        $console->output($console->render('ckwongloy@gmail.com', 'DARK_GRAY'));
        $console->line(null, 2);
    }

    /**
     * @CMD(help)
     * @Desc(Print help messages of Dof commands)
     * @Argv(1){notes=The command name used to print help message}
     */
    public function help($console)
    {
        $console->line();

        $cmd = $console->getParams()[0] ?? null;
        if ($cmd) {
            $cmd = strtolower($cmd);
            $attr = CommandManager::get($cmd);
            if (! $attr) {
                $console->exception('CommandToHelpNotExist', [$cmd]);
            }

            $console->line($console->render("Usage: php dof {$cmd} [--options ...] [[--] parameters ...]", 'YELLOW'), 2);
            extract($attr);

            $console->line(
                $console->render('* Command: ', $console::TITLE_COLOR)
                .$console->render($cmd, $console::SUCCESS_COLOR)
            );
            $console->line();
            $console->info('* Comment: '.$comment);
            $console->line();
            $console->title('* Options: ');
            foreach ($options as $option => $_attr) {
                extract($_attr);
                $default = $DEFAULT ?: 'NULL';
                $console->line(
                    $console->render("\t--{$option}\t", $console::SUCCESS_COLOR)
                    .$console->render($NOTES, $console::INFO_COLOR)
                    .$console->render("\t(Default: {$default})", 'CYAN')
                );
            }
            $console->title('* Arguments: ');
            foreach ($argvs as $order => $desc) {
                $console->line(
                    $console->render("\t#{$order}\t", $console::SUCCESS_COLOR)
                    .$console->render($desc, $console::INFO_COLOR)
                );
            }

            $console->line();
            $console->info('* Class: '.$class);
            $console->info('* Method: '.$method);
        } else {
            $console->line($console->render('Usage: php dof {COMMAND} [--options ...] [[--] parameters ...]', 'YELLOW'));
        }

        $console->line();
    }

    /**
     * @CMD(php)
     * @Desc(Execute a standalone php script)
     * @Argv(1){notes=The php script file to run}
     */
    public function php($console)
    {
        $php = $console->getParams()[0] ?? null;
        if (! $php) {
            $console->fail('NoPhpScriptToRun', true);
        }
        if (! is_file($php)) {
            $php = ospath(Kernel::getRoot(), $php);
            if (! is_file($php)) {
                $console->exception('PhpScriptNotExists', ['path' => $php]);
            }
        }

        try {
            require $php;
        } catch (Throwable $e) {
            $console->exception('FailedToExecutePhpScript', [
                'message' => $e->getMessage(),
                'path' => $php
            ], $e);
        }
    }

    /**
     * @CMD(root)
     * @Desc(Get Dof framework root)
     * @Option(project){notes=Get project root instead}
     */
    public function getRoot($console)
    {
        if ($console->hasOption('project')) {
            return $console->success(Kernel::getRoot(), true);
        }

        return $console->success(Kernel::root(), true);
    }

    /**
     * @CMD(cmd.domain)
     * @Desc(List domain commands in current project)
     */
    public function listDomainCMD($console)
    {
        $commands = CommandManager::getDomain();
        ksort($commands);
        
        $console->line();
        foreach ($commands as $domain => $cmds) {
            $title = ConfigManager::getDomainDomainByKey($domain, 'title', strtoupper($domain));
            $path = str_replace(Kernel::getRoot().'/', '', DomainManager::getByKey($domain));
            $console->title(join(' | ', [$domain, $title, $path]));

            foreach ($cmds as $cmd => $idx) {
                $attr = CommandManager::get($cmd);
                extract($attr);

                $console->line(
                    $console->render($cmd, $console::SUCCESS_COLOR)
                    ."\t\t"
                    .$console->render($comment, $console::INFO_COLOR)
                );

                if (false !== next($commands)) {
                    if (! ci_equal(mb_strcut($cmd, 0, 1), mb_strcut(key($commands), 0, 1))) {
                        $console->line();
                    }
                }
            }
        }
        $console->line();
    }

    /**
     * @CMD(cmd.default)
     * @Desc(List default commands builtin Dof-PHP framework)
     */
    public function listDefaultCMD($console)
    {
        $commands = CommandManager::getDefault();
        ksort($commands);
        
        $console->line();
        foreach ($commands as $cmd => $idx) {
            $attr = CommandManager::get($cmd);
            extract($attr);

            $console->line(
                $console->render($cmd, $console::SUCCESS_COLOR)
                ."\t\t"
                .$console->render($comment, $console::INFO_COLOR)
            );

            if (false !== next($commands)) {
                if (! ci_equal(mb_strcut($cmd, 0, 1), mb_strcut(key($commands), 0, 1))) {
                    $console->line();
                }
            }
        }
        $console->line();
    }

    /**
     * @CMD(cmd)
     * @Desc(List all commands in current Dof-PHP project)
     */
    public function listCMD($console)
    {
        return $this->listAllCMD($console);
    }

    /**
     * @CMD(cmd.all)
     * @Desc(List all commands in current Dof-PHP project)
     */
    public function listAllCMD($console)
    {
        $commands = CommandManager::getCommands();
        ksort($commands);

        $console->line();
        foreach ($commands as $cmd => $attr) {
            extract($attr);
            $console->line(
                $console->render($cmd, $console::SUCCESS_COLOR)
                ."\t\t"
                .$console->render($comment, $console::INFO_COLOR)
            );

            if (false !== next($commands)) {
                if (! ci_equal(mb_strcut($cmd, 0, 1), mb_strcut(key($commands), 0, 1))) {
                    $console->line();
                }
            }
        }

        $console->line();
    }

    /**
     * @CMD(dof)
     * @Desc(Dof default command)
     * @Option(help){notes=Print dof cli help message}
     * @Option(version){notes=Get dof framework version string}
     * @Option(root){notes=Get dof framework root}
     */
    public function dof($console)
    {
        if ($console->hasOption('help')) {
            return $this->help($console);
        }

        if ($console->hasOption('version')) {
            return $this->version($console);
        }

        if ($console->hasOption('root')) {
            return $this->getRoot($console);
        }

        return $this->header($console);
    }

    /**
     * @CMD(web.start)
     * @Desc(Start/Restart web application)
     */
    public function startWeb($console)
    {
        $lock = ospath(Kernel::getRoot(), WebKernel::HALT_FLAG);
        if (! is_file($lock)) {
            $console->success('OK! Application is up and running.', true);
        }

        $res = unlink($lock);
        if ($res === false) {
            $console->fail('Failed!', true);
        }

        $console->success('Success!', true);
    }

    /**
     * @CMD(web.stop)
     * @Desc(Stop/Halt web application)
     * @Option(force){notes=Whether force stop web application even if it's stopped already}
     * @Option(message){notes=The application shutdown message text displays to visitors}
     */
    public function stopWeb($console)
    {
        $lock = ospath(Kernel::getRoot(), WebKernel::HALT_FLAG);
        if (is_file($lock)) {
            if ($console->hasOption('force')) {
                if (false === unlink($lock)) {
                    $console->fail('ERROR! Force shutdown failed.', true);
                }
            } else {
                $console->success('OK! Application was shutdown already.', true);
            }
        }

        $message = $console->getOption('message', 'Unknown');
        $since = microftime('T Y-m-d H:i:s');
        $res = file_put_contents($lock, enjson(compact('message', 'since')));
        if (false === $res) {
            $console->fail('Failed!', true);
        }

        $console->success('Success!', true);
    }

    /**
     * @CMD(test)
     * @Desc(Run all domain tests)
     */
    public function test($console)
    {
        $domains = DomainManager::getDirs();
        foreach ($domains as $domain) {
            $console->title("---- Domain Root: {$domain} ----");
            $tests = ospath($domain, 'tests');
            if (! is_dir($tests)) {
                continue;
            }

            $this->__test($console, $tests);
            $console->line();
        }
    }

    /**
     * @CMD(test.domain)
     * @Desc(Run domain tests)
     * @Argv(1){notes=The domain name to run test cases}
     */
    public function testDomain($console)
    {
        $domain = $console->first('domain');
        if (! $domain) {
            $console->fail('MissingDomainName', true);
        }

        $_domain = DomainManager::getByKey($domain);
        if (! $_domain) {
            $console->exception('DomainNotFound', compact('domain'));
        }

        $this->__test($console, ospath($_domain, 'tests'));
    }

    /**
     * @CMD(test.dir)
     * @Desc(Run Dof GWT test cases by directory)
     * @Option(path){notes=The directory to run test cases}
     */
    public function testDir($console)
    {
        if (! $console->hasOption('path')) {
            $console->fail('MissingTestsPath', true);
        }

        $path = $console->getOption('path');
        if (! is_dir($path)) {
            $path = ospath(Kernel::getRoot(), $path);
            if (! is_dir($path)) {
                $console->exception('TestsPathNotExists', compact('path'));
            }
        }

        $this->__test($console, $path);
    }

    private function __test($console, string $dir, array $excludes = [])
    {
        GWT::reset();
        $start = microtime(true);
        GWT::run($dir, $excludes);
        $end = microtime(true);
        $success  = GWT::getSuccess();
        $_success = count($success);
        $failure  = GWT::getFailure();
        $_failure = count($failure);
        $exception  = GWT::getException();
        $_exception = count($exception);

        $console->info('-- Time Taken: '.($end-$start).' s');
        $console->info('-- Memory Used: '.format_bytes(memory_get_usage()));
        $console->info('-- Total Test Cases: '.($_success + $_failure + $_exception));
        $console->success('-- Passed Tests: '.$_success);
        $console->fail('-- Failed Tests: '.$_failure);
        if ($_failure > 0) {
            $console->fail(json_pretty($failure));
        }
        $console->warning('-- Exception Exceptions: '.$_exception);
        if ($_exception > 0) {
            $console->warning(json_pretty($exception));
        }
    }

    /**
     * @CMD(test.framework)
     * @Desc(Run framework tests)
     */
    public function testFramework($console)
    {
        $tests = ospath(__DIR__.'/../../..', ['tests']);
        $this->__test($console, $tests, [
            ospath($tests, 'run.php'),
            ospath($tests, 'data'),
        ]);
    }

    /**
     * @CMD(docs.build.model)
     * @Desc(Generate data model docs of domains)
     * @Option(save){notes=The path to save the build result}
     * @Option(absolute){notes=Whether the save path is an absolute path}
     * @Option(ui){notes=The UI name used to render docs}
     */
    public function buildDocsModel($console)
    {
        $save  = $console->getOption('save', 'tmp/dof-docs-data-model');
        $isabs = $console->getOption('absolute', 0);
        if (! $isabs) {
            $save = ospath(Kernel::getRoot(), $save);
        }

        DocGen::buildModel($console->getOption('ui', 'gitbook'), $save);

        $console->success('Done!', true);
    }

    /**
     * @CMD(docs.build.wrapin)
     * @Desc(Generate wrapin docs of domains)
     * @Option(save){notes=The path to save the build result}
     * @Option(absolute){notes=Whether the save path is an absolute path}
     * @Option(ui){notes=The UI name used to render docs}
     */
    public function buildDocsWrapin($console)
    {
        $save  = $console->getOption('save', 'tmp/dof-docs-wrapin');
        $isabs = $console->getOption('absolute', 0);
        if (! $isabs) {
            $save = ospath(Kernel::getRoot(), $save);
        }

        DocGen::buildWrapin($console->getOption('ui', 'gitbook'), $save);

        $console->success('Done!', true);
    }

    /**
     * @CMD(docs.build.http)
     * @Desc(Generate http docs of domains)
     * @Option(save){notes=The path to save the build result}
     * @Option(absolute){notes=Whether the save path is an absolute path}
     * @Option(ui){notes=The UI name used to render docs}
     */
    public function buildDocsHttp($console)
    {
        $save  = $console->getOption('save', 'tmp/dof-docs-http');
        $isabs = $console->getOption('absolute', 0);
        if (! $isabs) {
            $save = ospath(Kernel::getRoot(), $save);
        }

        DocGen::buildHttp($console->getOption('ui', 'gitbook'), $save);

        $console->success('Done!', true);
    }

    /**
     * @CMD(docs.build.all)
     * @Desc(Generate all docs of domains)
     * @Option(save){notes=The path to save the build result}
     * @Option(absolute){notes=Whether the save path is an absolute path}
     * @Option(ui){notes=The UI name used to render docs}
     */
    public function buildDocsAll($console)
    {
        $save  = $console->getOption('save', 'tmp/dof-docs-all');
        $isabs = $console->getOption('absolute', 0);
        if (! $isabs) {
            $save = ospath(Kernel::getRoot(), $save);
        }

        DocGen::buildAll($console->getOption('ui', 'gitbook'), $save);

        $console->success('Done!', true);
    }

    /**
     * @CMD(docs.build)
     * @Desc(Generate all docs of domains)
     * @Option(save){notes=The path to save the build result}
     * @Option(absolute){notes=Whether the save path is an absolute path}
     * @Option(ui){notes=The UI name used to render docs}
     */
    public function buildDocs($console)
    {
        return $this->buildDocsAll($console);
    }

    /**
     * @CMD(clear.compile)
     * @Desc(Clear all classes compile cache)
     */
    public function clearCompile($console)
    {
        ConfigManager::flush();

        DomainManager::flush();

        EntityManager::flush();

        ModelManager::flush();

        StorageManager::flush();

        RepositoryManager::flush();

        CommandManager::flush();

        WrapinManager::flush();

        PortManager::flush();

        EventManager::flush();

        $console->success('Done!');
    }

    /**
     * @CMD(compile)
     * @Desc(Compile all classes)
     */
    public function compile($console)
    {
        try {
            ConfigManager::compileDefault(Kernel::getRoot(), true);

            DomainManager::compile(Kernel::getRoot(), true);

            ConfigManager::compileDomains(DomainManager::getMetas(), true);

            $domains = DomainManager::getDirs();

            EntityManager::compile($domains, true);

            ModelManager::compile($domains, true);

            StorageManager::compile($domains, true);

            RepositoryManager::compile($domains, true);

            CommandManager::compile($domains, true);

            WrapinManager::compile($domains, true);

            PortManager::compile($domains, true);

            EventManager::compile($domains, true);

            $console->success('Done!', true);
        } catch (Throwable $e) {
            $console->exception('CompileFailed', [], $e);
        }
    }

    /**
     * @CMD(compile.port)
     * @Desc(Compiles Port classes and it's annotations)
     */
    public function compliePort($console)
    {
        ConfigManager::compileDefault(Kernel::getRoot(), true);

        DomainManager::compile(Kernel::getRoot(), true);

        ConfigManager::compileDomains(DomainManager::getMetas(), true);

        $domains = DomainManager::getDirs();

        PortManager::compile($domains, true);

        $console->exit();
    }

    /**
     * @CMD(compile.port.clear)
     * @Desc(Clear Port compile result)
     */
    public function clearPortComplie($console)
    {
        PortManager::flush();

        $console->exit();
    }

    /**
     * @CMD(compile.domain)
     * @Desc(Compiles classes and annotations of domains)
     * @Option(domain){notes=Domain key}
     */
    public function complieDomain($console)
    {
    }

    /**
     * @CMD(orm.init)
     * @Desc(Init an ORM storage from its annotations to connected driver schema)
     * @Option(orm){notes=The single orm class filepath or namespace to init}
     * @Option(force){notes=Whether execute the dangerous operations like drop/delete&default=false}
     * @Option(dump){notes=Dump the sqls will be executed rather than execute them directly&default=false}
     */
    public function initORM($console)
    {
        $orm = $console->getOption('orm');
        if (! $orm) {
            $console->exception('MissingORMToInit');
        }

        $class = null;
        if (is_file($orm)) {
            $class = get_namespace_of_file($orm, true);
        } elseif (class_exists($orm)) {
            $class = $orm;
        }

        if ((! $class) || (! is_subclass_of($class, ORMStorage::class))) {
            $console->exception('InvalidORMClass', compact('orm', 'class'));
        }

        $force = $console->hasOption('force');
        $dump  = $console->hasOption('dump');

        $res = StorageSchema::init($class, $force, $dump);
        if ($dump) {
            foreach ($res as $sql) {
                $console->line($sql, 2);
            }
        } else {
            $_force = $force ? ' (FORCE) ' : '';
            $console->render("Initializing{$_force}... {$class} ... ", $console::INFO_COLOR, true);
            $res ? $console->success('OK') : $console->fail('FAILED', true);
        }
    }

    /**
    * @CMD(orm.sync)
    * @Desc(Sync from storage ORM annotations to storage driver schema)
    * @Option(single){notes=The single file name to sync at once}
    * @Option(force){notes=Whether execute the dangerous operations like drop/delete&default=false}
    * @Option(domain){notes=The domain name used to sync orm classes schema}
    * @Option(dump){notes=Dump the sqls will be executed rather than execute them directly&default=false}
    * @Option(skip){notes=The orm class files to exclude, using `,` to separate}
    */
    public function syncORM($console)
    {
        $params = $console->getParams();
        $options = $console->getOptions();
        $excludes = array_trim_from_string($console->getOption('skip', ''), ',');
        array_walk($excludes, function (&$skip) {
            $class = get_namespace_of_file($skip, true);
            $skip = $class ? $class : '';
        });
        array_filter($excludes);

        $syncSingle = function ($single) use ($console, $excludes) {
            $storage = null;
            if (class_exists($single)) {
                if (! is_subclass_of($single, ORMStorage::class)) {
                    $console->exception('SingleClassNotAnORMStorage', compact('single'));
                }
                $storage = $single;
            } elseif (is_file($single)) {
                $class = get_namespace_of_file($single, true);
                if ((! $class) || (! is_subclass_of($class, Storage::class))) {
                    $console->exception('InvalidSingleStorageFile', compact('single', 'class'));
                }
                $storage = $class;
            }
            if (! $storage) {
                $console->exception('InvalidStorageSingle', compact('single', 'storage'));
            }

            $force = $console->hasOption('force');
            $dump = $console->hasOption('dump');

            if (in_array($storage, $excludes)) {
                if ($dump) {
                    return $console->line("-- SKIP: {$storage}");
                }

                return $console->line(
                    $console->render('SKIPPED: ', 'BLUE')
                    .$console->render($storage, $console::INFO_COLOR)
                );
            }

            $res = StorageSchema::sync($storage, $force, $dump);
            if ($dump) {
                foreach ($res as $sql) {
                    $console->line($sql, 2);
                }
            } else {
                $_force = $force ? ' (FORCE) ' : '';
                $console->render("Syncing{$_force}... {$storage} ... ", $console::INFO_COLOR, true);
                $res ? $console->success('OK') : $console->fail('FAILED', true);
            }
        };

        if ($console->hasOption('single')) {
            $single = $console->getOption('single');
            if (! $single) {
                $console->exception('MissingSingleTarget');
            }

            $syncSingle($single);
        } elseif ($console->hasOption('domain')) {
            $domain = $console->getOption('domain');
            if (! $domain) {
                $console->exception('MissingStorageDomainToInit');
            }

            $orms = DomainManager::getNamespaces(function ($key, $ns) use ($domain) {
                return true
                    && ci_equal($key, $domain)
                    && is_subclass_of($ns, Storage::class)
                    && ci_equal(mb_substr($ns, -3, 3), 'ORM');
            });

            foreach ($orms as $orm) {
                $syncSingle($orm);
            }
        } elseif ($params) {
            foreach ($params as $single) {
                $syncSingle($single);
            }
        } else {
            $orms = DomainManager::getNamespaces(function ($key, $ns) {
                return true
                    && is_subclass_of($ns, Storage::class)
                    && ci_equal(mb_substr($ns, -3, 3), 'ORM');
            });

            foreach ($orms as $orm) {
                $syncSingle($orm);
            }
        }
    }

    /**
     * @CMD(config.get.framework)
     * @Desc(Get framework configs)
     */
    public function getFrameworkConfig()
    {
    }

    /**
     * CMD(config.get.domain)
     * @Desc(Get domain's configs)
     */
    public function getDomainConfig($console)
    {
    }

    /**
     * @CMD(entity.add)
     * @Desc(Add an entity class in a domain)
     * @Option(domain){notes=Domain name of entity to be created}
     * @Option(entity){notes=Name of entity to be created}
     * @Option(force){notes=Whether force recreate entity when given entity name exists}
     * @Option(withts){notes=Whether the entity to be created has timestamp properties&default=true}
     */
    public function addEntity($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $name = $console->getOption('entity');
        if (! $name) {
            $console->exception('MissingEntityName');
        }
        $class = ospath($path, EntityManager::ENTITY_DIR, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('EntityAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }

        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', 'entity.tpl');
        if (! is_file($template)) {
            $console->exception('EntityClassTemplateNotExist', [$template]);
        }

        $parent = $console->getOption('withts', true) ? 'EntityWithTS' : 'Entity';

        $entity = file_get_contents($template);
        $entity = str_replace('__DOMAIN__', $domain, $entity);
        $entity = str_replace('__NAMESPACE__', path2ns($name), $entity);
        $entity = str_replace('__PARENT__', $parent, $entity);
        $entity = str_replace('__NAME__', basename($name), $entity);

        save($class, $entity);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render('Created Entity: ', $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(dm.add)
     * @Desc(Add an data model class in a domain)
     * @Option(domain){notes=Domain name of model to be created}
     * @Option(model){notes=Name of data model to be created}
     * @Option(force){notes=Whether force recreate model when given model name exists}
     */
    public function addModel($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $name = $console->getOption('model');
        if (! $name) {
            $console->exception('MissingModelName');
        }
        $class = ospath($path, ModelManager::MODEL_DIR, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('ModelAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }

        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', 'model.tpl');
        if (! is_file($template)) {
            $console->exception('ModelClassTemplateNotExist', [$template]);
        }

        $model = file_get_contents($template);
        $model = str_replace('__DOMAIN__', $domain, $model);
        $model = str_replace('__NAMESPACE__', path2ns($name), $model);
        $model = str_replace('__NAME__', basename($name), $model);

        save($class, $model);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render('Created Model: ', $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(port.add)
     * @Desc(Add a port class in a domain)
     * @Option(domain){notes=Domain name of port to be created}
     * @Option(port){notes=Name of port to be created}
     * @Option(force){notes=Whether force recreate port when given port name exists}
     * @Option(crud){notes=Whether add crud port methods into port&default=false}
     */
    public function addPort($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $name = $console->getOption('port');
        if (! $name) {
            $console->exception('MissingPortName');
        }
        $class = ospath($path, PortManager::PORT_DIR, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('PortAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }

        $tpl = $console->hasOption('crud') ? 'port-crud.tpl' : 'port-basic.tpl';
        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', $tpl);
        if (! is_file($template)) {
            $console->exception('PortClassTemplateNotExist', [$template]);
        }

        $port = file_get_contents($template);
        $port = str_replace('__DOMAIN__', $domain, $port);
        $port = str_replace('__NAMESPACE__', path2ns($name), $port);
        $port = str_replace('__NAME__', basename($name), $port);

        save($class, $port);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render('Created Port: ', $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(storage.add.orm)
     * @Desc(Add an orm storage class in a domain)
     * @Option(domain){notes=Domain name of orm storage to be created}
     * @Option(storage){notes=Name of orm storage to be created}
     * @Option(force){notes=Whether force recreate orm storage when given orm storage name exists}
     * @Option(withts){notes=Whether orm storage has timestamps&default=true}
     * @Option(withtssd){notes=Whether orm storage has timestamps and is soft deleted&default=false}
     * @Option(withsd){notes=Whether orm storage is soft deleted&default=false}
     * @Option(impl){notes=Whether orm storage implements a repository&default=false}
     */
    public function addORMStorage($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $name = $console->getOption('storage');
        if (! $name) {
            $console->exception('MissingORMStorageName');
        }
        $class = ospath($path, StorageManager::STORAGE_DIR, "{$name}ORM.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('StorageAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }

        $tpl = $console->getOption('impl', false) ? 'storage-orm-impl.tpl' : 'storage-orm.tpl';
        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', $tpl);
        if (! is_file($template)) {
            $console->exception('ORMStorageClassTemplateNotExist', [$template]);
        }

        $storage = $console->getOption('withts', true) ? 'ORMStorageWithTS' : 'ORMStorage';
        $storage = $console->getOption('withtssd', false) ? 'ORMStorageWithTSSD' : $storage;
        $storage = $console->getOption('withsd', false) ? 'ORMStorageWithSD' : $storage;

        $orm = file_get_contents($template);
        $orm = str_replace('__DOMAIN__', $domain, $orm);
        $orm = str_replace('__NAMESPACE__', path2ns($name), $orm);
        $orm = str_replace('__NAME__', basename($name), $orm);
        $orm = str_replace('__STORAGE__', $storage, $orm);

        save($class, $orm);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render('Created ORM Storage: ', $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(storage.add.kv)
     * @Desc(Add an kv storage class in a domain)
     * @Option(domain){notes=Domain name of kv storage to be created}
     * @Option(storage){notes=Name of kv storage to be created}
     * @Option(force){notes=Whether force recreate kv storage when given kv storage name exists}
     * @Option(impl){notes=Whether kv storage implements a repository&default=false}
     */
    public function addKVStorage($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $name = $console->getOption('storage');
        if (! $name) {
            $console->exception('MissingKVStorageName');
        }
        $class = ospath($path, StorageManager::STORAGE_DIR, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('KVStorageAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }

        $tpl = $console->getOption('impl', false) ? 'storage-kv-impl.tpl' : 'storage-kv.tpl';
        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', $tpl);
        if (! is_file($template)) {
            $console->exception('KVStorageClassTemplateNotExist', [$template]);
        }

        $kv = file_get_contents($template);
        $kv = str_replace('__DOMAIN__', $domain, $kv);
        $kv = str_replace('__NAMESPACE__', path2ns($name), $kv);
        $kv = str_replace('__NAME__', basename($name), $kv);

        save($class, $kv);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render('Created KV Storage: ', $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(repo.add)
     * @Desc(Add a repository interface in a domain)
     * @Option(domain){notes=Domain name of repository to be created}
     * @Option(repo){notes=Name of repository to be created}
     * @Option(force){notes=Whether force recreate repository when given repository name exists}
     * @Option(type){notes=Repository type: Entity/ORM | Model/KV&default=Entity/ORM}
     * @Option(storage){notes=Storage path relative to storage base}
     * @Option(entity){notes=Entity path relative to entity base}
     * @Option(model){notes=Model path relative to model base}
     */
    public function addRepository($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $name = $console->getOption('repo');
        if (! $name) {
            $console->exception('MissingRepositoryName');
        }
        $class = ospath($path, RepositoryManager::REPOSITORY_DIR, "{$name}Repository.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('StorageAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }

        $type = $console->getOption('type', 'entity');
        $isEntity = ciin($type, ['entity', 'orm']);
        $tpl = $isEntity ? 'repository-entity.tpl' : 'repository-model.tpl';
        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', $tpl);
        if (! is_file($template)) {
            $console->exception('RepositoryInterfaceTemplateNotExist', [$template]);
        }

        $storage = $name = basename($name);
        if ($_storage = $console->getOption('storage')) {
            $storage = path2ns($_storage, true);
        }
        if ($isEntity) {
            $storage .= 'ORM';
        }

        $repo = file_get_contents($template);
        $repo = str_replace('__DOMAIN__', $domain, $repo);
        $repo = str_replace('__NAMESPACE__', path2ns($name), $repo);
        $repo = str_replace('__NAME__', $name, $repo);
        $repo = str_replace('__STORAGE__', $storage, $repo);

        if ($isEntity) {
            $entity = $console->getOption('entity', $name);
            $repo = str_replace('__ENTITY__', path2ns($entity, true), $repo);
        } else {
            $model = $console->getOption('model', $name);
            $repo = str_replace('__MODEL__', path2ns($model, true), $repo);
        }

        save($class, $repo);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render("Created Repository ({$type}): ", $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(service.add)
     * @Desc(Add a service class in a domain)
     * @Option(domain){notes=Domain name of service to be created}
     * @Option(service){notes=Name of service to be created}
     * @Option(force){notes=Whether force recreate service when given service name exists}
     * @Option(entity){notes=Entity name used for CRUD template}
     * @Option(crud){notes=CRUD template type, one of create/delete/update/show/list}
     */
    public function addService($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $name = $console->getOption('service');
        if (! $name) {
            $console->exception('MissingServiceName');
        }
        $class = ospath($path, Kernel::SERVICE, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('ServiceAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }

        $tpl = 'service-basic.tpl';
        if ($console->hasOption('crud')) {
            $crud = strtolower(strval($console->getOption('crud')));
            $types = ['create', 'delete', 'update', 'show', 'list'];
            if ((! $crud) || (! in_array($crud, $types))) {
                $console->exception('InvalidCRUDType', compact('crud', 'types'));
            }
            $tpl = "service-crud-{$crud}.tpl";
        }

        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', $tpl);
        if (! is_file($template)) {
            $console->exception('ServiceClassTemplateNotExist', [$template]);
        }

        $service = file_get_contents($template);
        $service = str_replace('__DOMAIN__', $domain, $service);
        $service = str_replace('__NAMESPACE__', path2ns($name), $service);
        $service = str_replace('__NAME__', basename($name), $service);

        if ($entity = $console->getOption('entity')) {
            $service = str_replace('__ENTITY__', path2ns($entity, true), $service);
        }

        save($class, $service);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render("Created Service: ", $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(asm.add)
     * @Desc(Add Assembler in a domain)
     * @Option(domain){notes=Domain name of assembler to be created}
     * @Option(asm){notes=Assembler name}
     * @Option(force){notes=Whether force recreate assembler when given assembler name exists}
     */
    public function addAssembler($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $name = $console->getOption('asm');
        if (! $name) {
            $console->exception('MissingAssemblerName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $class = ospath($path, Kernel::ASSEMBLER, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('AssemblerAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }
        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', 'assembler.tpl');
        if (! is_file($template)) {
            $console->exception('AssemblerClassTemplateNotExist', [$template]);
        }

        $assembler = file_get_contents($template);
        $assembler = str_replace('__DOMAIN__', $domain, $assembler);
        $assembler = str_replace('__NAMESPACE__', path2ns($name), $assembler);
        $assembler = str_replace('__NAME__', basename($name), $assembler);

        save($class, $assembler);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render("Created Assembler: ", $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(crud)
     * @Desc(Generate all CRUD operations related classes based on a resource/entity name)
     * @Option(domain){notes=Domain name of classes to be created}
     * @Option(entity){notes=Entity directory path}
     * @Option(storage){notes=ORM storage directory path}
     * @Option(repo){notes=Repository directory path}
     * @Option(port){notes=Port directory path}
     * @Option(noport){notes=Do not create port class&default=false}
     * @Option(service){notes=Service directory path}
     * @Option(asm){notes=Assembler directory path}
     * @Option(noasm){notes=Do not create assembler class&default=false}
     * @Option(withts){notes=Entity and ORM storage to be created need timestamps or not&default=true}
     * @Option(nodelete){notes=Do not create delete service&default=false}
     * @Option(noshow){notes=Do not create show service&default=false}
     * @Option(noupdate){notes=Do not create update service&default=false}
     * @Option(nolist){notes=Do not create list service&default=false}
     */
    public function crud($console)
    {
        $entity = $console->getOption('entity');
        if (! $entity) {
            $console->exception('MissingEntityName');
        }
        $this->addEntity($console);
        $_entity = basename($entity);

        $storage = $console->hasOption('storage')
            ? join('/', [$console->getOption('storage'), $_entity])
            : $_entity;

        $repo = $console->hasOption('repo')
            ? join('/', [$console->getOption('repo'), $_entity])
            : $_entity;
        $console->setOption('storage', $storage)->setOption('repo', $repo);
        $this->addRepository($console);

        $console->setOption('impl', true)->setOption('storage', $storage);
        $this->addORMStorage($console);

        if (! $console->hasOption('noport')) {
            $port = $console->hasOption('port')
                ? join('/', [$console->getOption('port'), $_entity])
                : $_entity;
            $console->setOption('crud', true)->setOption('port', $port);
            $this->addPort($console);
        }

        $console->setOption('crud', 'create')->setOption('service', "CRUD/Create{$_entity}");
        $this->addService($console);
        if (! $console->hasOption('nodelete')) {
            $console->setOption('crud', 'delete')->setOption('service', "CRUD/Delete{$_entity}");
            $this->addService($console);
        }
        if (! $console->hasOption('noupdate')) {
            $console->setOption('crud', 'update')->setOption('service', "CRUD/Update{$_entity}");
            $this->addService($console);
        }
        if (! $console->hasOption('noshow')) {
            $console->setOption('crud', 'show')->setOption('service', "CRUD/Show{$_entity}");
            $this->addService($console);
        }
        if (! $console->hasOption('nolist')) {
            $console->setOption('crud', 'list')->setOption('service', "CRUD/List{$_entity}");
            $this->addService($console);
        }

        if (! $console->hasOption('noasm')) {
            $asm = $console->hasOption('asm')
                ? join('/', [$console->getOption('asm'), $_entity])
                : $_entity;
            $console->setOption('asm', $asm);
            $this->addAssembler($console);
        }
    }

    /**
     * @CMD(domain.add)
     * @Desc(Create a new domain)
     * @Argv(1){notes=Domain name to be Created}
     */
    public function addDomain($console)
    {
        $name = $console->getParams()[0] ?? null;
        if (! $name) {
            $console->exception('MissingDomainName');
        }

        if (DomainManager::getByKey($name)) {
            $console->exception('DomainAlreadyExists', compact('name'));
        }

        $_name = ucfirst($name);

        $file = ospath(Kernel::getRoot(), DomainManager::DOMAIN_PATH, $_name, DomainManager::DOMAIN_FLAG, DomainManager::DOMAIN_FILE);
        $init = <<<PHP
<?php

return [
];
PHP;

        save($file, $init);

        $console->success('Done!');
    }

    /**
     * @CMD(cmd.add)
     * @Desc(Create a domain command class)
     * @Option(cmd){notes=Command name to be Created}
     * @Option(domain){notes=Domain name of command to be created}
     * @Option(force){notes=Whether force recreate command when given command class exists}
     */
    public function addCMD($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $name = $console->getOption('cmd');
        if (! $name) {
            $console->exception('MissingCommandName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $class = ospath($path, Kernel::COMMAND, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('CommandAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }
        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', 'command.tpl');
        if (! is_file($template)) {
            $console->exception('CommandClassTemplateNotExist', [$template]);
        }

        $command = file_get_contents($template);
        $command = str_replace('__DOMAIN__', $domain, $command);
        $command = str_replace('__NAMESPACE__', path2ns($name), $command);
        $command = str_replace('__NAME__', basename($name), $command);

        save($class, $command);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render("Created Command: ", $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(event.add)
     * @Desc(Add Event in a domain)
     * @Option(domain){notes=Domain name of event to be created}
     * @Option(event){notes=Event name}
     * @Option(force){notes=Whether force recreate event when given event name exists}
     */
    public function addEvent($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $name = $console->getOption('event');
        if (! $name) {
            $console->exception('MissingEventName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $class = ospath($path, Kernel::EVENT, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('EventAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }
        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', 'event.tpl');
        if (! is_file($template)) {
            $console->exception('EventClassTemplateNotExist', [$template]);
        }

        $event = file_get_contents($template);
        $event = str_replace('__DOMAIN__', $domain, $event);
        $event = str_replace('__NAMESPACE__', path2ns($name), $event);
        $event = str_replace('__NAME__', basename($name), $event);

        save($class, $event);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render("Created Event: ", $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }

    /**
     * @CMD(listener.add)
     * @Desc(Add Listener in a domain)
     * @Option(domain){notes=Domain name of listener to be created}
     * @Option(listener){notes=Listener name}
     * @Option(force){notes=Whether force recreate listener when given listener name exists}
     */
    public function addListener($console)
    {
        $domain = $console->getOption('domain');
        if (! $domain) {
            $console->exception('MissingDomainName');
        }
        $name = $console->getOption('listener');
        if (! $name) {
            $console->exception('MissingListenerName');
        }
        $path = DomainManager::getByKey($domain);
        if (! $path) {
            $console->exception('DomainNotExists', [$domain]);
        }
        $class = ospath($path, Kernel::LISTENER, "{$name}.php");
        if (is_file($class) && (! $console->hasOption('force'))) {
            $console->exception('ListenerAlreadyExists', [get_namespace_of_file($class, true), $class]);
        }
        $template = ospath(Kernel::root(), Kernel::TEMPLATE, 'code', 'listener.tpl');
        if (! is_file($template)) {
            $console->exception('ListenerClassTemplateNotExist', [$template]);
        }

        $listener = file_get_contents($template);
        $listener = str_replace('__DOMAIN__', $domain, $listener);
        $listener = str_replace('__NAMESPACE__', path2ns($name), $listener);
        $listener = str_replace('__NAME__', basename($name), $listener);

        save($class, $listener);

        $_class = get_namespace_of_file($class, true);

        $console->line(
            $console->render("Created Listener: ", $console::SUCCESS_COLOR)
            .$console->render("{$_class} ({$class})", $console::INFO_COLOR)
        );
    }
}
