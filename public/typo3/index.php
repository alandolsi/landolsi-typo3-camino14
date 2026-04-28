<?php

declare(strict_types=1);

call_user_func(static function () {
    $classLoader = require dirname(__DIR__, 2) . '/vendor/autoload.php';
    \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::run(1);

    $isInstallToolDirectAccess = false;
    if (class_exists(\TYPO3\CMS\Install\Http\Application::class)) {
        $isInstallToolDirectAccess = isset($_GET['__typo3_install']);
    }

    $container = \TYPO3\CMS\Core\Core\Bootstrap::init($classLoader, $isInstallToolDirectAccess);

    if ($container->has(\TYPO3\CMS\Core\Http\Application::class)) {
        $container->get(\TYPO3\CMS\Core\Http\Application::class)->run();
        return;
    }

    $container->get(\TYPO3\CMS\Install\Http\Application::class)->run();
});
