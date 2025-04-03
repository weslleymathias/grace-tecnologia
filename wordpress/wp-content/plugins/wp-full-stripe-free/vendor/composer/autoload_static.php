<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit117a2a47b99f376e92b8a9586fec470e
{
    public static $files = array (
        '3109cb1a231dcd04bee1f9f620d46975' => __DIR__ . '/..' . '/paragonie/sodium_compat/autoload.php',
        '538d11a8c218d52c670b27979ae23ec9' => __DIR__ . '/..' . '/codeinwp/themeisle-sdk/load.php',
    );

    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Psr\\EventDispatcher\\' => 20,
            'Psr\\Container\\' => 14,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Psr\\EventDispatcher\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/event-dispatcher/src',
        ),
        'Psr\\Container\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/container/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit117a2a47b99f376e92b8a9586fec470e::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit117a2a47b99f376e92b8a9586fec470e::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit117a2a47b99f376e92b8a9586fec470e::$classMap;

        }, null, ClassLoader::class);
    }
}
