<?php

declare(strict_types=1);

namespace Dof\Framework\Doc;

use Dof\Framework\Kernel;
use Dof\Framework\PortManager;
use Dof\Framework\ErrorManager;
use Dof\Framework\EntityManager;
use Dof\Framework\ModelManager;
use Dof\Framework\WrapinManager;
use Dof\Framework\Doc\UI\GitBook;

final class Generator
{
    const SUPPORT_UI = [
        'gitbook' => GitBook::class,
    ];

    private static function support(string $ui)
    {
        $_ui = self::SUPPORT_UI[$ui] ?? null;
        if (! $_ui) {
            exception('DocUiNotSupport', compact('ui'));
        }

        return new $_ui;
    }

    public static function getModels()
    {
        return array_merge(EntityManager::getEntities(), ModelManager::getModels());
    }

    /**
     * Build Docs with given $ui and save to $save
     *
     * @param string $ui: The docs ui to use
     * @param string $save: The docs path to save
     * @param string $lang: The doc template language
     */
    public static function buildAll(string $ui, string $save, string $lang = 'zh-CN')
    {
        $templates = ospath(dirname(dirname(dirname(__FILE__))), Kernel::TEMPLATE, 'doc');

        self::support($ui)
            ->setTemplates($templates)
            ->setLanguage($lang)
            ->setOutput($save)
            ->setApiData(PortManager::getDocs())
            ->setWrapinData(WrapinManager::getWrapins())
            ->setModelData(self::getModels())
            ->setErrorData([ErrorManager::getDefault(), ErrorManager::getDomains()])
            ->buildAll();
    }

    public static function buildModel(string $ui, string $save, string $lang = 'zh-CN')
    {
        $templates = ospath(dirname(dirname(dirname(__FILE__))), Kernel::TEMPLATE, 'doc');

        self::support($ui)
            ->setTemplates($templates)
            ->setLanguage($lang)
            ->setOutput($save)
            ->setModelData(self::getModels())
            ->buildModel(true);
    }

    public static function buildWrapin(string $ui, string $save, string $lang = 'zh-CN')
    {
        $templates = ospath(dirname(dirname(dirname(__FILE__))), Kernel::TEMPLATE, 'doc');

        self::support($ui)
            ->setTemplates($templates)
            ->setLanguage($lang)
            ->setOutput($save)
            ->setWrapinData(WrapinManager::getWrapins())
            ->buildWrapin(true);
    }

    public static function buildHttp(string $ui, string $save, string $lang = 'zh-CN')
    {
        $templates = ospath(dirname(dirname(dirname(__FILE__))), Kernel::TEMPLATE, 'doc');

        self::support($ui)
            ->setTemplates($templates)
            ->setLanguage($lang)
            ->setOutput($save)
            ->setApiData(PortManager::getDocs())
            ->setErrorData([ErrorManager::getDefault(), ErrorManager::getDomains()])
            ->buildHttp(true);
    }
}
